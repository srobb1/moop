<?php
/**
 * Error Logging Functions
 * Centralized error logging and retrieval for admin viewing
 */

/**
 * Log an error to the error log file
 * 
 * @param string $error_message The error message to log
 * @param string $context Optional context (e.g., organism name, page name)
 * @param array $additional_info Additional details to log
 * @return void
 */
function logError($error_message, $context = '', $additional_info = []) {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath('error_log_file');
    
    $error_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $error_message,
        'context' => $context,
        'user' => $_SESSION['username'] ?? 'anonymous',
        'page' => $_SERVER['REQUEST_URI'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'details' => $additional_info
    ];
    
    $log_line = json_encode($error_entry) . "\n";
    
    if ($log_file) {
        error_log($log_line, 3, $log_file);
    }
}

/**
 * Get error log entries
 * 
 * @param int $limit Maximum number of entries to retrieve (0 = all)
 * @return array Array of error entries
 */
function getErrorLog($limit = 0) {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath('error_log_file');
    
    if (!$log_file || !file_exists($log_file)) {
        return [];
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }
    
    $errors = [];
    foreach (array_reverse($lines) as $line) {
        $error = json_decode($line, true);
        if ($error) {
            $errors[] = $error;
        }
    }
    
    if ($limit > 0) {
        $errors = array_slice($errors, 0, $limit);
    }
    
    return $errors;
}

/**
 * Clear the error log file
 * 
 * @return bool True if successful
 */
function clearErrorLog() {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath('error_log_file');
    
    if (!$log_file) {
        return false;
    }
    
    return file_put_contents($log_file, '') !== false;
}
