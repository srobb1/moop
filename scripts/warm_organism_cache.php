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

// Optional: rescan a single named organism and skip taxonomy/annotation updates.
$single_organism = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--organism=')) {
        $single_organism = substr($arg, 11);
        break;
    }
}

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

if (!$force && !$single_organism) {
    // Check if organism cache is fresh
    $cache_file = "$organism_data/.organism_cache.json";
    if (file_exists($cache_file)) {
        $cached = loadJsonFile($cache_file, []);
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

if ($single_organism) {
    echo "Rescanning single organism: $single_organism\n";
} else {
    echo "Scanning organisms...\n";
}
$start = microtime(true);

$progress_file = "$organism_data/.organism_cache_progress.json";
$progress = function($organism, $current, $total, $step = 'scanning') use ($progress_file) {
    if ($step === 'scanning') {
        echo "  [$current/$total] $organism\n";
    }
    @file_put_contents($progress_file, json_encode([
        'organism' => $organism,
        'step'     => $step,
        'current'  => $current,
        'total'    => $total,
    ]));
};

$organisms = getCachedOrganismsInfo(
    $organism_data, $sequence_types, $taxonomy_tree_file, $groups_data, $groups_file,
    $force && !$single_organism,       // force_refresh: only for all-organism scans
    $progress,
    $single_organism ? [$single_organism] : []  // force_organisms: targeted rescan
);

$elapsed = round(microtime(true) - $start, 2);
echo "Done! Scanned " . count($organisms) . " organisms in {$elapsed}s\n";

// Verify cache was written
$cache_file = "$organism_data/.organism_cache.json";
if (file_exists($cache_file)) {
    $size = round(filesize($cache_file) / 1024, 1);
    echo "Cache written: $cache_file ({$size} KB)\n";
} else {
    echo "WARNING: Cache file was not written. Check directory permissions.\n";
    @unlink($progress_file);
    exit(1);
}

@unlink($progress_file);

if ($single_organism) {
    echo "Done.\n";
    exit(0);
}

// --- Warm annotation config cache (per-organism, only re-query changed databases) ---
echo "\nWarming annotation config...\n";
$config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFile($config_file, []);

[$annotation_config, $ann_updated] = update_annotation_config_modular(
    $annotation_config,
    $organism_data,
    $force,
    function($org, $cur, $tot) { echo "  [$cur/$tot] Querying $org\n"; }
);

if ($ann_updated === 0 && !$force) {
    echo "Annotation config is already up to date.\n";
} else {
    saveJsonFile($config_file, $annotation_config);
    echo "Annotation config updated ($ann_updated organism" . ($ann_updated !== 1 ? 's' : '') . " re-queried).\n";
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

$stored_gz = "$metadata_path/ncbi_rankedlineage.dmp.gz";

if (empty($need_fetch) && !$force) {
    echo "Lineage cache is up to date.\n";
} elseif (!file_exists($stored_gz)) {
    echo "WARNING: Local NCBI taxonomy dump not found.\n";
    echo "         Lineage and taxonomy tree cannot be updated without it.\n";
    echo "         Run: php scripts/sync_ncbi_taxonomy_dump.php\n";
} else {
    $fetch_count = count($need_fetch);
    if ($fetch_count > 0) {
        echo "Resolving lineage for $fetch_count new organism(s) from local dump...\n";
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

        // Patch the organism cache in-place so in_taxonomy_tree reflects the new tree.
        // The cache was written before the tree update, so without this patch the
        // manage_organisms page would show stale tree-membership until the next full rescan.
        $org_cache_file = "$organism_data/.organism_cache.json";
        $org_cache = @json_decode(@file_get_contents($org_cache_file), true);
        if ($org_cache && isset($org_cache['data'])) {
            $tree_content = $tree_json;
            $changed = false;
            foreach ($org_cache['data'] as $org_name => &$org_entry) {
                $in_tree = strpos($tree_content, '"organism": "' . $org_name . '"') !== false;
                if (($org_entry['in_taxonomy_tree'] ?? null) !== $in_tree) {
                    $org_entry['in_taxonomy_tree'] = $in_tree;
                    // overall_status also embeds in_taxonomy_tree — update it too
                    if (isset($org_entry['overall_status']['checks']['in_taxonomy_tree'])) {
                        $org_entry['overall_status']['checks']['in_taxonomy_tree'] = $in_tree;
                    }
                    $changed = true;
                }
            }
            unset($org_entry);
            if ($changed) {
                organism_cache_write_atomic($org_cache_file, $org_cache);
                echo "Organism cache patched with updated in_taxonomy_tree values.\n";
            }
        }
    } else {
        echo "WARNING: Could not write taxonomy tree to $tree_config_file\n";
    }
}
