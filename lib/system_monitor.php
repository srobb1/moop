<?php
/**
 * System Monitor — MOOP's side of the root collector (scripts/moop_monitor.py, installed by
 * scripts/install_monitor.sh). Plan: notes/SYSTEM_MONITOR_PLAN.md.
 *
 * MOOP never touches nginx itself. It PUBLISHES what it wants (nginx_wanted.json, in the cache
 * directory) and the root collector — once a minute — re-validates it and applies it, so a change
 * to the auto-login IP ranges reaches the rate-limit exemptions without anyone re-running a
 * script. The collector publishes what it measured (monitor_now/24h/7d.json); this file reads
 * them for the admin page.
 */

require_once __DIR__ . '/cache_paths.php';

/**
 * Write {cache}/nginx_wanted.json from the site's auto_login_ip_ranges.
 * Called by housekeeping (hourly) and right after Site Configuration is saved.
 * Only valid IPv4 ranges are written (the collector checks again; this keeps a bad entry from
 * blocking the good ones in the meantime). Returns false if the file could not be written.
 *
 * @param array|null $ranges ranges to publish; null = read them from the config
 */
function moop_monitor_publish_wanted(?array $ranges = null): bool {
    $ranges = $ranges ?? ConfigManager::getInstance()->getArray('auto_login_ip_ranges', []);
    $valid = [];
    foreach ($ranges as $r) {
        $s = (string) ($r['start'] ?? '');
        $e = (string) ($r['end'] ?? '');
        if (filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($e, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ip2long($s) <= ip2long($e)) {
            $valid[] = ['start' => $s, 'end' => $e];
        }
    }
    $cache = moop_cache_root();
    if (!moop_ensure_cache_dir($cache)) return false;
    $json = json_encode(['generated' => time(), 'exempt_ranges' => $valid], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp = tempnam($cache, '.nginx_wanted.');
    if ($tmp === false || @file_put_contents($tmp, $json . "\n") === false) {
        if ($tmp) @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0644);
    return @rename($tmp, "$cache/nginx_wanted.json");
}

/** One of the collector's published files, decoded, or null if absent/unreadable. */
function moop_monitor_read(string $which): ?array {
    if (!in_array($which, ['now', '24h', '7d'], true)) return null;
    $file = moop_cache_root() . "/monitor_$which.json";
    if (!is_readable($file)) return null;
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/**
 * good | warning | critical for a reading, by two thresholds. Higher = worse.
 * Status is never shown by colour alone: the page pairs it with an icon and a word.
 */
function moop_monitor_level(?float $value, float $warn, float $crit): string {
    if ($value === null) return 'unknown';
    return $value >= $crit ? 'critical' : ($value >= $warn ? 'warning' : 'good');
}

/** php-fpm's worker limit (pm.max_children), or null if www.conf cannot be read. */
function moop_monitor_fpm_max_children(string $conf = '/etc/php-fpm.d/www.conf'): ?int {
    $text = @file_get_contents($conf);
    return ($text !== false && preg_match('/^\s*pm\.max_children\s*=\s*(\d+)/m', $text, $m)) ? (int) $m[1] : null;
}

/** Is $ip inside one of the auto-login ranges (internal network, VPN, institute)? */
function moop_monitor_ip_internal(string $ip, array $ranges): bool {
    $n = ip2long($ip);
    if ($n === false) return false;
    foreach ($ranges as $r) {
        $s = ip2long((string) ($r['start'] ?? ''));
        $e = ip2long((string) ($r['end'] ?? ''));
        if ($s !== false && $e !== false && $n >= $s && $n <= $e) return true;
    }
    return false;
}

/**
 * Add up the collector's per-minute (24 h) or per-hour (7 d) records for the traffic tables:
 * requests by class, status, user-agent kind, 429s, bytes, and the busiest addresses.
 */
function moop_monitor_traffic(array $points): array {
    $t = ['req' => 0, 'bytes' => 0, 's429' => 0, 'fpm_max_children_reached' => 0,
          'cls' => [], 'status' => [], 'ua' => [], 'limited' => [], 'ips' => []];
    foreach ($points as $p) {
        foreach (['req', 'bytes', 's429', 'fpm_max_children_reached'] as $k) $t[$k] += (int) ($p[$k] ?? 0);
        foreach (['cls', 'status', 'ua', 'limited'] as $k) {
            foreach (($p[$k] ?? []) as $name => $n) $t[$k][$name] = ($t[$k][$name] ?? 0) + (int) $n;
        }
        foreach (($p['top_ips'] ?? []) as $pair) {
            [$ip, $n] = $pair;
            $t['ips'][$ip] = ($t['ips'][$ip] ?? 0) + (int) $n;
        }
    }
    foreach (['cls', 'status', 'ua', 'limited', 'ips'] as $k) arsort($t[$k]);
    $t['ips'] = array_slice($t['ips'], 0, 20, true);
    return $t;
}

/** 850 ms / 4.2 s / 3.1 min — a duration as a person reads it. */
function moop_monitor_fmt_ms(float $ms): string {
    if ($ms < 1000) return round($ms) . ' ms';
    if ($ms < 60000) return round($ms / 1000, 1) . ' s';
    if ($ms < 3600000) return round($ms / 60000, 1) . ' min';
    return round($ms / 3600000, 1) . ' h';
}

/** 512 B / 3.4 MB / 1.2 GB. */
function moop_monitor_fmt_bytes(float $b): string {
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $u) {
        if ($b < 1024 || $u === 'TB') return ($i ? round($b, 1) : (int) $b) . ' ' . $u;
        $b /= 1024;
    }
    return '';
}
