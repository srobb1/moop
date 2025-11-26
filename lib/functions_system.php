<?php
/**
 * MOOP System Functions
 * System-level operations, user detection, and permission management
 */

/**
 * Get the web server user and group
 * 
 * Detects the user running the current PHP process (web server)
 * 
 * @return array - ['user' => string, 'group' => string]
 */
function getWebServerUser() {
    $user = 'www-data';
    $group = 'www-data';
    
    // Try to get the actual user running this process
    if (function_exists('posix_getuid')) {
        $uid = posix_getuid();
        $pwinfo = posix_getpwuid($uid);
        if ($pwinfo !== false) {
            $user = $pwinfo['name'];
        }
    }
    
    // Try to get the actual group
    if (function_exists('posix_getgid')) {
        $gid = posix_getgid();
        $grinfo = posix_getgrgid($gid);
        if ($grinfo !== false) {
            $group = $grinfo['name'];
        }
    }
    
    return ['user' => $user, 'group' => $group];
}

/**
 * Attempt to fix database file permissions
 * 
 * Tries to make database readable by web server user.
 * Returns instructions if automatic fix fails.
 * 
 * @param string $dbFile - Path to database file
 * @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
 */
function fixDatabasePermissions($dbFile) {
    $result = [
        'success' => false,
        'message' => '',
        'command' => ''
    ];
    
    if (!file_exists($dbFile)) {
        $result['message'] = 'Database file not found';
        return $result;
    }
    
    if (!is_file($dbFile)) {
        $result['message'] = 'Path is not a file';
        return $result;
    }
    
    // Get web server user
    $webserver = getWebServerUser();
    $web_user = $webserver['user'];
    $web_group = $webserver['group'];
    
    // Get file info for reporting
    $file_owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($dbFile))['name'] : 'unknown';
    $file_group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($dbFile))['name'] : 'unknown';
    $current_perms = substr(sprintf('%o', fileperms($dbFile)), -4);
    
    // Build command for admin to run if automatic fix fails
    $result['command'] = "sudo chmod 644 " . escapeshellarg($dbFile) . " && sudo chown " . escapeshellarg("$web_user:$web_group") . " " . escapeshellarg($dbFile);
    
    // Try to fix permissions
    try {
        // Try chmod to make readable (644 = rw-r--r--)
        $chmod_result = @chmod($dbFile, 0644);
        
        if (!$chmod_result) {
            $result['message'] = 'Web server lacks permission to change file permissions.';
            return $result;
        }
        
        // Try to change ownership to web server user (may fail if not root)
        @chown($dbFile, $web_user);
        @chgrp($dbFile, $web_group);
        
        // Verify it worked
        if (is_readable($dbFile)) {
            $result['success'] = true;
            $result['message'] = 'Permissions fixed successfully! Database is now readable.';
        } else {
            $result['message'] = 'Permissions were modified but file still not readable.';
        }
        
    } catch (Exception $e) {
        $result['message'] = 'Error: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Fix file or directory permissions (AJAX handler)
 * 
 * Called via AJAX when user clicks "Fix Permissions" button.
 * Only works if web server has sufficient permissions to chmod/chown.
 * 
 * @param string $file_path Path to file or directory
 * @param string $file_type 'file' or 'directory'
 * @return array Result array with 'success', 'message' keys
 */
function fixFilePermissions($file_path, $file_type = 'file') {
    $result = [
        'success' => false,
        'message' => '',
        'command' => ''
    ];
    
    // Validate input
    if (empty($file_path) || !file_exists($file_path)) {
        $result['message'] = 'File or directory not found';
        return $result;
    }
    
    $is_dir = is_dir($file_path);
    
    // Get web server user info
    $web_user = get_current_user() ?: 'www-data';
    $web_group_info = @posix_getgrgid(@posix_getegid());
    $web_group = $web_group_info !== false ? $web_group_info['name'] : 'www-data';
    
    try {
        if ($is_dir) {
            // For directories: chmod 755 (rwxr-xr-x)
            $chmod_result = @chmod($file_path, 0755);
            
            if (!$chmod_result) {
                $result['message'] = 'Web server lacks permission to change directory permissions.';
                return $result;
            }
            
            // Try to change ownership (may fail if not root)
            @chown($file_path, $web_user);
            @chgrp($file_path, $web_group);
            
            // Verify it worked
            if (is_readable($file_path) && is_writable($file_path)) {
                $result['success'] = true;
                $result['message'] = 'Directory permissions fixed successfully!';
            } else {
                $result['message'] = 'Permissions were modified but directory still has issues.';
            }
        } else {
            // For files: chmod 644 (rw-r--r--)
            $chmod_result = @chmod($file_path, 0644);
            
            if (!$chmod_result) {
                $result['message'] = 'Web server lacks permission to change file permissions.';
                return $result;
            }
            
            // Try to change ownership (may fail if not root)
            @chown($file_path, $web_user);
            @chgrp($file_path, $web_group);
            
            // Verify it worked
            if (is_readable($file_path)) {
                $result['success'] = true;
                $result['message'] = 'File permissions fixed successfully!';
            } else {
                $result['message'] = 'Permissions were modified but file still not readable.';
            }
        }
    } catch (Exception $e) {
        $result['message'] = 'Error: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Handle file permission fix AJAX request
 * 
 * Call this in your admin script's POST handler:
 * if (isset($_POST['action']) && $_POST['action'] === 'fix_file_permissions') {
 *     header('Content-Type: application/json');
 *     echo json_encode(handleFixFilePermissionsAjax());
 *     exit;
 * }
 * 
 * @return array JSON-serializable result array
 */
function handleFixFilePermissionsAjax() {
    if (empty($_POST['file_path'])) {
        return ['success' => false, 'message' => 'File path not provided'];
    }
    
    $file_path = $_POST['file_path'];
    $file_type = $_POST['file_type'] ?? 'file';
    
    // Basic security: prevent directory traversal
    $file_path = realpath($file_path);
    
    if (!$file_path || !file_exists($file_path)) {
        return ['success' => false, 'message' => 'File or directory not found'];
    }
    
    return fixFilePermissions($file_path, $file_type);
}

/**
 * Handle AJAX requests at page start
 * 
 * Consolidates common AJAX request handling for admin pages.
 * Handles JSON response headers and early exit for AJAX requests.
 * 
 * Supported actions:
 * - 'fix_file_permissions': Calls handleFixFilePermissionsAjax()
 * - Custom actions: Pass callback function to handle additional actions
 * 
 * @param callable|null $customHandler - Optional callback for custom actions
 *                                       Receives $_POST['action'] and should return true if handled
 * @return void - Exits after sending response
 */
function handleAdminAjax($customHandler = null) {
    if (!isset($_POST['action'])) {
        return; // Not an AJAX request
    }
    
    header('Content-Type: application/json');
    
    // Handle standard fix_file_permissions action
    if ($_POST['action'] === 'fix_file_permissions') {
        echo json_encode(handleFixFilePermissionsAjax());
        exit;
    }
    
    // Pass to custom handler if provided
    if ($customHandler && is_callable($customHandler)) {
        $handled = call_user_func($customHandler, $_POST['action']);
        if ($handled) {
            exit;
        }
    }
}
