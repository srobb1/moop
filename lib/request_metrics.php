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
// org/asm/gs (added 2026-10-28): which organism / assembly / gene set the request was about, for
// "most viewed / searched / downloaded". The usage tables never record search WORDS — only which
// organisms a search covered. (Slow or failed requests do keep them, in params: see below.)
// Lines written before then have 10 columns; moop_metrics_parse() accepts both.
//
// db_ms/db_n/disk_kb/params (added 2026-10-28): SQLite time and statement count (lib/db_timer.php);
// bytes this request actually read from DISK (/proc/self/io read_bytes — reads served from the RAM
// cache do not count, so 0 = warm, megabytes = cold); and, for SLOW or FAILED requests only, the
// request's parameters from an allowlist, so the request can be opened again exactly as it ran.
const MOOP_METRICS_COLUMNS = ['ts', 'script', 'method', 'status', 'ms', 'peak_mb', 'user', 'level', 'ip', 'agent', 'org', 'asm', 'gs',
                              'db_ms', 'db_n', 'disk_kb', 'params'];
const MOOP_METRICS_COLUMNS_V1 = 10;   // before org/asm/gs
const MOOP_METRICS_COLUMNS_V2 = 13;   // before db/disk/params

const MOOP_METRICS_SLOW_MS = 2000;    // slower than this, or a 5xx: parameters recorded
const MOOP_METRICS_COLD_KB = 64;      // read more than this from disk = a cold request
// Safety cap: the log lives on /var (4 GB here). Past either limit, ordinary fast requests are
// only COUNTED (skipped-YYYY-MM-DD.cnt, one byte each), never written; slow and failed ones still
// are. A crawler storm then cannot fill the disk nginx and the OS log to.
const MOOP_METRICS_DAILY_CAP_MB = 200;
const MOOP_METRICS_MIN_FREE_PCT = 15;

/**
 * Parameters worth keeping to reproduce a slow request — an ALLOWLIST, never "everything but".
 * Search words ARE kept here (search_keywords, quoted, and the gene-ID lookup's q): Sofia,
 * 2026-10-28 — for a slow or failed search they are what is needed to see what went on. Only
 * for those requests, admin-only, 30 days; the usage tables still never record them.
 * Deliberately absent: BLAST sequences (query — can be unpublished data, and large), pasted
 * gene-ID lists (uniquenames), tokens (JBrowse puts its JWT in the URL), anything password-like.
 */
const MOOP_METRICS_PARAM_ALLOW = ['organism', 'organisms', 'assembly', 'gene_set', 'uniquename', 'transcript', 'loc',
                                  'sequence_type', 'group', 'range', 'feature_type', 'source_names',
                                  'search_keywords', 'quoted', 'scope', 'no_annotations', 'q'];

function moop_metrics_dir(): string {
    require_once __DIR__ . '/cache_paths.php';   // functions only; access_control.php does not load it
    return moop_cache_root() . '/metrics';
}

/** Run once per request, from access_control.php. */
function moop_metrics_register(): void {
    static $done = false;
    if ($done || PHP_SAPI === 'cli') return;
    $done = true;
    moop_metrics_disk_read_bytes(true);    // baseline for this request
    register_shutdown_function('moop_metrics_record');
}

/**
 * Bytes this process has read from storage — NOT from the RAM page cache. With $reset, remember the
 * current value as the request's starting point; otherwise return the bytes read since then.
 *
 * getrusage()'s ru_inblock (512-byte blocks the kernel had to read from the device), not
 * /proc/self/io: php-fpm workers switch from root to the web user at start, which makes a
 * process "non-dumpable", and a non-dumpable process cannot read its own /proc/self/io — the
 * first version recorded nothing. Measured 2026-10-28: a just-evicted database read 16 MB in
 * 20 ms; the same query again read 0 KB in 3.9 ms.
 */
function moop_metrics_disk_read_bytes(bool $reset = false): ?int {
    static $start = null;
    $ru = function_exists('getrusage') ? getrusage() : [];
    if (!isset($ru['ru_inblock'])) return null;
    $bytes = (int) $ru['ru_inblock'] * 512;
    if ($reset) { $start = $bytes; return 0; }
    return $start === null ? null : max(0, $bytes - $start);
}

/** The allowlisted parameters of this request, as a query string ('' when none). */
function moop_metrics_params(array $params): string {
    $keep = [];
    foreach (MOOP_METRICS_PARAM_ALLOW as $k) {
        if (!isset($params[$k])) continue;
        $v = is_array($params[$k]) ? array_slice(array_filter($params[$k], 'is_scalar'), 0, 20) : $params[$k];
        if (is_scalar($v) && strlen((string) $v) > 300) continue;
        $keep[$k] = $v;
    }
    $q = http_build_query($keep);
    return strlen($q) > 1000 ? substr($q, 0, 1000) : $q;
}

function moop_metrics_record(): void {
    $dir = moop_metrics_dir();
    if ($dir === '' || !moop_ensure_cache_dir($dir)) return;

    $start  = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    $status = http_response_code();
    $ms     = (int) round((microtime(true) - $start) * 1000);
    $notable = $ms >= MOOP_METRICS_SLOW_MS || (int) $status >= 500;
    $date   = date('Y-m-d', (int) $start);
    $file   = $dir . "/requests-$date.tsv";

    // Safety cap (see MOOP_METRICS_DAILY_CAP_MB): count, do not write, ordinary requests.
    if (!$notable) {
        clearstatcache(true, $file);
        $total = @disk_total_space($dir);
        $free  = @disk_free_space($dir);
        if ((is_file($file) && filesize($file) > MOOP_METRICS_DAILY_CAP_MB * 1048576)
            || ($total && $free !== false && 100 * $free / $total < MOOP_METRICS_MIN_FREE_PCT)) {
            @file_put_contents($dir . "/skipped-$date.cnt", '.', FILE_APPEND);
            return;
        }
    }

    $disk = moop_metrics_disk_read_bytes();
    $line = moop_metrics_line([
        'ts'      => sprintf('%.3f', $start),
        'script'  => moop_metrics_script_name($_SERVER['SCRIPT_FILENAME'] ?? ''),
        'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
        'status'  => $status === false ? '' : (string) $status,
        'ms'      => (string) $ms,
        'peak_mb' => sprintf('%.1f', memory_get_peak_usage(true) / 1048576),
        'user'    => (string) ($_SESSION['username'] ?? ''),
        'level'   => (string) ($_SESSION['access_level'] ?? 'PUBLIC'),
        'ip'      => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'agent'   => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160),
        'db_ms'   => class_exists('MoopDbTimer', false) ? (string) (int) round(MoopDbTimer::$ms) : '',
        'db_n'    => class_exists('MoopDbTimer', false) ? (string) MoopDbTimer::$n : '',
        'disk_kb' => $disk === null ? '' : (string) (int) round($disk / 1024),
        'params'  => $notable ? moop_metrics_params($_GET + $_POST) : '',
    ] + moop_metrics_context($_GET + $_POST));
    @file_put_contents($file, $line, FILE_APPEND);
}

/**
 * The organism / assembly / gene set a request names, from its parameters (organism, assembly,
 * gene_set; or organisms[] on multi-organism pages, up to 10, comma-joined). Only identifier-shaped
 * values are kept — anything else (a mistyped or hostile parameter) is recorded as ''.
 */
function moop_metrics_context(array $params): array {
    $clean = function ($v): string {
        if (is_array($v)) $v = implode(',', array_slice(array_filter($v, 'is_string'), 0, 10));
        $v = (string) $v;
        return preg_match('/^[A-Za-z0-9_.,-]{1,300}$/', $v) ? $v : '';
    };
    return [
        'org' => $clean($params['organism'] ?? ($params['organisms'] ?? '')),
        'asm' => $clean($params['assembly'] ?? ''),
        'gs'  => $clean($params['gene_set'] ?? ''),
    ];
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
    if (count($f) === MOOP_METRICS_COLUMNS_V1 || count($f) === MOOP_METRICS_COLUMNS_V2) {
        $f = array_merge($f, array_fill(0, count(MOOP_METRICS_COLUMNS) - count($f), ''));
    }
    if (count($f) !== count(MOOP_METRICS_COLUMNS)) return null;
    $row = array_combine(MOOP_METRICS_COLUMNS, $f);
    $row['ts'] = (float) $row['ts'];
    $row['status'] = (int) $row['status'];
    $row['ms'] = (int) $row['ms'];
    $row['peak_mb'] = (float) $row['peak_mb'];
    $row['db_ms'] = (int) $row['db_ms'];
    $row['db_n'] = (int) $row['db_n'];
    $row['disk_kb'] = $row['disk_kb'] === '' ? null : (int) $row['disk_kb'];   // null = not measured
    return $row;
}

/**
 * Summarise rows: per script and per visitor, the numbers the monitor page shows.
 * Pure (takes any iterable of parsed rows) so it is testable and works on a stream.
 *
 * Visitor = the username when logged in, else the address — an anonymous visitor's address is
 * the only identity there is.
 */
function moop_metrics_summarise(iterable $rows, int $slowest_n = 0): array {
    $by_script = [];
    $by_who = [];
    $slowest = [];
    $peaks = [];
    $by_org = [];
    $io = ['measured' => 0, 'cold' => 0, 'disk_kb' => 0];
    $total = 0;
    foreach ($rows as $r) {
        if (!$r) continue;
        $total++;
        $peaks[] = $r['peak_mb'];
        if ($slowest_n > 0) {
            // Keep the N slowest without holding every row: trim back to N whenever it doubles.
            $slowest[] = $r;
            if (count($slowest) >= 2 * $slowest_n) {
                usort($slowest, fn($a, $b) => $b['ms'] <=> $a['ms']);
                $slowest = array_slice($slowest, 0, $slowest_n);
            }
        }
        $s = &$by_script[$r['script']];
        $s['count'] = ($s['count'] ?? 0) + 1;
        $s['total_ms'] = ($s['total_ms'] ?? 0) + $r['ms'];
        $s['max_ms'] = max($s['max_ms'] ?? 0, $r['ms']);
        $s['sum_mb'] = ($s['sum_mb'] ?? 0) + $r['peak_mb'];
        $s['max_mb'] = max($s['max_mb'] ?? 0, $r['peak_mb']);
        $s['errors'] = ($s['errors'] ?? 0) + ($r['status'] >= 500 ? 1 : 0);
        $s['times'][] = $r['ms'];
        $s['db_ms'] = ($s['db_ms'] ?? 0) + ($r['db_ms'] ?? 0);
        $s['db_n'] = ($s['db_n'] ?? 0) + ($r['db_n'] ?? 0);
        $cold = ($r['disk_kb'] ?? null) !== null && $r['disk_kb'] > MOOP_METRICS_COLD_KB;
        if (($r['disk_kb'] ?? null) !== null) {
            $s['io_n'] = ($s['io_n'] ?? 0) + 1;
            $s['cold'] = ($s['cold'] ?? 0) + ($cold ? 1 : 0);
            $s['disk_kb'] = ($s['disk_kb'] ?? 0) + $r['disk_kb'];
            $io['measured']++;
            $io['cold'] += $cold ? 1 : 0;
            $io['disk_kb'] += $r['disk_kb'];
            // Per organism: how much had to come from disk — the working set the RAM cache
            // would need to hold for these pages to be warm.
            if (($r['org'] ?? '') !== '' && strpos($r['org'], ',') === false) {
                $o = &$by_org[$r['org']];
                $o['requests'] = ($o['requests'] ?? 0) + 1;
                $o['cold'] = ($o['cold'] ?? 0) + ($cold ? 1 : 0);
                $o['disk_kb'] = ($o['disk_kb'] ?? 0) + $r['disk_kb'];
                unset($o);
            }
        }
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
    usort($slowest, fn($a, $b) => $b['ms'] <=> $a['ms']);
    sort($peaks);
    // Memory per request across ALL pages: what one PHP worker typically needs at its busiest.
    // This, times the worker count, is what has to fit in RAM.
    $peak_p95 = $peaks ? $peaks[(int) floor(0.95 * (count($peaks) - 1))] : 0;
    uasort($by_org, fn($a, $b) => $b['disk_kb'] <=> $a['disk_kb']);
    return ['requests' => $total, 'by_script' => $by_script, 'by_who' => $by_who,
            'slowest' => array_slice($slowest, 0, $slowest_n),
            'peak_p95_mb' => $peak_p95, 'peak_max_mb' => $peaks ? end($peaks) : 0,
            'io' => $io, 'by_org_disk' => $by_org];
}

/**
 * Rows from the last $seconds, across however many daily files that spans (newest last).
 * Streams: never holds a whole file.
 */
function moop_metrics_rows_since(int $seconds, ?int $now = null): \Generator {
    $now = $now ?? time();
    $from = $now - $seconds;
    for ($d = strtotime(date('Y-m-d', $from)); $d <= $now; $d = strtotime('+1 day', $d)) {   // not +86400: DST days
        foreach (moop_metrics_rows(date('Y-m-d', $d)) as $row) {
            if ($row['ts'] >= $from) yield $row;
        }
    }
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
    foreach (array_merge(glob("$dir/requests-*.tsv") ?: [], glob("$dir/daily-*.json") ?: [], glob("$dir/skipped-*.cnt") ?: []) as $file) {
        if (preg_match('/(?:requests|daily|skipped)-(\d{4}-\d{2}-\d{2})\.(?:tsv|json|cnt)$/', $file, $m) && $m[1] < $cutoff && @unlink($file)) {
            $deleted++;
        }
    }
    return $deleted;
}

/**
 * What a request did, for usage counts: 'view' (organism / assembly / gene set / gene pages,
 * genome browser), 'search' (one annotation-search request per organism searched), 'blast' (a
 * BLAST run, i.e. POST), 'download' (files and sequences). Anything else: null (not counted).
 */
function moop_metrics_usage_kind(string $script, string $method): ?string {
    static $map = [
        'tools/organism.php' => 'view', 'tools/assembly.php' => 'view', 'tools/gene_set.php' => 'view',
        'tools/parent.php' => 'view', 'jbrowse2.php' => 'view', 'tools/multi_organism.php' => 'view',
        'tools/annotation_search_ajax.php' => 'search',
        'api/download_file.php' => 'download', 'api/get_sequence.php' => 'download',
        'tools/retrieve_sequences.php' => 'download', 'tools/retrieve_selected_sequences.php' => 'download',
    ];
    if ($script === 'tools/blast.php') return $method === 'POST' ? 'blast' : null;
    return $map[$script] ?? null;
}

/**
 * Most viewed / searched / BLASTed / downloaded organisms, assemblies and gene sets.
 * Internal = logged in (which includes the automatic login for internal/VPN addresses);
 * public = everyone else. Requests AND distinct visitors, so one busy visitor or crawler
 * cannot make an organism look popular.
 *
 * Returns [kind][level][name] => [in, out, in_v, out_v]; level is organism | assembly
 * ("Org/asm") | gene_set ("Org/asm/gs").
 */
function moop_metrics_usage(iterable $rows): array {
    $u = [];
    $seen = [];
    foreach ($rows as $r) {
        if (!$r || $r['org'] === '' || $r['status'] >= 400) continue;
        $kind = moop_metrics_usage_kind($r['script'], $r['method']);
        if ($kind === null) continue;
        $side = $r['level'] !== 'PUBLIC' ? 'in' : 'out';
        $who = $r['user'] !== '' ? $r['user'] : $r['ip'];
        $keys = [];
        $orgs = explode(',', $r['org']);
        foreach ($orgs as $o) $keys[] = ['organism', $o];
        if (count($orgs) === 1 && $r['asm'] !== '') {
            $keys[] = ['assembly', $r['org'] . '/' . $r['asm']];
            if ($r['gs'] !== '') $keys[] = ['gene_set', $r['org'] . '/' . $r['asm'] . '/' . $r['gs']];
        }
        foreach ($keys as [$level, $name]) {
            $c = &$u[$kind][$level][$name];
            $c = $c ?? ['in' => 0, 'out' => 0, 'in_v' => 0, 'out_v' => 0];
            $c[$side]++;
            if (!isset($seen[$kind][$level][$name][$side][$who])) {
                $seen[$kind][$level][$name][$side][$who] = true;
                $c[$side . '_v']++;
            }
            unset($c);
        }
    }
    return $u;
}

/**
 * Combine per-day usage (each from moop_metrics_usage on one day). Requests add up exactly;
 * visitors become VISITOR-DAYS (someone who came on 3 days counts 3) — distinct visitors across
 * days cannot be recovered from daily counts, and the page says which it shows.
 */
function moop_metrics_usage_merge(array $days): array {
    $u = [];
    foreach ($days as $d) {
        foreach ($d as $kind => $levels) foreach ($levels as $level => $names) foreach ($names as $name => $c) {
            $t = &$u[$kind][$level][$name];
            $t = $t ?? ['in' => 0, 'out' => 0, 'in_v' => 0, 'out_v' => 0];
            foreach ($t as $k => $_) $t[$k] += (int) ($c[$k] ?? 0);
            unset($t);
        }
    }
    return $u;
}

/**
 * One finished day, summarised, for the 30-day view: {cache}/metrics/daily-YYYY-MM-DD.json.
 * Built by housekeeping once the day is over, so the page adds up 30 small files instead of
 * re-reading 30 days of request lines. Rebuilt if the day's log is newer than its summary.
 * Same 30-day retention as the logs (it names users and addresses).
 */
function moop_metrics_build_daily(?int $now = null, ?string $dir = null): int {
    $dir = $dir ?? moop_metrics_dir();
    $today = date('Y-m-d', $now ?? time());
    $built = 0;
    foreach (glob("$dir/requests-*.tsv") ?: [] as $log) {
        if (!preg_match('/requests-(\d{4}-\d{2}-\d{2})\.tsv$/', $log, $m) || $m[1] >= $today) continue;
        $out = "$dir/daily-{$m[1]}.json";
        if (is_file($out) && filemtime($out) >= filemtime($log)) continue;
        $rows = function () use ($log) {
            $fh = fopen($log, 'r');
            while ($fh && ($line = fgets($fh)) !== false) {
                if ($row = moop_metrics_parse($line)) yield $row;
            }
            if ($fh) fclose($fh);
        };
        $summary = moop_metrics_summarise($rows(), 20);
        $summary['usage'] = moop_metrics_usage($rows());
        $summary['date'] = $m[1];
        $summary['skipped'] = moop_metrics_skipped($m[1], $dir);
        $tmp = tempnam($dir, '.daily.');
        if ($tmp && file_put_contents($tmp, json_encode($summary)) !== false && rename($tmp, $out)) {
            @chmod($out, 0644);
            $built++;
        } elseif ($tmp) {
            @unlink($tmp);
        }
    }
    return $built;
}

/**
 * Add up summaries (daily files, plus today's live one) into the same shape
 * moop_metrics_summarise() returns. Counts, totals and maxima are exact; "slowest 5%" becomes the
 * worst day's figure, and the overall memory percentile likewise — percentiles do not add.
 */
function moop_metrics_merge(array $summaries): array {
    $m = ['requests' => 0, 'by_script' => [], 'by_who' => [], 'slowest' => [], 'peak_p95_mb' => 0, 'peak_max_mb' => 0,
          'io' => ['measured' => 0, 'cold' => 0, 'disk_kb' => 0], 'by_org_disk' => [], 'skipped' => 0];
    foreach ($summaries as $s) {
        $m['requests'] += (int) ($s['requests'] ?? 0);
        foreach (($s['by_script'] ?? []) as $k => $v) {
            $t = &$m['by_script'][$k];
            $t = $t ?? ['count' => 0, 'total_ms' => 0, 'max_ms' => 0, 'p95_ms' => 0, 'mb_x_count' => 0, 'max_mb' => 0, 'errors' => 0];
            $t['count'] += $v['count'];
            $t['total_ms'] += $v['total_ms'];
            $t['max_ms'] = max($t['max_ms'], $v['max_ms']);
            $t['p95_ms'] = max($t['p95_ms'], $v['p95_ms']);
            $t['mb_x_count'] += $v['mean_mb'] * $v['count'];
            $t['max_mb'] = max($t['max_mb'], $v['max_mb']);
            $t['errors'] += $v['errors'];
            foreach (['db_ms', 'db_n', 'io_n', 'cold', 'disk_kb'] as $f) $t[$f] = ($t[$f] ?? 0) + ($v[$f] ?? 0);
            unset($t);
        }
        foreach (['measured', 'cold', 'disk_kb'] as $f) $m['io'][$f] += (int) ($s['io'][$f] ?? 0);
        foreach (($s['by_org_disk'] ?? []) as $k => $v) {
            $t = &$m['by_org_disk'][$k];
            foreach (['requests', 'cold', 'disk_kb'] as $f) $t[$f] = ($t[$f] ?? 0) + ($v[$f] ?? 0);
            unset($t);
        }
        $m['skipped'] += (int) ($s['skipped'] ?? 0);
        foreach (($s['by_who'] ?? []) as $k => $v) {
            $t = &$m['by_who'][$k];
            $t = $t ?? ['count' => 0, 'total_ms' => 0, 'max_mb' => 0, 'level' => $v['level'], 'ip' => $v['ip']];
            $t['count'] += $v['count'];
            $t['total_ms'] += $v['total_ms'];
            $t['max_mb'] = max($t['max_mb'], $v['max_mb']);
            unset($t);
        }
        $m['slowest'] = array_merge($m['slowest'], $s['slowest'] ?? []);
        $m['peak_p95_mb'] = max($m['peak_p95_mb'], $s['peak_p95_mb'] ?? 0);
        $m['peak_max_mb'] = max($m['peak_max_mb'], $s['peak_max_mb'] ?? 0);
    }
    foreach ($m['by_script'] as &$t) {
        $t['mean_ms'] = (int) round($t['total_ms'] / max(1, $t['count']));
        $t['mean_mb'] = round($t['mb_x_count'] / max(1, $t['count']), 1);
        unset($t['mb_x_count']);
    }
    unset($t);
    uasort($m['by_script'], fn($a, $b) => $b['total_ms'] <=> $a['total_ms']);
    uasort($m['by_who'], fn($a, $b) => $b['total_ms'] <=> $a['total_ms']);
    uasort($m['by_org_disk'], fn($a, $b) => $b['disk_kb'] <=> $a['disk_kb']);
    usort($m['slowest'], fn($a, $b) => $b['ms'] <=> $a['ms']);
    $m['slowest'] = array_slice($m['slowest'], 0, 20);
    return $m;
}

/** Requests the safety cap counted but did not log on $date (0 = none, the day is complete). */
function moop_metrics_skipped(string $date, ?string $dir = null): int {
    $f = ($dir ?? moop_metrics_dir()) . "/skipped-$date.cnt";
    clearstatcache(true, $f);
    return is_file($f) ? (int) filesize($f) : 0;
}

/** The daily summaries for the last $days finished days (oldest first), as decoded arrays. */
function moop_metrics_daily(int $days, ?int $now = null): array {
    $out = [];
    $now = $now ?? time();
    for ($i = $days; $i >= 1; $i--) {
        $date = date('Y-m-d', strtotime("-$i day", $now));
        $file = moop_metrics_dir() . "/daily-$date.json";
        if (is_readable($file) && is_array($d = json_decode((string) file_get_contents($file), true))) $out[] = $d;
    }
    return $out;
}
