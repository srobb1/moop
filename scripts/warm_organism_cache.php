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
    // Check if cache is already fresh
    $cache_file = "$organism_data/.organism_cache.json";
    if (file_exists($cache_file)) {
        $fingerprint = buildOrganismCacheFingerprint($organism_data, $taxonomy_tree_file, $groups_file);
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached && isset($cached['fingerprint']) && $cached['fingerprint'] === $fingerprint) {
            echo "Cache is already up to date (generated: {$cached['generated']})\n";
            echo "Use --force to rescan anyway.\n";
            exit(0);
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
