<?php
/**
 * ORGANISM DISPLAY PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (organism.php)
 *   ↓
 * Validate user access
 *   ↓
 * Load organism data from database
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/organism.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Validate user access (via access_control.php)
 * - Load organism data from database
 * - Configure title, scripts, styles
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (pages/organism.php does that)
 */

// Load initialization
include_once __DIR__ . '/tool_init.php';

// Get config values
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$images_path = $config->getString('images_path');
$site = $config->getString('site');
$metadata_path = $config->getPath('metadata_path');

// Setup organism context (validates param, loads info, checks access)
$organism_context = setupOrganismDisplayContext($_GET['organism'] ?? '', $organism_data);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Load taxonomy tree and user access for breadcrumb counts
$group_data = getGroupData();
$taxonomy_user_access = getTaxonomyTreeUserAccess($group_data);
$taxonomy_tree_data = json_decode(file_get_contents("$metadata_path/taxonomy_tree_config.json"), true);

// Configure display template
$display_config = [
    'title' => htmlspecialchars(
        ($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) . ' - ' . $config->getString('siteTitle')
    ),
    'content_file' => __DIR__ . '/pages/organism.php',
    'page_script' => [
        '/' . $site . '/js/modules/search-utils.js',
        '/' . $site . '/js/organism-display.js'
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $site . "';",
        "const organismName = '" . addslashes($organism_name) . "';",
        "const siteTitle = '" . addslashes($config->getString('siteTitle')) . "';"
    ]
];

// Prepare data for content file
$data = [
    'organism_name' => $organism_name,
    'organism_info' => $organism_info,
    'config' => $config,
    'site' => $site,
    'images_path' => $images_path,
    'absolute_images_path' => $absolute_images_path,
    'taxonomy_user_access' => $taxonomy_user_access,
    'taxonomy_tree_data' => $taxonomy_tree_data,
];

// Use generic template to render
include_once __DIR__ . '/display-template.php';

?>
