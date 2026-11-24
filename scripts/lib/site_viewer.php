<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }   // never run from a URL (CLAUDE.md §8b)
/**
 * Reaching a site's pages from a CLI script, as a chosen viewer -- shared by
 * scripts/render_compare.php and scripts/extract_page_purposes.php.
 *
 * A site with a public_url (simrbase.stowers.org) is asked for by that NAME -- nginx picks the
 * site by name, so 127.0.0.1 or the bare host IP no longer reach it -- while the connection still
 * goes to THIS machine (curl --resolve) from <host ip>, inside auto_login_ip_ranges and so free of
 * nginx's rate limits. A site without one is asked for at http(s)://<host ip>/<link prefix>.
 *
 * Viewers:
 *   public   the admin's "view as PUBLIC" preview (view_as.php): exactly what a PUBLIC visitor
 *            gets, and exempt from the human check (Turnstile), which a script cannot pass
 *   network  from <host ip>: IP_IN_RANGE, full data, no admin
 *   admin    logged in with the login file (two lines: username, password; read, never printed)
 *
 * Uses the global $PUBLIC_HOST, set by the caller from site_viewer_target().
 */

/** [link prefix ("/moop", or "" at the domain's root), public_url host or ''] for the checkout at $root.
 *  Read from config_editable.json directly (not config_init.php): runs from any checkout. */
function site_viewer_target(string $root): array {
    $cfg  = json_decode((string) @file_get_contents("$root/config/config_editable.json"), true) ?: [];
    $site = trim((string) ($cfg['link_prefix'] ?? ''));
    $site = $site === '' ? '/' . basename($root) : ($site === '/' ? '' : '/' . trim($site, '/'));
    $host = (string) (parse_url(trim((string) ($cfg['public_url'] ?? '')), PHP_URL_HOST) ?? '');
    return [$site, $host];
}

function host_ip(): string {
    $ip = trim((string) shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
    return $ip !== '' ? $ip : '127.0.0.1';
}

/** $site is the link prefix with its leading slash ("/moop"), or "" for a site at its domain's root. */
function viewer_base(string $viewer, string $site): string {
    global $PUBLIC_HOST;
    if ($PUBLIC_HOST !== '') return "https://$PUBLIC_HOST$site";   // connected by viewer_from()
    switch ($viewer) {
        case 'public':  return 'https://' . host_ip() . $site;   // logged in, then previewing
        case 'network': return 'http://' . host_ip() . $site;
        case 'admin':   return 'https://' . host_ip() . $site;
    }
    throw new RuntimeException("unknown viewer '$viewer'");
}

/** The address the requests come from (and go to): the host IP, inside auto_login_ip_ranges --
 *  which also exempts them from nginx's rate limits (from 127.0.0.1 a second run got 429s).
 *  public is the admin's preview, so its address does not change what it sees. */
function viewer_from(string $viewer): string {
    return host_ip();
}

/** One HTTP request; returns [status, body]. Cookies persist per viewer in $jar.
 *  $from: with a public_url, connect to this machine at that address instead of the name's DNS. */
function http(string $url, string $jar, ?array $post = null, string $from = ''): array {
    global $PUBLIC_HOST;
    $ch = curl_init($url);
    if ($PUBLIC_HOST !== '' && $from !== '') curl_setopt($ch, CURLOPT_RESOLVE, ["$PUBLIC_HOST:443:$from"]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,   // the host IP is not on the cert
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    if ($body === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException("$url: $err"); }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, $body];
}

function csrf_from(string $html): string {
    return preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m) ? $m[1] : '';
}

/** Log $jar in as the admin in $login_file; for 'public', then enter the "view as PUBLIC" preview. */
function viewer_login(string $viewer, string $jar, string $site, string $login_file): void {
    $lines = is_readable($login_file) ? file($login_file, FILE_IGNORE_NEW_LINES) : [];
    if (count($lines) < 2) throw new RuntimeException("$viewer viewer needs $login_file (username, password)");
    $base = viewer_base($viewer, $site); $from = viewer_from($viewer);
    [, $login_page] = http("$base/login.php", $jar, null, $from);
    preg_match('/name="csrf_token" value="([^"]+)"/', $login_page, $m);
    http("$base/login.php", $jar, ['username' => trim($lines[0]), 'password' => $lines[1], 'csrf_token' => $m[1] ?? ''], $from);
    [$st, $dash] = http("$base/admin/admin.php", $jar, null, $from);
    if ($st !== 200) throw new RuntimeException("admin login failed (dashboard answered $st)");
    if ($viewer === 'public') {
        // Into the PUBLIC preview: what an anonymous visitor sees, minus the human check.
        http("$base/view_as.php", $jar, ['action' => 'enter', 'csrf_token' => csrf_from($dash)], $from);
        [, $home] = http("$base/index.php", $jar, null, $from);
        if (stripos($home, 'name="action" value="leave"') === false) throw new RuntimeException('could not enter the "view as PUBLIC" preview');
    }
}
