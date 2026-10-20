<?php
/**
 * NOT FOUND - the page the web server shows for a path it refuses outright.
 *
 * docs/nginx/moop-security.conf sends data/genomes/, organisms/, config/, logs/, metadata/ and
 * .php in served data trees here (`error_page 404 /moop/tools/not_found.php`), so a refused
 * link gets the site's own header and menus and one way home, instead of nginx's stock Red Hat
 * page. nginx keeps the 404 status; moop_not_found_page() sets it too, for a direct visit.
 *
 * Says nothing about what was asked for: the same page whether the path exists or not.
 */
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/functions_display.php';

moop_not_found_page();
