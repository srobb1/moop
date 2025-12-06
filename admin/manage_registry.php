<?php
/**
 * MANAGE REGISTRY - Admin Controller
 * 
 * Displays the interactive PHP function registry with admin authentication.
 * Uses registry-template.php for consistent layout with tools/registry.php
 */

ob_start();
include_once __DIR__ . '/admin_init.php';

// Load config and data
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');
$json_registry = $docs_path . '/function_registry_test.json';

// Load JSON registry
$registry = null;
$lastUpdate = 'Never';
if (file_exists($json_registry)) {
    $lastUpdate = date('Y-m-d H:i:s', filemtime($json_registry));
    $json_content = file_get_contents($json_registry);
    $registry = json_decode($json_content, true);
}

$site = $config->getString('site');

// Prepare data for display
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $site,
    'config' => $config,
    'registry' => $registry,
    'lastUpdate' => $lastUpdate,
];

// Configure display
$display_config = [
    'content_file' => __DIR__ . '/pages/manage_registry.php',
    'title' => 'Function Registry',
    'page_script' => '/' . $site . '/js/manage-registry.js'
];

// Include template (which includes layout system)
include_once __DIR__ . '/../tools/registry-template.php';
