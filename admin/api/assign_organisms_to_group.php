<?php
/**
 * API endpoint to assign unassigned organisms to a group
 * Called from organism_checklist.php
 */

include_once __DIR__ . '/../admin_init.php';

// Only allow POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

// Get paths
$organism_data_dir = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$groups_file = "$metadata_path/organism_assembly_groups.json";
$group_name = $_POST['group'] ?? 'New';

// Load current group data
$group_data = json_decode(file_get_contents($groups_file), true) ?? [];

// Get all organisms in system
$organisms_in_system = [];
if (is_dir($organism_data_dir)) {
    foreach (scandir($organism_data_dir) as $item) {
        if ($item !== '.' && $item !== '..' && is_dir("$organism_data_dir/$item")) {
            $organisms_in_system[] = $item;
        }
    }
}

// Find organisms without ANY group assignments
$unassigned_organisms = [];
foreach ($organisms_in_system as $org) {
    $has_groups = false;
    foreach ($group_data as $entry) {
        if ($entry['organism'] === $org && !empty($entry['groups'])) {
            $has_groups = true;
            break;
        }
    }
    if (!$has_groups) {
        $unassigned_organisms[] = $org;
    }
}

// Find assemblies for unassigned organisms
$count = 0;
foreach ($unassigned_organisms as $org) {
    $org_dir = "$organism_data_dir/$org";
    if (is_dir($org_dir)) {
        foreach (scandir($org_dir) as $item) {
            if ($item !== '.' && $item !== '..' && is_dir("$org_dir/$item")) {
                // Add to group_data
                $group_data[] = [
                    'organism' => $org,
                    'assembly' => $item,
                    'groups' => [$group_name]
                ];
                $count++;
            }
        }
    }
}

// Save updated data
if (file_put_contents($groups_file, json_encode($group_data, JSON_PRETTY_PRINT)) !== false) {
    header('HTTP/1.1 200 OK');
    echo json_encode(['success' => true, 'count' => $count]);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'error' => 'Could not write to groups file']);
}
?>
