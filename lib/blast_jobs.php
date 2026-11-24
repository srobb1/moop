<?php
/**
 * BLAST results saved on the server, and BLAST jobs run in the background (api/v1/blast.php).
 *
 * ONE place for a finished search's files. The BLAST page and the API both save the three
 * downloads here and both serve them through api/blast_download.php, so what an agent hands a
 * person is byte for byte what that person gets from the page (Sofia's rule, 2026-11-24).
 * Until then the page put the text in hidden <div>s and the browser posted it back to be
 * echoed: the TXT gained 29 leading characters of template indentation and all three came
 * back with Windows (CRLF) line endings, because a form submission converts line breaks --
 * a hidden "\r" on the TSV's last column.
 *
 *   {cache}/blast_jobs/<id>/            one folder per search; <id> = 32 random hex (the only key)
 *       request.json    API jobs: what to run (database path resolved and access-checked by
 *                       the web request; this folder is never served)
 *       worker.lock     held (flock) by the background worker for as long as it lives
 *       blast_results.{txt,tsv,xml}     the downloads, exactly as blast_formatter wrote them
 *       result.json     written LAST, atomically: state done|failed (+ message)
 *
 * Built like blast_queue.php so a job cannot get stuck: no "running" flag is ever stored.
 * A job is finished when result.json exists; alive while worker.lock is held; and if neither,
 * past a short start-up grace, the worker died -- the kernel dropped its lock -- and the job
 * reads "failed". Folders are swept after BLAST_JOB_KEEP_SECONDS by housekeeping.
 *
 * Anyone holding the id can fetch the files (that is what lets an agent hand a link to a
 * person). The id is 128 random bits, and the results only ever contain databases the
 * submitter could open.
 */

require_once __DIR__ . '/cache_paths.php';
require_once __DIR__ . '/functions_system.php';

const BLAST_JOB_KEEP_SECONDS  = 86400;   // files kept 24 h
const BLAST_JOB_START_GRACE   = 30;      // seconds a just-spawned worker has to take its lock

/** Download format => [file name in the job folder, MIME type, key in runBlastSearch()'s result]. */
function blast_job_formats(): array
{
    return [
        'txt' => ['blast_results.txt', 'text/plain', 'pairwise'],
        'tsv' => ['blast_results.tsv', 'text/tab-separated-values', 'tabular'],
        'xml' => ['blast_results.xml', 'application/xml', 'output'],
    ];
}

/** The jobs folder, or '' if it cannot be created. */
function blast_jobs_dir(): string
{
    $dir = moop_cache_root() . '/blast_jobs';
    return moop_ensure_cache_dir($dir) ? $dir : '';
}

function blast_job_valid_id(string $id): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
}

/** A job's folder (existing or not), or '' for a malformed id / unusable cache. */
function blast_job_dir(string $id): string
{
    if (!blast_job_valid_id($id)) return '';
    $root = blast_jobs_dir();
    return $root === '' ? '' : "$root/$id";
}

/** Make a new job folder; returns [id, dir] or ['', ''] if the cache cannot be written. */
function blast_job_create(array $meta): array
{
    $root = blast_jobs_dir();
    if ($root === '') return ['', ''];
    $id  = bin2hex(random_bytes(16));
    $dir = "$root/$id";
    if (!@mkdir($dir, 02770)) return ['', ''];
    $meta['created'] = time();
    if (!blast_job_write_json("$dir/meta.json", $meta)) return ['', ''];
    return [$id, $dir];
}

/** Write JSON via a temp file + rename, so a reader never sees half a file. */
function blast_job_write_json(string $path, array $data): bool
{
    $tmp = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false) return false;
    return @rename($tmp, $path);
}

function blast_job_read_json(string $path): array
{
    $raw = is_file($path) ? @file_get_contents($path) : false;
    $j = $raw !== false ? json_decode($raw, true) : null;
    return is_array($j) ? $j : [];
}

/**
 * Save a successful runBlastSearch() result's three downloads into $dir, then mark it done.
 * A format BLAST produced nothing for (the TSV, if its formatter run failed) is simply absent.
 * Returns false if any write failed -- callers must say so, never report success.
 */
function blast_job_save_result(string $dir, array $result, array $extra = []): bool
{
    foreach (blast_job_formats() as [$file, , $key]) {
        $text = (string) ($result[$key] ?? '');
        if ($text === '') continue;
        if (@file_put_contents("$dir/$file", $text) !== strlen($text)) return false;
    }
    return blast_job_write_json("$dir/result.json", ['state' => 'done', 'finished' => time()] + $extra);
}

/** Mark a job failed with a message a visitor may read (never BLAST's raw stderr). */
function blast_job_save_failure(string $dir, string $message): bool
{
    return blast_job_write_json("$dir/result.json", ['state' => 'failed', 'finished' => time(), 'error' => $message]);
}

/**
 * The BLAST page: save a finished search so its download buttons are plain links.
 * Returns the id, or '' (and logs why) -- the page then says downloads are unavailable.
 */
function blast_job_store_page_result(array $result, array $meta): string
{
    [$id, $dir] = blast_job_create($meta + ['source' => 'page']);
    if ($id === '' || !blast_job_save_result($dir, $result)) {
        error_log('MOOP: could not save BLAST result files under ' . moop_cache_root() . '/blast_jobs');
        return '';
    }
    return $id;
}

/** ['path','mime','filename'] for one of a finished job's downloads, or null. */
function blast_job_file(string $id, string $format): ?array
{
    $formats = blast_job_formats();
    $dir = blast_job_dir($id);
    if ($dir === '' || !isset($formats[$format])) return null;
    if ((blast_job_read_json("$dir/result.json")['state'] ?? '') !== 'done') return null;
    $path = "$dir/{$formats[$format][0]}";
    if (!is_file($path)) return null;
    $date = date('Y-m-d', (int) (blast_job_read_json("$dir/meta.json")['created'] ?? filemtime($path)));
    return ['path' => $path, 'mime' => $formats[$format][1], 'filename' => "blast_results_$date.$format"];
}

/** Site-relative download link (the page); the API makes it absolute. */
function blast_job_download_url(string $id, string $format): string
{
    return moop_url('api/blast_download.php') . '?job=' . rawurlencode($id) . '&format=' . rawurlencode($format);
}

/** Formats this job has files for, in display order. */
function blast_job_available_formats(string $id): array
{
    return array_values(array_filter(array_keys(blast_job_formats()), fn($f) => blast_job_file($id, $f) !== null));
}

function blast_job_lock_held(string $path): bool
{
    if (!is_file($path)) return false;
    $fh = @fopen($path, 'r');
    if (!$fh) return false;
    $free = flock($fh, LOCK_EX | LOCK_NB);
    if ($free) flock($fh, LOCK_UN);
    fclose($fh);
    return !$free;
}

/**
 * Where a job stands: state = done | failed | waiting | running | starting | unknown.
 * waiting adds 'position' (1 = next to start). Never "running" without a live lock.
 */
function blast_job_state(string $id): array
{
    $dir = blast_job_dir($id);
    if ($dir === '' || !is_dir($dir)) return ['state' => 'unknown'];
    $result = blast_job_read_json("$dir/result.json");
    if ($result) return $result;

    if (blast_job_lock_held("$dir/worker.lock")) {
        require_once __DIR__ . '/blast_queue.php';
        $q = blast_queue_status($id);   // the job id is its queue ticket
        if ($q['state'] === 'waiting') return ['state' => 'waiting', 'position' => $q['position']];
        return ['state' => 'running'];   // running, or between queue and formatter
    }
    $created = (int) (blast_job_read_json("$dir/meta.json")['created'] ?? 0);
    if (time() - $created <= BLAST_JOB_START_GRACE) return ['state' => 'starting'];
    return ['state' => 'failed', 'error' => 'The search stopped before it finished (the server process ended). Nothing was saved -- please submit it again.'];
}

/** Background jobs of one client that are not finished (for the one-at-a-time limit). */
function blast_jobs_active_for(string $client): array
{
    $root = blast_jobs_dir();
    if ($root === '' || $client === '') return [];
    $active = [];
    foreach (glob("$root/*/request.json") ?: [] as $req) {
        $dir = dirname($req);
        if (time() - filemtime($req) > 3600 || is_file("$dir/result.json")) continue;
        if ((blast_job_read_json("$dir/meta.json")['client'] ?? '') !== $client) continue;
        $state = blast_job_state(basename($dir))['state'];
        if (in_array($state, ['starting', 'waiting', 'running'], true)) $active[] = basename($dir);
    }
    return $active;
}

/** Start the background worker for a job. False if it could not be launched. */
function blast_job_spawn(string $id): bool
{
    $script = dirname(__DIR__) . '/scripts/blast_job.php';
    $php = PHP_BINARY ?: 'php';
    if (str_contains($php, 'php-fpm') || !is_executable($php)) $php = moop_find_executable('php') ?: 'php';
    // Detached (&) so this request returns at once; the worker outlives it.
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($id) . ' >/dev/null 2>&1 &';
    $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
    $proc = @proc_open(['/bin/sh', '-c', $cmd], $descriptors, $pipes);
    if (!is_resource($proc)) return false;
    proc_close($proc);   // returns as soon as sh has put the worker in the background
    return true;
}

/** Delete job folders older than BLAST_JOB_KEEP_SECONDS (never one whose worker is alive). */
function blast_jobs_sweep(): int
{
    $root = blast_jobs_dir();
    if ($root === '') return 0;
    $deleted = 0;
    foreach (glob("$root/*", GLOB_ONLYDIR) ?: [] as $dir) {
        if (!blast_job_valid_id(basename($dir))) continue;
        if (time() - filemtime($dir) <= BLAST_JOB_KEEP_SECONDS) continue;
        if (blast_job_lock_held("$dir/worker.lock")) continue;
        foreach (glob("$dir/{,.}*", GLOB_BRACE) ?: [] as $f) if (is_file($f)) @unlink($f);
        if (@rmdir($dir)) $deleted++;
    }
    return $deleted;
}
