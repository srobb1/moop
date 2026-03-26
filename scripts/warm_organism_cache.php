#!/usr/bin/env php
<?php
/**
 * CLI script to warm the organism cache.
 *
 * Runs the full organism scan and writes organisms/.organism_cache.json
 * so the manage_organisms admin page loads instantly.
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

echo "Scanning organisms (this may take a while)...\n";
$start = microtime(true);

$organisms = getCachedOrganismsInfo($organism_data, $sequence_types, $taxonomy_tree_file, $groups_data, $groups_file, $force);

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
