<?php
/**
 * JBrowse2 Auth Gateway
 *
 * Reached via web server rewrite from jbrowse2/index.html (see README for config).
 * Checks MOOP session before serving JBrowse2. For non-public assemblies, unauthenticated
 * users are redirected to login and returned to their original JBrowse2 URL afterward.
 */

session_start();
require_once __DIR__ . '/includes/config_init.php';
require_once __DIR__ . '/includes/access_control.php';

if (!is_logged_in()) {
    // Extract organism/assembly from the config= param to check if the assembly is public
    $config_url = urldecode($_GET['config'] ?? '');
    $query_string = parse_url($config_url, PHP_URL_QUERY) ?? '';
    parse_str($query_string, $config_params);
    $organism = $config_params['organism'] ?? null;
    $assembly = $config_params['assembly'] ?? null;

    $is_public = $organism && $assembly && is_public_assembly($organism, $assembly);

    if (!$is_public) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $return_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: /moop/login.php?return=' . urlencode($return_url));
        exit;
    }
}

header('Content-Type: text/html; charset=UTF-8');
readfile(__DIR__ . '/jbrowse2/index.html');
