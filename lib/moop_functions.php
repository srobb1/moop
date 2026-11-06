<?php

/**
 * Does this site hold any data a visitor cannot see? True when an assembly directory on disk is
 * not public (no group entry, or not in the PUBLIC group). A site where everything is public
 * (cuttingclass) has no logins or access levels to explain, so the help leaves that topic out.
 */
function moop_site_has_restricted_data(): bool {
    static $memo = null;
    if ($memo !== null) return $memo;
    $config = ConfigManager::getInstance();
    $root   = rtrim($config->getPath('organism_data'), '/');
    foreach (glob("$root/*/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $organism = basename(dirname($dir));
        $assembly = basename($dir);
        if ($organism[0] === '.' || $assembly[0] === '.') continue;          // .retired, .cache
        if (!is_file("$dir/genome.json") && !glob("$dir/*/", GLOB_ONLYDIR)) continue;  // not an assembly
        if (!is_public_assembly($organism, $assembly)) return $memo = true;
    }
    return $memo = false;
}
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
