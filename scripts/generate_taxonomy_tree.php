#!/usr/bin/env php
<?php
/**
 * CLI Script: Generate Taxonomy Tree
 * 
 * Generates the taxonomy tree from organism metadata by fetching
 * lineage data from NCBI. This is the recommended way to generate
 * the tree for large numbers of organisms (70+) as it avoids browser
 * timeout issues.
 * 
 * Usage:
 *   php scripts/generate_taxonomy_tree.php
 * 
 * Output:
 *   Writes to metadata/taxonomy_tree_config.json
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Bootstrap
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/functions_data.php';
require_once __DIR__ . '/../lib/functions_display.php';
require_once __DIR__ . '/../lib/functions_system.php';
require_once __DIR__ . '/../lib/functions_errorlog.php';

$config = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$tree_config_file = "$metadata_path/taxonomy_tree_config.json";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TAXONOMY TREE GENERATOR\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Check file is writable
if (file_exists($tree_config_file) && !is_writable($tree_config_file)) {
    die("ERROR: Tree config file is not writable: $tree_config_file\n");
}

// Load organisms
echo "Loading organisms from $organism_data...\n";
$organisms = [];
$cache_file = "$organism_data/.organism_cache.json";
if (file_exists($cache_file)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if ($cached && isset($cached['data'])) {
        foreach ($cached['data'] as $org_name => $org_data) {
            if (!empty($org_data['info'])) {
                $organisms[$org_name] = $org_data['info'];
            }
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

// Estimate time
$est_seconds = count($organisms) * 1.5;
$est_display = $est_seconds > 90 ? round($est_seconds / 60, 1) . ' minutes' : round($est_seconds) . ' seconds';
echo "Estimated time: $est_display\n";
echo "Progress: Each dot = 1 organism\n\n";

// Build tree with progress dots
echo "Generating tree: ";
$count = 0;
$tree_data = build_tree_from_organisms_with_progress($organisms, function() use (&$count) {
    $count++;
    echo ".";
    if ($count % 50 === 0) echo " $count\n             ";
    flush();
});

echo "\n\n";

if ($tree_data === false || empty($tree_data)) {
    die("ERROR: Failed to build tree\n");
}

// Save to file
echo "Saving tree to $tree_config_file...\n";
$json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    die("ERROR: Failed to encode tree as JSON: " . json_last_error_msg() . "\n");
}

if (file_put_contents($tree_config_file, $json) === false) {
    die("ERROR: Failed to write tree config file\n");
}

echo "✓ SUCCESS: Tree generated with " . count($organisms) . " organisms\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

/**
 * Build tree with progress callback
 */
function build_tree_from_organisms_with_progress($organisms, $callback = null) {
    $all_lineages = [];
    
    foreach ($organisms as $organism_name => $data) {
        if (empty($data['taxon_id'])) {
            continue;
        }
        
        $lineage = fetch_taxonomy_lineage($data['taxon_id']);
        $image = fetch_organism_image($data['taxon_id'], $organism_name);
        
        // Wikipedia fallback
        if ($image === null && !empty($data['genus']) && !empty($data['species'])) {
            $scientific_name = $data['genus'] . ' ' . $data['species'];
            $wiki_data = getWikipediaOrganismData($organism_name, $scientific_name);
            
            if (!empty($wiki_data['image_url'])) {
                $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism_name) . '.jpg';
                $downloaded_path = downloadWikimediaImage($wiki_data['image_url'], $safe_filename);
                
                if ($downloaded_path !== false) {
                    $config = ConfigManager::getInstance();
                    $site = $config->getString('site');
                    $image = preg_replace('#^/' . preg_quote($site, '#') . '/#', '', $downloaded_path);
                }
            }
        }
        
        if ($lineage) {
            $all_lineages[$organism_name] = [
                'lineage' => $lineage,
                'common_name' => $data['common_name'],
                'image' => $image
            ];
        }
        
        if ($callback) $callback();
        
        usleep(500000); // 500ms rate limit
    }
    
    // Build tree structure (same as build_tree_from_organisms)
    $tree = ['name' => 'Life', 'children' => []];
    
    foreach ($all_lineages as $organism_name => $info) {
        $lineage = $info['lineage'];
        $current = &$tree;
        
        foreach ($lineage as $level) {
            $rank = $level['rank'];
            $name = $level['name'];
            
            $found = false;
            if (isset($current['children'])) {
                foreach ($current['children'] as &$child) {
                    if ($child['name'] === $name) {
                        $current = &$child;
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) {
                $new_node = ['name' => $name];
                
                if ($rank === 'species') {
                    $new_node['organism'] = $organism_name;
                    $new_node['common_name'] = $info['common_name'];
                    if ($info['image']) {
                        $new_node['image'] = $info['image'];
                    }
                } else {
                    $new_node['children'] = [];
                }
                
                $current['children'][] = $new_node;
                $current = &$current['children'][count($current['children']) - 1];
            }
        }
    }
    
    return ['tree' => $tree];
}
