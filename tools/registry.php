<?php
/**
 * PHP Function Registry Display
 * 
 * Displays the PHP function registry with search and filter capabilities.
 * Uses registry-template.php for consistent layout and styling.
 */

include_once __DIR__ . '/tool_init.php';

$config = ConfigManager::getInstance();
$site = $config->getString('site');

// Prepare data for display
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $site,
    'config' => $config,
];

// Configure display
$display_config = [
    'content_file' => __DIR__ . '/pages/registry.php',
    'title' => 'PHP Function Registry',
    'page_script' => '/' . $site . '/js/registry.js'
];

// Render using registry template
include_once __DIR__ . '/registry-template.php';
?>

