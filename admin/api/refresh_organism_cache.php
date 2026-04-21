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
$script_path    = realpath(dirname(dirname(__DIR__)) . '/scripts/warm_organism_cache.php');

// --- helpers -----------------------------------------------------------------

function read_cache_meta($cache_file) {
    if (!file_exists($cache_file)) return ['generated' => null, 'organism_count' => 0];
    $raw = json_decode(file_get_contents($cache_file), true);
    return [
        'generated'      => $raw['generated'] ?? null,
        'organism_count' => count($raw['data'] ?? []),
    ];
}

function lock_is_active($lock_file, $cache_file) {
    if (!file_exists($lock_file)) return false;
    $lock_mtime  = filemtime($lock_file);
    $cache_mtime = file_exists($cache_file) ? filemtime($cache_file) : 0;
    // Lock is stale if it is older than 10 minutes or the cache is newer (scan finished)
    if (time() - $lock_mtime > 600) {
        @unlink($lock_file);
        return false;
    }
    if ($cache_mtime > $lock_mtime) {
        @unlink($lock_file);
        return false;
    }
    return true;
}

// --- GET: status -------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $meta = read_cache_meta($cache_file);
    echo json_encode([
        'status'         => lock_is_active($lock_file, $cache_file) ? 'running' : 'idle',
        'generated'      => $meta['generated'],
        'organism_count' => $meta['organism_count'],
    ]);
    exit;
}

// --- POST: start refresh -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (lock_is_active($lock_file, $cache_file)) {
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
file_put_contents($lock_file, time());

$shell_cmd = 'php ' . escapeshellarg($script_path)
           . ' > /dev/null 2>&1 ; rm -f ' . escapeshellarg($lock_file);

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
