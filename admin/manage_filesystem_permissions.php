<?php
/**
 * MANAGE FILESYSTEM PERMISSIONS - Wrapper
 *
 * Handles admin access verification and renders filesystem permissions
 * management using clean architecture layout system.
 *
 * The actual permission rules and the organism-tree walk live in
 * lib/permission_check.php (moop_collect_permission_checks) so this detail page
 * and the dashboard pointer card (via lib/housekeeping.php) can't drift — the
 * same reason computeDataHealthAlerts() is shared.
 */

ob_start();
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/permission_check.php';

// Run all permission checks (shared collector — same numbers the dashboard shows).
$collected              = moop_collect_permission_checks($config);
$checks                 = $collected['checks'];
$assembly_subdir_issues = $collected['assembly_subdir_issues'];
$fasta_file_issues      = $collected['fasta_file_issues'];
$organism_data          = $collected['organism_data'];
$web_user               = $collected['web_user'];
$web_group              = $collected['web_group'];
$moop_owner             = $collected['moop_owner'];

// Prepare data for display
$site = $config->getString('site');
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $site,
    'checks' => $checks,
    'assembly_subdir_issues' => $assembly_subdir_issues,
    'fasta_file_issues' => $fasta_file_issues,
    'organism_data' => $organism_data,
    'moop_owner' => $moop_owner,
    'web_user' => $web_user,
    'web_group' => $web_group,
    'config' => $config,
    'page_styles' => ['/moop/css/manage-filesystem-permissions.css'],
    'page_script' => [
        '/' . $site . '/js/admin-utilities.js',
    ],
];

$display_config = [
    'content_file' => __DIR__ . '/pages/manage_filesystem_permissions.php',
    'title' => 'Filesystem Permissions'
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
