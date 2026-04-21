#!/usr/bin/env php
<?php
/**
 * CLI script to warm caches for sites with many organisms.
 *
 * Runs the full organism scan and writes organisms/.organism_cache.json
 * so the manage_organisms admin page loads instantly. Also warms the
 * annotation config cache so manage_annotations doesn't timeout.
 *
 * Usage:
 *   php scripts/warm_organism_cache.php          # normal run
 *   php scripts/warm_organism_cache.php --force   # force rescan even if cache is fresh
 *
 * This script has no timeout limit (unlike the web server), so it works
 * even with many organisms.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$force = in_array('--force', $argv);

// Bootstrap the app (config + all library functions)
$base_dir = dirname(__DIR__);
require_once "$base_dir/includes/config_init.php";
require_once "$base_dir/lib/moop_functions.php";
require_once "$base_dir/lib/blast_functions.php";
require_once "$base_dir/lib/functions_display.php";

$config = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sequence_types = $config->getSequenceTypes();
$groups_data = getGroupData();
$taxonomy_tree_file = "$metadata_path/taxonomy_tree_config.json";
$groups_file = "$metadata_path/organism_assembly_groups.json";

echo "Organism data path: $organism_data\n";

// Count organisms
$org_dirs = array_filter(scandir($organism_data), function($f) use ($organism_data) {
    return $f[0] !== '.' && is_dir("$organism_data/$f");
});
echo "Found " . count($org_dirs) . " organisms\n";

if (!$force) {
    // Check if organism cache is fresh
    $cache_file = "$organism_data/.organism_cache.json";
    if (file_exists($cache_file)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached && isset($cached['org_fingerprints'], $cached['config_fingerprint'], $cached['generated'])) {
            $current_org_fps   = buildPerOrganismFingerprints($organism_data);
            $current_config_fp = buildConfigFingerprint($taxonomy_tree_file, $groups_file);
            if ($cached['config_fingerprint'] === $current_config_fp && $cached['org_fingerprints'] === $current_org_fps) {
                // Organism cache is fresh — but still run taxonomy section if lineage cache is missing
                $lineage_cache_file = "$metadata_path/taxonomy_lineage_cache.json";
                if (file_exists($lineage_cache_file)) {
                    echo "Cache is already up to date (generated: {$cached['generated']})\n";
                    echo "Use --force to rescan anyway.\n";
                    exit(0);
                }
                echo "Organism cache is fresh but lineage cache is missing — running taxonomy update.\n";
                // Jump straight to taxonomy section using cached organism data
                $organism_infos = [];
                foreach ($cached['data'] as $org_name => $org_data) {
                    if (!empty($org_data['info'])) $organism_infos[$org_name] = $org_data['info'];
                }
                $lineage_cache = load_lineage_cache($metadata_path);
                $need_fetch = array_filter($organism_infos, function($d) use ($lineage_cache) {
                    return !empty($d['taxon_id']) && !isset($lineage_cache[(string)$d['taxon_id']]);
                });
                if (!empty($need_fetch)) {
                    echo "Fetching lineage from NCBI for " . count($need_fetch) . " organism(s)...\n";
                    $lineage_cache = refresh_lineage_cache($need_fetch, $lineage_cache, function($org, $cur, $tot) {
                        echo "  [$cur/$tot] $org\n";
                    });
                    save_lineage_cache($lineage_cache, $metadata_path);
                }
                $tree_config_file = "$metadata_path/taxonomy_tree_config.json";
                if (!file_exists($tree_config_file) || is_writable($tree_config_file)) {
                    $tree_data = build_tree_from_lineage_cache($organism_infos, $lineage_cache);
                    $tree_json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    if ($tree_json !== false && @file_put_contents($tree_config_file, $tree_json) !== false) {
                        @chmod($tree_config_file, 0664);
                        echo "Taxonomy tree rebuilt (" . count($organism_infos) . " organisms).\n";
                    }
                }
                exit(0);
            }
        }
    }
}

echo "Scanning organisms...\n";
$start = microtime(true);

$progress = function($organism, $current, $total) {
    echo "  [$current/$total] $organism\n";
};

$organisms = getCachedOrganismsInfo($organism_data, $sequence_types, $taxonomy_tree_file, $groups_data, $groups_file, $force, $progress);

$elapsed = round(microtime(true) - $start, 2);
echo "Done! Scanned " . count($organisms) . " organisms in {$elapsed}s\n";

// Verify cache was written
$cache_file = "$organism_data/.organism_cache.json";
if (file_exists($cache_file)) {
    $size = round(filesize($cache_file) / 1024, 1);
    echo "Cache written: $cache_file ({$size} KB)\n";
} else {
    echo "WARNING: Cache file was not written. Check directory permissions.\n";
    exit(1);
}

// --- Warm annotation config cache ---
echo "\nWarming annotation config...\n";
$config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFile($config_file, []);

$newest_mod_info = getNewestSqliteModTime($organism_data);
$need_update = shouldUpdateAnnotationCounts($annotation_config, $newest_mod_info);

if (!$need_update && !$force) {
    echo "Annotation config is already up to date.\n";
} else {
    $all_db_annotation_types = [];
    $orgs_with_assemblies = getOrganismsWithAssemblies($organism_data);
    $org_count = 0;
    $org_total = count($orgs_with_assemblies);

    foreach ($orgs_with_assemblies as $org_name => $assemblies) {
        $org_count++;
        $db_file = "$organism_data/$org_name/organism.sqlite";
        if (file_exists($db_file)) {
            echo "  [$org_count/$org_total] Querying $org_name\n";
            $db_types = getAnnotationTypesFromDB($db_file);
            foreach ($db_types as $type => $counts) {
                if (!isset($all_db_annotation_types[$type])) {
                    $all_db_annotation_types[$type] = $counts;
                } else {
                    $all_db_annotation_types[$type]['annotation_count'] += $counts['annotation_count'];
                    $all_db_annotation_types[$type]['feature_count'] += $counts['feature_count'];
                }
            }
        }
    }

    // Sync and save
    if (isset($annotation_config['annotation_types'])) {
        $annotation_config = syncAnnotationTypes($annotation_config, $all_db_annotation_types);

        // Rebuild type order
        $type_order = [];
        foreach ($annotation_config['annotation_types'] as $type_name => $type_config) {
            $type_order[] = ['name' => $type_name, 'order' => $type_config['order'] ?? 999];
        }
        usort($type_order, function($a, $b) { return $a['order'] - $b['order']; });
        $annotation_config['annotation_type_order'] = array_map(function($item) { return $item['name']; }, $type_order);
    }

    if ($newest_mod_info !== null) {
        $annotation_config['sqlite_mod_time'] = $newest_mod_info['unix_time'];
    }

    saveJsonFile($config_file, $annotation_config);
    echo "Annotation config updated with " . count($all_db_annotation_types) . " annotation types.\n";
}

// --- Update taxonomy tree ---
echo "\nUpdating taxonomy tree...\n";
$tree_config_file = "$metadata_path/taxonomy_tree_config.json";

// Extract organism info from the freshly-scanned cache
$organism_infos = [];
foreach ($organisms as $org_name => $org_data) {
    if (!empty($org_data['info'])) {
        $organism_infos[$org_name] = $org_data['info'];
    }
}

$lineage_cache = $force ? [] : load_lineage_cache($metadata_path);

// Determine how many need NCBI fetches
$need_fetch = array_filter($organism_infos, function($d) use ($lineage_cache) {
    return !empty($d['taxon_id']) && !isset($lineage_cache[(string)$d['taxon_id']]);
});

if (empty($need_fetch) && !$force) {
    echo "Lineage cache is up to date.\n";
} else {
    $fetch_count = count($need_fetch);
    if ($fetch_count > 0) {
        echo "Fetching lineage from NCBI for $fetch_count new organism(s)...\n";
        $lineage_cache = refresh_lineage_cache($need_fetch, $lineage_cache, function($org, $cur, $tot) {
            echo "  [$cur/$tot] $org\n";
        });
        save_lineage_cache($lineage_cache, $metadata_path);
    }
}

// Always rebuild the tree so it stays consistent with the current organism list
if (file_exists($tree_config_file) && !is_writable($tree_config_file)) {
    echo "WARNING: Taxonomy tree file is not writable — skipping rebuild.\n";
    echo "         Fix permissions: chmod 664 $tree_config_file\n";
} else {
    $tree_data = build_tree_from_lineage_cache($organism_infos, $lineage_cache);
    $tree_json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($tree_json !== false && @file_put_contents($tree_config_file, $tree_json) !== false) {
        @chmod($tree_config_file, 0664);
        echo "Taxonomy tree rebuilt (" . count($organism_infos) . " organisms).\n";
    } else {
        echo "WARNING: Could not write taxonomy tree to $tree_config_file\n";
    }
}
