<?php
/**
 * Input Validation and Sanitization Functions
 * Handles user input validation, search term processing, and parameter validation
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
 * Sanitize search input specifically for use in database search queries
 * 
 * This function handles search-specific sanitization that removes or escapes
 * characters that could interfere with search functionality while preserving
 * useful search characters like spaces, quotes, and basic punctuation.
 * 
 * @param string $input - Raw search input from user
 * @return string - Sanitized search string safe for database queries
 */
function sanitize_search_input($input) {
    // Remove null bytes and control characters
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    // Trim whitespace
    $input = trim($input);
    
    // Remove excessive whitespace (multiple spaces become single space)
    $input = preg_replace('/\s+/', ' ', $input);
    
    return $input;
}

/**
 * Validate a search term for safety and usability
 * 
 * Checks that a search term meets minimum requirements and doesn't contain
 * problematic patterns that could cause issues with database queries or
 * return meaningless results.
 * 
 * @param string $term - Search term to validate
 * @return array - Validation result with 'valid' boolean and 'error' message
 */
function validate_search_term($term) {
    $term = sanitize_search_input($term);
    
    if (empty($term)) {
        return ['valid' => false, 'error' => 'Search term cannot be empty'];
    }
    
    if (strlen($term) < 2) {
        return ['valid' => false, 'error' => 'Search term must be at least 2 characters long'];
    }
    
    if (strlen($term) > 100) {
        return ['valid' => false, 'error' => 'Search term too long (maximum 100 characters)'];
    }
    
    // Check for patterns that might cause performance issues
    if (preg_match('/^[%_*]+$/', $term)) {
        return ['valid' => false, 'error' => 'Search term cannot consist only of wildcards'];
    }
    
    return ['valid' => true, 'term' => $term];
}

/**
 * Check if a search term is quoted (surrounded by quotes)
 * 
 * @param string $term - Search term to check
 * @return bool - True if term is quoted, false otherwise
 */
function is_quoted_search($term) {
    $term = trim($term);
    return (strlen($term) >= 2 && 
            (($term[0] === '"' && $term[-1] === '"') ||
             ($term[0] === "'" && $term[-1] === "'")));
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