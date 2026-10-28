<?php
/**
 * Cloudflare Turnstile — "is this a person?" once per browser session, for anonymous visitors.
 *
 * Why: AI-training crawlers request every URL they can find, from thousands of IPs, and ignore
 * robots.txt. Each gene page is several SQLite queries; each export or download is far more.
 * A challenge a real browser passes invisibly — and a mass crawler cannot afford — stops them
 * before any data code runs. Plan and layers: notes/BOT_PROTECTION_PLAN.md.
 *
 * Who is checked: anonymous visitors only. Logged-in users never are, and internal/VPN
 * addresses are auto-logged-in by access_control.php (auto_login_ip_ranges), so they never
 * are either.
 *
 * If Cloudflare cannot be reached (server side), the visitor is let in for
 * HUMAN_CHECK_FAILOPEN_TTL and asked again after (Sofia, 2026-10-28: keep the site up for real
 * users; the nginx rate limits are the backstop for that window). A visitor can never claim
 * an outage — only this server's own failed call to Cloudflare counts as one.
 *
 * History: the first version (cd1b8161, June 2026) called /turnstile/v1/, which does not exist
 * (only v0 does), so it could never have verified anyone; and its page was then deleted as dead
 * code (ac0ce979) while the redirect to it stayed live — enabling Turnstile would have sent
 * every anonymous visitor to a 404.
 */

const HUMAN_CHECK_PASS_TTL     = 12 * 3600;  // a pass lasts the working day
const HUMAN_CHECK_FAILOPEN_TTL = 10 * 60;    // a Cloudflare outage lets people in, briefly
const HUMAN_CHECK_SITEVERIFY   = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const HUMAN_CHECK_SCRIPT       = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

/** Turnstile settings, or null when it is off or not fully configured. */
function moop_human_check_config(): ?array {
    $ts = ConfigManager::getInstance()->getArray('turnstile', []);
    if (empty($ts['enabled']) || empty($ts['site_key']) || empty($ts['secret_key'])) {
        return null;
    }
    return $ts;
}

function moop_human_verified(): bool {
    return (int) ($_SESSION['human_verified_until'] ?? 0) > time();
}

/**
 * Does this request need the check, and if so how should it be turned away?
 * Returns null (let it through), 'redirect' (a page load: send to the challenge) or 'deny'
 * (anything a script made — AJAX, api/, a POST: a JSON 403 it can read, not a 302 to an HTML
 * page it cannot follow).
 *
 * Pure: everything it needs is passed in, so the smoke tests can call it.
 */
function moop_human_check_decision(bool $enabled, bool $logged_in, bool $verified, string $script,
                                   string $method, string $uri, array $headers): ?string {
    if (!$enabled || $logged_in || $verified) return null;
    // The challenge page itself, and the way in for people who have an account (login.php has
    // its own brute-force protection).
    if (in_array($script, ['verify-human.php', 'login.php', 'logout.php'], true)) return null;

    $path   = parse_url($uri, PHP_URL_PATH) ?? '';
    $xhr    = strcasecmp($headers['X-Requested-With'] ?? '', 'XMLHttpRequest') === 0;
    $accept = $headers['Accept'] ?? '';
    $is_api = (bool) preg_match('#/(api|admin/api)/#', $path);
    $page   = $method === 'GET' && !$xhr && !$is_api && ($accept === '' || stripos($accept, 'text/html') !== false);
    return $page ? 'redirect' : 'deny';
}

/**
 * A return address that can only lead back into this site: a path starting with one "/", no
 * scheme, no host, no backslash (browsers read "/\evil.com" as "//evil.com"), no control
 * characters. Anything else becomes the site home.
 */
function moop_human_check_safe_return(string $raw, string $site): string {
    $home = '/' . trim($site, '/') . '/';
    if ($raw === '' || $raw[0] !== '/' || strlen($raw) > 2000) return $home;
    if (isset($raw[1]) && ($raw[1] === '/' || $raw[1] === '\\')) return $home;
    if (strpos($raw, '\\') !== false || preg_match('/[\x00-\x1f\x7f]/', $raw)) return $home;
    $p = parse_url($raw);
    if ($p === false || isset($p['scheme']) || isset($p['host'])) return $home;
    return $raw;
}

/** Run by access_control.php on every request. */
function moop_human_check_enforce(): void {
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) return;
    $ts = moop_human_check_config();
    $decision = moop_human_check_decision(
        $ts !== null,
        is_logged_in() || moop_viewing_as_public(),  // the PUBLIC preview is exempt, see access_control.php
        moop_human_verified(),
        basename($_SERVER['SCRIPT_FILENAME'] ?? ''),
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/',
        [
            'X-Requested-With' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '',
            'Accept'           => $_SERVER['HTTP_ACCEPT'] ?? '',
        ]
    );
    if ($decision === null) return;

    $site   = ConfigManager::getInstance()->getString('site', 'moop');
    $verify = '/' . $site . '/verify-human.php';
    if ($decision === 'redirect') {
        header('Location: ' . $verify . '?return=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'), true, 302);
        exit;
    }
    http_response_code(403);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode([
        'error'      => 'verification_required',
        'message'    => 'Please reload the page in your browser to continue.',
        'verify_url' => $verify,
    ]);
    exit;
}

/**
 * Ask Cloudflare whether a widget token is genuine.
 * Returns 'pass', 'fail' (Cloudflare answered no), or 'unreachable' (no real answer — network
 * error, non-200, unparseable). Only 'unreachable' may fail open.
 */
function moop_human_check_siteverify(string $secret, string $token, string $remote_ip): string {
    $ch = curl_init(HUMAN_CHECK_SITEVERIFY);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $remote_ip]),
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => function_exists('moop_user_agent') ? moop_user_agent() : 'MOOP/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_errno($ch);
    curl_close($ch);

    $result = ($body !== false && !$err && $code === 200) ? json_decode($body, true) : null;
    if (!is_array($result) || !array_key_exists('success', $result)) {
        error_log("human_check: siteverify unreachable (curl $err, http $code)");
        return 'unreachable';
    }
    return $result['success'] === true ? 'pass' : 'fail';
}
