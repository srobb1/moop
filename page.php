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

// English until the language work (cuttingclass step 5) chooses per visitor.
$lang = 'en';

echo render_display_page(
    __DIR__ . '/tools/pages/content_page.php',
    [
        'page_title' => $page['title'][$lang],
        'page_html'  => moop_markdown($page['body'][$lang]),
    ],
    $page['title'][$lang] . ' - ' . $config->getString('siteTitle')
);
