<?php
/**
 * JBrowse2 Viewer - Fullscreen Genome Browser
 * 
 * Displays JBrowse2 in fullscreen mode with dynamic config injection
 * Loads JBrowse2 index.html and injects user authentication + assembly config
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/lib/moop_functions.php';
include_once __DIR__ . '/includes/config_init.php';

// Get configuration
$config = ConfigManager::getInstance();
$site = $config->getString('site');
$jbrowse_config = $config->getArray('jbrowse2');

// Get assembly parameter
$assembly_name = $_GET['assembly'] ?? null;

// User authentication info for JavaScript
$user_info = [
    'logged_in' => is_logged_in(),
    'username' => get_username(),
    'access_level' => get_access_level(),
    'is_admin' => ($_SESSION['is_admin'] ?? false),
];

// Load JBrowse2 index.html as base
$jbrowse_index = file_get_contents(__DIR__ . '/jbrowse2/index.html');

// Inject base tag and user info script before </head> tag
$head_injection = sprintf(
    '<base href="/%s/jbrowse2/" /><script>window.moopUserInfo = %s; window.moopAssemblyName = %s; window.moopSite = %s;</script>',
    $site,
    json_encode($user_info),
    json_encode($assembly_name ?? ''),
    json_encode($site)
);

$jbrowse_index = str_replace('</head>', $head_injection . '</head>', $jbrowse_index);

// Inject loader script before closing body tag
$jbrowse_index = str_replace(
    '</body>',
    '<script src="/' . $site . '/js/jbrowse2-view-loader.js"></script></body>',
    $jbrowse_index
);

// Output with proper headers
header('Content-Type: text/html; charset=utf-8');
echo $jbrowse_index;
