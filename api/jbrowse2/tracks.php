<?php
/**
 * Track File Server - SINGLE SECURE ENDPOINT
 * 
 * Serves track data files with JWT token validation
 * Supports HTTP range requests for efficient data streaming
 * 
 * This endpoint will be deployed on remote track servers that:
 * - Have NO access to the MOOP session database
 * - ONLY validate JWT tokens using public key
 * - Serve files based on validated permissions
 * 
 * Security:
 * - JWT token REQUIRED for all requests
 * - Token contains: organism, assembly, expiry
 * - File paths are validated to prevent directory traversal
 * - Files are only served if token grants access to that organism/assembly
 * 
 * Usage:
 * GET /api/jbrowse2/tracks.php?file=path/to/file.bw&token=JWT_TOKEN
 * 
 * Headers:
 * Range: bytes=0-1000  (optional, for partial content)
 */

require_once __DIR__ . '/../../lib/jbrowse/track_token.php';

// Configuration
$TRACKS_BASE_DIR = __DIR__ . '/../../data/tracks';

// Get parameters
$file = $_GET['file'] ?? '';
// SECURITY NOTE: JWT token is passed as a URL query parameter because JBrowse2
// initiates track data requests internally and does not support custom
// Authorization headers on those requests. This means tokens appear in server
// access logs and browser history. This is a known JBrowse2 architectural
// constraint. Tokens are short-lived to limit exposure. If a future JBrowse2
// version supports custom headers for track requests, migrate to that approach.
$token = $_GET['token'] ?? '';

// 1. VALIDATE FILE PARAMETER
if (empty($file)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing file parameter']);
    exit;
}

// Prevent directory traversal attacks
if (strpos($file, '..') !== false || strpos($file, '//') !== false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid file path']);
    exit;
}

// 2. REQUIRE JWT TOKEN
if (empty($token)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    error_log("Tracks server: No token provided from IP: {$_SERVER['REMOTE_ADDR']}");
    exit;
}

// 3. VALIDATE JWT TOKEN
$token_data = verifyTrackToken($token);

if (!$token_data) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

// 4. AUTHORIZE: the token must be bound to THIS exact file (audit #17).
// Each token MOOP issues authorizes exactly one file (its `file` claim equals the
// `?file=` path). A token handed out for a PUBLIC file therefore cannot be replayed
// against a restricted file on the same assembly. No per-file access list is needed
// on the tracks server — the authorization is carried in the signed token itself.
$token_file = $token_data->file ?? '';

if ($token_file === '' || $token_file !== $file) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Access denied',
        'message' => 'Token does not authorize this file',
    ]);
    error_log("Tracks server: token/file mismatch - IP {$_SERVER['REMOTE_ADDR']} requested '$file' with token for '" . ($token_file !== '' ? $token_file : '(no file claim)') . "'");
    exit;
}

// 5. BUILD AND SERVE FILE
$resolved_base = realpath($TRACKS_BASE_DIR);
$file_path = realpath($TRACKS_BASE_DIR . '/' . $file);

if ($file_path === false || strpos($file_path, $resolved_base . '/') !== 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid file path']);
    error_log("Tracks server: Path escape attempt - IP {$_SERVER['REMOTE_ADDR']} requested: $file");
    exit;
}

if (!file_exists($file_path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit;
}

if (!is_readable($file_path)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not readable']);
    exit;
}

// 6. DETERMINE CONTENT TYPE
$content_type = getContentTypeFromFile($file);
header('Content-Type: ' . $content_type);

// 7. HANDLE RANGE REQUESTS (for efficient streaming)
$file_size = filesize($file_path);
$range_header = $_SERVER['HTTP_RANGE'] ?? '';

if (!empty($range_header)) {
    // Parse range header: "bytes=0-1000"
    if (preg_match('/bytes=(\d+)-(\d*)/', $range_header, $matches)) {
        $start = (int)$matches[1];
        $end = !empty($matches[2]) ? min((int)$matches[2], $file_size - 1) : $file_size - 1;

        // Validate range
        if ($start > $end || $start >= $file_size) {
            http_response_code(416); // Range Not Satisfiable
            header("Content-Range: bytes */$file_size");
            exit;
        }
        
        $length = $end - $start + 1;

        // Send partial content
        http_response_code(206); // Partial Content
        header('Accept-Ranges: bytes');
        header("Content-Range: bytes $start-$end/$file_size");
        header("Content-Length: $length");

        moop_stream_file_range($file_path, $start, $length);
        exit;
    }
}

// 8. SEND FULL FILE
header('Accept-Ranges: bytes');
header('Content-Length: ' . $file_size);
moop_stream_file_range($file_path, 0, $file_size);

/**
 * Stream $length bytes of $path starting at $start, without ever holding more than one
 * chunk in memory.
 *
 * Two separate memory hazards were being fixed here, both fatal above memory_limit (128M):
 *
 *  1. php.ini sets `output_buffering = 4096`, so under php-fpm EVERY request begins inside
 *     an implicit output buffer that nothing in this file had closed. `readfile()` and
 *     `echo` write into whatever buffer is open, so the response accumulated in memory
 *     before a byte reached the client. Same root cause as the genome-download 500 fixed in
 *     `lib/fasta_download_handler.php` (30ad553); `api/download_file.php` already did the
 *     right thing at its :130. This file simply never got the pattern.
 *  2. `echo fread($fp, $length)` read the ENTIRE requested range into a PHP string first.
 *     An open-ended `Range: bytes=0-` sets $end = filesize-1, which JBrowse does send, so
 *     "partial content" could mean the whole BAM. The range path was therefore no safer
 *     than the full-file path, which is easy to miss because the name says otherwise.
 *
 * ob_end_clean() rather than ob_end_flush(): nothing legitimate is in that buffer at this
 * point, and flushing stray bytes ahead of binary track data would corrupt it. header()
 * queues headers independently of the output buffer, so those are unaffected.
 *
 * Track files are BAM/bigWig and are large by nature — this endpoint is the one place in
 * MOOP where a multi-GB body is the normal case, not the edge case.
 */
function moop_stream_file_range(string $path, int $start, int $length): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fp = fopen($path, 'rb');
    if ($fp === false) {
        return;
    }
    if ($start > 0) {
        fseek($fp, $start);
    }

    $chunk_size = 1024 * 1024; // 1 MB
    $remaining  = $length;
    while ($remaining > 0 && !feof($fp)) {
        $buffer = fread($fp, (int)min($chunk_size, $remaining));
        if ($buffer === false || $buffer === '') {
            break;
        }
        echo $buffer;
        flush();
        $remaining -= strlen($buffer);
    }
    fclose($fp);
}

/**
 * Determine content type from file extension
 */
function getContentTypeFromFile($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $mime_types = [
        'bw' => 'application/octet-stream',
        'bigwig' => 'application/octet-stream',
        'bam' => 'application/octet-stream',
        'bai' => 'application/octet-stream',
        'cram' => 'application/octet-stream',
        'crai' => 'application/octet-stream',
        'vcf' => 'text/plain',
        'gz' => 'application/gzip',
        'tbi' => 'application/octet-stream',
        'tai' => 'application/octet-stream',  // TAF index files
        'gzi' => 'application/octet-stream',
        'maf' => 'text/plain',
        'bed' => 'text/plain',
        'gff' => 'text/plain',
        'gff3' => 'text/plain',
        'gtf' => 'text/plain'
    ];
    
    return $mime_types[$ext] ?? 'application/octet-stream';
}
?>
