<?php
/**
 * Admin Pages Initialization
 * 
 * This file handles ALL setup needed for admin pages:
 * - Session management
 * - Access control and role verification
 * - Configuration loading
 * - Common includes (head, navbar, functions)
 * - Header image setup
 * 
 * USAGE - At the very top of any admin page, add ONE line:
 *   <?php include_once __DIR__ . '/admin_init.php'; ?>
 * 
 * That's it! Everything else is handled automatically.
 * 
 * OPTIONAL - Load additional config values after including:
 *   <?php include_once __DIR__ . '/admin_init.php'; 
 *   // Now $config is available with all values
 *   $metadata_path = $config->getPath('metadata_path');
 *   $organism_data = $config->getPath('organism_data');
 *   ?>
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration system
include_once __DIR__ . '/../includes/config_init.php';

// Load access control and verify admin role
include_once __DIR__ . '/admin_access_check.php';

// Get config instance - available for use in admin pages
$config = ConfigManager::getInstance();

// Load header image config (needed by navbar)
$header_img = $config->getString('header_img');
$images_path = $config->getString('images_path');
$site = $config->getString('site');

// Load common includes
include_once __DIR__ . '/../lib/moop_functions.php';
include_once __DIR__ . '/../lib/functions_display.php';
include_once __DIR__ . '/../lib/functions_filesystem.php';
include_once __DIR__ . '/../lib/housekeeping.php';

// Admin pages do more work than public pages (once-per-session housekeeping snapshot,
// drift scans, cold-DB reads right after a rebuild). Give them more headroom than the
// global 30s max_execution_time so a slow cold first-load isn't killed mid-render.
// NOTE: for the full effect the server's nginx `fastcgi_read_timeout` should also be
// raised for the /admin/ location — max_execution_time only bounds CPU time, not the
// I/O wait of cold disk reads, which is what the gateway timeout measures.
@set_time_limit(180);

// CSRF protection - verify token on every POST request.
// This covers all admin pages and admin API endpoints in one place.
// AJAX requests are covered automatically: csrf.js attaches the token as
// X-CSRF-Token header on every jQuery POST, which csrf_protect() checks first.
// NOTE: manage_site_config.php has early AJAX handlers that run before this
// file is included - those are verified individually in that file.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect(/* json_response: */ isset($_SERVER['HTTP_X_CSRF_TOKEN']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']));
}

// Kick off maintenance tasks. Genuinely non-blocking: this hydrates the session from the
// last run's cached status, then — at most once per HOUSEKEEPING_MIN_INTERVAL — spawns a
// DETACHED background process and returns in ~1ms. It does not run the tasks here.
//
// (Until 2026-07-16 this comment claimed "once per session, non-blocking" while the tasks
// ran INLINE, so every ~4h one admin silently paid ~4.5s on a page load. Both halves of
// that comment were false, which is probably why the cost went unnoticed for so long.)
run_housekeeping();

?>
