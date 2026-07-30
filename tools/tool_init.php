<?php
/**
 * Tools Pages Initialization
 * 
 * This file handles common setup for tool pages:
 * - Session management
 * - Access control and configuration
 * - Common includes (navigation, functions)
 * - Header image setup
 * 
 * USAGE - At the very top of any tool page, add ONE line:
 *   <?php include_once __DIR__ . '/../tools/tool_init.php'; ?>
 * 
 * That's it! Then load any page-specific config you need:
 *   <?php include_once __DIR__ . '/../tools/tool_init.php';
 *   // Now $config is available
 *   $organism_data = $config->getPath('organism_data');
 *   $metadata_path = $config->getPath('metadata_path');
 *   ?>
 */

// FIRST, before anything can fail: capture PHP fatals to logs/error.log. nginx maps
// 500/502/503/504 to one generic page and the real message goes to a root-only log, so
// without this a crash is invisible to anyone who can read this deployment. Must precede
// every other require, since a fatal inside one of them is exactly what needs recording.
require_once __DIR__ . '/../includes/fatal_log.php';

// Session cookie attributes are set in ONE place, and only BEFORE the session starts.
// This used to call session_start() directly, several lines ahead of access_control.php
// -- so by the time moop_session_start() ran it found a live session and returned early,
// and every public tool page got PHP's default cookie: no HttpOnly, no SameSite, no
// Secure, and (after the per-scheme split) the wrong cookie name entirely.
require_once __DIR__ . '/../includes/session_init.php';
moop_session_start();

// Load access control and configuration
include_once __DIR__ . '/../includes/access_control.php';
include_once __DIR__ . '/../lib/moop_functions.php';

// Get config instance - available for use in tool pages
$config = ConfigManager::getInstance();

// Load header image config (needed by navbar)
$header_img = $config->getString('header_img');
$images_path = $config->getString('images_path');
$site = $config->getString('site');

?>
