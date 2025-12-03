<?php
/**
 * GROUPS DISPLAY PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (groups.php)
 *   ↓
 * Validate user access
 *   ↓
 * Load group data from database
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/groups.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Validate user access (via access_control.php)
 * - Load group data from database
 * - Configure title, scripts, styles
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (pages/groups.php does that)
 */

include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$images_path = $config->getString('images_path');

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
    'title' => htmlspecialchars($group_name) . ' - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/groups.php',
    'page_script' => "/$site/js/groups-display.js",
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const groupName = '" . addslashes($group_name) . "';",
        "const groupOrganisms = " . json_encode($group_organisms) . ";"
    ]
];

// Data to pass to content file
$data = [
    'group_name' => $group_name,
    'group_info' => $group_info,
    'group_organisms' => $group_organisms,
    'config' => $config,
    'site' => $site,
    'images_path' => $images_path,
    'absolute_images_path' => $absolute_images_path,
    'organism_data' => $organism_data,
];

// Use generic display template
include_once __DIR__ . '/display-template.php';
?>
