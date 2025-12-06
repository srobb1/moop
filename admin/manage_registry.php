<?php
/**
 * MANAGE REGISTRY - Admin Controller
 * 
 * Displays the interactive PHP function registry with admin authentication.
 * Uses the same visualization as tools/registry.php but requires admin access.
 */

ob_start();
include_once __DIR__ . '/admin_init.php';

// Load config and data
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');
$json_registry = $docs_path . '/function_registry.json';

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
    'page_script' => '/' . $site . '/js/registry.js'
];

// Include layout system
include_once __DIR__ . '/../includes/layout.php';
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
