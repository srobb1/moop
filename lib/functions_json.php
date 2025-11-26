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

/**
 * Save data to JSON file with pretty printing
 * Writes JSON data with readable formatting
 * 
 * @param string $path Path to JSON file to save
 * @param array $data Data to encode and save
 * @return int|false Number of bytes written, or false on failure
 */
function saveJsonFile($path, $data) {
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Build mapping from DB types to canonical config names
 * Uses synonyms to map all aliases to their canonical entry
 * 
 * @param array $annotation_config - Loaded annotation_config.json
 * @return array - [db_type => canonical_name] mapping
 */
function getAnnotationTypeMapping($annotation_config) {
    $mapping = [];
    
    if (!isset($annotation_config['annotation_types'])) {
        return $mapping;
    }
    
    foreach ($annotation_config['annotation_types'] as $canonical_name => $config) {
        // Map the canonical name to itself
        $mapping[$canonical_name] = $canonical_name;
        
        // Map each synonym to this canonical name
        if (isset($config['synonyms']) && is_array($config['synonyms'])) {
            foreach ($config['synonyms'] as $synonym) {
                $mapping[$synonym] = $canonical_name;
            }
        }
    }
    
    return $mapping;
}

/**
 * Synchronize annotation types between config and database
 * Creates entries for unmapped DB types, marks unused entries
 * Populates annotation_count and feature_count for each type
 * 
 * @param array $annotation_config - Current annotation_config.json
 * @param array $db_types - [annotation_type => ['annotation_count' => N, 'feature_count' => M]]
 * @return array - Updated config with sync metadata
 */
function syncAnnotationTypes($annotation_config, $db_types) {
    if (!isset($annotation_config['annotation_types'])) {
        $annotation_config['annotation_types'] = [];
    }
    
    // Get current mapping (canonical + synonyms)
    $mapping = getAnnotationTypeMapping($annotation_config);
    
    // Mark all existing entries as not in database initially
    foreach ($annotation_config['annotation_types'] as &$config) {
        $config['in_database'] = false;
        $config['annotation_count'] = 0;
        $config['feature_count'] = 0;
    }
    unset($config);
    
    // Check which DB types are covered by existing config
    $mapped_db_types = [];
    foreach ($db_types as $db_type => $counts) {
        if (isset($mapping[$db_type])) {
            // This DB type maps to an existing config entry
            $canonical = $mapping[$db_type];
            $annotation_config['annotation_types'][$canonical]['in_database'] = true;
            $annotation_config['annotation_types'][$canonical]['annotation_count'] += $counts['annotation_count'];
            $annotation_config['annotation_types'][$canonical]['feature_count'] += $counts['feature_count'];
            $mapped_db_types[$db_type] = true;
        }
    }
    
    // Add unmapped DB types as new entries
    $next_order = 1;
    if (!empty($annotation_config['annotation_types'])) {
        $orders = array_map(function($c) { return $c['order'] ?? 0; }, 
                          $annotation_config['annotation_types']);
        $next_order = max($orders) + 1;
    }
    
    foreach ($db_types as $db_type => $counts) {
        if (!isset($mapped_db_types[$db_type])) {
            // New type not in config - add it
            $annotation_config['annotation_types'][$db_type] = [
                'display_name' => $db_type,
                'display_label' => $db_type,
                'color' => 'secondary',
                'order' => $next_order,
                'description' => '',
                'enabled' => true,
                'synonyms' => [],
                'in_database' => true,
                'annotation_count' => $counts['annotation_count'],
                'feature_count' => $counts['feature_count'],
                'new' => true
            ];
            $next_order++;
        }
    }
    
    return $annotation_config;
}

/**
 * Consolidate a synonym entry into the canonical entry
 * Removes the synonym as separate entry, adds to synonyms array
 * 
 * @param array &$annotation_config - Reference to config (modified in place)
 * @param string $canonical_name - Target canonical entry
 * @param string $synonym_name - Synonym entry to consolidate
 * @return bool - Success status
 */
function consolidateSynonym(&$annotation_config, $canonical_name, $synonym_name) {
    if (!isset($annotation_config['annotation_types'][$canonical_name])) {
        return false;
    }
    
    if (!isset($annotation_config['annotation_types'][$synonym_name])) {
        return false;
    }
    
    // Add synonym entry to synonyms array
    if (!isset($annotation_config['annotation_types'][$canonical_name]['synonyms'])) {
        $annotation_config['annotation_types'][$canonical_name]['synonyms'] = [];
    }
    
    if (!in_array($synonym_name, $annotation_config['annotation_types'][$canonical_name]['synonyms'])) {
        $annotation_config['annotation_types'][$canonical_name]['synonyms'][] = $synonym_name;
    }
    
    // Combine counts
    $synonym_annotation_count = $annotation_config['annotation_types'][$synonym_name]['annotation_count'] ?? 0;
    $synonym_feature_count = $annotation_config['annotation_types'][$synonym_name]['feature_count'] ?? 0;
    
    if ($synonym_annotation_count > 0) {
        $annotation_config['annotation_types'][$canonical_name]['annotation_count'] = 
            ($annotation_config['annotation_types'][$canonical_name]['annotation_count'] ?? 0) + $synonym_annotation_count;
    }
    
    if ($synonym_feature_count > 0) {
        $annotation_config['annotation_types'][$canonical_name]['feature_count'] = 
            ($annotation_config['annotation_types'][$canonical_name]['feature_count'] ?? 0) + $synonym_feature_count;
    }
    
    // Mark canonical as in database if synonym was
    if ($annotation_config['annotation_types'][$synonym_name]['in_database'] ?? false) {
        $annotation_config['annotation_types'][$canonical_name]['in_database'] = true;
    }
    
    // Remove the synonym entry
    unset($annotation_config['annotation_types'][$synonym_name]);
    
    return true;
}

/**
 * Get display label for an annotation type from database
 * Resolves through synonym mapping and returns configured display_label
 * 
 * @param string $db_annotation_type - Type from annotation_source table
 * @param array $annotation_config - Loaded annotation_config.json
 * @return string - Display label to use in UI
 */
function getAnnotationDisplayLabel($db_annotation_type, $annotation_config) {
    if (!isset($annotation_config['annotation_types'])) {
        return $db_annotation_type;
    }
    
    // Get mapping
    $mapping = getAnnotationTypeMapping($annotation_config);
    
    // Find canonical name
    if (isset($mapping[$db_annotation_type])) {
        $canonical = $mapping[$db_annotation_type];
        
        // Get the configured display label for this canonical entry
        if (isset($annotation_config['annotation_types'][$canonical]['display_label'])) {
            return $annotation_config['annotation_types'][$canonical]['display_label'];
        }
        
        // Fallback to display_name
        if (isset($annotation_config['annotation_types'][$canonical]['display_name'])) {
            return $annotation_config['annotation_types'][$canonical]['display_name'];
        }
        
        return $canonical;
    }
    
    return $db_annotation_type;
}

/**
 * Check if annotation counts need to be updated
 * 
 * Compares the stored SQLite modification time with the current newest modification time.
 * If they differ or if counts are empty, returns true to indicate update is needed.
 * 
 * @param array $annotation_config - Current annotation config from JSON
 * @param array $newest_mod_info - Result from getNewestSqliteModTime()
 * @return bool - True if counts need to be updated
 */
function shouldUpdateAnnotationCounts($annotation_config, $newest_mod_info) {
    // If no SQLite files found, no need to update
    if ($newest_mod_info === null) {
        return false;
    }
    
    // If no stored modification time, update is needed
    if (!isset($annotation_config['sqlite_mod_time'])) {
        return true;
    }
    
    // If modification time changed, update is needed
    if ($annotation_config['sqlite_mod_time'] !== $newest_mod_info['unix_time']) {
        return true;
    }
    
    // Check if any annotation types are missing counts or have empty/zero values
    if (isset($annotation_config['annotation_types'])) {
        foreach ($annotation_config['annotation_types'] as $type_config) {
            // Check if counts don't exist or are empty/zero
            if (!isset($type_config['annotation_count']) || $type_config['annotation_count'] === '' || $type_config['annotation_count'] === null) {
                return true;
            }
            if (!isset($type_config['feature_count']) || $type_config['feature_count'] === '' || $type_config['feature_count'] === null) {
                return true;
            }
        }
    }
    
    return false;
}
