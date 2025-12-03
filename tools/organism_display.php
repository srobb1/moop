<?php
/**
 * ORGANISM DISPLAY - Wrapper / Entry Point
 * 
 * Part of clean architecture refactoring.
 * This wrapper coordinates the display of an organism page.
 * 
 * Workflow:
 * 1. Load initialization (tool_init.php)
 * 2. Load layout system (layout.php)
 * 3. Get organism context/data
 * 4. Prepare data for content file
 * 5. Call render_display_page() which:
 *    - Loads all CSS/JS
 *    - Includes navbar/footer
 *    - Renders content file
 *    - Returns complete HTML page
 * 
 * The actual display content is in: tools/pages/organism.php
 * The HTML structure is handled by: includes/layout.php
 */

// Load initialization and layout system
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$images_path = $config->getString('images_path');
$site = $config->getString('site');

// Setup organism context (validates param, loads info, checks access)
$organism_context = setupOrganismDisplayContext($_GET['organism'] ?? '', $organism_data);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Prepare data array for content file
// These variables will be extracted and available in tools/pages/organism.php
$data = [
    'organism_name' => $organism_name,
    'organism_info' => $organism_info,
    'config' => $config,
    'images_path' => $images_path,
    'absolute_images_path' => $absolute_images_path,
    'site' => $site,
    'page_script' => '/' . $site . '/js/organism-display.js'
];

// Build page title
$page_title = htmlspecialchars(
    ($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) . ' - ' . $config->getString('siteTitle')
);

// Render using layout system
echo render_display_page(
    __DIR__ . '/pages/organism.php',
    $data,
    $page_title
);

?>
