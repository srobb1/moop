<?php
/**
 * MOOP Core Functions
 * Central utilities for database access, data sanitization, and common operations
 * Used across display, extract, and search modules
 */

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

?>
