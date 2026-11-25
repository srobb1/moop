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
    'title' => 'Admin Tools',
    'content_file' => __DIR__ . '/pages/admin.php',
];

// Read organism cache metadata for dashboard status widget (no scanning)
$organism_data = $config->getPath('organism_data');
$cache_file    = moop_organism_cache_file();
$lock_file     = moop_organism_cache_lock_file();
$cache_info    = ['generated' => null, 'organism_count' => 0, 'refreshing' => false];
$_raw_cache_data = [];
if (file_exists($cache_file)) {
    $raw = loadJsonFile($cache_file, []);
    if ($raw) {
        $cache_info['generated']      = $raw['generated'] ?? null;
        $cache_info['organism_count'] = count($raw['data'] ?? []);
        $_raw_cache_data = $raw['data'] ?? [];
    }
}
// Content-based staleness ("your data actually changed", not "the cache is old"): the same
// test Manage Organisms, the setup checklist and the Quick Links counts apply. This page had
// its own inline copy until 2026-10-22, with slightly different rules.
['cache_stale' => $cache_stale, 'cache_changed_orgs' => $cache_changed_orgs, 'cache_stale_reasons' => $cache_stale_reasons] =
    moop_organism_cache_stale_summary($raw ?? []);
// Same liveness test the status endpoint and housekeeping use — see
// moop_organism_cache_refresh_active(). This was `file_exists($lock_file) && mtime < 600`,
// which answered a different question (was a lock created recently) and so disagreed with
// the endpoint whenever a worker died without dropping its lock: the banner said
// "Refresh in progress…" while the poller said idle and pinned the bar at 100%.
$cache_info['refreshing'] = moop_organism_cache_refresh_active();
// Data health alerts — computed by a shared helper so the dashboard and the manage
// organisms page always show the SAME issues (single source of truth).
$_health = computeDataHealthAlerts($organism_data);   // passed to the page whole, below
unset($_raw_cache_data);

// Prepare data for content file
// Site-data backup status comes from housekeeping (stored in session)
$data = [
    'config' => $config,
    'site' => $site,
    'site_data_backup' => $_SESSION['site_data_backup'] ?? null,
    // Precomputed by housekeeping_check_jbrowse_version() and hydrated from
    // logs/.housekeeping_status.json — never computed on dashboard load, because it can
    // involve a network call. Null when JBrowse is not installed.
    'jbrowse_version' => $_SESSION['jbrowse_version'] ?? null,
    'cache_info' => $cache_info,
    'cache_stale' => $cache_stale,
    'cache_changed_orgs' => $cache_changed_orgs,
    'cache_stale_reasons' => $cache_stale_reasons,
    // Per CLAUDE.md §6: JS logic belongs in js/modules/, not inline. (The organism-cache
    // widget below predates that rule.)
    'page_script' => [
        moop_url_base() . '/js/modules/housekeeping-rerun.js',
        moop_url_base() . '/js/modules/remember-collapse.js',
        moop_url_base() . '/js/admin-dashboard.js',   // organism cache card
    ],
    'inline_scripts' => [
        "const sitePath = '" . moop_url_base() . "';",
        "const dashCacheRefreshing = " . ($cache_info['refreshing'] ? 'true' : 'false') . ";",   // js/admin-dashboard.js polls when true
    ],
] + $_health;   // the health card's data, whole -- moop_data_health_empty() gives its shape

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
