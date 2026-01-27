<?php
/**
 * MOOP Help - Wrapper
 * 
 * Handles help page rendering using clean architecture layout system.
 * Supports viewing help dashboard or specific tutorial pages.
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';

// Get config
$config = ConfigManager::getInstance();

// Determine which help page to display
$topic = $_GET['topic'] ?? null;

if ($topic && preg_match('/^[a-z0-9\-]+$/', $topic)) {
    $content_file = __DIR__ . '/tools/pages/help/' . $topic . '.php';
    $page_title = 'Tutorial - Help';
} else {
    $content_file = __DIR__ . '/tools/pages/help/dashboard.php';
    $page_title = 'Help - Tutorials';
}

// Check if content file exists
if (!file_exists($content_file)) {
    $content_file = __DIR__ . '/tools/pages/help/dashboard.php';
    $page_title = 'Help - Tutorials';
}

// Prepare data for content file
$data = [
    'config' => $config,
    'siteTitle' => $config->getString('siteTitle'),
];

// Render page using layout system
echo render_display_page(
    $content_file,
    $data,
    $page_title . ' - ' . $config->getString('siteTitle')
);
?>
