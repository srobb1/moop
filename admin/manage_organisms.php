<?php
// Enable output buffering BEFORE any includes
ob_start();

include_once __DIR__ . '/admin_init.php';
require_once __DIR__ . '/../lib/retire.php';
include_once __DIR__ . '/../lib/blast_functions.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sequence_types = $config->getSequenceTypes();
$groups_data = getGroupData();
$taxonomy_tree_file = $config->getPath('metadata_path') . '/taxonomy_tree_config.json';

// Pre-build lookup structures for the Groups column
$existing_groups = getAllExistingGroups($groups_data);
$organism_groups_lookup = [];
foreach ($groups_data as $_ge) {
    foreach ($_ge['groups'] as $_g) {
        $organism_groups_lookup[$_ge['organism']][$_g] = true;
    }
}
foreach ($organism_groups_lookup as &$_gs) {
    $_gs = array_keys($_gs);
    sort($_gs);
}
unset($_gs, $_ge, $_g);
$groups_file = $metadata_path . '/organism_assembly_groups.json';

// Read the cache file directly — never scan synchronously in a web request.
// Scanning happens via the background refresh endpoint (admin/api/refresh_organism_cache.php).
$cache_file = moop_organism_cache_file();
$organisms = [];
$cache_generated = null;
$raw_cache = null;
if (file_exists($cache_file)) {
    $raw_cache = loadJsonFile($cache_file, []);
    if ($raw_cache && isset($raw_cache['data'])) {
        $organisms = $raw_cache['data'];
        $cache_generated = $raw_cache['generated'] ?? null;
    }
}

// Quick staleness check — only filemtime + scandir calls, no full scan (~50ms for 60 organisms).
// moop_organism_cache_staleness() is the one test every admin page and housekeeping use.
$stale_organisms     = [];
$cache_stale_reason  = null;
$cache_stale_reasons = [];
if ($raw_cache) {
    $staleness           = moop_organism_cache_staleness($raw_cache, $organism_data, $taxonomy_tree_file, $groups_file);
    $stale_organisms     = $staleness['changed'];   // marked Stale in the table, config change or not
    $cache_stale_reasons = $staleness['reasons'];

    // Remove organisms that no longer exist on disk — update cache immediately so they
    // disappear on page reload without needing a full background refresh. ONLY their entries:
    // this used to store every current fingerprint, which also recorded each changed-but-not-
    // rescanned organism as current, so its Stale badge vanished on the next load while its
    // cached data stayed old.
    if ($staleness['deleted'] && isset($raw_cache['org_fingerprints'])) {
        foreach ($staleness['deleted'] as $org_name) {
            unset($organisms[$org_name], $raw_cache['org_fingerprints'][$org_name]);
        }
        $raw_cache['data']      = $organisms;
        $raw_cache['generated'] = date('Y-m-d H:i:s');
        organism_cache_write_atomic($cache_file, $raw_cache);
    }

    $parts = array_merge($stale_organisms ? [count($stale_organisms) . ' organism(s) changed on disk'] : [],
                         $cache_stale_reasons);
    if ($parts) $cache_stale_reason = implode('; ', $parts);
}

// Data health alerts — same shared helper the dashboard uses, so both pages show the
// SAME warnings (ungrouped, orphaned gene sets, not-in-tree, stale groups, new gene sets).
$_health = computeDataHealthAlerts($organism_data);

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
    
    // Handle rename gene set directory
    if ($action === 'rename_gene_set' && isset($_POST['organism']) && isset($_POST['assembly']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $old_name = $_POST['old_name'];
        $new_name = $_POST['new_name'];

        if (!isset($organisms[$organism])) {
            echo json_encode(['success' => false, 'message' => 'Organism not found']);
            return true;
        }

        $organism_dir = $organisms[$organism]['path'];
        $result = renameGeneSetDirectory($organism_dir, $assembly, $old_name, $new_name);

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
        
        
        // Add feature types if provided
        if (!empty($parents) || !empty($children)) {
            $metadata['feature_types'] = [
                'parents' => $parents ?: ['gene'],
                'children' => $children ?: ['mRNA', 'transcript']
            ];
        }
        
        // Merge with existing data to preserve other fields
        $metadata = loadAndMergeJson($organism_json_path, $metadata);

        // Images and paragraphs are written exactly as the modal shows them -- it now reads
        // organism.json itself, so what it sends is the whole list. They used to be written
        // only when non-empty, so removing the last image or paragraph never took effect.
        // An empty list removes the key: the site then picks its own image / writes its own
        // description again (NCBI or Wikipedia), as the modal's help text says.
        // Only a list the request actually sent: a missing field must never read as "remove all".
        foreach (['images' => [$images, 'images_json'], 'html_p' => [$html_p, 'html_p_json']] as $key => [$list, $field]) {
            if (!isset($_POST[$field])) continue;
            if (!empty($list)) $metadata[$key] = $list;
            else unset($metadata[$key]);
        }
        
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
    'title' => 'Manage Organisms',
    'content_file' => __DIR__ . '/pages/manage_organisms.php',
];

// Read lineage cache metadata for UI display
$lineage_cache_generated = null;
$lineage_cache_file = "$metadata_path/taxonomy_lineage_cache.json";
if (file_exists($lineage_cache_file)) {
    $lc_raw = loadJsonFile($lineage_cache_file, []);
    $lineage_cache_generated = $lc_raw['generated'] ?? null;
}

// Prepare data for content file
$data = [
    'organisms' => $organisms,
    'groups_data' => $groups_data,
    'existing_groups' => $existing_groups,
    'organism_groups_lookup' => $organism_groups_lookup,
    'sequence_types' => $sequence_types,
    'config' => $config,
    'organism_data' => $organism_data,
    'taxonomy_tree_file' => $taxonomy_tree_file,
    'duplicate_taxon_ids' => $duplicate_taxon_ids,
    'cache_generated' => $cache_generated,
    'stale_organisms' => $stale_organisms,
    'cache_stale_reason' => $cache_stale_reason,
    // Reuse this page's existing staleness computation for the shared health card's
    // "cache out of date" notice ($stale_organisms = per-org changes; reason covers
    // the config-only case where no individual organism file changed).
    'cache_stale' => ($cache_stale_reason !== null),
    'cache_changed_orgs' => $stale_organisms,
    'cache_stale_reasons' => $cache_stale_reasons,
    'lineage_cache_generated' => $lineage_cache_generated,
    'retired' => moop_retired_list($config),   // lib/retire.php
    'page_styles' => [
        '/' . $config->getString('site') . '/css/manage-organisms.css'
    ],
    'page_script' => [
        '/' . $config->getString('site') . '/js/admin-utilities.js',
        '/' . $config->getString('site') . '/js/modules/organism-management.js',
        '/' . $config->getString('site') . '/js/modules/retire-organism.js'
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        "const existingGroups = " . json_encode($existing_groups) . ";",
        "(function(){
  function setAgeText(el) {
    if (!el || !el.dataset.generated) return;
    const d = new Date(el.dataset.generated.replace(' ', 'T') + 'Z');
    const sec = Math.round((Date.now() - d) / 1000);
    if (sec < 60) el.textContent = sec + 's ago';
    else if (sec < 3600) el.textContent = Math.floor(sec/60) + 'm ago';
    else if (sec < 86400) el.textContent = Math.floor(sec/3600) + 'h ago';
    else el.textContent = Math.floor(sec/86400) + 'd ago';
  }
  setAgeText(document.getElementById('cacheAge'));
  setAgeText(document.getElementById('taxonomySyncAge'));
})();"
    ]
] + $_health;   // the health card's data, whole -- moop_data_health_empty() gives its shape

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
