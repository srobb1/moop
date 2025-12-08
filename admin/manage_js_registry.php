<?php
/**
 * MANAGE JS REGISTRY - Admin Controller
 * 
 * Displays the interactive JavaScript function registry with admin authentication.
 * Uses registry-template.php for consistent layout with tools/registry.php
 */

ob_start();
include_once __DIR__ . '/admin_init.php';

// Load config and data
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');
$json_registry = $docs_path . '/js_function_registry.json';

// Load JSON registry
$registry = null;
$lastUpdate = 'Never';
$registryStatus = [];
if (file_exists($json_registry)) {
    $json_content = file_get_contents($json_registry);
    $registry = json_decode($json_content, true);
    
    // Get registry status (includes staleness check)
    require_once __DIR__ . '/../lib/functions_filesystem.php';
    $registryStatus = getRegistryLastUpdate($json_registry, $json_registry);
    $lastUpdate = $registryStatus['timestamp'];
    $isStale = $registryStatus['isStale'];
    $statusMessage = $registryStatus['status'];
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
    'content_file' => __DIR__ . '/pages/manage_js_registry.php',
    'title' => 'JavaScript Function Registry',
    'page_script' => [
       '/' . $site . '/js/manage-registry.js',
       '/' . $site . '/js/admin-utilities.js',
     ]
];

// Include template (which includes layout system)
include_once __DIR__ . '/registry-template.php';

?>
