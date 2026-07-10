<?php
/**
 * Background organism cache refresh endpoint
 *
 * POST: Launches warm_organism_cache.php as a background CLI process and
 *       returns immediately. Creates a lock file so the UI can show progress.
 * GET:  Returns current status — running/idle, cache age, organism count.
 */

include_once __DIR__ . '/../admin_init.php';

header('Content-Type: application/json');

$organism_data  = $config->getPath('organism_data');
$cache_file     = "$organism_data/.organism_cache.json";
$lock_file      = "$organism_data/.organism_cache_lock";
$progress_file  = "$organism_data/.organism_cache_progress.json";
$script_path    = realpath(dirname(dirname(__DIR__)) . '/scripts/warm_organism_cache.php');

// --- helpers -----------------------------------------------------------------

function read_cache_meta($cache_file) {
    if (!file_exists($cache_file)) return ['generated' => null, 'organism_count' => 0];
    $raw = loadJsonFile($cache_file, []);
    return [
        'generated'      => $raw['generated'] ?? null,
        'organism_count' => count($raw['data'] ?? []),
    ];
}

function lock_is_active($lock_file, $progress_file) {
    if (!file_exists($lock_file)) return false;
    $pid = (int)trim(file_get_contents($lock_file));
    // Check if the process that created the lock is still running.
    // This works regardless of how long the scan takes — no arbitrary timeout.
    if ($pid > 0 && file_exists("/proc/$pid")) {
        return true;
    }
    // Process is gone — clean up stale lock and any leftover progress file.
    @unlink($lock_file);
    @unlink($progress_file);
    return false;
}

// --- GET: status -------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $is_running = lock_is_active($lock_file, $progress_file);
    $meta = read_cache_meta($cache_file);
    $progress = null;
    if ($is_running && file_exists($progress_file)) {
        $progress = loadJsonFile($progress_file, null);
    }
    echo json_encode([
        'status'         => $is_running ? 'running' : 'idle',
        'generated'      => $meta['generated'],
        'organism_count' => $meta['organism_count'],
        'progress'       => $progress,
    ]);
    exit;
}

// --- POST: start refresh -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (lock_is_active($lock_file, $progress_file)) {
    echo json_encode(['status' => 'already_running']);
    exit;
}

if (!$script_path || !file_exists($script_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'warm_organism_cache.php not found']);
    exit;
}

// Write lock file then launch background process via proc_open so it truly
// detaches from the web-server request (exec() + & blocks on some setups).
// The lock file stores the PID of the PHP child process so lock_is_active()
// can check whether the process is still alive rather than using a time limit.
$force = !empty($_POST['force']) && $_POST['force'] === '1';

// Optional single-organism rescan: only rescan the named organism.
$organism = isset($_POST['organism']) ? trim($_POST['organism']) : '';
if ($organism && !preg_match('/^[a-zA-Z0-9_.\-]+$/', $organism)) {
    $organism = '';  // reject anything that looks odd
}
if ($organism) {
    // Verify the organism directory actually exists before launching a background job.
    if (!is_dir("$organism_data/$organism")) {
        http_response_code(400);
        echo json_encode(['error' => "Organism not found: $organism"]);
        exit;
    }
}

// Write a placeholder lock so a second click can't race past the check above
// before the child process writes its real PID.
file_put_contents($lock_file, '0');

$shell_cmd = 'echo $$ > ' . escapeshellarg($lock_file) . ' ; '
           . 'php ' . escapeshellarg($script_path)
           . ($force && !$organism ? ' --force' : '')   // --force only for all-organism scans
           . ($organism ? ' --organism=' . escapeshellarg($organism) : '')
           . ' > /dev/null 2>&1 ; rm -f ' . escapeshellarg($lock_file) . ' ' . escapeshellarg($progress_file);

$descriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['file', '/dev/null', 'w'],
    2 => ['file', '/dev/null', 'w'],
];
$proc = proc_open(['/bin/sh', '-c', $shell_cmd], $descriptors, $pipes);
if (!is_resource($proc)) {
    @unlink($lock_file);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to start background process']);
    exit;
}
// Intentionally NOT calling proc_close() — the child runs independently.

echo json_encode(['status' => 'started']);
