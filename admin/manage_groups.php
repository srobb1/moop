<?php
/**
 * MANAGE GROUPS - Wrapper
 * 
 * Handles admin access verification and renders group management
 * using clean architecture layout system.
 */

ob_start();
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Get config
$siteTitle = $config->getString('siteTitle');
$site = $config->getString('site');

// Handle AJAX requests after admin access verification
handleAdminAjax();

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');
$organism_data_path = $config->getPath('organism_data');

$groups_file = $metadata_path . '/organism_assembly_groups.json';
$file_write_error = null;
$desc_file_write_error = null;

// Load group data using helper
$groups_data = loadJsonFile($groups_file, []);

// Load group descriptions using helper
$descriptions_file = $metadata_path . '/group_descriptions.json';
$descriptions_data = loadJsonFile($descriptions_file, []);

// Check if groups file is writable
$file_write_error = getFileWriteError($groups_file);

// Check if descriptions file is writable
$desc_file_write_error = getFileWriteError($descriptions_file);

// Check if change_log directory is writable (do this check early but safely)
$change_log_dir = $metadata_path . '/change_log';
$change_log_error = null;
if (!is_dir($change_log_dir)) {
    // Try to create it
    if (!@mkdir($change_log_dir, 0775, true)) {
        $change_log_error = @getDirectoryError($change_log_dir);
    }
} else {
    $change_log_error = @getDirectoryError($change_log_dir);
}

$all_organisms = getOrganismsWithAssemblies($organism_data_path);

$all_existing_groups = getAllExistingGroups($groups_data);
$descriptions_data = loadJsonFile($descriptions_file, []);
$updated_descriptions = syncGroupDescriptions($all_existing_groups, $descriptions_data);

// Save synced descriptions data
if (file_exists($descriptions_file) && is_writable($descriptions_file)) {
    file_put_contents($descriptions_file, json_encode($descriptions_data, JSON_PRETTY_PRINT));
}

// Create a mapping of which entries exist in the filesystem
$groups_data_with_status = [];
foreach ($groups_data as $data) {
    $exists_in_fs = isset($all_organisms[$data['organism']]) && 
                    in_array($data['assembly'], $all_organisms[$data['organism']]);
    $data['_fs_exists'] = $exists_in_fs;
    $groups_data_with_status[] = $data;
}

// Keep the status-marked data, but don't modify the original JSON file
// The original JSON is preserved as-is for user review

// Handle POST requests for updates, additions, and deletions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $log_file = $metadata_path . '/change_log/manage_groups.log';
    $timestamp = date('Y-m-d H:i:s');
    $username = $_SESSION['username'] ?? 'unknown';
    
    // Ensure log directory exists
    $log_dir = $metadata_path . '/change_log';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }
    
    // Handle group description updates
    if (isset($_POST['save_description']) && !$desc_file_write_error) {
        $group_name = $_POST['group_name'];
        $images = json_decode($_POST['images_json'], true);
        $html_p = json_decode($_POST['html_p_json'], true);
        
        // Update the description
        foreach ($descriptions_data as &$desc) {
            if ($desc['group_name'] === $group_name) {
                $desc['images'] = $images;
                $desc['html_p'] = $html_p;
                break;
            }
        }
        unset($desc);
        
        // Save to file
        $save_result = file_put_contents($descriptions_file, json_encode($descriptions_data, JSON_PRETTY_PRINT));
        
        if ($save_result === false) {
            $_SESSION['error_message'] = "Error: Could not write to group_descriptions.json. Check file permissions.";
        } else {
            // Log the change
            $desc_log_entry = sprintf(
                "[%s] UPDATE by %s | Group: %s\n",
                $timestamp,
                $username,
                $group_name
            );
            file_put_contents($log_file, $desc_log_entry, FILE_APPEND);
            $_SESSION['success_message'] = "Group description updated successfully!";
        }
    }
    
    if (isset($_POST['update'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $groups_input = trim($_POST['groups']);
        
        if ($groups_input === '') {
            $groups = [];
        } else {
            $groups = array_values(array_filter(array_map('trim', explode(',', $groups_input))));
        }

        // Find old groups for logging
        $old_groups = [];
        foreach ($groups_data as &$data) {
            if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                $old_groups = $data['groups'];
                $data['groups'] = $groups;
                break;
            }
        }
        unset($data);
        
        // Log the change
        $log_entry = sprintf(
            "[%s] UPDATE by %s | Organism: %s | Assembly: %s | Old groups: [%s] | New groups: [%s]\n",
            $timestamp,
            $username,
            $organism,
            $assembly,
            implode(', ', $old_groups),
            implode(', ', $groups)
        );
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
    } elseif (isset($_POST['add'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $groups_input = trim($_POST['groups']);
        
        if ($groups_input === '') {
            $groups = [];
        } else {
            $groups = array_values(array_filter(array_map('trim', explode(',', $groups_input))));
        }

        // Add new entry
        $new_entry = [
            'organism' => $organism,
            'assembly' => $assembly,
            'groups' => $groups
        ];
        
        $groups_data[] = $new_entry;
        
        // Log the addition
        $log_entry = sprintf(
            "[%s] ADD by %s | Organism: %s | Assembly: %s | Groups: [%s]\n",
            $timestamp,
            $username,
            $organism,
            $assembly,
            implode(', ', $groups)
        );
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
    } elseif (isset($_POST['delete'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        
        // Find and remove the entry
        $old_groups = [];
        foreach ($groups_data as $key => &$data) {
            if ($data['organism'] === $organism && $data['assembly'] === $assembly) {
                $old_groups = $data['groups'];
                unset($groups_data[$key]);
                break;
            }
        }
        unset($data);
        $groups_data = array_values($groups_data); // Reset keys
        
        // Log the deletion
        $log_entry = sprintf(
            "[%s] DELETE by %s | Organism: %s | Assembly: %s | Old groups: [%s]\n",
            $timestamp,
            $username,
            $organism,
            $assembly,
            implode(', ', $old_groups)
        );
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
    
    // Save the updated groups data
    if (!$file_write_error) {
        if (@file_put_contents($groups_file, json_encode($groups_data, JSON_PRETTY_PRINT))) {
            $_SESSION['success_message'] = "Groups configuration updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error saving groups configuration. Check file permissions.";
        }
    }
    
    ob_end_clean();
    header('Location: manage_groups.php');
    exit();
}

// Identify unrepresented organisms/assemblies
$represented_organisms = [];
foreach ($groups_data as $data) {
    $represented_organisms[$data['organism']][] = $data['assembly'];
}

$unrepresented_organisms = [];
foreach ($all_organisms as $organism => $assemblies) {
    foreach ($assemblies as $assembly) {
        if (!isset($represented_organisms[$organism]) || !in_array($assembly, $represented_organisms[$organism])) {
            $unrepresented_organisms[$organism][] = $assembly;
        }
    }
}

// Find stale entries (entries in groups_data but not in filesystem)
$stale_entries = array_filter($groups_data_with_status, function($data) {
    return !$data['_fs_exists'];
});

// Configure display
$display_config = [
    'title' => 'Manage Groups - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_groups.php',
];

// Prepare data for content file
$data = [
    'groups_file' => $groups_file,
    'file_write_error' => $file_write_error,
    'desc_file_write_error' => $desc_file_write_error,
    'change_log_error' => $change_log_error,
    'all_organisms' => $all_organisms,
    'groups_data_with_status' => $groups_data_with_status,
    'descriptions_data' => $descriptions_data,
    'unrepresented_organisms' => $unrepresented_organisms,
    'stale_entries' => $stale_entries,
    'existing_groups' => $all_existing_groups,
    'config' => $config,
    'page_styles' => [
        '/' . $site . '/css/manage-groups.css'
    ],
    'page_script' => [
        '/' . $site . '/js/admin-utilities.js',
        '/' . $site . '/js/modules/manage-groups.js'
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $site . "';",
        "const isDescFileWriteError = " . ($desc_file_write_error ? 'true' : 'false') . ";",
        "const existingGroups = " . json_encode($all_existing_groups) . ";"
    ]
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
