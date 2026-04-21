<?php
// Enable output buffering BEFORE any includes
ob_start();

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../lib/blast_functions.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sequence_types = $config->getSequenceTypes();
$groups_data = getGroupData();
$taxonomy_tree_file = $config->getPath('metadata_path') . '/taxonomy_tree_config.json';
$groups_file = $metadata_path . '/organism_assembly_groups.json';

// Read the cache file directly — never scan synchronously in a web request.
// Scanning happens via the background refresh endpoint (admin/api/refresh_organism_cache.php).
$cache_file = "$organism_data/.organism_cache.json";
$organisms = [];
$cache_generated = null;
$raw_cache = null;
if (file_exists($cache_file)) {
    $raw_cache = json_decode(file_get_contents($cache_file), true);
    if ($raw_cache && isset($raw_cache['data'])) {
        $organisms = $raw_cache['data'];
        $cache_generated = $raw_cache['generated'] ?? null;
    }
}

// Quick staleness check — only filemtime + scandir calls, no full scan (~50ms for 60 organisms).
$stale_organisms = [];
$cache_stale_reason = null;
if ($raw_cache) {
    $cached_config_fp = $raw_cache['config_fingerprint'] ?? null;
    $current_config_fp = buildConfigFingerprint($taxonomy_tree_file, $groups_file);
    if ($cached_config_fp !== $current_config_fp) {
        // Groups or taxonomy tree changed — all organisms need a rescan
        $stale_organisms = array_keys($organisms);
        $cache_stale_reason = 'groups or taxonomy config changed';
    } else {
        $cached_org_fps = $raw_cache['org_fingerprints'] ?? [];
        $current_org_fps = buildPerOrganismFingerprints($organism_data);
        foreach ($current_org_fps as $org_name => $fp) {
            if (($cached_org_fps[$org_name] ?? null) !== $fp) {
                $stale_organisms[] = $org_name;
            }
        }
        // Also flag organisms in cache that no longer exist on disk
        // (they'll just disappear after refresh — no warning needed)
        if (!empty($stale_organisms)) {
            $cache_stale_reason = count($stale_organisms) . ' organism(s) changed on disk';
        }
    }
}

// Handle image upload via AJAX
include_once __DIR__ . '/api/handle_image_upload.php';
handleImageUpload($config);

// Handle standard AJAX fix permissions request
handleAdminAjax(function($action) use ($organisms) {
    // Handle organism-specific actions
    if ($action === 'fix_permissions' && isset($_POST['organism'])) {
        $organism = $_POST['organism'];
        
        
        if (!isset($organisms[$organism]) || !$organisms[$organism]['db_file']) {
            echo json_encode(['success' => false, 'message' => 'Organism or database not found']);
            return true;
        }
        
        $db_file = $organisms[$organism]['db_file'];
        $result = fixDatabasePermissions($db_file);
        
        echo json_encode($result);
        return true;
    }
    
    // Handle rename assembly
    if ($action === 'rename_assembly' && isset($_POST['organism']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
        $organism = $_POST['organism'];
        $old_name = $_POST['old_name'];
        $new_name = $_POST['new_name'];
        
        
        
        if (!isset($organisms[$organism])) {
            echo json_encode(['success' => false, 'message' => 'Organism not found']);
            return true;
        }
        
        $organism_dir = $organisms[$organism]['path'];
        $result = renameAssemblyDirectory($organism_dir, $old_name, $new_name);
        
        echo json_encode($result);
        return true;
    }
    
    // Handle delete assembly
    if ($action === 'delete_assembly' && isset($_POST['organism']) && isset($_POST['dir_name'])) {
        $organism = $_POST['organism'];
        $dir_name = $_POST['dir_name'];
        
        
        
        if (!isset($organisms[$organism])) {
            echo json_encode(['success' => false, 'message' => 'Organism not found']);
            return true;
        }
        
        $organism_dir = $organisms[$organism]['path'];
        $result = deleteAssemblyDirectory($organism_dir, $dir_name);
        
        echo json_encode($result);
        return true;
    }
    
    // Handle save metadata
    if ($action === 'save_metadata' && isset($_POST['organism'])) {
        $organism = $_POST['organism'];
        $genus = $_POST['genus'] ?? '';
        $species = $_POST['species'] ?? '';
        $common_name = $_POST['common_name'] ?? '';
        $taxon_id = $_POST['taxon_id'] ?? '';
        $images_json = $_POST['images_json'] ?? '[]';
        $html_p_json = $_POST['html_p_json'] ?? '[]';
        $parents_json = $_POST['parents_json'] ?? '["gene"]';
        $children_json = $_POST['children_json'] ?? '["mRNA", "transcript"]';
        
        // Validate inputs
        if (empty($genus) || empty($species) || empty($common_name) || empty($taxon_id)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            return true;
        }
        
        
        
        if (!isset($organisms[$organism])) {
            echo json_encode(['success' => false, 'message' => 'Organism not found']);
            return true;
        }
        
        $organism_dir = $organisms[$organism]['path'];
        $organism_json_path = $organism_dir . '/organism.json';
        
        // Parse JSON fields safely
        $images = decodeJsonString($images_json);
        $html_p = decodeJsonString($html_p_json);
        $parents = decodeJsonString($parents_json);
        $children = decodeJsonString($children_json);
        
        // Build the metadata array
        $metadata = [
            'genus' => $genus,
            'species' => $species,
            'common_name' => $common_name,
            'taxon_id' => $taxon_id
        ];
        
        // Add images if provided
        if (!empty($images)) {
            $metadata['images'] = $images;
        }
        
        // Add html paragraphs if provided
        if (!empty($html_p)) {
            $metadata['html_p'] = $html_p;
        }
        
        // Add feature types if provided
        if (!empty($parents) || !empty($children)) {
            $metadata['feature_types'] = [
                'parents' => $parents ?: ['gene'],
                'children' => $children ?: ['mRNA', 'transcript']
            ];
        }
        
        // Merge with existing data to preserve other fields
        $metadata = loadAndMergeJson($organism_json_path, $metadata);
        
        // Write the file
        $json_string = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($json_string === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to encode JSON']);
            return true;
        }
        
        if (@file_put_contents($organism_json_path, $json_string) === false) {
            $write_error = getFileWriteError($organism_json_path);
            if ($write_error) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'File not writable',
                    'error' => $write_error
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to write organism.json file.']);
            }
            return true;
        }
        
        echo json_encode(['success' => true, 'message' => 'Metadata saved successfully']);
        return true;
    }
    
    return false;
});

// Use same organisms data for page display
$organisms = $organisms;

// Check for duplicate taxon IDs
$taxon_id_map = [];
$duplicate_taxon_ids = [];
foreach ($organisms as $org_name => $org_data) {
    if (!empty($org_data['info']['taxon_id'])) {
        $taxon_id = $org_data['info']['taxon_id'];
        if (!isset($taxon_id_map[$taxon_id])) {
            $taxon_id_map[$taxon_id] = [];
        }
        $taxon_id_map[$taxon_id][] = $org_name;
    }
}
foreach ($taxon_id_map as $taxon_id => $org_names) {
    if (count($org_names) > 1) {
        $duplicate_taxon_ids[$taxon_id] = $org_names;
    }
}

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Configure display
$display_config = [
    'title' => 'Manage Organisms - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/manage_organisms.php',
];

// Prepare data for content file
$data = [
    'organisms' => $organisms,
    'groups_data' => $groups_data,
    'sequence_types' => $sequence_types,
    'config' => $config,
    'organism_data' => $organism_data,
    'taxonomy_tree_file' => $taxonomy_tree_file,
    'duplicate_taxon_ids' => $duplicate_taxon_ids,
    'cache_generated' => $cache_generated,
    'stale_organisms' => $stale_organisms,
    'cache_stale_reason' => $cache_stale_reason,
    'page_script' => [
        '/' . $config->getString('site') . '/js/admin-utilities.js',
        '/' . $config->getString('site') . '/js/modules/organism-management.js'
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        "(function(){
  const el = document.getElementById('cacheAge');
  if (!el || !el.dataset.generated) return;
  const d = new Date(el.dataset.generated.replace(' ', 'T'));
  const sec = Math.round((Date.now() - d) / 1000);
  if (sec < 60) el.textContent = sec + 's ago';
  else if (sec < 3600) el.textContent = Math.floor(sec/60) + 'm ago';
  else el.textContent = Math.floor(sec/3600) + 'h ago';
})();"
    ]
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
