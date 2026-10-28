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
 * Read-only. Nothing here runs a command or touches nginx.
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/system_monitor.php';
require_once __DIR__ . '/../lib/request_metrics.php';

$siteTitle = $config->getString('siteTitle');
$site      = $config->getString('site');

$range   = ($_GET['range'] ?? '24h') === '7d' ? '7d' : '24h';
$seconds = $range === '7d' ? 7 * 86400 : 86400;

$now_file = moop_monitor_read('now');
$hist     = moop_monitor_read($range);
$points   = $range === '7d' ? ($hist['hours'] ?? []) : ($hist['minutes'] ?? []);
$now      = $now_file['now'] ?? null;
$sync     = $now_file['nginx_sync'] ?? null;
$age      = $now_file ? time() - (int) $now_file['generated'] : null;

$max_children = moop_monitor_fpm_max_children();
$ranges       = $config->getArray('auto_login_ip_ranges', []);

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
    $worst = null;
    foreach (($now['disks'] ?? []) as $label => $d) {
        if ($worst === null || $d['used_pct'] > $worst['used_pct']) $worst = $d + ['label' => $label];
    }
    if ($worst) {
        $tiles[] = ['label' => 'Fullest disk', 'value' => $worst['used_pct'] . '%',
                    'sub' => $worst['path'] . ' · ' . $worst['free_gb'] . ' GB free',
                    'level' => moop_monitor_level($worst['used_pct'], 80, 90)];
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
    ];
}

$traffic = moop_monitor_traffic($points);
$pages   = moop_metrics_summarise(moop_metrics_rows_since($seconds), 20);

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
        'page_styles'  => ['/' . $site . '/css/system-monitor.css'],
        'page_script'  => ['/' . $site . '/js/system-monitor.js'],
        'inline_scripts' => [
            'window.MOOP_MONITOR = ' . json_encode(['range' => $range, 'series' => $series],
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
        ],
    ],
    'System Monitor - ' . $siteTitle
);
