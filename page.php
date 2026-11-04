<?php
/**
 * A content page: /<site>/page.php?p=<slug>. Written on Admin -> Manage Pages, stored as site
 * data (lib/content_pages.php). An unknown or malformed address answers the site's 404 page.
 */
include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/lib/content_pages.php';
require_once __DIR__ . '/lib/functions_display.php';

$config = ConfigManager::getInstance();
$page   = moop_page_get((string) ($_GET['p'] ?? ''));
if ($page === null) {
    moop_not_found_page('There is no page at this address.');
}

// The visitor's language (moop_current_lang()); a page not yet written in it is shown in English,
// title and text separately, so nothing is ever blank.
$lang  = moop_current_lang();
$title = trim($page['title'][$lang] ?? '') !== '' ? $page['title'][$lang] : $page['title']['en'];
$body  = trim($page['body'][$lang] ?? '') !== '' ? $page['body'][$lang] : $page['body']['en'];

echo render_display_page(
    __DIR__ . '/tools/pages/content_page.php',
    [
        'page_title' => $title,
        'page_html'  => moop_page_render($body, $page['format']),
        // English shown on a Spanish page says so, for screen readers and translators.
        'title_lang' => $title === $page['title']['en'] && $lang !== 'en' && trim($page['title'][$lang] ?? '') === '' ? 'en' : '',
        'body_lang'  => $body === $page['body']['en'] && $lang !== 'en' && trim($page['body'][$lang] ?? '') === '' ? 'en' : '',
    ],
    $title . ' - ' . $config->getString('siteTitle')
);
