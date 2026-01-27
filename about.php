<?php
/**
 * MOOP About Page
 * 
 * Displays information about the MOOP application.
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';

$config = ConfigManager::getInstance();

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/about.php',
    [
        'siteTitle' => $config->getString('siteTitle'),
    ],
    'About - ' . $config->getString('siteTitle')
);
?>
