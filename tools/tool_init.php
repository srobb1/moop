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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load access control and configuration
include_once __DIR__ . '/../includes/access_control.php';
include_once __DIR__ . '/../includes/navigation.php';
include_once __DIR__ . '/../lib/moop_functions.php';

// Get config instance - available for use in tool pages
$config = ConfigManager::getInstance();

// Load header image config (needed by navbar)
$header_img = $config->getString('header_img');
$images_path = $config->getString('images_path');
$site = $config->getString('site');

?>
