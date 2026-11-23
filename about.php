<?php
/**
 * About page: the site's own story first (an editable content page, slug "about", per site --
 * Admin -> Pages), then "About MOOP", the software every MOOP site shares.
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/lib/content_pages.php';

$config = ConfigManager::getInstance();

// The site's own About page, if it has one and this visitor may see it (a private page reads as absent).
$site_page = moop_page_for_visitor('about');
$site_about = null;
if ($site_page !== null) {
    $lang = moop_current_lang();   // the visitor's language; English where the page has none
    $pick = fn(string $part) => trim($site_page[$part][$lang] ?? '') !== '' ? $site_page[$part][$lang] : $site_page[$part]['en'];
    $site_about = ['title' => $pick('title'), 'html' => moop_page_render($pick('body'), $site_page['format'])];
}

echo render_display_page(
    __DIR__ . '/tools/pages/about.php',
    [
        'siteTitle'  => $config->getString('siteTitle'),
        'site_about' => $site_about,
    ],
    'About - ' . $config->getString('siteTitle')
);
