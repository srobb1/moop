<?php
/**
 * ALL NEWS -- every "What's new" item this visitor may see, newest first (2026-11-23).
 * Storage and visibility rules: lib/home_panel.php. Linked from the home page's What's new box.
 */
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/home_panel.php';

$siteTitle = $config->getString('siteTitle');
echo render_display_page(__DIR__ . '/pages/news.php', [
    'news'      => moop_news_for_visitor(moop_news_all() ?? [], moop_news_visible_groups()),
    'siteTitle' => $siteTitle,
], t_plain('News') . ' - ' . $siteTitle);
