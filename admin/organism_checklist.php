<?php
/**
 * ORGANISM CHECKLIST - Wrapper
 * 
 * Handles admin access verification and renders organism setup checklist
 * using clean architecture layout system.
 */

// Load admin initialization (handles auth, config, includes)
include_once __DIR__ . '/admin_init.php';

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Get config
$site = $config->getString('site');
$organism_data = $config->getPath('organism_data');

// Configure display
$display_config = [
    'title' => 'New Organism Setup Checklist - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/organism_checklist.php',
];

// Prepare data for content file
$data = [
    'config' => $config,
    'site' => $site,
    'organism_data' => $organism_data,
    'page_script' => [
        '/' . $config->getString('site') . '/js/admin-utilities.js',
    ],
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
