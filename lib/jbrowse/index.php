<?php
/**
 * JBrowse2 Library Index
 * 
 * Includes all JBrowse2-related functionality:
 * - Track token generation and validation (JWT)
 * - Assembly configuration helpers
 * - Track access control
 */

// Autoload all jbrowse library files
foreach (glob(__DIR__ . '/*.php') as $file) {
    if (basename($file) !== 'index.php') {
        require_once $file;
    }
}
?>
