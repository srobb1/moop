<?php
/**
 * Search Helper Functions (DEPRECATED)
 * 
 * As of November 2024, all functions have been consolidated into moop_functions.php
 * This file is maintained for backwards compatibility only.
 * 
 * MIGRATION STATUS:
 * ✓ getDbConnection() → moop_functions.php
 * ✓ fetchData() → moop_functions.php
 * ✓ buildLikeConditions() → moop_functions.php
 * ✓ sanitize_search_input() → moop_functions.php
 * ✓ validate_search_term() → moop_functions.php
 * ✓ is_quoted_search() → moop_functions.php
 * 
 * NEW INCLUDE:
 * Include moop_functions.php instead of search_functions.php:
 *   include_once __DIR__ . '/../moop_functions.php';
 */

// Include moop_functions for backwards compatibility during transition
include_once realpath(__DIR__ . '/../moop_functions.php');

?>

