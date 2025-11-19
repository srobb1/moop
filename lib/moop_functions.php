<?php
/**
 * MOOP Core Functions - Master Include File
 * 
 * This file provides backward compatibility by including all function categories.
 * Refactored from monolithic moop_functions.php into focused function files.
 * 
 * Individual files can also include specific functions_*.php files directly
 * for more granular control and faster loading if needed.
 */

// Include database query builder functions (dependency)
require_once __DIR__ . '/database_queries.php';

// Include all function categories
require_once __DIR__ . '/functions_errorlog.php';
require_once __DIR__ . '/functions_validation.php';
require_once __DIR__ . '/functions_database.php';
require_once __DIR__ . '/functions_access.php';
require_once __DIR__ . '/functions_filesystem.php';
require_once __DIR__ . '/functions_system.php';
require_once __DIR__ . '/functions_tools.php';
require_once __DIR__ . '/functions_data.php';
require_once __DIR__ . '/functions_display.php';
require_once __DIR__ . '/functions_json.php';
?>
