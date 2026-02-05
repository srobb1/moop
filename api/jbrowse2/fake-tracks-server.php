<?php
/**
 * /api/jbrowse2/fake-tracks-server.php
 * 
 * Simulates a separate tracks server for testing
 * In production, this would run on a different machine
 * 
 * This endpoint:
 * 1. Validates JWT tokens
 * 2. Serves track data files (BigWig, BAM, etc)
 * 3. Supports HTTP range requests
 * 
 * Test: curl -H "Range: bytes=0-1000" "http://moop.local/api/jbrowse2/fake-tracks-server.php?file=test.bw&token=JWT"
 */

// Do NOT use MOOP session - tracks server is separate
// This simulates a standalone service

header('Accept-Ranges: bytes');

// Get parameters
$file = $_GET['file'] ?? '';
$token = $_GET['token'] ?? '';

// Validate file parameter (prevent path traversal)
if (empty($file) || strpos($file, '..') !== false) {
    http_response_code(400);
    echo "Invalid file parameter";
    exit;
}

// 1. VALIDATE TOKEN (simulating external tracks server)
require_once '../../lib/jbrowse/track_token.php';

$token_valid = false;
$token_data = null;

if (!empty($token)) {
    $token_data = verifyTrackToken($token);
    if ($token_data) {
        $token_valid = true;
    }
}

// For testing: allow local IPs without token validation
$visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$is_local = in_array($visitor_ip, ['127.0.0.1', '::1']) || 
            strpos($visitor_ip, '127.') === 0 ||
            strpos($visitor_ip, '192.168.') === 0 ||
            strpos($visitor_ip, '10.') === 0 ||
            strpos($visitor_ip, '172.16.') === 0;

if (!$is_local && !$token_valid) {
    http_response_code(403);
    echo "Access denied: Invalid or missing token";
    exit;
}

// 2. RESOLVE FILE PATH
// Tracks are organized by format: /api/jbrowse2/fake-tracks-server.php?file=bigwig/organism_assembly_track.bw
$test_data_dir = __DIR__ . '/../../data/tracks';

// Extract format from file path (e.g., "bigwig/file.bw")
$file_parts = explode('/', $file);
if (count($file_parts) < 2) {
    http_response_code(400);
    echo "Invalid file format";
    exit;
}

$format = $file_parts[0];
$filename = $file_parts[1];

// Build full file path
$file_path = $test_data_dir . '/' . $format . '/' . $filename;

// Security: ensure file is within test data directory
$real_path = realpath($file_path);
if (!$real_path || strpos($real_path, realpath($test_data_dir)) !== 0) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// 3. CHECK IF FILE EXISTS
if (!file_exists($file_path)) {
    http_response_code(404);
    echo "File not found: $filename";
    exit;
}

// 4. SERVE FILE WITH RANGE REQUEST SUPPORT
$file_size = filesize($file_path);
$range = $_SERVER['HTTP_RANGE'] ?? null;

if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
    // Handle range request
    $start = intval($matches[1]);
    $end = $matches[2] !== '' ? intval($matches[2]) : $file_size - 1;
    
    // Validate range
    if ($start > $end || $start >= $file_size) {
        http_response_code(416);
        header("Content-Range: bytes */$file_size");
        exit;
    }
    
    $length = $end - $start + 1;
    
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$file_size");
    header("Content-Length: $length");
    
    // Read and send only requested range
    $fp = fopen($file_path, 'rb');
    fseek($fp, $start);
    echo fread($fp, $length);
    fclose($fp);
} else {
    // Send entire file
    header("Content-Length: $file_size");
    header('Content-Type: application/octet-stream');
    readfile($file_path);
}

exit;
?>
