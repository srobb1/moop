<?php
/**
 * GROUPS DISPLAY PAGE - Wrapper
 * 
 * This is the main entry point for group display pages.
 * It:
 * 1. Loads context (group data, organisms in group)
 * 2. Handles access control
 * 3. Configures the display template
 * 4. Uses generic template system to render the page
 * 
 * The actual HTML content is in: pages/groups.php
 * The generic rendering is in: display-template.php
 * The layout/structure is in: includes/layout.php
 */

include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');
$organism_data = $config->getPath('organism_data');

// Get the group name from query parameter
$group_name = $_GET['group'] ?? '';

if (empty($group_name)) {
    header("Location: /$site/index.php");
    exit;
}

// Load group descriptions using helper
$group_descriptions_file = "$metadata_path/group_descriptions.json";
$group_descriptions = loadJsonFile($group_descriptions_file, []);

// Load organism assembly groups
$group_data = getGroupData();

// Find the description for this group
$group_info = null;
foreach ($group_descriptions as $desc) {
    if ($desc['group_name'] === $group_name && ($desc['in_use'] ?? false)) {
        $group_info = $desc;
        break;
    }
}

// Get organisms in this group that user has access to
$group_organisms = getAccessibleOrganismsInGroup($group_name, $group_data);

// Access control: Check if user has access to this group
if (!is_public_group($group_name)) {
    requireAccess('Collaborator', $group_name);
}

// Configure display template
$display_config = [
    'title' => htmlspecialchars($group_name) . ' - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/groups.php',
    'page_script' => "/$site/js/groups-display.js",
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const groupName = '" . addslashes($group_name) . "';"
    ]
];

// Data to pass to content file
$data = [
    'group_name' => $group_name,
    'group_info' => $group_info,
    'group_organisms' => $group_organisms,
    'config' => $config,
    'inline_scripts' => $display_config['inline_scripts']
];

// Use generic display template
include_once __DIR__ . '/display-template.php';
?>
