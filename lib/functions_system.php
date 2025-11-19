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
