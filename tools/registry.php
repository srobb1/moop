<?php
/**
 * PHP Function Registry Display Wrapper
 * Loads and displays the PHP function registry with integrated site styling
 */

require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Set page variables for layout.php
$page_title = 'PHP Function Registry';
$page_description = 'Searchable registry of all PHP functions in the codebase';
$current_page = 'registry';
$page_styles = ['/css/registry.css'];
$page_script = '/js/registry.js';

// Load layout template
require_once __DIR__ . '/../includes/layout.php';
?>
