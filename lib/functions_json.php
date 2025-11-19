<?php
/**
 * MOOP JSON Functions
 * JSON file loading, parsing, and data manipulation
 */

/**
 * Load JSON file safely with error handling
 * 
 * @param string $path Path to JSON file
 * @param mixed $default Default value if file doesn't exist (default: [])
 * @return mixed Decoded JSON data or default value
 */
function loadJsonFile($path, $default = []) {
    if (!file_exists($path)) {
        return $default;
    }
    
    $json_content = file_get_contents($path);
    if ($json_content === false) {
        return $default;
    }
    
    $data = json_decode($json_content, true);
    return $data !== null ? $data : $default;
}

/**
 * Load JSON file and require it to exist
 * 
 * @param string $path Path to JSON file
 * @param string $errorMsg Error message to log if file missing
 * @param bool $exitOnError Whether to exit if file not found (default: true)
 * @return mixed Decoded JSON data or empty array if error
 */
function loadJsonFileRequired($path, $errorMsg = '', $exitOnError = false) {
    if (!file_exists($path)) {
        if ($errorMsg) {
            error_log($errorMsg);
        }
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Required data file not found");
        }
        return [];
    }
    
    $json_content = file_get_contents($path);
    if ($json_content === false) {
        $msg = $errorMsg ?: "Failed to read file: $path";
        error_log($msg);
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Failed to read required data");
        }
        return [];
    }
    
    $data = json_decode($json_content, true);
    if ($data === null) {
        $msg = $errorMsg ?: "Invalid JSON in file: $path";
        error_log($msg);
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Invalid data format");
        }
        return [];
    }
    
    return $data;
}

/**
 * Load existing JSON file and merge with new data
 * Handles wrapped JSON automatically, preserves existing fields not in merge data
 * 
 * @param string $file_path Path to JSON file to load
 * @param array $new_data New data to merge in (overwrites matching keys)
 * @return array Merged data (or just new_data if file doesn't exist)
 */
function loadAndMergeJson($file_path, $new_data = []) {
    // If file doesn't exist, just return new data
    if (!file_exists($file_path)) {
        return $new_data;
    }
    
    // Load existing data
    $existing = loadJsonFile($file_path);
    
    if (!is_array($existing)) {
        return $new_data;
    }
    
    // Handle wrapped JSON (extra outer braces)
    if (!isset($existing['genus']) && !isset($existing['common_name'])) {
        $keys = array_keys($existing);
        if (count($keys) > 0 && is_array($existing[$keys[0]])) {
            $existing = $existing[$keys[0]];
        }
    }
    
    // Merge: existing fields are preserved, new data overwrites matching keys
    return array_merge($existing, $new_data);
}

/**
 * Decode JSON string safely with type checking
 * 
 * @param string $json_string JSON string to decode
 * @param bool $as_array Return as array (default: true)
 * @return array|null Decoded data or null if invalid
 */
function decodeJsonString($json_string, $as_array = true) {
    if (empty($json_string)) {
        return $as_array ? [] : null;
    }
    
    $decoded = json_decode($json_string, $as_array);
    
    // Validate it decoded properly
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return $as_array ? [] : null;
    }
    
    return $decoded;
}
