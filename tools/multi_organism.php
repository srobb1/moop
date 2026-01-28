<?php
/**
 * MULTI-ORGANISM SEARCH PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (multi_organism.php)
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
 * Content file (pages/multi_organism.php) displays data
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
 * - Display content (pages/multi_organism.php does that)
 */

include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$images_path = $config->getString('images_path');

// Get organisms from query parameters
$organisms = $_GET['organisms'] ?? [];
if (is_string($organisms)) {
    $organisms = [$organisms];
}

if (empty($organisms)) {
    header("Location: /$site/index.php");
    exit;
}

// Validate access for all organisms
foreach ($organisms as $organism) {
    $is_public = is_public_organism($organism);
    $has_organism_access = has_access('Collaborator', $organism);
    
    if (!$has_organism_access && !$is_public) {
        header("Location: /$site/access_denied.php");
        exit;
    }
}

// Configure display template
$organism_list = implode(', ', array_map('htmlspecialchars', $organisms));
$display_config = [
    'title' => 'Multi-Organism Search - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/multi_organism.php',
    'page_script' => [
        "/$site/js/modules/organism-utils.js",
        "/$site/js/modules/search-utils.js",
        "/$site/js/multi-organism-search.js"
    ],
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const allOrganisms = " . json_encode($organisms) . ";",
        "let selectedOrganisms = allOrganisms;",
        "const totalOrganisms = allOrganisms.length;",
        "const siteTitle = '" . addslashes($config->getString('siteTitle')) . "';"
    ]
];

// Data to pass to content file
$data = [
    'organisms' => $organisms,
    'organism_list' => $organism_list,
    'config' => $config,
    'site' => $site,
    'images_path' => $images_path,
    'absolute_images_path' => $absolute_images_path,
    'inline_scripts' => $display_config['inline_scripts']
];

// Use generic display template
include_once __DIR__ . '/display-template.php';
?>
