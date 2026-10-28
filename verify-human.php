<?php
/**
 * Human check (Cloudflare Turnstile) — the page access_control.php sends anonymous visitors
 * to, once per browser session, when Turnstile is enabled. Logic: lib/human_check.php.
 * Plan: notes/BOT_PROTECTION_PLAN.md.
 *
 * NOT dead code even when Turnstile is off: access_control.php redirects here whenever it is
 * on. The previous version was deleted as dead (ac0ce979) while that redirect stayed live.
 */

require_once __DIR__ . '/includes/session_init.php';
moop_session_start();

include_once __DIR__ . '/includes/config_init.php';
include_once __DIR__ . '/includes/access_control.php';   // skips the check for this script
include_once __DIR__ . '/includes/layout.php';

$config    = ConfigManager::getInstance();
$site      = $config->getString('site', 'moop');
$siteTitle = $config->getString('siteTitle', 'MOOP');
$return    = moop_human_check_safe_return((string) ($_POST['return'] ?? $_GET['return'] ?? ''), $site);
$ts        = moop_human_check_config();

// Nothing to do: Turnstile off, already passed, or logged in.
if ($ts === null || moop_human_verified() || is_logged_in()) {
    header('Location: ' . $return);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $token = (string) ($_POST['cf-turnstile-response'] ?? '');
    if ($token === '') {
        $error = 'The check did not finish. Please try again.';
    } else {
        $verdict = moop_human_check_siteverify($ts['secret_key'], $token, $_SERVER['REMOTE_ADDR'] ?? '');
        if ($verdict !== 'fail') {
            // 'pass' — or 'unreachable': Cloudflare could not be asked, so let people in
            // briefly rather than lock everyone out (decided 2026-10-28; see human_check.php).
            $_SESSION['human_verified_until'] = time()
                + ($verdict === 'pass' ? HUMAN_CHECK_PASS_TTL : HUMAN_CHECK_FAILOPEN_TTL);
            header('Location: ' . $return);
            exit;
        }
        $error = 'The check could not confirm this browser. Please try again.';
    }
}

echo render_display_page(
    __DIR__ . '/tools/pages/verify_human.php',
    [
        'siteTitle'   => $siteTitle,
        'site_key'    => $ts['site_key'],
        'return'      => $return,
        'error'       => $error,
        'admin_email' => $config->getString('admin_email', ''),
        'page_script' => ['/' . $site . '/js/verify-human.js'],
    ],
    'One moment - ' . $siteTitle
);
