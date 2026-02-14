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
 * - JWT token REQUIRED for all requests (except whitelisted IPs)
 * - Token contains: user_id, organism, assembly, access_level, expiry
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

// 2. CHECK IF IP IS WHITELISTED (skip token check for internal network)
$is_whitelisted = isWhitelistedIP();

$token_data = null;
if (!$is_whitelisted) {
    // 3. VALIDATE JWT TOKEN
    if (empty($token)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    
    $token_data = verifyTrackToken($token);
    
    if (!$token_data) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    
    // 4. VALIDATE FILE MATCHES TOKEN PERMISSIONS
    // File path format: organism/assembly/type/filename
    $file_parts = explode('/', $file);
    
    if (count($file_parts) < 2) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid file path format']);
        exit;
    }
    
    $file_organism = $file_parts[0];
    $file_assembly = $file_parts[1];
    
    // Check if token grants access to this organism/assembly
    if ($token_data->organism !== $file_organism || $token_data->assembly !== $file_assembly) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Access denied',
            'message' => 'Token does not grant access to this file'
        ]);
        exit;
    }
}

// 5. BUILD FILE PATH
$file_path = $TRACKS_BASE_DIR . '/' . $file;

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
        $end = !empty($matches[2]) ? (int)$matches[2] : $file_size - 1;
        
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
        
        $fp = fopen($file_path, 'rb');
        fseek($fp, $start);
        echo fread($fp, $length);
        fclose($fp);
        exit;
    }
}

// 8. SEND FULL FILE
header('Accept-Ranges: bytes');
header('Content-Length: ' . $file_size);
readfile($file_path);

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
