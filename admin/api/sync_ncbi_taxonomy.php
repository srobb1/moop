<?php
/**
 * NCBI taxonomy dump sync endpoint
 *
 * POST: Launch sync_ncbi_taxonomy_dump.php as a background process.
 *       Returns immediately; UI polls for completion.
 * GET:  Return running/idle status + lineage cache age.
 */

include_once __DIR__ . '/../admin_init.php';

header('Content-Type: application/json');

$metadata_path = $config->getPath('metadata_path');
$lock_file     = "$metadata_path/.ncbi_taxonomy_sync_lock";
$script_path   = realpath(dirname(dirname(__DIR__)) . '/scripts/sync_ncbi_taxonomy_dump.php');

function ncbi_lock_is_active($lock_file) {
    if (!file_exists($lock_file)) return false;
    $pid = (int)trim(file_get_contents($lock_file));
    if ($pid > 0 && file_exists("/proc/$pid")) return true;
    @unlink($lock_file);
    return false;
}

function ncbi_lineage_cache_meta($metadata_path) {
    $f = "$metadata_path/taxonomy_lineage_cache.json";
    if (!file_exists($f)) return ['generated' => null, 'count' => 0];
    $d = loadJsonFile($f, []);
    $count = is_array($d) ? count(array_filter(array_keys($d), fn($k) => $k !== 'generated')) : 0;
    return ['generated' => $d['generated'] ?? null, 'count' => $count];
}

// --- GET: status ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $meta = ncbi_lineage_cache_meta($metadata_path);
    echo json_encode([
        'status'    => ncbi_lock_is_active($lock_file) ? 'running' : 'idle',
        'generated' => $meta['generated'],
        'count'     => $meta['count'],
    ]);
    exit;
}

// --- POST: start ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (ncbi_lock_is_active($lock_file)) {
    echo json_encode(['status' => 'already_running']);
    exit;
}

if (!$script_path || !file_exists($script_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'sync_ncbi_taxonomy_dump.php not found']);
    exit;
}

file_put_contents($lock_file, '0');

$shell_cmd = 'echo $$ > ' . escapeshellarg($lock_file) . ' ; '
           . 'php ' . escapeshellarg($script_path)
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

echo json_encode(['status' => 'started']);
