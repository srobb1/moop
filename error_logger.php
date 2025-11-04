<?php
/**
 * Error Logging Utility
 * Logs application errors to a file for admin review
 */

/**
 * Log an error to the error log file
 * 
 * @param string $error_message The error message to log
 * @param string $context Optional context (e.g., feature name, organism)
 * @param array $additional_info Optional array of additional info to log
 */
function logError($error_message, $context = '', $additional_info = []) {
    $log_file = __DIR__ . '/logs/error.log';
    
    // Create log entry
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['username'] ?? 'anonymous';
    $page = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    $log_entry = [
        'timestamp' => $timestamp,
        'error' => $error_message,
        'context' => $context,
        'user' => $user,
        'page' => $page,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Add additional info if provided
    if (!empty($additional_info)) {
        $log_entry['details'] = $additional_info;
    }
    
    // Format as JSON for easy parsing
    $log_line = json_encode($log_entry) . "\n";
    
    // Append to log file
    @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
}

/**
 * Get all logged errors
 * 
 * @param int $limit Maximum number of entries to return (0 = all)
 * @return array Array of error entries
 */
function getErrorLog($limit = 0) {
    $log_file = __DIR__ . '/logs/error.log';
    $errors = [];
    
    if (!file_exists($log_file)) {
        return $errors;
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines === false) {
        return $errors;
    }
    
    // Read lines in reverse order (newest first)
    $lines = array_reverse($lines);
    
    foreach ($lines as $line) {
        if (empty($line)) {
            continue;
        }
        
        $entry = json_decode($line, true);
        if ($entry !== null) {
            $errors[] = $entry;
        }
        
        // Stop if we've reached the limit
        if ($limit > 0 && count($errors) >= $limit) {
            break;
        }
    }
    
    return $errors;
}

/**
 * Clear the error log file
 * 
 * @return bool True on success, false on failure
 */
function clearErrorLog() {
    $log_file = __DIR__ . '/logs/error.log';
    
    if (!file_exists($log_file)) {
        return true; // Already empty
    }
    
    return @unlink($log_file);
}
