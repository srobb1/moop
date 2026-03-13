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

// CSRF protection - verify token on every POST request.
// This covers all admin pages and admin API endpoints in one place.
// AJAX requests are covered automatically: csrf.js attaches the token as
// X-CSRF-Token header on every jQuery POST, which csrf_protect() checks first.
// NOTE: manage_site_config.php has early AJAX handlers that run before this
// file is included - those are verified individually in that file.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect(/* json_response: */ isset($_SERVER['HTTP_X_CSRF_TOKEN']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']));
}

?>
