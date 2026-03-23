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

// Prepare data for content file
// Site-data backup status comes from housekeeping (stored in session)
$data = [
    'config' => $config,
    'site' => $site,
    'site_data_backup' => $_SESSION['site_data_backup'] ?? null,
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
