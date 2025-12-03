<?php
/**
 * ORGANISM DISPLAY PAGE
 * 
 * Entry point for displaying a single organism's information.
 * 
 * Uses generic template system (display-template.php) for:
 * - HTML structure
 * - Script loading
 * - Layout consistency
 * 
 * This file is responsible for:
 * 1. Loading organism data
 * 2. Validating access
 * 3. Configuring display template
 * 4. Including generic template to render page
 */

// Load initialization
include_once __DIR__ . '/tool_init.php';

// Get config values
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$images_path = $config->getString('images_path');
$site = $config->getString('site');

// Setup organism context (validates param, loads info, checks access)
$organism_context = setupOrganismDisplayContext($_GET['organism'] ?? '', $organism_data);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Configure display template
$display_config = [
    'title' => htmlspecialchars(
        ($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) . ' - ' . $config->getString('siteTitle')
    ),
    'content_file' => __DIR__ . '/pages/organism.php',
    'page_script' => '/' . $site . '/js/organism-display.js',
    'inline_scripts' => [
        "const sitePath = '/" . $site . "';",
        "const organismName = '" . addslashes($organism_name) . "';"
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
];

// Use generic template to render
include_once __DIR__ . '/display-template.php';

?>
