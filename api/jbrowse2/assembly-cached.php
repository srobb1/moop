<?php
/**
 * Cached Assembly Config API for JBrowse2
 * 
 * Generates and caches assembly configs per access level for better performance
 * and perfect sharing support.
 * 
 * Cache strategy:
 * - Configs cached as: /jbrowse2/configs/{organism}_{assembly}/{ACCESS_LEVEL}.json
 * - Cache valid for 24 hours
 * - Auto-regenerates if stale or missing
 * - Cleared when tracks are added/removed
 * 
 * Security:
 * - COLLABORATOR users must have assembly access (checked via $_SESSION['access'])
 * - PUBLIC, ADMIN, IP_IN_RANGE access is unrestricted
 * 
 * GET /api/jbrowse2/assembly-cached.php?organism={organism}&assembly={assembly}
 */

// Configuration
$CACHE_MAX_AGE = 86400; // 24 hours in seconds

// Get parameters
$organism = $_GET['organism'] ?? '';
$assembly = $_GET['assembly'] ?? '';

// Validate input
if (empty($organism) || empty($assembly)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing organism or assembly parameter']);
    exit;
}

// Start session to get user access level
session_start();

// Determine user access level
require_once __DIR__ . '/../../includes/access_control.php';
require_once __DIR__ . '/../../lib/functions_access.php';

$user_access_level = get_access_level();

// Security check for COLLABORATOR users - must have assembly access
if ($user_access_level === 'COLLABORATOR') {
    $user_access = $_SESSION['access'] ?? [];
    $has_assembly_access = isset($user_access[$organism]) && 
                           in_array($assembly, (array)$user_access[$organism]);
    
    if (!$has_assembly_access) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Access denied',
            'message' => 'You do not have permission to view this assembly'
        ]);
        exit;
    }
}

// Build cache file path
$assembly_dir = __DIR__ . "/../../jbrowse2/configs/{$organism}_{$assembly}";
$cache_file = "$assembly_dir/{$user_access_level}.json";

// Ensure assembly directory exists
if (!is_dir($assembly_dir)) {
    mkdir($assembly_dir, 0775, true);
}

// Check if cache exists and is fresh
$use_cache = false;
$cache_age = 0;
if (file_exists($cache_file)) {
    $cache_age = time() - filemtime($cache_file);
    if ($cache_age < $CACHE_MAX_AGE) {
        $use_cache = true;
    }
}

// Serve from cache if available
if ($use_cache) {
    header('Content-Type: application/json');
    header('X-JBrowse-Cache: hit');
    header('X-JBrowse-Cache-Age: ' . $cache_age . ' seconds');
    readfile($cache_file);
    exit;
}

// Cache miss or stale - generate fresh config
// Include the main assembly.php logic
ob_start();
$_GET['organism'] = $organism;
$_GET['assembly'] = $assembly;

try {
    include __DIR__ . '/assembly.php';
    $config_json = ob_get_clean();
    
    // Validate it's valid JSON
    $config = json_decode($config_json, true);
    if (!$config) {
        throw new Exception('Invalid JSON generated from assembly.php');
    }
    
    // Save to cache
    $bytes_written = file_put_contents($cache_file, $config_json);
    if ($bytes_written === false) {
        throw new Exception('Failed to write cache file');
    }
    
    chmod($cache_file, 0664); // Make readable by web server
    
    // Serve it
    header('Content-Type: application/json');
    header('X-JBrowse-Cache: miss');
    header('X-JBrowse-Cache-Generated: ' . date('c'));
    header('X-JBrowse-Cache-File: ' . basename($cache_file));
    echo $config_json;
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to generate config',
        'message' => $e->getMessage(),
        'organism' => $organism,
        'assembly' => $assembly,
        'access_level' => $user_access_level
    ]);
    error_log("JBrowse2 config generation error for {$organism}/{$assembly}: " . $e->getMessage());
}
?>
