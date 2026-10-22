<?php
/**
 * MOOP Help - Wrapper
 *
 * Handles help page rendering using clean architecture layout system.
 * Supports viewing help dashboard or specific tutorial pages.
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';
include_once __DIR__ . '/lib/moop_functions.php';   // access + data helpers the topic pages read

// Get config
$config = ConfigManager::getInstance();

// Determine which help page to display
$topic = $_GET['topic'] ?? null;

// The landing page splits topics by audience (see tools/pages/help/landing.php).
// The old flat dashboard.php grid is no longer linked from anywhere.
$landing = __DIR__ . '/tools/pages/help/landing.php';

$content_file = $landing;
$page_title   = 'Help & Documentation';
if ($topic && preg_match('/^[a-z0-9\-]+$/', $topic)
        && file_exists(__DIR__ . '/tools/pages/help/' . $topic . '.php')) {
    $content_file = __DIR__ . '/tools/pages/help/' . $topic . '.php';
    // Title from the topic registry, so a tab or bookmark says which topic it is
    // (every topic used to be titled 'Tutorial - Help').
    foreach (include __DIR__ . '/tools/pages/help/topics.php' as $t) {
        if ($t['id'] === $topic) {
            $page_title = html_entity_decode($t['title']) . ' - Help';
            break;
        }
    }
}

// Prepare data for content file
$data = [
    'config' => $config,
    'siteTitle' => $config->getString('siteTitle'),
    'page_script' => '/' . $config->getString('site') . '/js/modules/help-filter.js',
];

// Render page using layout system
echo render_display_page(
    $content_file,
    $data,
    $page_title . ' - ' . $config->getString('siteTitle')
);
?>
