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

// Get all organisms info once (used by both AJAX handler and page display)
$organisms = getDetailedOrganismsInfo($organism_data, $sequence_types);

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
    'taxonomy_tree_file' => $taxonomy_tree_file,
    'page_script' => '/' . $config->getString('site') . '/js/modules/organism-management.js',
    'inline_scripts' => [
        "const style = document.createElement('style');
        style.textContent = `.collapse:not(.show) { display: none !important; } .collapse.show { display: block !important; }`;
        document.head.appendChild(style);
        
        document.addEventListener('DOMContentLoaded', function() {
            const aboutElement = document.getElementById('aboutOrganismManagement');
            if (aboutElement) {
                // Prevent Bootstrap from re-showing after hide
                aboutElement.addEventListener('hide.bs.collapse', function(e) {
                    // Do nothing - just let it hide
                    return true;
                });
            }
        });"
    ]
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
