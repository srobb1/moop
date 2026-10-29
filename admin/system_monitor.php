<?php
/**
 * SYSTEM MONITOR — resources, traffic, the heaviest pages, and who is using them.
 *
 * Two sources (notes/SYSTEM_MONITOR_PLAN.md):
 *   - the root collector's files (scripts/moop_monitor.py, once a minute): CPU, RAM, disk,
 *     php-fpm workers, nginx traffic and 429s, nginx rate-limit sync — read via
 *     lib/system_monitor.php;
 *   - MOOP's own request log (lib/request_metrics.php): per page, time and peak memory, and who.
 *
 * The one thing it changes is the heavy group (rate_limit_heavy in config_editable.json), from
 * the Add / Remove buttons. MOOP then publishes it and the root collector applies it to nginx
 * within a minute. Nothing here runs a command or touches nginx itself.
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/system_monitor.php';
require_once __DIR__ . '/../lib/request_metrics.php';

$siteTitle = $config->getString('siteTitle');
$site      = $config->getString('site');

// ── Heavy group: add / remove (POST; admin_init.php has checked the CSRF token) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['heavy_add', 'heavy_remove'], true)) {
    $script = (string) ($_POST['script'] ?? '');
    $list   = moop_monitor_heavy_list();
    if ($_POST['action'] === 'heavy_add' && moop_monitor_valid_heavy($script) && !in_array($script, $list, true)) {
        $list[] = $script;
    } elseif ($_POST['action'] === 'heavy_remove') {
        $list = array_values(array_diff($list, [$script]));
    }
    $config_dir = $config->getPath('root_path') . '/' . $site . '/config';
    $result = $config->saveEditableConfig(['rate_limit_heavy' => $list], $config_dir);
    $published = $result['success'] && moop_monitor_publish_wanted(null, $list);
    $_SESSION['sysmon_flash'] = $published
        ? ['ok', ($_POST['action'] === 'heavy_add' ? 'Added ' : 'Removed ') . $script . ' — nginx will follow within a minute.']
        : ['error', 'Not saved: ' . ($result['success'] ? 'could not write nginx_wanted.json to the cache directory' : $result['message'])];
    $back = in_array($_GET['range'] ?? '', ['7d', '30d'], true) ? '?range=' . $_GET['range'] : '';
    header('Location: system_monitor.php' . $back . '#sysmon-heavy');
    exit;
}
$flash = $_SESSION['sysmon_flash'] ?? null;
unset($_SESSION['sysmon_flash']);

$range   = in_array($_GET['range'] ?? '', ['7d', '30d'], true) ? $_GET['range'] : '24h';
$seconds = ['24h' => 86400, '7d' => 7 * 86400, '30d' => 30 * 86400][$range];

$now_file = moop_monitor_read('now');
$hist     = moop_monitor_read($range);
$points   = $range === '30d' ? ($hist['days'] ?? []) : ($range === '7d' ? ($hist['hours'] ?? []) : ($hist['minutes'] ?? []));
if ($range === '30d') {
    // The collector keeps a year of days; this view is the last 30.
    $points = array_values(array_filter($points, fn($p) => $p['t'] >= time() - 31 * 86400));
}
$now      = $now_file['now'] ?? null;
$sync     = $now_file['nginx_sync'] ?? null;
$age      = $now_file ? time() - (int) $now_file['generated'] : null;

$max_children = moop_monitor_fpm_max_children();
$ranges       = $config->getArray('auto_login_ip_ranges', []);
// This server's own address: requests from it are tests and health checks, not visitors.
$self_ips     = array_values(array_filter([$_SERVER['SERVER_ADDR'] ?? '']));

// ── "Right now" tiles. Thresholds are deliberately plain; each tile says what it means. ──
$tiles = [];
if ($now) {
    $tiles[] = ['label' => 'CPU', 'value' => isset($now['cpu_pct']) ? $now['cpu_pct'] . '%' : '—',
                'sub' => $now['cpus'] . ' cores · load ' . $now['load1'],
                'level' => moop_monitor_level($now['cpu_pct'] ?? null, 60, 85)];
    $tiles[] = ['label' => 'Memory used', 'value' => $now['mem_used_pct'] . '%',
                'sub' => round($now['mem_avail_mb'] / 1024, 1) . ' GB free of ' . round($now['mem_total_mb'] / 1024) . ' GB'
                         . ($now['swap_used_mb'] > 0 ? ' · swap ' . $now['swap_used_mb'] . ' MB' : ''),
                'level' => moop_monitor_level($now['mem_used_pct'], 75, 90)];
    if (isset($now['fpm_active'])) {
        $busy_pct = $max_children ? 100 * $now['fpm_active'] / $max_children : null;
        $tiles[] = ['label' => 'PHP workers busy', 'value' => $now['fpm_active'] . ($max_children ? ' / ' . $max_children : ''),
                    'sub' => 'queue ' . (int) $now['fpm_queue'] . ' · ' . (int) $now['fpm_total'] . ' started',
                    'level' => (int) $now['fpm_queue'] > 0 ? 'critical' : moop_monitor_level($busy_pct, 60, 90)];
    } else {
        $tiles[] = ['label' => 'PHP workers busy', 'value' => '—', 'sub' => 'status page unreachable', 'level' => 'unknown'];
    }
    if (isset($now['db_total_mb'], $now['cache_mb'])) {
        // How much of the data can be in RAM at once. Not a fault in itself — most of a database
        // is rarely read — but the lower it goes, the more requests go cold (see the warm/cold card).
        // "At most": the RAM cache also holds other files, so this is the ceiling, not a measurement
        // of what is cached. Whether it HURTS is the disk wait (and the warm/cold card).
        $fit = $now['db_total_mb'] > 0 ? min(100, 100 * $now['cache_mb'] / $now['db_total_mb']) : null;
        $tiles[] = ['label' => 'Databases that fit in RAM', 'value' => $fit === null ? '—' : 'at most ' . round($fit) . '%',
                    'sub' => round($now['cache_mb'] / 1024, 1) . ' GB of RAM for ' . round($now['db_total_mb'] / 1024, 1) . ' GB of databases'
                             . (isset($now['iowait_pct']) ? ' · waiting on disk ' . $now['iowait_pct'] . '%' : ''),
                    'level' => isset($now['iowait_pct']) ? moop_monitor_level($now['iowait_pct'], 10, 25) : 'unknown'];
    }
    // The data disk always; any other disk only once it is filling up (it matters then, and
    // only then — "the OS disk is 33% full" is noise).
    foreach (moop_monitor_disks($now['disks'] ?? []) as $key => $d) {
        if ($key !== 'organisms' && $d['used_pct'] < 80) continue;
        $tiles[] = ['label' => $key === 'organisms' ? 'Data disk used' : 'Disk filling: ' . $d['label'],
                    'value' => $d['used_pct'] . '%',
                    'sub' => $d['free_gb'] . ' GB free of ' . $d['size_gb'] . ' GB',
                    'level' => moop_monitor_level($d['used_pct'], 80, 90)];
    }
}

// ── Chart series (the JS draws them; one measure per chart, never two y-axes). ──
$series = [];
foreach ($points as $p) {
    $series[] = [
        't'    => (int) $p['t'],
        'cpu'  => $p['cpu_pct'] ?? null,
        'mem'  => $p['mem_used_pct'] ?? null,
        'req'  => (int) ($p['req'] ?? 0),
        'fpm'  => $p['fpm_active'] ?? null,
        's429' => (int) ($p['s429'] ?? 0),
        'iowait' => $p['iowait_pct'] ?? null,
        'dread'  => $p['disk_read_mbs'] ?? null,
    ];
}

$traffic = moop_monitor_traffic($points, $ranges, $self_ips);
if ($range === '30d') {
    // 29 finished days from housekeeping's daily summaries + today, read live. Re-reading a month
    // of request lines on every page load would not scale with traffic.
    $today_rows = fn() => moop_metrics_rows(date('Y-m-d'));
    $daily = moop_metrics_daily(29);
    $pages = moop_metrics_merge(array_merge($daily, [moop_metrics_summarise($today_rows(), 20) + ['skipped' => moop_metrics_skipped(date('Y-m-d'))]]));
    $usage = moop_metrics_usage_merge(array_merge(array_column($daily, 'usage'), [moop_metrics_usage($today_rows())]));
    $days_covered = count($daily) + 1;
} else {
    $pages = moop_metrics_summarise(moop_metrics_rows_since($seconds), 20);
    $usage = moop_metrics_usage(moop_metrics_rows_since($seconds));
    $days_covered = null;
    // Requests the safety cap counted but did not log, over the dates this window touches.
    $pages['skipped'] = 0;
    for ($d = time() - $seconds; $d <= time() + 60; $d += 86400) $pages['skipped'] += moop_metrics_skipped(date('Y-m-d', $d));
}
// Recommendations judge pages on 7 days whatever window is shown: a day is too little evidence.
$pages7  = $range === '7d' ? $pages : moop_metrics_summarise(moop_metrics_rows_since(7 * 86400));
$heavy   = moop_monitor_heavy_list();
$cap     = moop_monitor_capacity($points, $pages7, $now, $max_children);
$recs    = moop_monitor_recommendations($cap, $pages7, $heavy, $now, $traffic, $sync, $max_children,
                                         !empty($config->getArray('turnstile', [])['enabled']), $ranges,
                                         ['24h' => 'in the last 24 hours', '7d' => 'in the last 7 days', '30d' => 'in the last 30 days'][$range],
                                         $self_ips);

echo render_display_page(
    __DIR__ . '/pages/system_monitor.php',
    [
        'siteTitle'    => $siteTitle,
        'range'        => $range,
        'age'          => $age,
        'collector_ok' => $now_file !== null && $age !== null && $age < 300,
        'sync'         => $sync,
        'tiles'        => $tiles,
        'now'          => $now,
        'traffic'      => $traffic,
        'pages'        => $pages,
        'max_children' => $max_children,
        'ranges'       => $ranges,
        'self_ips'     => $self_ips,
        'heavy'        => $heavy,
        'cap'          => $cap,
        'recs'         => $recs,
        'flash'        => $flash,
        'usage'        => $usage,
        'days_covered' => $days_covered,
        'site'         => $site,
        'page_styles'  => ['/' . $site . '/css/system-monitor.css'],
        'page_script'  => ['/' . $site . '/js/system-monitor.js'],
        'inline_scripts' => [
            'window.MOOP_MONITOR = ' . json_encode(['range' => $range, 'series' => $series],
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
        ],
    ],
    'System Monitor - ' . $siteTitle
);
