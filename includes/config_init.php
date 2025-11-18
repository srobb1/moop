<?php
/**
 * CONFIGURATION INITIALIZATION
 * 
 * Single initialization point for all configuration.
 * Include this ONE TIME per page load, usually early in index.php or admin.php
 * 
 * Usage:
 *   include_once __DIR__ . '/config_init.php';
 *   
 *   // Then anywhere in your code:
 *   $config = ConfigManager::getInstance();
 *   $site_path = $config->getPath('site_path');
 *   $admin_email = $config->getString('admin_email');
 *   $tools = $config->getAllTools();
 *
 * SECURITY NOTE:
 * This file only loads configuration data. It does NOT:
 *   - Touch $_SESSION (user access control is separate)
 *   - Perform authentication (that's in access_control.php)
 *   - Validate user permissions (that's in helper functions)
 * Access control remains in access_control.php and is unaffected.
 */

// Load the ConfigManager class
require_once __DIR__ . '/ConfigManager.php';

// Initialize ConfigManager with config files
ConfigManager::getInstance()->initialize(
    __DIR__ . '/../config/site_config.php',
    __DIR__ . '/../config/tools_config.php'
);

// Validate configuration on boot (can be disabled in production with env var)
if (getenv('VALIDATE_CONFIG') !== 'false') {
    $config = ConfigManager::getInstance();
    if (!$config->validate()) {
        $errors = $config->getMissingKeys();
        error_log('Configuration validation errors: ' . json_encode($errors));
        // Continue anyway to avoid breaking the app, but log the issue
    }
}

?>
