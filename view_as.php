<?php
/**
 * Enter / leave "View as PUBLIC" preview mode.
 *
 * Lets an administrator see exactly what an unauthenticated visitor sees — the only way to
 * do that on a deployment reached from trusted IP ranges, where auto-login re-authenticates
 * you on every request and logout.php cannot keep you logged out. See the block comment on
 * moop_viewing_as_public() in includes/access_control.php for the full reasoning.
 *
 * This endpoint deliberately does NOT use admin_init.php / admin_access_check.php: those are
 * built on the accessors that the preview makes lie, so while previewing they would (quite
 * correctly) refuse the request and the admin could never get back out. Authorisation here
 * reads the REAL session identity instead, with the same bar admin_access_check.php applies
 * — access level AND users-file role must both say admin.
 *
 * POST only, CSRF-verified: entering the preview changes what the session can see.
 */

include_once __DIR__ . '/includes/access_control.php';

$config = ConfigManager::getInstance();
$site   = $config->getString('site');

/**
 * Where to send the browser afterwards.
 *
 * Only same-site relative paths are honoured — an attacker-supplied absolute URL here would
 * turn this into an open redirect. Anything else falls back to the home page.
 */
function view_as_return_target($site) {
    $candidate = $_POST['return'] ?? ($_SERVER['HTTP_REFERER'] ?? '');

    if ($candidate !== '') {
        // Strip any scheme/host: keep only the path+query the browser should go to.
        $path = parse_url($candidate, PHP_URL_PATH);
        $query = parse_url($candidate, PHP_URL_QUERY);

        // Must be an absolute path on this site, and must not be "//evil.com" (which a
        // browser reads as a protocol-relative URL to another host).
        if (is_string($path) && $path !== '' && $path[0] === '/' && strpos($path, '//') !== 0) {
            return $path . ($query ? '?' . $query : '');
        }
    }

    return '/' . $site . '/index.php';
}

$return_to = view_as_return_target($site);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $return_to, true, 303);
    exit;
}

csrf_protect();

// Authorise against the REAL identity, never the previewed one.
if (!moop_real_is_admin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Administrator access is required to use the public-view preview.";
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'enter') {
    $_SESSION['view_as_public'] = true;
    error_log('MOOP: "view as PUBLIC" preview ENTERED by ' . moop_real_username());
} elseif ($action === 'leave') {
    unset($_SESSION['view_as_public']);
    error_log('MOOP: "view as PUBLIC" preview LEFT by ' . moop_real_username());
}

// 303 so a refresh of the destination does not repost this form.
header('Location: ' . $return_to, true, 303);
exit;
