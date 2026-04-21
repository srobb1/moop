<?php
/**
 * ADMIN DASHBOARD - Wrapper
 * 
 * Handles admin access verification and renders admin dashboard
 * using clean architecture layout system.
 */

// Load admin initialization (handles auth, config, includes)
include_once __DIR__ . '/admin_init.php';

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Get config
$site = $config->getString('site');

// Configure display
$display_config = [
    'title' => 'Admin Tools - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/admin.php',
];

// Read organism cache metadata for dashboard status widget (no scanning)
$organism_data = $config->getPath('organism_data');
$cache_file    = "$organism_data/.organism_cache.json";
$lock_file     = "$organism_data/.organism_cache_lock";
$cache_info    = ['generated' => null, 'organism_count' => 0, 'refreshing' => false];
if (file_exists($cache_file)) {
    $raw = json_decode(file_get_contents($cache_file), true);
    if ($raw) {
        $cache_info['generated']      = $raw['generated'] ?? null;
        $cache_info['organism_count'] = count($raw['data'] ?? []);
    }
}
if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 600) {
    $cache_info['refreshing'] = true;
}

// Prepare data for content file
// Site-data backup status comes from housekeeping (stored in session)
$data = [
    'config' => $config,
    'site' => $site,
    'site_data_backup' => $_SESSION['site_data_backup'] ?? null,
    'cache_info' => $cache_info,
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
