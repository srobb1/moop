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

// Optional feature location deep-link (chr:start-end or feature name)
$loc = '';
if (!empty($_GET['loc']) && preg_match('/^[\w.\-:]+$/', $_GET['loc'])) {
    $loc = $_GET['loc'];
}

// Primary gene track IDs for this assembly (used for deep-link track pre-selection)
$primary_gene_tracks = [];
$dl_organism = $_GET['organism'] ?? '';
$dl_assembly = $_GET['assembly'] ?? '';
if (!empty($dl_organism) && !empty($dl_assembly)
    && preg_match('/^[A-Za-z0-9_\-\.]+$/', $dl_organism)
    && preg_match('/^[A-Za-z0-9_\-\.]+$/', $dl_assembly)) {
    $assembly_json_path = $config->getPath('metadata_path')
        . '/jbrowse2-configs/assemblies/'
        . $dl_organism . '_' . $dl_assembly . '.json';
    if (file_exists($assembly_json_path)) {
        $assembly_def = json_decode(file_get_contents($assembly_json_path), true);
        $primary_gene_tracks = $assembly_def['primaryGeneTracks'] ?? [];
    }
}

// Render page using MOOP layout system
echo render_display_page(
    __DIR__ . '/tools/pages/jbrowse2.php',
    [
        'user_info' => json_encode($user_info),
        'page_script' => '/moop/js/jbrowse2-loader.js',
        'page_styles' => ['/moop/css/jbrowse2.css'],
        'inline_scripts' => [
            "window.moopUserInfo = " . json_encode($user_info) . ";",
            "window.moopLoc = " . json_encode($loc) . ";",
            "window.moopGeneTracks = " . json_encode(array_values($primary_gene_tracks)) . ";",
            "console.log('JBrowse2 loaded for user:', window.moopUserInfo.username || 'anonymous');"
        ]
    ],
    'JBrowse2 - Genome Browser'
);
?>
