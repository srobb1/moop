<?php
/**
 * MOOP Database Functions
 * Database connectivity, query execution, and database integrity validation
 */

/**
 * Validates database file is readable and accessible
 * 
 * @param string $dbFile - Path to SQLite database file
 * @return array - Validation results with 'valid' and 'error' keys
 */
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
function validateDatabaseIntegrity($dbFile) {
    $result = [
        'valid' => false,
        'readable' => false,
        'database_valid' => false,
        'tables_present' => [],
        'tables_missing' => [],
        'row_counts' => [],
        'feature_counts' => [],
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
        
        // Get counts by feature type
        if (in_array('feature', $result['tables_present'])) {
            $stmt = $dbh->query("
                SELECT feature_type, COUNT(*) as count
                FROM feature
                GROUP BY feature_type
                ORDER BY feature_type
            ");
            $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($features as $feature) {
                $result['feature_counts'][$feature['feature_type']] = $feature['count'];
            }
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
        
        // Register custom REGEXP function for word boundary matching
        $dbh->sqliteCreateFunction('REGEXP', function($pattern, $text) {
            return preg_match('/' . $pattern . '/i', $text) ? 1 : 0;
        }, 2);
        
        return $dbh;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Execute SQL query with prepared statement
 * 
 * @param string $sql - SQL query with ? placeholders
 * @param string $dbFile - Path to SQLite database file
 * @param array $params - Parameters to bind to query (optional)
 * @return array - Array of associative arrays (results)
 * @throws PDOException if query fails
 */
function fetchData($sql, $dbFile, $params = []) {
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
 * Get accessible genome IDs from database for organism
 * 
 * @param string $organism_name - Organism name
 * @param array $accessible_assemblies - List of accessible assembly names
 * @param string $db_path - Path to SQLite database file
 * @return array - Array of genome IDs
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
    $results = fetchData($query, $db_path, $params);
    
    return array_column($results, 'genome_id');
}

/**
 * Load organism info from organism.json file
 * 
 * @param string $organism_name - Organism name
 * @param string $organism_data_dir - Path to organism data directory
 * @return array|null - Organism info array or null if not found
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
 * Get organism database path if it exists
 * Returns null if database doesn't exist (doesn't die like verifyOrganismDatabase)
 * 
 * @param string $organism_name - Organism name
 * @param string $organism_data_dir - Path to organism data directory
 * @return string|null - Database path if exists, null if not
 */
function getOrganismDatabase($organism_name, $organism_data_dir) {
    $db_path = "$organism_data_dir/$organism_name/organism.sqlite";
    
    if (!file_exists($db_path)) {
        return null;
    }
    
    return $db_path;
}

/**
 * Verify organism database file exists
 * 
 * @param string $organism_name - Organism name
 * @param string $organism_data_dir - Path to organism data directory
 * @return string - Database path if exists, exits with error if not
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
 * Get all assemblies (genomes) for an organism from its database
 * Filters by user access permissions
 * 
 * @param string $organism_name - Organism name
 * @param string $organism_data_dir - Path to organism data directory
 * @return array - Array of assembly accessions accessible to current user, or empty array if none
 */
function getOrganismAssemblies($organism_name, $organism_data_dir) {
    $db_path = getOrganismDatabase($organism_name, $organism_data_dir);
    
    if (empty($db_path)) {
        return [];
    }
    
    try {
        $dbh = new PDO("sqlite:" . $db_path);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $dbh->query("SELECT genome_accession FROM genome ORDER BY genome_name ASC");
        $genomes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Filter by user access
        $accessible = [];
        foreach ($genomes as $assembly) {
            if (has_assembly_access($organism_name, $assembly)) {
                $accessible[] = $assembly;
            }
        }
        
        return $accessible;
    } catch (PDOException $e) {
        return [];
    }
}
