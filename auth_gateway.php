<?php
/**
 * JBrowse2 Auth Gateway
 *
 * Reached via web server rewrite from jbrowse2/index.html (see README for config).
 * Checks MOOP session before serving JBrowse2. For non-public assemblies, unauthenticated
 * users are redirected to login and returned to their original JBrowse2 URL afterward.
 */

require_once __DIR__ . '/includes/session_init.php';
moop_session_start();
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
        // The path alone: login.php returns to a same-site path (no Host header involved), and the
        // login link follows link_prefix (CLAUDE.md 3b) -- it was a hardcoded /moop/ until 2026-11-17.
        $return_url = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . moop_url('login.php') . '?return=' . urlencode($return_url));
        exit;
    }
}

header('Content-Type: text/html; charset=UTF-8');
readfile(__DIR__ . '/jbrowse2/index.html');
