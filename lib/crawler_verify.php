<?php
/**
 * Is this request really from Google's, Bing's or DuckDuckGo's search crawler?
 *
 * Why: the human check (lib/human_check.php) turned every search engine away along with the
 * scrapers, so no page of the site could appear in search results (seen 2026-11-23). A search
 * crawler cannot solve a challenge, but it CAN be proven genuine, the way each engine documents:
 *   - Google, Bing: reverse DNS of the address ends in their domain, AND that name resolves back
 *     to the same address (a scraper can set any User-Agent, and can even control its own reverse
 *     DNS, but not Google's forward DNS).
 *   - DuckDuckGo: the address is in its published list (duckduckbot.json, refreshed weekly by
 *     housekeeping task crawler_lists). DuckDuckGo publishes no reverse-DNS names.
 * Anything merely CLAIMING to be one of them in its User-Agent is checked; nothing else is, so
 * ordinary visitors never cost a DNS lookup. Results are cached per address for a day (both yes
 * and no), so each address costs at most one lookup a day.
 *
 * A verified crawler is let past the human check for PAGES only (human_check.php) -- never the
 * API, AJAX, downloads or exports -- and robots.txt still decides which pages it should fetch.
 */

require_once __DIR__ . '/cache_paths.php';

const MOOP_CRAWLER_CACHE_TTL = 86400;
const MOOP_CRAWLER_DDG_URL   = 'https://duckduckgo.com/duckduckbot.json';

/** Which crawler the User-Agent claims to be: 'google' | 'bing' | 'duckduckgo' | null. */
function moop_crawler_claimed(string $ua): ?string {
    if (stripos($ua, 'Googlebot') !== false) return 'google';
    if (stripos($ua, 'bingbot') !== false) return 'bing';
    if (stripos($ua, 'DuckDuckBot') !== false) return 'duckduckgo';
    return null;
}

/** Does a reverse-DNS host name belong to that crawler? (Pure; the smoke tests call it.) */
function moop_crawler_host_ok(string $name, string $host): bool {
    $host = strtolower(rtrim($host, '.'));
    $suffixes = ['google' => ['.googlebot.com', '.google.com'], 'bing' => ['.search.msn.com']][$name] ?? [];
    foreach ($suffixes as $s) {
        if (substr($host, -strlen($s)) === $s) return true;
    }
    return false;
}

/** Is $ip inside $cidr? IPv4 and IPv6. (Pure.) */
function moop_crawler_in_cidr(string $ip, string $cidr): bool {
    [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
    $a = @inet_pton($ip);
    $b = @inet_pton($net);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bits = $bits === null ? strlen($a) * 8 : (int) $bits;
    $whole = intdiv($bits, 8);
    if (substr($a, 0, $whole) !== substr($b, 0, $whole)) return false;
    $rest = $bits % 8;
    if ($rest === 0) return true;
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($a[$whole]) & $mask) === (ord($b[$whole]) & $mask);
}

function moop_crawler_dir(): string {
    return moop_cache_root() . '/crawlers';
}

/**
 * The crawler this request is, verified, or null. Only call it for a request the human check
 * would otherwise turn away -- it may do DNS lookups (once a day per address).
 */
function moop_crawler_verified(string $ip, string $ua): ?string {
    $name = moop_crawler_claimed($ua);
    if ($name === null || filter_var($ip, FILTER_VALIDATE_IP) === false) return null;

    $dir = moop_crawler_dir() . '/seen';
    $file = $dir . '/' . sha1($name . '|' . $ip) . '.json';
    $c = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (is_array($c) && ($c['ts'] ?? 0) > time() - MOOP_CRAWLER_CACHE_TTL) {
        return !empty($c['ok']) ? $name : null;
    }

    $ok = $name === 'duckduckgo' ? moop_crawler_ddg_listed($ip) : moop_crawler_dns_confirmed($name, $ip);
    if (moop_ensure_cache_dir($dir)) {
        @file_put_contents($file, json_encode(['ok' => $ok, 'ts' => time()]));
    }
    return $ok ? $name : null;
}

/** Reverse DNS, then forward-confirm the name points back to the same address. */
function moop_crawler_dns_confirmed(string $name, string $ip): bool {
    $host = @gethostbyaddr($ip);
    if (!$host || $host === $ip || !moop_crawler_host_ok($name, $host)) return false;
    $is6 = strpos($ip, ':') !== false;
    $recs = @dns_get_record($host, $is6 ? DNS_AAAA : DNS_A) ?: [];
    $want = inet_pton($ip);
    foreach ($recs as $r) {
        $addr = $r[$is6 ? 'ipv6' : 'ip'] ?? '';
        if ($addr !== '' && @inet_pton($addr) === $want) return true;
    }
    return false;
}

function moop_crawler_ddg_file(): string {
    return moop_crawler_dir() . '/duckduckbot.json';
}

function moop_crawler_ddg_listed(string $ip): bool {
    $d = json_decode((string) @file_get_contents(moop_crawler_ddg_file()), true);
    foreach (($d['prefixes'] ?? []) as $p) {
        $cidr = $p['ipv4Prefix'] ?? $p['ipv6Prefix'] ?? '';
        if ($cidr !== '' && moop_crawler_in_cidr($ip, $cidr)) return true;
    }
    return false;
}

/**
 * Refresh DuckDuckGo's address list (housekeeping, weekly) and drop day-old per-address results.
 * Returns ['ddg_prefixes' => n|null, 'error' => ?string].
 */
function moop_crawler_refresh_lists(bool $force = false): array {
    $dir = moop_crawler_dir();
    if (!moop_ensure_cache_dir($dir)) return ['ddg_prefixes' => null, 'error' => "cannot create $dir"];
    foreach (glob($dir . '/seen/*.json') ?: [] as $f) {
        if (filemtime($f) < time() - MOOP_CRAWLER_CACHE_TTL) @unlink($f);
    }
    $file = moop_crawler_ddg_file();
    if (!$force && is_file($file) && filemtime($file) > time() - 7 * 86400) {
        $d = json_decode((string) file_get_contents($file), true);
        return ['ddg_prefixes' => count($d['prefixes'] ?? []), 'error' => null];
    }
    $raw = @file_get_contents(MOOP_CRAWLER_DDG_URL, false, stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'MOOP crawler list update']]));
    $d = $raw ? json_decode($raw, true) : null;
    if (!is_array($d) || empty($d['prefixes'])) {
        return ['ddg_prefixes' => null, 'error' => 'could not read ' . MOOP_CRAWLER_DDG_URL . ' (the old list, if any, is kept)'];
    }
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $raw) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        return ['ddg_prefixes' => null, 'error' => "could not write $file"];
    }
    @chmod($file, 0664);
    return ['ddg_prefixes' => count($d['prefixes']), 'error' => null];
}
