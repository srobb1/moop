<?php
/**
 * Search Helper Functions
 * 
 * This file contains database and search-related utility functions
 * used across the search functionality of the application.
 * 
 * Functions:
 * - getDbConnection()        : Establishes SQLite database connection
 * - fetchData()              : Executes prepared SQL queries and returns results
 * - buildLikeConditions()    : Builds SQL LIKE clauses for search queries
 * - sanitize_search_input()  : Cleans and validates search input
 */

/**
 * Establishes a PDO connection to a SQLite database
 * 
 * @param string $dbFile Path to the SQLite database file
 * @return PDO Database connection object
 * @throws PDOException if connection fails
 */
function getDbConnection($dbFile) {
    // TODO: Could be extended to support other database types (MySQL, PostgreSQL)
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $dbh;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Executes a prepared SQL query and returns the results
 * 
 * @param string $sql SQL query with placeholders (?)
 * @param array $params Parameters to bind to the query
 * @param string $dbFile Path to the SQLite database file
 * @return array Array of associative arrays containing query results
 * @throws PDOException if query fails
 */
function fetchData($sql, $params = [], $dbFile) {
    try {
        $dbh = getDbConnection($dbFile);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbh = null; // Close the connection
        return $result;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

/**
 * Builds SQL LIKE conditions for multi-column search
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
 * @param array $columns Array of column names to search
 * @param string $search Search term(s)
 * @param bool $quoted Whether to treat search as a single quoted phrase
 * @return array [SQL fragment string, array of parameters for binding]
 * 
 * @example
 * // Keyword search across 2 columns
 * list($sql, $params) = buildLikeConditions(['name', 'description'], 'ABC transporter', false);
 * // Returns: ["(name LIKE ? OR description LIKE ?) AND (name LIKE ? OR description LIKE ?)", 
 * //           ["%ABC%", "%ABC%", "%transporter%", "%transporter%"]]
 * 
 * @example
 * // Quoted phrase search
 * list($sql, $params) = buildLikeConditions(['name', 'description'], 'ABC transporter', true);
 * // Returns: ["(name LIKE ? OR description LIKE ?)", 
 * //           ["%ABC transporter%", "%ABC transporter%"]]
 */
function buildLikeConditions($columns, $search, $quoted = false) {
    $conditions = [];
    $params = [];

    if ($quoted) {
        // Treat the entire search as a single phrase
        $searchConditions = [];
        foreach ($columns as $col) {
            $searchConditions[] = "$col LIKE ?";
            $params[] = "%" . $search . "%";
        }
        // Phrase can match any column
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
    } else {
        // Split search into individual terms (keywords)
        $terms = preg_split('/\s+/', trim($search));
        
        // Each term must match at least one column
        foreach ($terms as $term) {
            $termConditions = [];
            foreach ($columns as $col) {
                $termConditions[] = "$col LIKE ?";
                $params[] = "%" . $term . "%";
            }
            // Term can match any column (OR within term)
            $conditions[] = "(" . implode(" OR ", $termConditions) . ")";
        }
    }

    // All terms must match (AND between terms)
    $sqlFragment = implode(" AND ", $conditions);

    return [$sqlFragment, $params];
}

/**
 * Sanitizes and validates search input
 * 
 * Removes dangerous characters, filters short terms, and cleans the input
 * to prevent SQL injection and ensure valid search terms.
 * 
 * @param string $data Raw search input from user
 * @param bool $quoted_search Whether this is a quoted phrase search
 * @return string Sanitized search string
 * 
 * @example
 * sanitize_search_input('ABC transporter', false);
 * // Returns: "ABC transporter"
 * 
 * @example
 * sanitize_search_input('a b ABC', false);
 * // Returns: "ABC" (filters out "a" and "b" - too short)
 * 
 * @example
 * sanitize_search_input('"ABC a"', true);
 * // Returns: "ABC a" (keeps short terms in quoted search)
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
 * Validates that a search term meets minimum requirements
 * 
 * @param string $search_term The search term to validate
 * @param int $min_length Minimum length required (default: 3)
 * @return bool True if valid, false otherwise
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
 * @param string $search_term The search term to check
 * @return bool True if the search term is wrapped in quotes
 */
function is_quoted_search($search_term) {
    return preg_match('/^".+"$/', trim($search_term)) === 1;
}

?>
