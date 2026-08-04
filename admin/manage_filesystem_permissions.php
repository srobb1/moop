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
// The dashboard counts these (moop_permission_findings uses exec_file_issues), so this
// page has to render them or the two disagree -- which they did: the dashboard said
// "1 permission area to address — Executable Data Files (1)" and linked here, where the
// count merged everything EXCEPT exec_file_issues and therefore read 0.
$exec_file_issues       = $collected['exec_file_issues'] ?? [];
$organism_data          = $collected['organism_data'];
$web_user               = $collected['web_user'];
$web_group              = $collected['web_group'];
$moop_owner             = $collected['moop_owner'];

// Publish this scan to the dashboard's cached summary.
//
// The dashboard permission card is a snapshot taken once per housekeeping interval, so
// after fixing something it kept reporting the problem for up to an hour — and the page
// you naturally land on to confirm the fix is THIS one, which already re-checks live.
// Nothing was feeding that fresh answer back, so the two views disagreed by design.
//
// Free: moop_permission_issue_summary() takes the $collected result above rather than
// re-walking the tree, so this is arithmetic over data already in memory — no second scan.
// Writing to both $_SESSION and the status file matches what housekeeping_permission_check()
// does, so the card is correct on this request and after the session is rehydrated.
$_SESSION['perm_summary'] = moop_permission_issue_summary($config, $collected);
housekeeping_persist_status('perm_summary', $_SESSION['perm_summary']);

// Prepare data for display
$site = $config->getString('site');
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $site,
    'checks' => $checks,
    'assembly_subdir_issues' => $assembly_subdir_issues,
    'fasta_file_issues' => $fasta_file_issues,
    'exec_file_issues' => $exec_file_issues,
    'organism_data' => $organism_data,
    'moop_owner' => $moop_owner,
    'web_user' => $web_user,
    'web_group' => $web_group,
    'config' => $config,
    'page_styles' => ['/' . $site . '/css/manage-filesystem-permissions.css'],
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
