<?php
/**
 * Input Validation and Sanitization Functions
 * Handles user input validation, search term processing, and parameter validation
 */

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
function sanitize_search_input($input, $quoted_search = false) {
    // Remove null bytes and control characters
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    // Trim whitespace
    $input = trim($input);
    
    // Remove quotes if this is a quoted search - they will be removed before passing to database
    if ($quoted_search) {
        $input = trim($input, '"');
    }
    
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
 * @param string|null $redirect_on_empty URL to redirect to if empty (default: /{site}/index.php)
 * @return string Validated organism name
 */
function validateOrganismParam($organism_name, $redirect_on_empty = null) {
    if (empty($organism_name)) {
        if ($redirect_on_empty === null) {
            $site = ConfigManager::getInstance()->getString('site');
            $redirect_on_empty = "/$site/index.php";
        }
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
 * @param string|null $redirect_on_empty URL to redirect to if empty (default: /{site}/index.php)
 * @return string Validated assembly name
 */
function validateAssemblyParam($assembly, $redirect_on_empty = null) {
    if (empty($assembly)) {
        if ($redirect_on_empty === null) {
            $site = ConfigManager::getInstance()->getString('site');
            $redirect_on_empty = "/$site/index.php";
        }
        header("Location: $redirect_on_empty");
        exit;
    }
    return $assembly;
}
/**
 * Decode URL-encoded annotation/description text for display
 * Converts %3D back to =, %3B back to ;, etc.
 * 
 * Used when displaying descriptions/annotations from database that were
 * URL-encoded for GFF file storage.
 * 
 * @param string $text - URL-encoded text from database
 * @return string - Decoded, readable text
 */
function decodeAnnotationText($text) {
    if (empty($text)) {
        return $text;
    }
    // Decode URL-encoded characters: %XX -> character
    return preg_replace_callback('/%([0-9A-F]{2})/i', function($matches) {
        return chr(hexdec($matches[1]));
    }, $text);
}

/**
 * The annotation-source filter a search request carries, as a list of exact source names.
 *
 * Sent as a JSON array: `["Ensembl Homo sapiens", "Closest human gene (Ensembl, no HGNC record)"]`.
 * It used to be one comma-joined string split on ',' and each piece trim()med, which broke on two
 * real names (2026-10-20):
 *   - "Closest human gene (Ensembl, no HGNC record)" -- the gene-naming pipeline's own output --
 *     split in two, so filtering by it matched nothing;
 *   - eight databases named their RBBH sources "Ensembl Danio rerio " (trailing space), the only
 *     difference from the Homologs source; trim() turned every one into the Homologs name, so
 *     a filtered search returned no RBBH rows for those organisms.
 * Names are matched EXACTLY as stored; normalising them is the loader's job, not the request's.
 *
 * A plain comma string is still accepted (a page open in a browser from before the change sends
 * one) and logged, so we can tell when it is safe to drop.
 *
 * @return list<string>
 */
function moop_parse_source_names($raw): array {
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    if ($raw[0] === '[') {
        $list = json_decode($raw, true);
        if (!is_array($list)) {
            return [];
        }
        return array_values(array_filter($list, fn($name) => is_string($name) && $name !== ''));
    }
    error_log('MOOP: source_names sent as a comma string (old page?) -- names containing commas will not match');
    return array_values(array_filter(explode(',', $raw), fn($name) => $name !== ''));
}
