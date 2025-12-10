<?php
/**
 * MANAGE TAXONOMY TREE - Wrapper
 * 
 * Handles admin access verification and renders taxonomy tree management
 * using clean architecture layout system.
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

$siteTitle = $config->getString('siteTitle');
$site = $config->getString('site');

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$absolute_images_path = $config->getPath('absolute_images_path');

// Handle standard AJAX fix permissions request
handleAdminAjax();

$tree_config_file = "$metadata_path/taxonomy_tree_config.json";
$organism_data_dir = $organism_data;
$message = '';
$error = '';
$file_write_error = getFileWriteError($tree_config_file);
$dir_error = getDirectoryError($absolute_images_path . '/ncbi_taxonomy');

// Load organisms metadata
$organisms = loadAllOrganismsMetadata($organism_data_dir);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($file_write_error) {
            $error = "File is not writable. Please fix permissions first.";
        } elseif ($_POST['action'] === 'generate') {
            try {
                // $organisms was already loaded at the top of the file
                
                if (empty($organisms)) {
                    $error = "No organisms found in {$organism_data_dir}";
                } else {
                    $tree_data = build_tree_from_organisms($organisms);
                    
                    // Save to file
                    $json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    if ($json === false) {
                        $error = "Failed to encode tree data as JSON: " . json_last_error_msg();
                    } elseif (file_put_contents($tree_config_file, $json) !== false) {
                        $message = "Phylogenetic tree generated successfully! Found " . count($organisms) . " organisms.";
                    } else {
                        $error = "Failed to write tree config file to: " . $tree_config_file;
                        if (!is_writable($tree_config_file)) {
                            $error .= " (File is not writable by current process)";
                        }
                    }
                }
            } catch (Exception $e) {
                $error = "Error generating tree: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'save_manual') {
            $tree_json = $_POST['tree_json'] ?? '';
            
            // Validate JSON
            $tree_data = json_decode($tree_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = json_encode($tree_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($json === false) {
                    $error = "Failed to encode tree data as JSON: " . json_last_error_msg();
                } elseif (file_put_contents($tree_config_file, $json) !== false) {
                    $message = "Tree configuration saved successfully!";
                } else {
                    $error = "Failed to save tree configuration to: " . $tree_config_file;
                    if (!is_writable($tree_config_file)) {
                        $error .= " (File is not writable by current process)";
                    }
                }
            } else {
                $error = "Invalid JSON: " . json_last_error_msg();
            }
        }
    }
}

// Load current tree config using helper
$current_tree = loadJsonFile($tree_config_file, null);

// Configure display
$display_config = [
    'title' => 'Manage Taxonomy Tree - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_taxonomy_tree.php',
];

// Prepare data for content file
$data = [
    'message' => $message,
    'error' => $error,
    'file_write_error' => $file_write_error,
    'dir_error' => $dir_error,
    'organisms' => $organisms,
    'organism_data_dir' => $organism_data_dir,
    'current_tree' => $current_tree,
    'config' => $config,
    'site' => $site,
    'page_styles' => [
        '/' . $site . '/css/manage-taxonomy-tree.css'
    ],
    'page_script' => [
        '/' . $site . '/js/admin-utilities.js',
        '/' . $site . '/js/modules/manage-taxonomy-tree.js'
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $site . "';",
        "const currentTree = " . json_encode($current_tree) . ";"
    ]
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);
?>
