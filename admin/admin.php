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

// Check if site-data backup repo is configured and ready
include_once __DIR__ . '/../lib/functions_system.php';
$site_data_path = $config->getPath('site_data_path');
$site_data_status = 'disabled';
if (!empty($site_data_path)) {
    if (!is_dir($site_data_path)) {
        $site_data_status = 'missing_dir';
    } elseif (!is_dir($site_data_path . '/.git')) {
        $site_data_status = 'not_git';
    } else {
        $site_data_status = 'ok';
    }
}

// Check if the site-data repo has a remote configured
$site_data_has_remote = false;
if ($site_data_status === 'ok') {
    $remote_output = [];
    @exec("cd " . escapeshellarg($site_data_path) . " && git remote 2>/dev/null", $remote_output);
    $site_data_has_remote = !empty(array_filter($remote_output));
}

$web_server = getWebServerUser();

// Prepare data for content file
$data = [
    'config' => $config,
    'site' => $site,
    'site_data_status' => $site_data_status,
    'site_data_path' => $site_data_path,
    'site_data_has_remote' => $site_data_has_remote,
    'web_user' => $web_server['user'],
    'web_group' => $web_server['group'],
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
