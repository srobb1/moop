<?php
/**
 * Download ZIP (tar.gz) API
 *
 * Streams a tar.gz archive of selected files to the user.
 * Files are organized as organism/assembly/filename within the archive.
 *
 * POST params:
 *   files[N][organism]  - Organism directory name
 *   files[N][assembly]  - Assembly directory name
 *   files[N][filename]  - Filename within the assembly directory
 *
 * Security: same checks as download_file.php per file.
 * Streaming: uses tar piped to stdout — no temp file, memory-efficient.
 */

ob_start();
include_once __DIR__ . '/../tools/tool_init.php';
ob_end_clean();

$blocked_exts = array_flip([
    'ndb', 'nhr', 'nin', 'njs', 'nog', 'nos', 'not', 'nsq', 'ntf', 'nto',
    'pdb', 'phr', 'pin', 'pjs', 'pog', 'pos', 'pot', 'psq', 'ptf', 'pto',
    'sqlite', 'json',
]);

$files_param = $_POST['files'] ?? [];

if (empty($files_param) || !is_array($files_param)) {
    http_response_code(400);
    exit('No files specified.');
}

$organism_data = $config->getPath('organism_data');
$real_data_dir = realpath($organism_data);

if (!$real_data_dir) {
    http_response_code(500);
    exit('Organism data directory not configured.');
}

// Validate every requested file before opening the output stream.
// Collect relative paths (organism/assembly/filename) for tar.
$valid_relative_paths = [];
$errors = [];

foreach ($files_param as $idx => $entry) {
    $organism = trim($entry['organism'] ?? '');
    $assembly = trim($entry['assembly'] ?? '');
    $gene_set = trim($entry['gene_set'] ?? '');
    $filename = trim($entry['filename'] ?? '');

    if ($organism === '' || $assembly === '' || $filename === '') {
        $errors[] = "Entry $idx: missing fields.";
        continue;
    }

    // No path separators or traversal in gene_set or filename
    if ($gene_set !== '' && (strpbrk($gene_set, '/\\') !== false || strpos($gene_set, '..') !== false)) {
        $errors[] = "Entry $idx: invalid gene_set.";
        continue;
    }
    if (strpbrk($filename, '/\\') !== false || strpos($filename, '..') !== false) {
        $errors[] = "Entry $idx: invalid filename.";
        continue;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (isset($blocked_exts[$ext])) {
        $errors[] = "Entry $idx: blocked file type.";
        continue;
    }

    if (!has_assembly_access($organism, $assembly)) {
        $errors[] = "Entry $idx: access denied.";
        continue;
    }

    $subdir   = $gene_set !== '' ? "$organism/$assembly/$gene_set" : "$organism/$assembly";
    $base_dir = realpath("$real_data_dir/$subdir");
    if (!$base_dir) {
        $errors[] = "Entry $idx: directory not found.";
        continue;
    }

    $file_path = realpath("$base_dir/$filename");
    if (!$file_path || !is_file($file_path)) {
        $errors[] = "Entry $idx: file not found.";
        continue;
    }

    // Path traversal guard
    if (strpos($file_path, $base_dir . DIRECTORY_SEPARATOR) !== 0) {
        $errors[] = "Entry $idx: path traversal detected.";
        continue;
    }

    // Relative path within organism_data preserves directory hierarchy in the archive
    $valid_relative_paths[] = $subdir . '/' . $filename;
}

if (empty($valid_relative_paths)) {
    http_response_code(403);
    $msg = empty($errors) ? 'No valid files found.' : implode(' ', $errors);
    exit($msg);
}

// Build the tar command. Run from organism_data so paths are relative.
// -z = gzip, -f - = write to stdout, each file as a relative path arg.
$rel_paths_escaped = array_map('escapeshellarg', $valid_relative_paths);
$cmd = 'tar -czf - -C ' . escapeshellarg($real_data_dir) . ' '
     . implode(' ', $rel_paths_escaped);

// Determine archive filename
if (count($valid_relative_paths) === 1) {
    $archive_name = pathinfo($valid_relative_paths[0], PATHINFO_FILENAME) . '.tar.gz';
} else {
    $archive_name = 'moop_downloads_' . date('Ymd_His') . '.tar.gz';
}

if (ob_get_level()) ob_end_clean();

// Stream headers — no Content-Length since size is unknown before compression
header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $archive_name) . '"');
header('Cache-Control: no-store');
header('Pragma: no-cache');
// Disable nginx/apache output buffering so data streams immediately
header('X-Accel-Buffering: no');

$handle = popen($cmd, 'r');
if ($handle === false) {
    http_response_code(500);
    exit('Failed to create archive.');
}

while (!feof($handle)) {
    echo fread($handle, 65536); // 64 KB chunks
    flush();
}

$exit_code = pclose($handle);

// tar exit code 2 = fatal error; 1 = warnings (often acceptable)
if ($exit_code === 2) {
    // Headers already sent — can't send a proper error response, just exit
    error_log("download_zip.php: tar exited with code $exit_code for command: $cmd");
}

exit;
