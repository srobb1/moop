<?php
/**
 * JBrowse2 - Integrated Genome Browser
 * 
 * Main entry point for JBrowse2 with MOOP authentication and layout
 * 
 * - IP-based users get auto-login with ALL access
 * - Anonymous users see only Public assemblies
 * - Logged-in users see their permitted assemblies
 * - Full MOOP navbar, header, and footer integration
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';
include_once __DIR__ . '/lib/moop_functions.php';

// Get configuration
$config = ConfigManager::getInstance();

// User authentication info for JavaScript
$user_info = [
    'logged_in' => is_logged_in(),
    'username' => get_username(),
    'access_level' => get_access_level(),
    'is_admin' => ($_SESSION['is_admin'] ?? false),
];

// Render page using MOOP layout system
echo render_display_page(
    __DIR__ . '/tools/pages/jbrowse2.php',
    [
        'user_info' => json_encode($user_info),
        'page_script' => '/moop/js/jbrowse2-loader.js',
        'page_styles' => ['/moop/css/jbrowse2.css'],
        'inline_scripts' => [
            "const moopUserInfo = " . json_encode($user_info) . ";",
            "console.log('JBrowse2 loaded for user:', moopUserInfo.username || 'anonymous');"
        ]
    ],
    'JBrowse2 - Genome Browser'
);
?>
