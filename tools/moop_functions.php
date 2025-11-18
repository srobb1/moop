<?php
/**
 * MOOP Core Functions
 * Central utilities for database access, data sanitization, and common operations
 * Used across display, extract, and search modules
 */

// Include database query builder functions
require_once __DIR__ . '/database_queries.php';

/**
 * Sanitize user input - remove dangerous characters
 * 
 * DEPRECATED: Use context-specific sanitization instead:
 * - For database queries: Use prepared statements with parameter binding
 * - For HTML output: Use htmlspecialchars() at the point of output
 * - For URL parameters: Use urlencode()/urldecode() as needed
 * 
 * This function is kept for backwards compatibility but combines multiple
 * concerns and is typically misused. It applies both raw character removal
 * and HTML escaping, which should be handled separately based on context.
 * 
 * @param string $data - Raw user input
 * @return string - Sanitized string with < > removed and HTML entities escaped
 * @deprecated Use prepared statements and context-specific escaping
 */
function test_input($data) {
    $data = stripslashes($data);
    $data = preg_replace('/[\<\>]+/', '', $data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Sanitize user input - remove dangerous characters (recommended name)
 * 
 * DEPRECATED: Use context-specific sanitization instead:
 * - For database queries: Use prepared statements with parameter binding
 * - For HTML output: Use htmlspecialchars() at the point of output
 * - For URL parameters: Use urlencode()/urldecode() as needed
 * 
 * This function combines multiple sanitization concerns and should typically
 * be avoided in favor of context-specific approaches. It applies both raw 
 * character removal and HTML escaping.
 * 
 * @param string $data - Raw user input
 * @return string - Sanitized string with < > removed and HTML entities escaped
 * @deprecated Use prepared statements and context-specific escaping
 */
function sanitize_input($data) {
    return test_input($data);
}

/**
 * Validate database integrity and data quality
 * 
 * Checks:
 * - File is readable
 * - Valid SQLite database
 * - All required tables exist
 * - Tables have data
 * - Data completeness (no orphaned records)
 * 
 * @param string $dbFile - Path to SQLite database file
 * @return array - Validation results with status and details
 */

/**
 * Validates database file is readable and accessible
 * 
 * @param string $dbFile - Path to SQLite database file
 * @return array - Validation results with 'valid' and 'error' keys
 */
/* TO DO: determine if both validateDatabaseFile and validateDatabaseIntegrity are needed */
function validateDatabaseFile($dbFile) {
    if (!file_exists($dbFile)) {
        return ['valid' => false, 'error' => 'Database file not found'];
    }
    
    if (!is_readable($dbFile)) {
        return ['valid' => false, 'error' => 'Database file not readable (permission denied)'];
    }
    
    try {
        $db = new PDO('sqlite:' . $dbFile);
        $db = null;
        return ['valid' => true, 'error' => ''];
    } catch (Exception $e) {
        return ['valid' => false, 'error' => $e->getMessage()];
    }
}

function validateDatabaseIntegrity($dbFile) {
    $result = [
        'valid' => false,
        'readable' => false,
        'database_valid' => false,
        'tables_present' => [],
        'tables_missing' => [],
        'row_counts' => [],
        'data_issues' => [],
        'errors' => []
    ];
    
    // Check if file exists and is readable
    if (!file_exists($dbFile)) {
        $result['errors'][] = 'Database file not found';
        return $result;
    }
    
    if (!is_readable($dbFile)) {
        $result['errors'][] = 'Database file not readable (permission denied)';
        return $result;
    }
    
    $result['readable'] = true;
    
    // Try to connect to database
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $result['errors'][] = 'Invalid SQLite database: ' . $e->getMessage();
        return $result;
    }
    
    $result['database_valid'] = true;
    
    // Required tables based on schema
    $required_tables = [
        'organism',
        'genome',
        'feature',
        'annotation_source',
        'annotation',
        'feature_annotation'
    ];
    
    // Check which tables exist
    try {
        $stmt = $dbh->query("SELECT name FROM sqlite_master WHERE type='table'");
        $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($required_tables as $table) {
            if (in_array($table, $existing_tables)) {
                $result['tables_present'][] = $table;
            } else {
                $result['tables_missing'][] = $table;
            }
        }
    } catch (PDOException $e) {
        $result['errors'][] = 'Cannot query tables: ' . $e->getMessage();
        $dbh = null;
        return $result;
    }
    
    // Check row counts and data quality
    try {
        foreach ($result['tables_present'] as $table) {
            $stmt = $dbh->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            $result['row_counts'][$table] = $count;
        }
        
        // Check for data quality issues
        
        // 1. Check for annotations without sources (orphaned)
        if (in_array('annotation', $result['tables_present']) && in_array('annotation_source', $result['tables_present'])) {
            $stmt = $dbh->query("
                SELECT COUNT(*) FROM annotation a 
                LEFT JOIN annotation_source ans ON a.annotation_source_id = ans.annotation_source_id 
                WHERE ans.annotation_source_id IS NULL
            ");
            $orphaned_count = $stmt->fetchColumn();
            if ($orphaned_count > 0) {
                $result['data_issues'][] = "Orphaned annotations (no source): $orphaned_count";
            }
        }
        
        // 2. Check for incomplete annotations (missing accession or description)
        if (in_array('annotation', $result['tables_present'])) {
            $stmt = $dbh->query("
                SELECT COUNT(*) FROM annotation 
                WHERE annotation_accession IS NULL OR annotation_accession = ''
            ");
            $missing_accession = $stmt->fetchColumn();
            if ($missing_accession > 0) {
                $result['data_issues'][] = "Annotations with missing accession: $missing_accession";
            }
        }
        
        // 3. Check for features without organisms
        if (in_array('feature', $result['tables_present']) && in_array('organism', $result['tables_present'])) {
            $stmt = $dbh->query("
                SELECT COUNT(*) FROM feature f 
                LEFT JOIN organism o ON f.organism_id = o.organism_id 
                WHERE o.organism_id IS NULL
            ");
            $orphaned_features = $stmt->fetchColumn();
            if ($orphaned_features > 0) {
                $result['data_issues'][] = "Features without organism: $orphaned_features";
            }
        }
        
    } catch (PDOException $e) {
        $result['errors'][] = 'Data quality check failed: ' . $e->getMessage();
    }
    
    $dbh = null;
    
    // Determine overall validity
    $result['valid'] = (
        $result['readable'] && 
        $result['database_valid'] && 
        empty($result['tables_missing']) && 
        empty($result['data_issues']) &&
        empty($result['errors'])
    );
    
    return $result;
}

/**
 * Get database connection
 * 
 * @param string $dbFile - Path to SQLite database file
 * @return PDO - Database connection
 * @throws PDOException if connection fails
 */
function getDbConnection($dbFile) {
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $dbh;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Execute SQL query with prepared statement
 * 
 * @param string $sql - SQL query with ? placeholders
 * @param array $params - Parameters to bind to query
 * @param string $dbFile - Path to SQLite database file
 * @return array - Array of associative arrays (results)
 * @throws PDOException if query fails
 */
function fetchData($sql, $params = [], $dbFile) {
    try {
        $dbh = getDbConnection($dbFile);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbh = null;
        return $result;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

/**
 * Build SQL LIKE conditions for multi-column search
 * Supports both quoted (phrase) and unquoted (word-by-word) searches
 * 
 * Creates SQL WHERE clause fragments for searching multiple columns.
 * Supports both keyword search (AND logic) and quoted phrase search.
 * 
 * Keyword search: "ABC transporter"
 *   - Splits into terms: ["ABC", "transporter"]
 *   - Logic: (col1 LIKE '%ABC%' OR col2 LIKE '%ABC%') AND (col1 LIKE '%transporter%' OR col2 LIKE '%transporter%')
 *   - Result: Both terms must match somewhere
 * 
 * Quoted search: '"ABC transporter"'
 *   - Keeps as single phrase: "ABC transporter"
 *   - Logic: (col1 LIKE '%ABC transporter%' OR col2 LIKE '%ABC transporter%')
 *   - Result: Exact phrase must match
 * 
 * @param array $columns - Column names to search
 * @param string $search - Search string (unquoted: words separated by space, quoted: single phrase)
 * @param bool $quoted - If true, treat entire $search as single phrase; if false, split on whitespace
 * @return array - [$sqlFragment, $params] for use with fetchData()
 */
function buildLikeConditions($columns, $search, $quoted = false) {
    $conditions = [];
    $params = [];

    if ($quoted) {
        $searchConditions = [];
        foreach ($columns as $col) {
            $searchConditions[] = "$col LIKE ?";
            $params[] = "%" . $search . "%";
        }
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
    } else {
        $terms = preg_split('/\s+/', trim($search));
        foreach ($terms as $term) {
            if (empty($term)) continue;
            $termConditions = [];
            foreach ($columns as $col) {
                $termConditions[] = "$col LIKE ?";
                $params[] = "%" . $term . "%";
            }
            $conditions[] = "(" . implode(" OR ", $termConditions) . ")";
        }
    }

    $sqlFragment = implode(" AND ", $conditions);
    return [$sqlFragment, $params];
}

/**
 * Sanitizes and validates search input
 * 
 * Removes dangerous characters, filters short terms, and cleans the input
 * to prevent SQL injection and ensure valid search terms.
 * 
 * @param string $data - Raw search input from user
 * @param bool $quoted_search - Whether this is a quoted phrase search
 * @return string - Sanitized search string
 */
function sanitize_search_input($data, $quoted_search) {
    // Remove quotes if this is a quoted search
    if ($quoted_search) {
        $data = trim($data, '"');
    }
    
    // Remove potentially dangerous characters
    $data = preg_replace('/[\<\>\t\;]+/', ' ', $data);
    $data = htmlspecialchars($data);
    
    // Filter out short terms (less than 3 characters) unless it's a quoted search
    if (preg_match('/\s+/', $data)) {
        $data_array = explode(' ', $data, 99);
        foreach ($data_array as $key => &$value) {
            if (strlen($value) < 3 && !$quoted_search) {
                unset($data_array[$key]);
            }
        }
        $data = implode(' ', $data_array);
    }
    
    $data = stripslashes($data);
    return $data;
}

/**
 * Sanitize database input - remove dangerous characters for SQL queries
 * 
 * NOTE: This is a DEFENSIVE measure. Prepared statements with parameter binding
 * (used via fetchData()) are the PRIMARY protection against SQL injection.
 * Use this only for additional safety when raw input might be logged or displayed.
 * 
 * @param string $data - Raw input from user
 * @return string - Sanitized string safe for SQL logging/display
 */
function sanitize_database_input($data) {
    $data = stripslashes($data);
    $data = preg_replace('/[\<\>]+/', '', $data);
    return $data;
}

/**
 * Sanitize HTML output - escape special characters for HTML context
 * 
 * Use this at the point where data is output to HTML, not before database queries.
 * This is context-specific escaping that should be applied just before rendering.
 * 
 * @param string $data - Data to be output in HTML
 * @return string - HTML-safe string with entities escaped
 */
function sanitize_html_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Validates that a search term meets minimum requirements
 * 
 * @param string $search_term - The search term to validate
 * @param int $min_length - Minimum length required (default: 3)
 * @return bool - True if valid, false otherwise
 */
function validate_search_term($search_term, $min_length = 3) {
    $trimmed = trim($search_term);
    
    // Check minimum length
    if (strlen($trimmed) < $min_length) {
        return false;
    }
    
    // For quoted searches, check the content inside quotes
    if (preg_match('/^"(.+)"$/', $trimmed, $matches)) {
        return strlen(trim($matches[1])) >= $min_length;
    }
    
    return true;
}

/**
 * Detects if a search query is a quoted phrase search
 * 
 * @param string $search_term - The search term to check
 * @return bool - True if the search term is wrapped in quotes
 */
function is_quoted_search($search_term) {
    return preg_match('/^".+"$/', trim($search_term)) === 1;
}

/**
 * Validate assembly directories against database genome records
 * 
 * Checks if assembly directories exist for each genome record
 * and if genome_name/genome_accession match directory names
 * 
 * @param string $dbFile - Path to SQLite database
 * @param string $organism_data_dir - Path to organism data directory
 * @return array - Validation results with mismatches and genome info
 */
function validateAssemblyDirectories($dbFile, $organism_data_dir) {
    $result = [
        'valid' => true,
        'genomes' => [],
        'mismatches' => [],
        'errors' => []
    ];
    
    if (!file_exists($dbFile) || !is_readable($dbFile)) {
        $result['valid'] = false;
        $result['errors'][] = 'Database not readable';
        return $result;
    }
    
    if (!is_dir($organism_data_dir)) {
        $result['valid'] = false;
        $result['errors'][] = 'Organism directory not found';
        return $result;
    }
    
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get all genome records
        $stmt = $dbh->query("SELECT genome_id, genome_name, genome_accession FROM genome ORDER BY genome_name");
        $genomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get list of directories in organism folder
        $dirs = array_diff(scandir($organism_data_dir), ['.', '..']);
        $dir_names = [];
        foreach ($dirs as $dir) {
            $full_path = "$organism_data_dir/$dir";
            if (is_dir($full_path)) {
                $dir_names[] = $dir;
            }
        }
        
        // Check each genome record
        foreach ($genomes as $genome) {
            $name = $genome['genome_name'];
            $accession = $genome['genome_accession'];
            $genome_id = $genome['genome_id'];
            
            // Check if either name or accession matches a directory
            $found_dir = null;
            if (in_array($name, $dir_names)) {
                $found_dir = $name;
            } elseif (in_array($accession, $dir_names)) {
                $found_dir = $accession;
            }
            
            $result['genomes'][] = [
                'genome_id' => $genome_id,
                'genome_name' => $name,
                'genome_accession' => $accession,
                'directory_found' => $found_dir,
                'exists' => $found_dir !== null
            ];
            
            if ($found_dir === null) {
                $result['valid'] = false;
                $result['mismatches'][] = [
                    'type' => 'missing_directory',
                    'genome_name' => $name,
                    'genome_accession' => $accession,
                    'message' => "No directory found matching genome_name '$name' or genome_accession '$accession'"
                ];
            } elseif ($found_dir !== $name && $found_dir !== $accession) {
                // Directory exists but doesn't match expected names
                $result['mismatches'][] = [
                    'type' => 'name_mismatch',
                    'genome_name' => $name,
                    'genome_accession' => $accession,
                    'found_directory' => $found_dir,
                    'message' => "Directory '$found_dir' found, but doesn't match genome_name '$name' or genome_accession '$accession'"
                ];
            }
        }
        
        $dbh = null;
    } catch (PDOException $e) {
        $result['valid'] = false;
        $result['errors'][] = 'Database query failed: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Validate assembly FASTA files exist
 * 
 * Checks if each assembly directory contains the required FASTA files
 * based on sequence_types patterns from site config
 * 
 * @param string $organism_dir - Path to organism directory
 * @param array $sequence_types - Sequence type patterns from site_config
 * @return array - Validation results for each assembly
 */
function validateAssemblyFastaFiles($organism_dir, $sequence_types) {
    $result = [
        'assemblies' => [],
        'missing_files' => []
    ];
    
    if (!is_dir($organism_dir)) {
        return $result;
    }
    
    // Get all directories in organism folder
    $dirs = array_diff(scandir($organism_dir), ['.', '..']);
    
    foreach ($dirs as $dir) {
        $full_path = "$organism_dir/$dir";
        if (!is_dir($full_path)) {
            continue;
        }
        
        $assembly_info = [
            'name' => $dir,
            'fasta_files' => [],
            'missing_patterns' => []
        ];
        
        // Check for each sequence type pattern
        foreach ($sequence_types as $type => $config) {
            $pattern = $config['pattern'];
            $files = glob("$full_path/*$pattern");
            
            if (!empty($files)) {
                $assembly_info['fasta_files'][$type] = [
                    'found' => true,
                    'pattern' => $pattern,
                    'file' => basename($files[0])
                ];
            } else {
                $assembly_info['fasta_files'][$type] = [
                    'found' => false,
                    'pattern' => $pattern
                ];
                $assembly_info['missing_patterns'][] = $pattern;
            }
        }
        
        $result['assemblies'][$dir] = $assembly_info;
        
        if (!empty($assembly_info['missing_patterns'])) {
            $result['missing_files'][$dir] = $assembly_info['missing_patterns'];
        }
    }
    
    return $result;
}

/**
 * Rename an assembly directory
 * 
 * Renames a directory within an organism folder from old_name to new_name
 * Used to align directory names with genome_name or genome_accession
 * Returns manual command if automatic rename fails
 * 
 * @param string $organism_dir - Path to organism directory
 * @param string $old_name - Current directory name
 * @param string $new_name - New directory name
 * @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
 */
function renameAssemblyDirectory($organism_dir, $old_name, $new_name) {
    $result = [
        'success' => false,
        'message' => '',
        'command' => ''
    ];
    
    if (!is_dir($organism_dir)) {
        $result['message'] = 'Organism directory not found';
        return $result;
    }
    
    $old_path = "$organism_dir/$old_name";
    $new_path = "$organism_dir/$new_name";
    
    // Validate old directory exists
    if (!is_dir($old_path)) {
        $result['message'] = "Directory '$old_name' not found";
        return $result;
    }
    
    // Check new name doesn't already exist
    if (is_dir($new_path) || file_exists($new_path)) {
        $result['message'] = "Directory '$new_name' already exists";
        return $result;
    }
    
    // Sanitize names to prevent path traversal
    if (strpos($old_name, '/') !== false || strpos($new_name, '/') !== false ||
        strpos($old_name, '..') !== false || strpos($new_name, '..') !== false) {
        $result['message'] = 'Invalid directory name (contains path separators)';
        return $result;
    }
    
    // Build command for admin to run if automatic rename fails
    $result['command'] = "cd " . escapeshellarg($organism_dir) . " && mv " . escapeshellarg($old_name) . " " . escapeshellarg($new_name);
    
    // Try to rename
    if (@rename($old_path, $new_path)) {
        $result['success'] = true;
        $result['message'] = "Successfully renamed '$old_name' to '$new_name'";
    } else {
        $result['message'] = 'Web server lacks permission to rename directory.';
    }
    
    return $result;
}

/**
 * Delete an assembly directory
 * 
 * Recursively deletes a directory within an organism folder
 * Used to remove incorrectly named or unused assembly directories
 * Returns manual command if automatic delete fails
 * 
 * @param string $organism_dir - Path to organism directory
 * @param string $dir_name - Directory name to delete
 * @return array - ['success' => bool, 'message' => string, 'command' => string (if manual fix needed)]
 */
function deleteAssemblyDirectory($organism_dir, $dir_name) {
    $result = [
        'success' => false,
        'message' => '',
        'command' => ''
    ];
    
    if (!is_dir($organism_dir)) {
        $result['message'] = 'Organism directory not found';
        return $result;
    }
    
    $dir_path = "$organism_dir/$dir_name";
    
    // Validate directory exists
    if (!is_dir($dir_path)) {
        $result['message'] = "Directory '$dir_name' not found";
        return $result;
    }
    
    // Prevent deletion of non-assembly directories
    if ($dir_name === '.' || $dir_name === '..' || strpos($dir_name, '/') !== false || 
        strpos($dir_name, '..') !== false || $dir_name === 'organism.json') {
        $result['message'] = 'Invalid directory name (security check failed)';
        return $result;
    }
    
    // Build command for admin to run if automatic delete fails
    $result['command'] = "rm -rf " . escapeshellarg($dir_path);
    
    // Try to delete recursively
    if (rrmdir($dir_path)) {
        $result['success'] = true;
        $result['message'] = "Successfully deleted directory '$dir_name'";
    } else {
        $result['message'] = 'Web server lacks permission to delete directory.';
    }
    
    return $result;
}

/**
 * Recursively remove directory
 * 
 * Helper function to delete a directory and all its contents
 * 
 * @param string $dir - Directory path
 * @return bool - True if successful
 */
function rrmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (!rrmdir($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }
    
    return @rmdir($dir);
}

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
 * ==========================================
 * ERROR HANDLING FUNCTIONS
 * ==========================================
 */

/**
 * Log an error to the error log file
 * 
 * @param string $error_message The error message to log
 * @param string $context Optional context (e.g., feature name, organism)
 * @param array $additional_info Optional array of additional info to log
 */
function logError($error_message, $context = '', $additional_info = []) {
    $log_file = __DIR__ . '/../logs/error.log';
    
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
    $log_file = __DIR__ . '/../logs/error.log';
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
 * Clear the error log file by backing it up with timestamp
 * 
 * Creates a backup file with format: error.log.backup_YYYYMMDD_HHMMSS
 * Then creates a fresh empty log file.
 * 
 * @return bool True on success, false on failure
 */
function clearErrorLog() {
    $log_file = __DIR__ . '/../logs/error.log';
    
    if (!file_exists($log_file)) {
        return true; // Already empty, nothing to backup
    }
    
    // Create backup filename with timestamp
    $timestamp = date('Ymd_His');
    $backup_file = __DIR__ . '/../logs/error.log.backup_' . $timestamp;
    
    // Move current log to backup
    if (@rename($log_file, $backup_file)) {
        return true;
    }
    
    return false;
}

/**
 * Get all accessible assemblies organized by group and organism
 * 
 * Returns assemblies that the user has access to, organized hierarchically:
 * Group -> Organism -> Assembly
 * 
 * Access is determined by is_public_organism() and has_access() functions:
 * - Admin: sees all assemblies in all groups
 * - Collaborator: sees assemblies in groups they have organism access to, plus Public groups
 * - Public: sees only assemblies in Public groups
 * 
 * @param string $specific_organism Optional: filter to single organism
 * @param string $specific_assembly Optional: filter to single assembly
 * @return array Organized by group => organism => [sources array]
 *              Each source contains: ['organism', 'assembly', 'path', 'groups']
 */
function getAccessibleAssemblies($specific_organism = null, $specific_assembly = null) {
    global $organism_data, $metadata_path;
    
    // Load groups data
    $groups_data = [];
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true) ?: [];
    }
    
    $accessible_sources = [];
    
    // Filter entries based on referrer (specific org/assembly or all)
    $entries_to_process = $groups_data;
    
    if (!empty($specific_organism)) {
        $entries_to_process = array_filter($groups_data, function($entry) use ($specific_organism) {
            return $entry['organism'] === $specific_organism;
        });
    }
    
    if (!empty($specific_assembly)) {
        $entries_to_process = array_filter($entries_to_process, function($entry) use ($specific_assembly) {
            return $entry['assembly'] === $specific_assembly;
        });
    }
    
    // Build list of accessible sources using assembly-based permissions
    foreach ($entries_to_process as $entry) {
        $org = $entry['organism'];
        $assembly = $entry['assembly'];
        $entry_groups = $entry['groups'] ?? [];
        
        // Check if user has access to this specific assembly
        // 1. ALL/Admin users have access to everything
        // 2. Public assemblies are accessible to everyone
        // 3. Collaborators can access assemblies in their $_SESSION['access'] list
        $access_granted = false;
        
        if (has_access('ALL')) {
            $access_granted = true;
        } elseif (is_public_assembly($org, $assembly)) {
            $access_granted = true;
        } elseif (has_access('Collaborator')) {
            // Check if user has access to this specific assembly
            $user_access = get_user_access();
            if (isset($user_access[$org]) && is_array($user_access[$org]) && in_array($assembly, $user_access[$org])) {
                $access_granted = true;
            }
        }
        
        if ($access_granted) {
            $assembly_path = "$organism_data/$org/$assembly";
            
            // Only include assembly if directory exists AND has FASTA files
            if (is_dir($assembly_path)) {
                // Check if assembly has any FASTA files (protein, transcript, cds, or genome)
                $has_fasta = false;
                foreach (['.fa', '.fasta', '.faa', '.nt.fa', '.aa.fa'] as $ext) {
                    if (glob("$assembly_path/*$ext")) {
                        $has_fasta = true;
                        break;
                    }
                }
                
                if ($has_fasta) {
                    $accessible_sources[] = [
                        'organism' => $org,
                        'assembly' => $assembly,
                        'path' => $assembly_path,
                        'groups' => $entry_groups
                    ];
                }
            }
        }
    }
    
    // Organize by group -> organism
    $organized = [];
    foreach ($accessible_sources as $source) {
        foreach ($source['groups'] as $group) {
            if (!isset($organized[$group])) {
                $organized[$group] = [];
            }
            $org = $source['organism'];
            if (!isset($organized[$group][$org])) {
                $organized[$group][$org] = [];
            }
            $organized[$group][$org][] = $source;
        }
    }
    
    // Sort groups (Public first, then alphabetically)
    uksort($organized, function($a, $b) {
        if ($a === 'Public') return -1;
        if ($b === 'Public') return 1;
        return strcasecmp($a, $b);
    });
    
    // Sort organisms within each group alphabetically
    foreach ($organized as &$group_data) {
        ksort($group_data);
    }
    
    return $organized;
}

/**
 * Get available tools filtered by context
 * Returns only tools that have the required context parameters available
 * 
 * @param array $context - Context array with optional keys: organism, assembly, group, display_name
 * @return array - Array of available tools with built URLs
 */
function getAvailableTools($context = []) {
    global $site, $available_tools;
    
    // Include tool configuration
    include_once __DIR__ . '/tool_config.php';
    
    // If $available_tools not set by include, return empty array
    if (!isset($available_tools) || !is_array($available_tools)) {
        return [];
    }
    
    // Get current page from context (optional)
    $current_page = $context['page'] ?? null;
    
    $tools = [];
    foreach ($available_tools as $tool_id => $tool) {
        // Check page visibility - skip if tool doesn't show on this page
        if ($current_page && !isToolVisibleOnPage($tool, $current_page)) {
            continue;
        }
        
        $url = buildToolUrl($tool_id, $context, $site);
        if ($url) {
            $tools[$tool_id] = array_merge($tool, ['url' => $url]);
        }
    }
    
    return $tools;
}

/**
 * Get genome IDs for accessible assemblies
 * Converts assembly names/accessions to genome_ids for efficient querying
 * 
 * @param string $organism_name The organism name
 * @param array $accessible_assemblies Array of assembly names/accessions user can access
 * @param string $db_path Path to SQLite database
 * @return array Array of genome_ids
 */
function getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db_path) {
    if (empty($accessible_assemblies)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($accessible_assemblies), '?'));
    $query = "SELECT DISTINCT genome_id FROM genome 
              WHERE genome_name IN ($placeholders) 
              OR genome_accession IN ($placeholders)";
    
    // Pass accessible_assemblies twice - once for names, once for accessions
    $params = array_merge($accessible_assemblies, $accessible_assemblies);
    $results = fetchData($query, $params, $db_path);
    
    return array_column($results, 'genome_id');
}

/**
 * Get group metadata from organism_assembly_groups.json
 * 
 * @return array Array of organism/assembly/groups data
 */
function getGroupData() {
    global $metadata_path;
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    $groups_data = [];
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true);
    }
    return $groups_data;
}

/**
 * Get all group cards from metadata
 * Returns card objects for every group in the system
 * 
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Associative array of group_name => card_info
 */
function getAllGroupCards($group_data) {
    $cards = [];
    foreach ($group_data as $data) {
        foreach ($data['groups'] as $group) {
            if (!isset($cards[$group])) {
                $cards[$group] = [
                    'title' => $group,
                    'text' => "Explore $group Data",
                    'link' => 'tools/display/groups_display.php?group=' . urlencode($group)
                ];
            }
        }
    }
    return $cards;
}

/**
 * Get group cards that have at least one public assembly
 * Returns card objects only for groups containing assemblies in the "Public" group
 * 
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Associative array of group_name => card_info for public groups only
 */
function getPublicGroupCards($group_data) {
    $public_groups = [];
    
    // Find all groups that contain at least one public assembly
    foreach ($group_data as $data) {
        if (in_array('Public', $data['groups'])) {
            foreach ($data['groups'] as $group) {
                if (!isset($public_groups[$group])) {
                    $public_groups[$group] = [
                        'title' => $group,
                        'text' => "Explore $group Data",
                        'link' => 'tools/display/groups_display.php?group=' . urlencode($group)
                    ];
                }
            }
        }
    }
    return $public_groups;
}

/**
 * Filter organisms in a group to only those with at least one accessible assembly
 * Respects user permissions for assembly access
 * 
 * @param string $group_name The group name to filter
 * @param array $group_data Array of organism/assembly/groups data
 * @return array Filtered array of organism => [accessible_assemblies]
 */
function getAccessibleOrganismsInGroup($group_name, $group_data) {
    $group_organisms = [];
    
    // Find all organisms/assemblies in this group
    foreach ($group_data as $data) {
        if (in_array($group_name, $data['groups'])) {
            $organism = $data['organism'];
            $assembly = $data['assembly'];
            
            if (!isset($group_organisms[$organism])) {
                $group_organisms[$organism] = [];
            }
            $group_organisms[$organism][] = $assembly;
        }
    }
    
    // Filter: only keep organisms with at least one accessible assembly
    $accessible_organisms = [];
    foreach ($group_organisms as $organism => $assemblies) {
        $has_accessible_assembly = false;
        
        foreach ($assemblies as $assembly) {
            // Check if user has access to this specific assembly
            if (has_assembly_access($organism, $assembly)) {
                $has_accessible_assembly = true;
                break;
            }
        }
        
        if ($has_accessible_assembly) {
            $accessible_organisms[$organism] = $assemblies;
        }
    }
    
    // Sort organisms alphabetically
    ksort($accessible_organisms);
    
    return $accessible_organisms;
}

/**
 * Get FASTA files for an assembly
 * 
 * Scans the assembly directory for FASTA files matching configured sequence types.
 * Uses patterns from $sequence_types global to identify file types (genome, protein, transcript, cds).
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_name The assembly name (accession)
 * @return array Associative array of type => ['path' => relative_path, 'label' => label]
 */
function getAssemblyFastaFiles($organism_name, $assembly_name) {
    global $organism_data, $sequence_types;
    $fasta_files = [];
    $assembly_dir = "$organism_data/$organism_name/$assembly_name";
    
    if (is_dir($assembly_dir)) {
        $fasta_files_found = glob($assembly_dir . '/*.fa');
        foreach ($fasta_files_found as $fasta_file) {
            $filename = basename($fasta_file);
            $relative_path = "$organism_name/$assembly_name/$filename";
            
            foreach ($sequence_types as $type => $config) {
                if (strpos($filename, $config['pattern']) !== false) {
                    $fasta_files[$type] = [
                        'path' => $relative_path,
                        'label' => $config['label']
                    ];
                    break;
                }
            }
        }
    }
    return $fasta_files;
}

/**
 * Load organism info and get image path
 * 
 * Loads organism.json file and returns the image path using getOrganismImagePath()
 * Encapsulates all the loading logic in one place.
 * 
 * @param string $organism_name The organism name
 * @param string $images_path URL path to images directory (e.g., 'moop/images')
 * @param string $absolute_images_path Absolute file system path to images directory
 * @return array ['organism_info' => array, 'image_path' => string]
 */
function loadOrganismAndGetImagePath($organism_name, $images_path = 'moop/images', $absolute_images_path = '') {
    global $organism_data;
    
    $result = [
        'organism_info' => [],
        'image_path' => ''
    ];
    
    $organism_json_path = "$organism_data/$organism_name/organism.json";
    if (file_exists($organism_json_path)) {
        $organism_info = json_decode(file_get_contents($organism_json_path), true);
        if ($organism_info) {
            $result['organism_info'] = $organism_info;
            $result['image_path'] = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
        }
    }
    
    return $result;
}

/**
 * Get organism image file path
 * 
 * Returns the URL path to an organism's image with fallback logic:
 * 1. Custom image from organism.json if defined
 * 2. NCBI taxonomy image if taxon_id exists and image file found
 * 3. Empty string if no image available
 * 
 * @param array $organism_info Array from organism.json with keys: images, taxon_id
 * @param string $images_path URL path to images directory (e.g., 'moop/images')
 * @param string $absolute_images_path Absolute file system path to images directory
 * @return string URL path to image file or empty string if no image
 */
function getOrganismImagePath($organism_info, $images_path = 'moop/images', $absolute_images_path = '') {
    // Validate input
    if (empty($organism_info) || !is_array($organism_info)) {
        logError('getOrganismImagePath received invalid organism_info', 'organism_image', [
            'organism_info_type' => gettype($organism_info),
            'organism_info_empty' => empty($organism_info)
        ]);
        return '';
    }
    
    // Check for custom image first
    if (!empty($organism_info['images']) && is_array($organism_info['images'])) {
        return "/$images_path/" . htmlspecialchars($organism_info['images'][0]['file']);
    }
    
    // Fall back to NCBI taxonomy image if taxon_id exists
    if (!empty($organism_info['taxon_id'])) {
        // Construct path - use absolute_images_path if provided, otherwise fall back
        if (!empty($absolute_images_path)) {
            $ncbi_image_file = "$absolute_images_path/ncbi_taxonomy/" . $organism_info['taxon_id'] . '.jpg';
        } else {
            $ncbi_image_file = __DIR__ . '/../../images/ncbi_taxonomy/' . $organism_info['taxon_id'] . '.jpg';
        }
        
        if (file_exists($ncbi_image_file)) {
            return "/moop/images/ncbi_taxonomy/" . $organism_info['taxon_id'] . ".jpg";
        } else {
            logError('NCBI taxonomy image not found', 'organism_image', [
                'taxon_id' => $organism_info['taxon_id'],
                'expected_path' => $ncbi_image_file
            ]);
        }
    }
    
    return '';
}

/**
 * Get organism image caption with optional link
 * 
 * Returns display caption for organism image:
 * - Custom images: caption from organism.json or empty string
 * - NCBI taxonomy fallback: "Image from NCBI Taxonomy" with link to NCBI
 * 
 * @param array $organism_info Array from organism.json with keys: images, taxon_id
 * @param string $absolute_images_path Absolute file system path to images directory
 * @return array ['caption' => caption text, 'link' => URL or empty string]
 */
function getOrganismImageCaption($organism_info, $absolute_images_path = '') {
    $result = [
        'caption' => '',
        'link' => ''
    ];
    
    // Validate input
    if (empty($organism_info) || !is_array($organism_info)) {
        logError('getOrganismImageCaption received invalid organism_info', 'organism_image', [
            'organism_info_type' => gettype($organism_info),
            'organism_info_empty' => empty($organism_info)
        ]);
        return $result;
    }
    
    // Custom image caption
    if (!empty($organism_info['images']) && is_array($organism_info['images'])) {
        if (!empty($organism_info['images'][0]['caption'])) {
            $result['caption'] = $organism_info['images'][0]['caption'];
        }
        return $result;
    }
    
    // NCBI taxonomy caption with link
    if (!empty($organism_info['taxon_id'])) {
        // Construct path - use absolute_images_path if provided, otherwise fall back
        if (!empty($absolute_images_path)) {
            $ncbi_image_file = "$absolute_images_path/ncbi_taxonomy/" . $organism_info['taxon_id'] . '.jpg';
        } else {
            $ncbi_image_file = __DIR__ . '/../../images/ncbi_taxonomy/' . $organism_info['taxon_id'] . '.jpg';
        }
        
        if (file_exists($ncbi_image_file)) {
            $result['caption'] = 'Image from NCBI Taxonomy';
            $result['link'] = 'https://www.ncbi.nlm.nih.gov/datasets/taxonomy/' . htmlspecialchars($organism_info['taxon_id']);
        }
    }
    
    return $result;
}

/**
 * Check file write permission and return error info if not writable
 * Keeps original owner, changes group to web server only
 * 
 * @param string $filepath Path to file to check
 * @return array|null Array with error details if not writable, null if writable
 */
function getFileWriteError($filepath) {
    if (!file_exists($filepath) || is_writable($filepath)) {
        return null;
    }
    
    $webserver = getWebServerUser();
    $web_user = $webserver['user'];
    $web_group = $webserver['group'];
    
    $current_owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($filepath))['name'] ?? 'unknown' : 'unknown';
    $current_perms = substr(sprintf('%o', fileperms($filepath)), -4);
    
    // Command keeps original owner but changes group to webserver and sets perms to 664
    $fix_command = "sudo chgrp " . escapeshellarg($web_group) . " " . escapeshellarg($filepath) . " && sudo chmod 664 " . escapeshellarg($filepath);
    
    return [
        'owner' => $current_owner,
        'perms' => $current_perms,
        'web_user' => $web_user,
        'web_group' => $web_group,
        'command' => $fix_command,
        'file' => $filepath
    ];
}

/**
 * Check directory existence and writeability, return error info if issues found
 * Uses owner of /moop directory and web server group
 * Automatically detects if sudo is needed for the commands
 * 
 * Usage:
 *   $dir_error = getDirectoryError('/path/to/directory');
 *   if ($dir_error) {
 *       // Display error alert with fix instructions
 *   }
 * 
 * Can be used in any admin page that needs to ensure a directory exists and is writable.
 * Common use cases:
 *   - Image cache directories (ncbi_taxonomy, organisms, etc)
 *   - Log directories
 *   - Upload/temp directories
 *   - Any other required filesystem paths
 * 
 * @param string $dirpath Path to directory to check
 * @return array|null Array with error details if directory missing/not writable, null if ok
 */
function getDirectoryError($dirpath) {
    if (is_dir($dirpath) && is_writable($dirpath)) {
        return null;
    }
    
    $webserver = getWebServerUser();
    $web_group = $webserver['group'];
    
    // Get owner from /moop directory
    $moop_owner = 'ubuntu';  // Default fallback
    if (function_exists('posix_getpwuid')) {
        $moop_info = @stat(__DIR__ . '/..');  // Get stat of /moop parent directory
        if ($moop_info) {
            $moop_pwd = posix_getpwuid($moop_info['uid']);
            if ($moop_pwd) {
                $moop_owner = $moop_pwd['name'];
            }
        }
    }
    
    // Detect if sudo is needed
    $current_uid = function_exists('posix_getuid') ? posix_getuid() : null;
    $need_sudo = false;
    
    if ($current_uid !== null && $current_uid !== 0) {
        // Not running as root, check if we're the moop owner
        $current_user = function_exists('posix_getpwuid') ? posix_getpwuid($current_uid)['name'] ?? null : null;
        if ($current_user !== $moop_owner) {
            $need_sudo = true;
        }
    }
    
    // Helper function to add sudo if needed
    $cmd_prefix = $need_sudo ? 'sudo ' : '';
    
    if (!is_dir($dirpath)) {
        // Directory doesn't exist
        return [
            'type' => 'missing',
            'dir' => $dirpath,
            'owner' => $moop_owner,
            'group' => $web_group,
            'need_sudo' => $need_sudo,
            'commands' => [
                "{$cmd_prefix}mkdir -p " . escapeshellarg($dirpath),
                "{$cmd_prefix}chown {$moop_owner}:{$web_group} " . escapeshellarg($dirpath),
                "{$cmd_prefix}chmod 775 " . escapeshellarg($dirpath)
            ]
        ];
    }
    
    // Directory exists but not writable
    $current_owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($dirpath))['name'] ?? 'unknown' : 'unknown';
    $current_perms = substr(sprintf('%o', fileperms($dirpath)), -4);
    
    return [
        'type' => 'not_writable',
        'dir' => $dirpath,
        'owner' => $current_owner,
        'perms' => $current_perms,
        'target_owner' => $moop_owner,
        'target_group' => $web_group,
        'need_sudo' => $need_sudo,
        'commands' => [
            "{$cmd_prefix}chown {$moop_owner}:{$web_group} " . escapeshellarg($dirpath),
            "{$cmd_prefix}chmod 775 " . escapeshellarg($dirpath)
        ]
    ];
}

/**
 * Validate organism.json file
 * 
 * Checks:
 * - File exists
 * - File is readable
 * - Valid JSON format
 * - Contains required fields (genus, species, common_name, taxon_id)
 * 
 * @param string $json_path - Path to organism.json file
 * @return array - Validation results with status and details
 */
function validateOrganismJson($json_path) {
    $validation = [
        'exists' => false,
        'readable' => false,
        'writable' => false,
        'valid_json' => false,
        'has_required_fields' => false,
        'required_fields' => ['genus', 'species', 'common_name', 'taxon_id'],
        'missing_fields' => [],
        'errors' => []
    ];
    
    if (!file_exists($json_path)) {
        $validation['errors'][] = 'organism.json file does not exist';
        return $validation;
    }
    
    $validation['exists'] = true;
    
    if (!is_readable($json_path)) {
        $validation['errors'][] = 'organism.json file is not readable';
        return $validation;
    }
    
    $validation['readable'] = true;
    $validation['writable'] = is_writable($json_path);
    
    $content = file_get_contents($json_path);
    $json_data = json_decode($content, true);
    
    if ($json_data === null) {
        $validation['errors'][] = 'organism.json contains invalid JSON: ' . json_last_error_msg();
        return $validation;
    }
    
    $validation['valid_json'] = true;
    
    // Handle wrapped JSON (single-level wrapping)
    if (!isset($json_data['genus']) && !isset($json_data['common_name'])) {
        $keys = array_keys($json_data);
        if (count($keys) > 0 && is_array($json_data[$keys[0]])) {
            $json_data = $json_data[$keys[0]];
        }
    }
    
    // Check for required fields
    foreach ($validation['required_fields'] as $field) {
        if (!isset($json_data[$field]) || empty($json_data[$field])) {
            $validation['missing_fields'][] = $field;
        }
    }
    
    $validation['has_required_fields'] = empty($validation['missing_fields']);
    
    if (!$validation['has_required_fields']) {
        $validation['errors'][] = 'Missing required fields: ' . implode(', ', $validation['missing_fields']);
    }
    
    return $validation;
}

/**
 * ==========================================
 * TOOL CONTEXT HELPER FUNCTIONS
 * ==========================================
 */

/**
 * Create a tool context for the index/home page
 * 
 * @param bool $use_onclick_handler Whether tools need onclick handlers (for phylo tree)
 * @return array Context array for tool_section.php
 */
function createIndexToolContext($use_onclick_handler = true) {
    return [
        'display_name' => 'Multi-Organism Search',
        'page' => 'index',
        'use_onclick_handler' => $use_onclick_handler
    ];
}

/**
 * Create a tool context for an organism display page
 * 
 * @param string $organism_name The organism name
 * @param string $display_name Optional display name (defaults to organism_name)
 * @return array Context array for tool_section.php
 */
function createOrganismToolContext($organism_name, $display_name = null) {
    return [
        'organism' => $organism_name,
        'display_name' => $display_name ?? $organism_name,
        'page' => 'organism'
    ];
}

/**
 * Create a tool context for an assembly display page
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_accession The assembly/genome accession
 * @param string $display_name Optional display name (defaults to assembly_accession)
 * @return array Context array for tool_section.php
 */
function createAssemblyToolContext($organism_name, $assembly_accession, $display_name = null) {
    return [
        'organism' => $organism_name,
        'assembly' => $assembly_accession,
        'display_name' => $display_name ?? $assembly_accession,
        'page' => 'assembly'
    ];
}

/**
 * Create a tool context for a group display page
 * 
 * @param string $group_name The group name
 * @return array Context array for tool_section.php
 */
function createGroupToolContext($group_name) {
    return [
        'group' => $group_name,
        'display_name' => $group_name,
        'page' => 'group'
    ];
}

/**
 * Create a tool context for a feature/parent display page
 * 
 * @param string $organism_name The organism name
 * @param string $assembly_accession The assembly/genome accession
 * @param string $feature_name The feature name
 * @return array Context array for tool_section.php
 */
function createFeatureToolContext($organism_name, $assembly_accession, $feature_name) {
    return [
        'organism' => $organism_name,
        'assembly' => $assembly_accession,
        'display_name' => $feature_name,
        'page' => 'parent'
    ];
}

/**
 * Create a tool context for multi-organism search page
 * 
 * @param array $organisms Array of organism names
 * @param string $display_name Optional display name
 * @return array Context array for tool_section.php
 */
function createMultiOrganismToolContext($organisms, $display_name = 'Multi-Organism Search') {
    return [
        'organisms' => $organisms,
        'display_name' => $display_name,
        'page' => 'multi_organism_search'
    ];
}

/**
 * Get cards to display on index page based on user access level
 * 
 * @param array $group_data Array of group data from getGroupData()
 * @return array Cards to display with title, text, and link
 */
function getIndexDisplayCards($group_data) {
    $cards_to_display = [];
    $all_cards = getAllGroupCards($group_data);
    
    if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {
        $cards_to_display = $all_cards;
    } elseif (is_logged_in()) {
        // Logged-in users see: public groups + their permitted organisms
        $cards_to_display = getPublicGroupCards($group_data);
        
        foreach (get_user_access() as $organism => $assemblies) {
            if (!isset($cards_to_display[$organism])) {
                $formatted_name = formatIndexOrganismName($organism);
                $cards_to_display[$organism] = [
                    'title' => $formatted_name,
                    'text'  => "Explore " . strip_tags($formatted_name) . " Data",
                    'link'  => 'tools/display/organism_display.php?organism=' . urlencode($organism)
                ];
            }
        }
    } else {
        // Visitors see only groups with public assemblies
        $cards_to_display = getPublicGroupCards($group_data);
    }
    
    return $cards_to_display;
}

/**
 * Format organism name for index page display with italics
 * 
 * @param string $organism Organism name with underscores
 * @return string Formatted name with proper capitalization and italics
 */
function formatIndexOrganismName($organism) {
    $parts = explode('_', $organism);
    $formatted_name = ucfirst(strtolower($parts[0]));
    for ($i = 1; $i < count($parts); $i++) {
        $formatted_name .= ' ' . strtolower($parts[$i]);
    }
    return '<i>' . $formatted_name . '</i>';
}

/**
 * Get phylogenetic tree user access based on access level
 * 
 * @param array $group_data Array of group data from getGroupData()
 * @return array User access mapping for phylogenetic tree
 */
function getPhyloTreeUserAccess($group_data) {
    $phylo_user_access = [];
    
    if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {
        // Admin gets access to all organisms
        foreach ($group_data as $data) {
            $organism = $data['organism'];
            if (!isset($phylo_user_access[$organism])) {
                $phylo_user_access[$organism] = true;
            }
        }
    } elseif (is_logged_in()) {
        // Logged-in users get their specific access
        $phylo_user_access = get_user_access();
    } else {
        // Public users: get organisms in Public group
        foreach ($group_data as $data) {
            if (in_array('Public', $data['groups'])) {
                $organism = $data['organism'];
                if (!isset($phylo_user_access[$organism])) {
                    $phylo_user_access[$organism] = true;
                }
            }
        }
    }
    
    return $phylo_user_access;
}

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
 * Require user to have specific access level or redirect to access denied
 * 
 * @param string $level Required access level (e.g., 'Collaborator', 'Admin')
 * @param string $resource Resource name (e.g., group name or organism name)
 * @param array $options Options: ['redirect_on_deny' => true, 'deny_page' => '/moop/access_denied.php']
 * @return bool True if user has access, false otherwise
 */
function requireAccess($level, $resource, $options = []) {
    global $site;
    
    $redirect_on_deny = $options['redirect_on_deny'] ?? true;
    $deny_page = $options['deny_page'] ?? "/$site/access_denied.php";
    
    $has_access = has_access($level, $resource);
    
    if (!$has_access && $redirect_on_deny) {
        header("Location: $deny_page");
        exit;
    }
    
    return $has_access;
}

/**
 * Check if user has access to a resource (without redirect)
 * Convenience wrapper for has_access() with better naming
 * 
 * @param string $level Required access level
 * @param string $resource Resource name
 * @return bool True if user has access
 */
function userHasAccess($level, $resource) {
    return has_access($level, $resource);
}

/**
 * Validate and extract organism parameter from GET/POST
 * Redirects to home if missing/empty
 * 
 * @param string $organism_name Organism name to validate
 * @param string $redirect_on_empty URL to redirect to if empty (default: /moop/index.php)
 * @return string Validated organism name
 */
function validateOrganismParam($organism_name, $redirect_on_empty = '/moop/index.php') {
    if (empty($organism_name)) {
        header("Location: $redirect_on_empty");
        exit;
    }
    return $organism_name;
}

/**
 * Validate and extract assembly parameter from GET/POST
 * Redirects to home if missing/empty
 * 
 * @param string $assembly Assembly accession to validate
 * @param string $redirect_on_empty URL to redirect to if empty
 * @return string Validated assembly name
 */
function validateAssemblyParam($assembly, $redirect_on_empty = '/moop/index.php') {
    if (empty($assembly)) {
        header("Location: $redirect_on_empty");
        exit;
    }
    return $assembly;
}

/**
 * Validate and extract multiple required parameters at once
 * Redirects if any are missing
 * 
 * @param array $params Parameter map: ['name' => value, ...]
 * @param array $required Array of required parameter names
 * @param string $redirect_on_missing URL to redirect to if missing
 * @return array Validated parameters (only required ones)
 */
function validateRequiredParams($params, $required = [], $redirect_on_missing = '/moop/index.php') {
    foreach ($required as $key) {
        if (empty($params[$key] ?? null)) {
            header("Location: $redirect_on_missing");
            exit;
        }
    }
    return array_filter($params, function($k) use ($required) {
        return in_array($k, $required);
    }, ARRAY_FILTER_USE_KEY);
}

/**
 * Load and validate organism info from JSON file
 * Handles improperly wrapped JSON automatically
 * 
 * @param string $organism_name Organism name
 * @param string $organism_data_dir Path to organism data directory (e.g., $organism_data)
 * @return array|null Organism info array or null if not found/invalid
 */
function loadOrganismInfo($organism_name, $organism_data_dir) {
    $organism_json_path = "$organism_data_dir/$organism_name/organism.json";
    $organism_info = loadJsonFile($organism_json_path);
    
    if (!$organism_info) {
        return null;
    }
    
    // Handle improperly wrapped JSON (extra outer braces)
    if (!isset($organism_info['genus']) && !isset($organism_info['common_name'])) {
        $keys = array_keys($organism_info);
        if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]]['genus'])) {
            $organism_info = $organism_info[$keys[0]];
        }
    }
    
    // Validate required fields
    if (!isset($organism_info['common_name']) && !isset($organism_info['genus'])) {
        return null;
    }
    
    return $organism_info;
}

/**
 * Verify organism database file exists
 * 
 * @param string $organism_name Organism name
 * @param string $organism_data_dir Path to organism data directory
 * @return string Database path if exists, exits with error if not
 */
function verifyOrganismDatabase($organism_name, $organism_data_dir) {
    $db_path = "$organism_data_dir/$organism_name/organism.sqlite";
    
    if (!file_exists($db_path)) {
        header("HTTP/1.1 500 Internal Server Error");
        die("Error: Database not found for organism '$organism_name'. Please ensure the organism is properly configured.");
    }
    
    return $db_path;
}

/**
 * Complete setup for organism display pages
 * Validates parameter, loads organism info, checks access, returns context
 * Use this to replace boilerplate in organism_display, assembly_display, parent_display
 * 
 * @param string $organism_name Organism from GET/POST
 * @param string $organism_data_dir Path to organism data directory
 * @param bool $check_access Whether to check access control (default: true)
 * @param string $redirect_home Home URL for redirects (default: /moop/index.php)
 * @return array Array with 'name' and 'info' keys, or exits on error
 */
function setupOrganismDisplayContext($organism_name, $organism_data_dir, $check_access = true, $redirect_home = '/moop/index.php') {
    // Validate organism parameter
    $organism_name = validateOrganismParam($organism_name, $redirect_home);
    
    // Load and validate organism info
    $organism_info = loadOrganismInfo($organism_name, $organism_data_dir);
    
    if (!$organism_info) {
        header("Location: $redirect_home");
        exit;
    }
    
    // Check access (unless it's public)
    if ($check_access) {
        $is_public = is_public_organism($organism_name);
        if (!$is_public) {
            require_access('Collaborator', $organism_name);
        }
    }
    
    return [
        'name' => $organism_name,
        'info' => $organism_info
    ];
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

?>







