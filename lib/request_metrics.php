<?php
/**
 * Request log for the System Monitor — one line per PHP request: what ran, how long, how much
 * memory, and for whom. Plan: notes/SYSTEM_MONITOR_PLAN.md (piece 1).
 *
 * Why PHP and not the nginx log: nginx cannot see peak memory or who is logged in, and those
 * are the two questions that matter most here — RAM is what runs out first on this host
 * (capacity test 2026-10-23), and "which page, by whom" is what the admin wants to see.
 *
 * Cost: one append per request, at shutdown, after the page is built. A line is ~200 bytes, well
 * under PIPE_BUF, so concurrent appends never interleave (O_APPEND); no lock is taken.
 *
 * Files: {cache_path}/metrics/requests-YYYY-MM-DD.tsv, kept 30 days (housekeeping
 * request_metrics_retention). Holds usernames and addresses: admin-only, never backed up.
 *
 * A failed append is silent per request by design (a page must never fail because its log line
 * could not be written) — the monitor reports a day with no lines instead, which is visible.
 */

const MOOP_METRICS_RETENTION_DAYS = 30;
const MOOP_METRICS_COLUMNS = ['ts', 'script', 'method', 'status', 'ms', 'peak_mb', 'user', 'level', 'ip', 'agent'];

function moop_metrics_dir(): string {
    require_once __DIR__ . '/cache_paths.php';   // functions only; access_control.php does not load it
    return moop_cache_root() . '/metrics';
}

/** Run once per request, from access_control.php. */
function moop_metrics_register(): void {
    static $done = false;
    if ($done || PHP_SAPI === 'cli') return;
    $done = true;
    register_shutdown_function('moop_metrics_record');
}

function moop_metrics_record(): void {
    $dir = moop_metrics_dir();
    if ($dir === '' || !moop_ensure_cache_dir($dir)) return;

    $start  = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    $status = http_response_code();
    $line = moop_metrics_line([
        'ts'      => sprintf('%.3f', $start),
        'script'  => moop_metrics_script_name($_SERVER['SCRIPT_FILENAME'] ?? ''),
        'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
        'status'  => $status === false ? '' : (string) $status,
        'ms'      => (string) (int) round((microtime(true) - $start) * 1000),
        'peak_mb' => sprintf('%.1f', memory_get_peak_usage(true) / 1048576),
        'user'    => (string) ($_SESSION['username'] ?? ''),
        'level'   => (string) ($_SESSION['access_level'] ?? 'PUBLIC'),
        'ip'      => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'agent'   => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160),
    ]);
    @file_put_contents($dir . '/requests-' . date('Y-m-d', (int) $start) . '.tsv', $line, FILE_APPEND);
}

/** "/var/www/html/moop/tools/parent.php" -> "tools/parent.php" (path under the app root). */
function moop_metrics_script_name(string $file): string {
    $root = dirname(__DIR__) . '/';
    return strpos($file, $root) === 0 ? substr($file, strlen($root)) : basename($file);
}

/** One TSV line; tabs and newlines inside a value become spaces, so a field can never split. */
function moop_metrics_line(array $row): string {
    $out = [];
    foreach (MOOP_METRICS_COLUMNS as $col) {
        $out[] = str_replace(["\t", "\r", "\n"], ' ', (string) ($row[$col] ?? ''));
    }
    return implode("\t", $out) . "\n";
}

/** Parse one line back into a row, or null if it is not a complete line. */
function moop_metrics_parse(string $line): ?array {
    $f = explode("\t", rtrim($line, "\n"));
    if (count($f) !== count(MOOP_METRICS_COLUMNS)) return null;
    $row = array_combine(MOOP_METRICS_COLUMNS, $f);
    $row['ts'] = (float) $row['ts'];
    $row['status'] = (int) $row['status'];
    $row['ms'] = (int) $row['ms'];
    $row['peak_mb'] = (float) $row['peak_mb'];
    return $row;
}

/**
 * Summarise rows: per script and per visitor, the numbers the monitor page shows.
 * Pure (takes any iterable of parsed rows) so it is testable and works on a stream.
 *
 * Visitor = the username when logged in, else the address — an anonymous visitor's address is
 * the only identity there is.
 */
function moop_metrics_summarise(iterable $rows): array {
    $by_script = [];
    $by_who = [];
    $total = 0;
    foreach ($rows as $r) {
        if (!$r) continue;
        $total++;
        $s = &$by_script[$r['script']];
        $s['count'] = ($s['count'] ?? 0) + 1;
        $s['total_ms'] = ($s['total_ms'] ?? 0) + $r['ms'];
        $s['max_ms'] = max($s['max_ms'] ?? 0, $r['ms']);
        $s['sum_mb'] = ($s['sum_mb'] ?? 0) + $r['peak_mb'];
        $s['max_mb'] = max($s['max_mb'] ?? 0, $r['peak_mb']);
        $s['errors'] = ($s['errors'] ?? 0) + ($r['status'] >= 500 ? 1 : 0);
        $s['times'][] = $r['ms'];
        unset($s);

        $who = $r['user'] !== '' ? $r['user'] : $r['ip'];
        $w = &$by_who[$who];
        $w['count'] = ($w['count'] ?? 0) + 1;
        $w['total_ms'] = ($w['total_ms'] ?? 0) + $r['ms'];
        $w['max_mb'] = max($w['max_mb'] ?? 0, $r['peak_mb']);
        $w['level'] = $r['level'];
        $w['ip'] = $r['ip'];
        unset($w);
    }
    foreach ($by_script as &$s) {
        sort($s['times']);
        $s['p95_ms'] = $s['times'][(int) floor(0.95 * (count($s['times']) - 1))];
        $s['mean_ms'] = (int) round($s['total_ms'] / $s['count']);
        $s['mean_mb'] = round($s['sum_mb'] / $s['count'], 1);
        unset($s['times'], $s['sum_mb']);
    }
    unset($s);
    uasort($by_script, fn($a, $b) => $b['total_ms'] <=> $a['total_ms']);
    uasort($by_who, fn($a, $b) => $b['total_ms'] <=> $a['total_ms']);
    return ['requests' => $total, 'by_script' => $by_script, 'by_who' => $by_who];
}

/** Stream the parsed rows of one day's file (no whole-file read: a busy day can be large). */
function moop_metrics_rows(string $date): \Generator {
    $file = moop_metrics_dir() . "/requests-$date.tsv";
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !is_readable($file)) return;
    $fh = fopen($file, 'r');
    if (!$fh) return;
    while (($line = fgets($fh)) !== false) {
        $row = moop_metrics_parse($line);
        if ($row) yield $row;
    }
    fclose($fh);
}

/** Housekeeping: delete request logs older than the retention. Returns how many went. */
function moop_metrics_prune(int $days = MOOP_METRICS_RETENTION_DAYS, ?int $now = null, ?string $dir = null): int {
    $dir = $dir ?? moop_metrics_dir();
    $cutoff = date('Y-m-d', ($now ?? time()) - $days * 86400);
    $deleted = 0;
    foreach (glob("$dir/requests-*.tsv") ?: [] as $file) {
        if (preg_match('/requests-(\d{4}-\d{2}-\d{2})\.tsv$/', $file, $m) && $m[1] < $cutoff && @unlink($file)) {
            $deleted++;
        }
    }
    return $deleted;
}
