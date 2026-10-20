<?php
/**
 * NOT FOUND - the page the web server shows for a path it refuses outright.
 *
 * docs/nginx/moop-security.conf sends data/genomes/, organisms/, config/, logs/, metadata/ and
 * .php in served data trees here (`error_page 404 /moop/tools/not_found.php`), so a refused
 * link gets the site's own header and menus and one way home, instead of nginx's stock Red Hat
 * page. nginx keeps the 404 status; this sets it too, for a direct visit.
 *
 * Says nothing about what was asked for: the same page whether the path exists or not.
 */
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';

http_response_code(404);
echo render_display_page(__DIR__ . '/pages/not_found.php', [
    'site' => ConfigManager::getInstance()->getString('site', 'moop'),
], 'Page not found');
