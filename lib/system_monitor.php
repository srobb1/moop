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
