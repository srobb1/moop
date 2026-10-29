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
 * Write {cache}/nginx_wanted.json from the site's auto_login_ip_ranges and rate_limit_heavy.
 * Called by housekeeping (hourly), right after Site Configuration is saved, and after the heavy
 * group is changed on the System Monitor page.
 * Only valid entries are written (the collector checks again; this keeps a bad entry from
 * blocking the good ones in the meantime). Returns false if the file could not be written.
 *
 * @param array|null $ranges ranges to publish; null = read them from the config
 * @param array|null $heavy  heavy scripts to publish; null = read them from the config
 */
function moop_monitor_publish_wanted(?array $ranges = null, ?array $heavy = null): bool {
    $ranges = $ranges ?? ConfigManager::getInstance()->getArray('auto_login_ip_ranges', []);
    $heavy  = array_values(array_filter($heavy ?? moop_monitor_heavy_list(), 'moop_monitor_valid_heavy'));
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
    $json = json_encode(['generated' => time(), 'exempt_ranges' => $valid, 'heavy' => $heavy], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp = tempnam($cache, '.nginx_wanted.');
    if ($tmp === false || @file_put_contents($tmp, $json . "\n") === false) {
        if ($tmp) @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0644);
    return @rename($tmp, "$cache/nginx_wanted.json");
}

/** The heavy group as configured (Admin -> System Monitor; default in site_config.php). */
function moop_monitor_heavy_list(): array {
    return array_values(array_map('strval', ConfigManager::getInstance()->getArray('rate_limit_heavy', [])));
}

/**
 * Can this be a heavy-group entry? "api/foo.php" or "tools/foo.php", lower-case, and the file must
 * exist — the same rule the root collector applies before anything reaches nginx.
 */
function moop_monitor_valid_heavy($script, ?string $root = null): bool {
    return is_string($script) && preg_match('#^(api|tools)/[a-z0-9_]+\.php$#', $script)
        && is_file(($root ?? dirname(__DIR__)) . '/' . $script);
}

/**
 * Which nginx rate-limit class a script falls in — the same order as the map in
 * docs/nginx/moop-ratelimit-http.conf (heavy first). Shown on the monitor page and used by the
 * recommendations; if that map changes, change this with it.
 */
function moop_monitor_class(string $script, array $heavy): string {
    if (in_array($script, $heavy, true)) return 'heavy';
    if (strpos($script, 'api/jbrowse2/') === 0) return 'jbrowse';
    if (strpos($script, 'api/') === 0) return 'api';
    if (preg_match('#^tools/(annotation_search_ajax|get_annotation_sources_grouped|get_organism_hierarchy)\.php$#', $script)) return 'api';
    return 'page';
}

/** One of the collector's published files, decoded, or null if absent/unreadable. */
function moop_monitor_read(string $which): ?array {
    if (!in_array($which, ['now', '24h', '7d', '30d'], true)) return null;
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
 * Where a request came from, in words an admin can act on:
 *   'self'     — this server itself (loopback, or one of $self): tests, health checks
 *   'private'  — a private-network address (10.x, 172.16–31.x, 192.168.x): your own network,
 *                cannot arrive from the internet, but outside the auto-login ranges
 *   'internal' — inside an auto-login range (institute network, VPN)
 *   'internet' — anything else: a public address on the internet
 * Only 'internet' means the site is reachable from outside. Until 2026-10-29 everything that was
 * not 'internal' was called outside, so this server's own tests on 127.0.0.1 were reported as
 * "crawlers reaching the site".
 */
function moop_monitor_ip_origin(string $ip, array $ranges, array $self = []): string {
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return 'private';   // unparseable: never claim "internet"
    if (in_array($ip, $self, true) || $ip === '::1' || strpos($ip, '127.') === 0) return 'self';
    if (moop_monitor_ip_internal($ip, $ranges)) return 'internal';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return 'private';
    return 'internet';
}

/** Badge text and explanation for each origin — the Busiest addresses table and the guide use these. */
function moop_monitor_origin_words(): array {
    return [
        'self'     => ['This server', 'The server talking to itself — tests and health checks. Not visitors.'],
        'private'  => ['Private network', 'A private address (10.x, 172.16–31.x, 192.168.x). It cannot come from the internet — it is on your own network, just outside the auto-login ranges.'],
        'internal' => ['Internal', 'Inside the auto-login ranges (institute network, VPN). Never rate-limited.'],
        'internet' => ['Internet', 'A public internet address. If the site is meant to be internal-only, it should not appear here.'],
    ];
}

/**
 * Add up the collector's per-minute (24 h) or per-hour (7 d) records for the traffic tables:
 * requests by class, status, user-agent kind, 429s, bytes, and the busiest addresses.
 */
function moop_monitor_traffic(array $points, array $ranges = [], array $self = []): array {
    $t = ['req' => 0, 'bytes' => 0, 's429' => 0, 'fpm_max_children_reached' => 0,
          'cls' => [], 'status' => [], 'ua' => [], 'limited' => [], 'ips' => [],
          // Requests from public internet addresses only. Records from before collector v2 have no
          // such count; for those it is estimated from their busiest addresses (a lower bound, and
          // with no client breakdown), and 'measured' turns false. A window mixing old and new
          // records — the first day after an upgrade — is therefore never undercounted to zero.
          'internet' => ['measured' => true, 'req' => 0, 'ua' => [], 'ips' => []]];
    foreach ($points as $p) {
        foreach (['req', 'bytes', 's429', 'fpm_max_children_reached'] as $k) $t[$k] += (int) ($p[$k] ?? 0);
        foreach (['cls', 'status', 'ua', 'limited'] as $k) {
            foreach (($p[$k] ?? []) as $name => $n) $t[$k][$name] = ($t[$k][$name] ?? 0) + (int) $n;
        }
        foreach (($p['top_ips'] ?? []) as $pair) {
            [$ip, $n] = $pair;
            $t['ips'][$ip] = ($t['ips'][$ip] ?? 0) + (int) $n;
        }
        $net = &$t['internet'];
        if (isset($p['internet'])) {
            $net['req'] += (int) ($p['internet']['req'] ?? 0);
            foreach (($p['internet']['ua'] ?? []) as $name => $n) $net['ua'][$name] = ($net['ua'][$name] ?? 0) + (int) $n;
            foreach (($p['internet']['top_ips'] ?? []) as $pair) $net['ips'][$pair[0]] = ($net['ips'][$pair[0]] ?? 0) + (int) $pair[1];
        } else {
            $net['measured'] = false;
            foreach (($p['top_ips'] ?? []) as $pair) {
                if (moop_monitor_ip_origin((string) $pair[0], $ranges, $self) !== 'internet') continue;
                $net['req'] += (int) $pair[1];
                $net['ips'][$pair[0]] = ($net['ips'][$pair[0]] ?? 0) + (int) $pair[1];
            }
        }
        unset($net);
    }
    arsort($t['internet']['ua']);
    arsort($t['internet']['ips']);
    foreach (['cls', 'status', 'ua', 'limited', 'ips'] as $k) arsort($t[$k]);
    $t['ips'] = array_slice($t['ips'], 0, 20, true);
    return $t;
}

/**
 * The collector's disks, named for what they hold on THIS kind of host, most important first,
 * with duplicates (two paths on one filesystem — the cache usually sits on /var) removed.
 * Each: label, role, used_pct, free_gb, size_gb, path.
 */
function moop_monitor_disks(array $disks): array {
    $roles = [
        'organisms' => ['Data', 'databases, genomes, uploads — new data cannot be added when full'],
        'var'       => ['/var', 'logs, MOOP caches, the request log — when full, logging fails and pages that write caches break'],
        'cache'     => ['Cache', 'MOOP caches'],
        'tmp'       => ['/tmp', 'temporary files — large exports and BLAST runs fail when full'],
        'root'      => ['System (/)', 'the operating system — updates fail when full'],
    ];
    $out = [];
    $seen = [];
    foreach ($roles as $key => [$label, $role]) {
        if (!isset($disks[$key])) continue;
        $d = $disks[$key];
        $sig = $d['size_gb'] . '|' . $d['used_pct'] . '|' . $d['free_gb'];   // same filesystem → same numbers
        if (isset($seen[$sig])) continue;
        $seen[$sig] = true;
        $out[$key] = $d + ['label' => $label, 'role' => $role];
    }
    return $out;
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

const MOOP_FPM_WORKER_BASE_MB = 15;   // a php-fpm worker's own footprint beyond the request's peak (estimate)

/**
 * Capacity: does the server need more RAM, more CPUs, or more PHP workers? From the collector's
 * samples ($points, 24 h or 7 d) and the request log summary ($pages). Each verdict says which
 * figure it rests on, so an admin can check it rather than trust it.
 *
 * How workers and RAM relate: each busy PHP worker holds up to one request's peak memory. If all
 * pm.max_children workers were busy with a typical-heavy request (95th-percentile peak) at once,
 * PHP alone would need max_children x (p95 peak + worker base). That must fit in RAM with room
 * left for the OS and — on this site above all — the page cache that keeps SQLite fast.
 */
function moop_monitor_capacity(array $points, array $pages, ?array $now, ?int $max_children): array {
    // Hourly records (7 d) carry both an average and a peak: averages from averages, peaks from
    // peaks. Minute records (24 h) have one value, used for both.
    $vals = function (string $k, bool $peak) use ($points) {
        return array_values(array_filter(array_map(fn($p) => $peak ? ($p[$k . '_max'] ?? ($p[$k] ?? null)) : ($p[$k] ?? null), $points), 'is_numeric'));
    };
    $cpu = $vals('cpu_pct', false);
    $cpu_peaks = $vals('cpu_pct', true);
    $mem = $vals('mem_used_pct', true);
    $avg = fn($a) => $a ? array_sum($a) / count($a) : null;
    $total_mb = (int) ($now['mem_total_mb'] ?? 0);
    $per_worker = ($pages['peak_p95_mb'] ?? 0) + MOOP_FPM_WORKER_BASE_MB;
    $reached = array_sum(array_map(fn($p) => (int) ($p['fpm_max_children_reached'] ?? 0), $points));
    $queued = count(array_filter($points, fn($p) => (int) ($p['fpm_queue_max'] ?? ($p['fpm_queue'] ?? 0)) > 0));
    $suggested = ($total_mb > 0 && $per_worker > 0) ? (int) floor(0.6 * $total_mb / $per_worker) : null;
    return [
        'samples'        => count($points),
        'cpu_avg'        => $avg($cpu) !== null ? round($avg($cpu), 1) : null,
        'cpu_peak'       => $cpu_peaks ? max($cpu_peaks) : null,
        'cpu_busy_share' => $cpu ? round(100 * count(array_filter($cpu, fn($v) => $v >= 80)) / count($cpu)) : null,
        'mem_peak'       => $mem ? max($mem) : null,
        'swap_peak_mb'   => ($s = $vals('swap_used_mb', true)) ? max($s) : 0,
        'workers_reached'=> $reached,
        'queued_samples' => $queued,
        'per_worker_mb'  => round($per_worker, 1),
        'largest_mb'     => (float) ($pages['peak_max_mb'] ?? 0),
        'php_worst_mb'   => $max_children ? (int) round($max_children * $per_worker) : null,
        'total_mb'       => $total_mb,
        'suggested_max_children' => $suggested,
        'iowait_avg'     => ($io = $vals('iowait_pct', false)) ? round($avg($io), 1) : null,
        'iowait_peak'    => ($iop = $vals('iowait_pct', true)) ? max($iop) : null,
    ];
}

/**
 * What the admin should act on, most serious first. Each: level (critical|warning|info), title,
 * detail, and optionally action = ['type' => 'heavy_add', 'script' => ...].
 *
 * @param array $pages7  request-log summary over 7 days (stable enough to judge pages on)
 */
function moop_monitor_recommendations(array $cap, array $pages7, array $heavy, ?array $now, array $traffic,
                                      ?array $sync, ?int $max_children, bool $turnstile_on, array $ranges,
                                      string $span = 'in this period', array $self = []): array {
    $out = [];
    $add = function ($level, $title, $detail, $action = null) use (&$out) {
        $out[] = ['level' => $level, 'title' => $title, 'detail' => $detail, 'action' => $action];
    };

    if (($sync['state'] ?? '') === 'error') {
        $add('critical', 'nginx rate-limit settings were not applied', (string) ($sync['message'] ?? ''));
    }

    // ── Capacity ──
    if ($cap['workers_reached'] > 0 || $cap['queued_samples'] > 0) {
        $fits = $cap['suggested_max_children'] !== null && $max_children !== null && $cap['suggested_max_children'] > $max_children;
        $add('critical', 'All PHP workers were busy — visitors waited',
            "Every one of the {$max_children} PHP workers was in use " . ($cap['workers_reached'] ?: $cap['queued_samples']) . ' time(s) in this window; requests queued until one was free. '
            . ($fits ? "RAM allows more workers: raise pm.max_children toward {$cap['suggested_max_children']} (see “How to change the number of PHP workers” below)."
                     : 'RAM does not allow more workers at the measured memory per request — this is the point to add RAM (or to limit the heaviest pages).'));
    }
    if (($cap['mem_peak'] ?? 0) >= 90 || $cap['swap_peak_mb'] > 256) {
        $add('critical', 'Memory ran short — time to add RAM',
            'Memory reached ' . $cap['mem_peak'] . '% used' . ($cap['swap_peak_mb'] > 0 ? ' and the server swapped ' . $cap['swap_peak_mb'] . ' MB to disk' : '')
            . '. Swapping makes every page slow. More RAM is the fix; lowering pm.max_children trades it for queueing.');
    } elseif (($cap['mem_peak'] ?? 0) >= 80) {
        $add('warning', 'Memory is getting tight', 'Memory peaked at ' . $cap['mem_peak'] . '% used. Watch it; at 90% or any swapping, add RAM.');
    }
    if ($cap['php_worst_mb'] !== null && $cap['total_mb'] > 0 && $cap['php_worst_mb'] > 0.7 * $cap['total_mb']) {
        $add('warning', 'Too many PHP workers for this much RAM',
            "If all {$max_children} workers were busy at once with a typical-heavy request ({$cap['per_worker_mb']} MB each, measured), PHP alone would need "
            . round($cap['php_worst_mb'] / 1024, 1) . ' GB of the server’s ' . round($cap['total_mb'] / 1024, 1) . ' GB. '
            . "About {$cap['suggested_max_children']} workers fit while leaving room for the OS and the page cache SQLite depends on. Either lower pm.max_children or add RAM.");
    }
    // Cold databases: many requests read from disk AND the CPUs spend real time waiting on it.
    $io = $pages7['io'] ?? ['measured' => 0, 'cold' => 0];
    $cold_share = $io['measured'] ? 100 * $io['cold'] / $io['measured'] : 0;
    if ($io['measured'] >= 200 && $cold_share >= 25 && ($cap['iowait_avg'] ?? 0) >= 5) {
        $add('warning', 'The databases often are not in RAM — more RAM would make the site faster',
            round($cold_share) . '% of requests over 7 days had to read from disk, and the CPUs spent on average '
            . $cap['iowait_avg'] . '% of their time waiting for it. '
            . (isset($now['db_total_mb'], $now['cache_mb']) ? 'The databases total ' . round($now['db_total_mb'] / 1024, 1) . ' GB; '
                . round($now['cache_mb'] / 1024, 1) . ' GB of RAM is caching files. ' : '')
            . 'See “Databases: warm or cold” for which organisms read the most — that working set is what the cache needs to hold.');
    }
    if (($cap['cpu_busy_share'] ?? 0) >= 25 || ($cap['cpu_avg'] ?? 0) >= 70) {
        $add('warning', 'CPU is busy much of the time — consider more CPUs',
            "CPU averaged {$cap['cpu_avg']}% and was above 80% in {$cap['cpu_busy_share']}% of samples. Short spikes are normal; sustained load like this means more cores would help — but first check the busiest addresses for a crawler.");
    }

    // ── Heavy-group candidates: measurably expensive, not yet limited as heavy ──
    foreach ($pages7['by_script'] ?? [] as $script => $s) {
        $cls = moop_monitor_class($script, $heavy);
        if ($cls === 'heavy' || $cls === 'jbrowse' || !moop_monitor_valid_heavy($script) || $s['count'] < 5) continue;
        if ($s['p95_ms'] >= 2000 || $s['max_mb'] >= 64) {
            $add('warning', "Add {$script} to the heavy group?",
                "Over 7 days: {$s['count']} requests, slowest 5% took " . moop_monitor_fmt_ms($s['p95_ms']) . ", peak memory {$s['max_mb']} MB. "
                . 'In the heavy group one address may run it 20 times a minute (3 at once) instead of 2 a second.',
                ['type' => 'heavy_add', 'script' => $script]);
        }
    }

    // ── Errors ──
    foreach ($pages7['by_script'] ?? [] as $script => $s) {
        if ($s['errors'] > 0) {
            $add('warning', "{$script} failed {$s['errors']} time(s) in 7 days", 'Server errors (5xx). The details are in the PHP error log (Admin → Error Logs).');
        }
    }

    // ── Exposure: is anyone on the public internet reaching the site? ──
    // Counted only from public addresses — never this server, a private address, or an auto-login
    // range (moop_monitor_traffic). Where older records had to be estimated there is no client
    // breakdown, so make no claim about what kind of client it was.
    $net = $traffic['internet'] ?? ['measured' => true, 'req' => 0, 'ua' => [], 'ips' => []];
    if ($net['req'] > 0) {
        $top = array_slice($net['ips'], 0, 3, true);
        $who = implode(', ', array_map(fn($ip, $n) => "{$ip} (" . number_format($n) . ')', array_keys($top), $top));
        $n_addr = count($net['ips']);
        $from = ($net['measured'] ? '' : 'At least ') . number_format($net['req']) . ' request' . ($net['req'] === 1 ? '' : 's')
              . " from public internet addresses {$span}"
              . ($n_addr ? ' — busiest: ' . $who . ($n_addr > 3 ? ', and ' . ($n_addr - 3) . ' more' : '') : '') . '.';
        $auto = $net['measured'] ? (int) (($net['ua']['ai'] ?? 0) + ($net['ua']['script'] ?? 0)) : null;
        if ($auto !== null && $auto > 1000 && !$turnstile_on) {
            $add('warning', 'Crawlers on the internet are reaching the site — turn on the browser check',
                $from . ' ' . number_format($auto) . ' of them came from scripts or AI crawlers ('
                . number_format($net['ua']['ai'] ?? 0) . ' AI). '
                . 'To stop them: Admin → Site Configuration → Turnstile. Logged-in and internal users never see the check.');
        } else {
            $add('warning', 'The site is reachable from the internet',
                $from . ($auto !== null ? ' ' . number_format($auto) . ' came from scripts or AI crawlers.' : '')
                . ' If the site should be internal-only, ask IT why these addresses can reach it. '
                . 'If it is meant to be public, nothing to do; turn on Turnstile (Site Configuration) if crawlers become a load.');
        }
    }
    foreach (($now['disks'] ?? []) as $label => $d) {
        if ($d['used_pct'] >= 80) {
            $add($d['used_pct'] >= 90 ? 'critical' : 'warning', "Disk {$d['path']} is {$d['used_pct']}% full", $d['free_gb'] . ' GB free.');
        }
    }

    $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
    usort($out, fn($a, $b) => $rank[$a['level']] <=> $rank[$b['level']]);
    return $out;
}

/**
 * Why was this request slow? From what the request log measured: cold database (it read from
 * disk AND most of its time was in the database), database warm (database-bound but nothing read
 * from disk — the query itself is slow), disk outside the database, or PHP/other.
 * null = not measured (logged before 2026-10-28, or from an endpoint without the counters).
 */
function moop_monitor_why_slow(array $r): ?string {
    if (($r['disk_kb'] ?? null) === null) return null;
    $ms = max(1, (int) ($r['ms'] ?? 0));
    $db_heavy = ($r['db_ms'] ?? 0) >= 0.5 * $ms;
    $cold = $r['disk_kb'] > MOOP_METRICS_COLD_KB;
    if ($db_heavy && $cold) return 'Cold database';
    if ($db_heavy) return 'Database (warm)';
    if ($cold) return 'Reading files from disk';
    return 'PHP / other';
}
