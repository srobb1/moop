<?php
/**
 * BLAST queue: at most a memory budget's worth of BLAST runs at once; the rest wait in line.
 *
 * Why: the capacity test (notes/CAPACITY_TEST_2026-10-23.md) found that nothing limited
 * concurrent BLASTs, and a genome tblastn needs 1.2 GB (bat) to 2.9 GB (wallaby). Eight at
 * once ran the 15 GB server out of memory and took the site down.
 *
 * Built so that it CANNOT get stuck. There is no job table, no worker process and no
 * "running" flag to go stale -- the failure that plagues queue-table designs (a job marked
 * running forever after its worker died). Every piece of state is a flock() held by the PHP
 * request that owns it, and the kernel releases a flock the instant its process dies, however
 * it dies (OOM kill, crash, php-fpm restart). A leftover FILE means nothing; only a held LOCK
 * counts, and dead files are swept by whoever sees them.
 *
 *   {cache}/blast_queue/units/u00..   one file per memory unit (BLAST_QUEUE_UNIT_MB). A running
 *                                     search holds as many unit locks as it is estimated to need.
 *   {cache}/blast_queue/wait/<seq>-<ticket>   held by each waiting request; sorted by seq it is
 *                                     the line. Only the head of the line tries to start, so a
 *                                     big search cannot be starved by a stream of small ones.
 *   {cache}/blast_queue/run/<ticket>  held while a search runs; counted for the status line.
 *
 * Bounded both ways: a request waits at most blast_queue_max_wait seconds, then gives up with
 * a "busy, try again" message; a running BLAST is killed after blast_max_runtime seconds
 * (`timeout`, see blast_queue_wrap_command()), so a runaway cannot hold its units forever.
 *
 * Fails OPEN: if the queue directory cannot be used, the search runs unqueued and the reason
 * goes to the error log -- a broken queue must never stop BLAST.
 */

require_once __DIR__ . '/cache_paths.php';
require_once __DIR__ . '/functions_system.php';

const BLAST_QUEUE_UNIT_MB = 256;

/** The queue directory, or '' if it cannot be created. */
function blast_queue_dir(): string
{
    $dir = moop_cache_root() . '/blast_queue';
    foreach (['', '/units', '/wait', '/run'] as $sub) {
        if (!moop_ensure_cache_dir($dir . $sub)) return '';
    }
    return $dir;
}

/**
 * Settings, with defaults sized from this machine.
 *
 *   blast_memory_budget_mb  total BLAST may use at once. Default: 60% of RAM, leaving the
 *                           rest for php-fpm, the OS and the page cache that search relies on.
 *   blast_max_concurrent    searches running at once, whatever their size. More than
 *                           cores/threads only makes every search slower, not more of them
 *                           finish (measured: flat to 4 on 8 cores x 2 threads, then linear).
 *   blast_queue_max_wait    seconds a request waits for its turn before giving up.
 *   blast_max_runtime       seconds a single BLAST may run before it is killed.
 *
 * max_wait + max_runtime must stay under nginx's fastcgi_read_timeout for the BLAST pages,
 * or nginx gives the user a 504 while the search carries on without them.
 */
function blast_queue_settings(): array
{
    $config = ConfigManager::getInstance();

    $budget = $config->getInt('blast_memory_budget_mb', 0);
    if ($budget <= 0) {
        $mem_kb = 0;
        if (preg_match('/^MemTotal:\s+(\d+)/m', (string)@file_get_contents('/proc/meminfo'), $m)) {
            $mem_kb = (int)$m[1];
        }
        $budget = $mem_kb > 0 ? (int)($mem_kb / 1024 * 0.6) : 4096;
    }

    $threads = max(1, $config->getInt('blast_num_threads', 2));
    $cores   = (int)trim((string)@shell_exec('nproc 2>/dev/null')) ?: 4;

    return [
        'units'          => max(1, intdiv($budget, BLAST_QUEUE_UNIT_MB)),
        'max_concurrent' => max(1, $config->getInt('blast_max_concurrent', 0) ?: intdiv($cores, $threads) + 2),
        'max_wait'       => max(5, $config->getInt('blast_queue_max_wait', 120)),
        'max_runtime'    => max(10, $config->getInt('blast_max_runtime', 150)),
    ];
}

/**
 * Estimated peak memory of one search, in queue units.
 *
 * Measured on this host (2026-10-23), peak RSS against the database's sequence file:
 *   tblastn / tblastx   ~3.5x the .nsq  (bat genome 1.2 GB, wallaby 2.9 GB)
 *   blastn              ~1x the .nsq    (bat genome 0.5 GB)
 *   blastp / blastx     tiny            (protein sets are small; 0.04 GB)
 * Everything gets at least one unit, so even small searches count toward max_concurrent.
 */
function blast_queue_cost_units(string $program, string $db): int
{
    $seq_bytes = 0;
    foreach (['nsq', 'psq'] as $ext) {
        // A large database is split into volumes: db.00.nsq, db.01.nsq, ...
        foreach (array_merge(glob("$db.$ext") ?: [], glob("$db.[0-9][0-9].$ext") ?: []) as $f) {
            $seq_bytes += (int)@filesize($f);
        }
    }
    $factor = ['tblastn' => 3.5, 'tblastx' => 3.5, 'blastn' => 1.2, 'blastp' => 1.5, 'blastx' => 1.5][$program] ?? 2.0;
    $mb = $seq_bytes / 1048576 * $factor;
    return max(1, (int)ceil($mb / BLAST_QUEUE_UNIT_MB));
}

/** A ticket from the browser (lets the waiting page show its place in line), or a fresh one. */
function blast_queue_ticket(?string $from_client): string
{
    if ($from_client !== null && preg_match('/^[a-f0-9]{16,32}$/', $from_client)) return $from_client;
    return bin2hex(random_bytes(8));
}

/** Is someone holding a lock on $path? Takes and drops it if not. */
function blast_queue_file_is_held(string $path): bool
{
    // 'r', not 'c': opening must never re-create a file its owner just removed.
    $fh = @fopen($path, 'r');
    if (!$fh) return false;
    $free = flock($fh, LOCK_EX | LOCK_NB);
    if ($free) flock($fh, LOCK_UN);
    fclose($fh);
    return !$free;
}

/**
 * The live waiters, oldest first, as basenames. Dead entries (file present, nobody holding it)
 * are deleted on the way -- this sweep is the whole of the queue's self-repair.
 */
function blast_queue_waiters(string $dir): array
{
    $live = [];
    $names = @scandir("$dir/wait") ?: [];
    sort($names, SORT_STRING);
    foreach ($names as $name) {
        if ($name[0] === '.') continue;
        $path = "$dir/wait/$name";
        // A file younger than 2 s may belong to a request that has created it but not yet
        // locked it; leave it alone rather than delete a live waiter.
        if (blast_queue_file_is_held($path) || (time() - (int)@filemtime($path)) < 2) {
            $live[] = $name;
        } else {
            @unlink($path);
        }
    }
    return $live;
}

/** Number of searches running now (held run/ files). Dead run files are swept. */
function blast_queue_running(string $dir): int
{
    $n = 0;
    foreach (@scandir("$dir/run") ?: [] as $name) {
        if ($name[0] === '.') continue;
        $path = "$dir/run/$name";
        if (blast_queue_file_is_held($path)) $n++;
        elseif ((time() - (int)@filemtime($path)) >= 2) @unlink($path);
    }
    return $n;
}

/**
 * Where a ticket stands, for the waiting page: ['state' => 'waiting'|'running'|'unknown',
 * 'position' => 1-based place in line, 'ahead' => searches running].
 */
function blast_queue_status(string $ticket): array
{
    $dir = blast_queue_dir();
    if ($dir === '' || !preg_match('/^[a-f0-9]{16,32}$/', $ticket)) return ['state' => 'unknown'];

    if (is_file("$dir/run/$ticket") && blast_queue_file_is_held("$dir/run/$ticket")) {
        return ['state' => 'running', 'running' => blast_queue_running($dir)];
    }
    foreach (blast_queue_waiters($dir) as $i => $name) {
        if (substr($name, -strlen($ticket) - 1) === "-$ticket") {
            return ['state' => 'waiting', 'position' => $i + 1, 'running' => blast_queue_running($dir)];
        }
    }
    return ['state' => 'unknown'];
}

/**
 * Try to take $need unit locks at once. All or nothing: a partial set is released, so two
 * waiters can never each hold half of what the other needs.
 *
 * Unit files are opened READ-ONLY -- flock() needs no write access, so a file's mode or owner
 * can never make a free unit look taken. A unit that cannot be opened at all is not "busy",
 * it is broken, and is reported as such so the caller runs unqueued instead of waiting for a
 * unit that will never come (a waiter at the head of the line would otherwise block it).
 *
 * @return resource[]|null|false  the held handles; null if not enough are free; false if broken
 */
function blast_queue_take_units(string $dir, int $total, int $need)
{
    $held = [];
    $broken = false;
    for ($i = 0; $i < $total && count($held) < $need; $i++) {
        $path = sprintf('%s/units/u%03d', $dir, $i);
        if (!is_file($path) && ($c = @fopen($path, 'c'))) {
            fclose($c);
            @chmod($path, 0664);
        }
        $fh = @fopen($path, 'r');
        if (!$fh) { $broken = true; break; }
        if (flock($fh, LOCK_EX | LOCK_NB)) $held[] = $fh;
        else fclose($fh);
    }
    if (!$broken && count($held) >= $need) return $held;
    foreach ($held as $fh) { flock($fh, LOCK_UN); fclose($fh); }
    return $broken ? false : null;
}

/**
 * Run $job when there is room for it, and return its result.
 *
 * @param callable $job     the BLAST work; its return value is passed back
 * @param int      $units   estimated memory, from blast_queue_cost_units()
 * @param string   $ticket  from blast_queue_ticket()
 * @return array   ['ran' => true, 'result' => <job's return>, 'waited' => seconds]
 *                 or ['ran' => false, 'error' => message for the user]
 */
function blast_queue_run(callable $job, int $units, string $ticket): array
{
    $dir = blast_queue_dir();
    if ($dir === '') {
        error_log('MOOP: BLAST queue directory unusable under ' . moop_cache_root() . ' -- running UNQUEUED');
        return ['ran' => true, 'result' => $job(), 'waited' => 0];
    }

    $s     = blast_queue_settings();
    $units = min($units, $s['units']);   // bigger than the whole budget: it runs alone
    $start = microtime(true);

    // Join the line. The sequence prefix orders it; the ticket lets the status call find us.
    $wait_path = sprintf('%s/wait/%017.6f-%s', $dir, $start, $ticket);
    $wait_fh   = @fopen($wait_path, 'x');
    if (!$wait_fh || !flock($wait_fh, LOCK_EX)) {
        error_log("MOOP: BLAST queue could not create $wait_path -- running UNQUEUED");
        if ($wait_fh) fclose($wait_fh);
        return ['ran' => true, 'result' => $job(), 'waited' => 0];
    }
    $leave_line = function () use (&$wait_fh, $wait_path) {
        if ($wait_fh) { @unlink($wait_path); flock($wait_fh, LOCK_UN); fclose($wait_fh); $wait_fh = null; }
    };

    $held = null;
    while (true) {
        $line = blast_queue_waiters($dir);
        if (($line[0] ?? '') === basename($wait_path)
            && blast_queue_running($dir) < $s['max_concurrent']) {
            $held = blast_queue_take_units($dir, $s['units'], $units);
            if ($held === false) {
                error_log("MOOP: BLAST queue unit files under $dir/units cannot be opened -- running UNQUEUED");
                $leave_line();
                return ['ran' => true, 'result' => $job(), 'waited' => (int)round(microtime(true) - $start)];
            }
            if ($held !== null) break;
        }
        if (microtime(true) - $start > $s['max_wait']) {
            $leave_line();
            return ['ran' => false, 'error' =>
                'The server is busy with other BLAST searches and your search could not start within '
                . $s['max_wait'] . ' seconds. Nothing was run -- please try again in a minute or two.'];
        }
        usleep(400000);
    }

    // Our turn. Mark ourselves running BEFORE leaving the line, so a status call never sees
    // the ticket in neither place.
    $run_path = "$dir/run/$ticket";
    $run_fh   = @fopen($run_path, 'c');
    if ($run_fh) flock($run_fh, LOCK_EX);
    $leave_line();
    $waited = (int)round(microtime(true) - $start);

    try {
        $result = $job();
    } finally {
        if ($run_fh) { @unlink($run_path); flock($run_fh, LOCK_UN); fclose($run_fh); }
        foreach ($held as $fh) { flock($fh, LOCK_UN); fclose($fh); }
    }
    return ['ran' => true, 'result' => $result, 'waited' => $waited];
}

/**
 * Prefix a BLAST command line so it is killed after blast_max_runtime seconds, even if the
 * PHP request that started it is gone. TERM first, KILL 5 s later. Exit status 124/137
 * means it was stopped -- see blast_queue_timed_out().
 */
function blast_queue_wrap_command(string $cmd): string
{
    $timeout = moop_find_executable('timeout');
    if ($timeout === '') return $cmd;
    return escapeshellarg($timeout) . ' --kill-after=5 ' . blast_queue_settings()['max_runtime'] . ' ' . $cmd;
}

function blast_queue_timed_out(int $exit_code): bool
{
    return $exit_code === 124 || $exit_code === 137;
}

function blast_queue_timeout_message(): string
{
    return 'This search ran longer than the ' . blast_queue_settings()['max_runtime']
        . '-second limit and was stopped. Try a shorter query, a stricter E-value, or a smaller database.';
}
