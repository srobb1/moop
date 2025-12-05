<?php
/**
 * PHP Function Registry Display
 * Public-facing display of the PHP function registry with search and filter capabilities
 */

require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../includes/auth_check.php';

$config = ConfigManager::getInstance();
$site = $config->getString('site');

// Prepare data for display
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $site,
    'config' => $config,
    'page_styles' => [
        '/' . $site . '/css/registry.css'
    ],
    'page_script' => [
        '/' . $site . '/js/registry.js'
    ],
];

$display_config = [
    'content_file' => __DIR__ . '/pages/registry.php',
    'title' => 'PHP Function Registry'
];

// Render page using layout system
require_once __DIR__ . '/../includes/layout.php';
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);
?>

