#!/usr/bin/env php
<?php
/**
 * CLI Script: Generate Taxonomy Tree
 *
 * Rebuilds taxonomy_tree_config.json using the lineage cache. Only organisms
 * whose taxon_id is absent from the cache require a live NCBI call (~0.5s each).
 * Subsequent runs are essentially instant.
 *
 * Usage:
 *   php scripts/generate_taxonomy_tree.php           # incremental (new orgs only)
 *   php scripts/generate_taxonomy_tree.php --force   # re-fetch all from NCBI
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$force = in_array('--force', $argv);

require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/functions_data.php';
require_once __DIR__ . '/../lib/functions_display.php';
require_once __DIR__ . '/../lib/functions_system.php';
require_once __DIR__ . '/../lib/functions_errorlog.php';

$config           = ConfigManager::getInstance();
$organism_data    = $config->getPath('organism_data');
$metadata_path    = $config->getPath('metadata_path');
$tree_config_file = "$metadata_path/taxonomy_tree_config.json";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TAXONOMY TREE GENERATOR\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if (file_exists($tree_config_file) && !is_writable($tree_config_file)) {
    die("ERROR: Tree config file is not writable: $tree_config_file\n");
}

// Load organisms from organism cache
echo "Loading organisms...\n";
$organisms = [];
$org_cache = "$organism_data/.organism_cache.json";
if (file_exists($org_cache)) {
    $cached = json_decode(file_get_contents($org_cache), true);
    if ($cached && isset($cached['data'])) {
        foreach ($cached['data'] as $org_name => $org_data) {
            if (!empty($org_data['info'])) $organisms[$org_name] = $org_data['info'];
        }
    }
}
if (empty($organisms)) {
    $organisms = loadAllOrganismsMetadata($organism_data);
}
if (empty($organisms)) {
    die("ERROR: No organisms found in $organism_data\n");
}
echo "Found " . count($organisms) . " organisms\n\n";

// Load (or clear) the lineage cache
$lineage_cache = $force ? [] : load_lineage_cache($metadata_path);

$need_fetch = array_filter($organisms, function($d) use ($lineage_cache) {
    return !empty($d['taxon_id']) && !isset($lineage_cache[(string)$d['taxon_id']]);
});

if (!empty($need_fetch)) {
    $n = count($need_fetch);
    $est = $n * 0.5;
    $est_str = $est > 90 ? round($est / 60, 1) . ' min' : round($est) . 's';
    echo "Fetching lineage from NCBI for $n organism(s) (~$est_str)...\n";
    $lineage_cache = refresh_lineage_cache($need_fetch, $lineage_cache, function($org, $cur, $tot) {
        echo "  [$cur/$tot] $org\n";
        flush();
    });
    save_lineage_cache($lineage_cache, $metadata_path);
    echo "\n";
} else {
    echo "All lineages already cached — rebuilding tree instantly.\n\n";
}

echo "Building tree...\n";
$tree_data = build_tree_from_lineage_cache($organisms, $lineage_cache);
$json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    die("ERROR: Failed to encode tree: " . json_last_error_msg() . "\n");
}
if (file_put_contents($tree_config_file, $json) === false) {
    die("ERROR: Failed to write $tree_config_file\n");
}
@chmod($tree_config_file, 0664);

echo "✓ SUCCESS: Tree written with " . count($organisms) . " organisms\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
