<?php
/**
 * ACCESS DENIED PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (access_denied.php)
 *   ↓
 * Load configuration
 *   ↓
 * Configure layout (title, content file)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (tools/pages/access_denied.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Load configuration
 * - Configure page display
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (tools/pages/access_denied.php does that)
 */

session_start();
include_once __DIR__ . '/includes/config_init.php';
include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';

$config = ConfigManager::getInstance();

// Prepare data for content file
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'adminEmail' => $config->getString('admin_email'),
    'site' => $config->getString('site'),
];

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/access_denied.php',
    $data,
    'Access Denied - ' . $config->getString('siteTitle')
);
?>
