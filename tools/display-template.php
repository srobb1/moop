<?php
/**
 * GENERIC DISPLAY TEMPLATE
 * 
 * Shared by all similar display pages for consistency and DRY principle.
 * 
 * Used by:
 * - organism_display.php
 * - assembly_display.php
 * - groups_display.php
 * - multi_organism_search.php
 * 
 * How It Works:
 * ─────────────
 * 1. Child page (e.g., tools/organism.php) includes this file
 * 2. Child page sets up the context/data specific to that page
 * 3. Child page defines $display_config with:
 *    - title: Page title
 *    - content_file: Path to content file (pages/organism.php, etc.)
 *    - page_script: Path to page-specific JS (js/organism-display.js, etc.)
 *    - inline_scripts: Array of JS code strings (variable definitions, etc.)
 * 4. This template renders the page using layout.php
 * 
 * Why This Pattern:
 * ─────────────────
 * All 4 display pages have identical HTML structure and script loading.
 * This template eliminates code duplication and ensures consistency.
 * Change template once = affects all 4 pages automatically.
 * 
 * Expected Variables (Set by child page):
 * ──────────────────────────────────────────
 * - $display_config = [
 *     'title' => 'Page Title',
 *     'content_file' => '/path/to/content.php',
 *     'page_script' => '/moop/js/page-display.js',
 *     'inline_scripts' => ['const var1 = "value";', 'const var2 = "value";']
 *   ]
 * - $data = [... any variables needed by content file ...]
 * 
 * Example Usage:
 * ───────────────
 * In organism_display.php:
 *   $display_config = [
 *       'title' => 'E. coli',
 *       'content_file' => __DIR__ . '/pages/organism.php',
 *       'page_script' => '/moop/js/organism-display.js',
 *       'inline_scripts' => [
 *           "const sitePath = '/moop';",
 *           "const organismName = 'E_coli';"
 *       ]
 *   ];
 *   $data = ['organism_name' => 'E_coli', ...];
 *   include_once __DIR__ . '/display-template.php';
 */

// Ensure layout.php is loaded
if (!function_exists('render_display_page')) {
    include_once __DIR__ . '/../includes/layout.php';
}

// Validate required configuration
if (!isset($display_config) || !is_array($display_config)) {
    die('Error: display-template.php requires $display_config array');
}

if (empty($display_config['content_file']) || !file_exists($display_config['content_file'])) {
    die('Error: Invalid or missing content_file in display_config');
}

if (empty($display_config['title'])) {
    die('Error: Missing title in display_config');
}

// Validate data array exists
if (!isset($data) || !is_array($data)) {
    $data = [];
}

// Add inline_scripts and page_script to data for layout.php
if (isset($display_config['inline_scripts']) && is_array($display_config['inline_scripts'])) {
    $data['inline_scripts'] = $display_config['inline_scripts'];
}

if (!empty($display_config['page_script'])) {
    $data['page_script'] = $display_config['page_script'];
}

// Ensure site variable is always set (required by layout.php and content files)
if (!isset($data['site'])) {
    $data['site'] = 'moop';
}

// Add CSS files for display pages (if not already set)
if (!isset($data['page_styles'])) {
    $site = $data['site'];
    $data['page_styles'] = [
        '/' . $site . '/css/display.css',
        '/' . $site . '/css/parent.css',
        '/' . $site . '/css/advanced-search-filter.css',
        '/' . $site . '/css/search-controls.css',
    ];
}

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
