<?php
/**
 * DEPRECATED: This file is deprecated as of November 2024
 * 
 * Core functions have been moved to moop_functions.php
 * Display-specific functions have been moved to their respective tool directories
 * 
 * MIGRATION STATUS:
 * ✓ test_input() → moop_functions.php (core utility)
 * ✓ getDbConnection() → moop_functions.php (core utility)
 * ✓ fetchData() → moop_functions.php (core utility)
 * ✓ buildLikeConditions() → moop_functions.php (core utility)
 * ✓ getAncestors() → tools/parent_display.php (display-specific)
 * ✓ getChildren() → tools/parent_display.php (display-specific)
 * 
 * REMOVED (Unused/Dead Code):
 * - test_input2() - unused in active code
 * - get_dir_and_files() - unused in active code
 * - getAnnotations() - unused in active code
 * - generateFeatureTreeHTML() - unused in active code
 * - buildLikeConditions1() - older version, superseded by buildLikeConditions()
 * 
 * NEW INCLUDES:
 * Include moop_functions.php instead of common_functions.php for core utilities:
 *   include_once __DIR__ . '/moop_functions.php';
 * 
 * If you need this file, it will be removed in a future release.
 * Please update your includes to use moop_functions.php for core utilities.
 */

// Include moop_functions for backwards compatibility during transition
include_once realpath(__DIR__ . '/moop_functions.php');

?>

