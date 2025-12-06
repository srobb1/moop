<?php
/**
 * REGISTRY TEMPLATE
 * 
 * Shared template for registry display pages for consistency and DRY principle.
 * 
 * Used by:
 * - tools/registry.php (PHP Function Registry)
 * - tools/js_registry.php (JavaScript Registry)
 * 
 * How It Works:
 * ─────────────
 * 1. Child page (e.g., tools/registry.php) includes this file
 * 2. Child page sets up the context/data specific to that registry
 * 3. Child page defines $display_config with:
 *    - title: Page title
 *    - content_file: Path to content file (pages/registry.php, etc.)
 *    - page_script: Path to page-specific JS (js/registry.js, etc.)
 *    - inline_scripts: Array of JS code strings (optional)
 * 4. This template renders the page using layout.php
 * 
 * Expected Variables (Set by child page):
 * ──────────────────────────────────────────
 * - $display_config = [
 *     'title' => 'Registry Title',
 *     'content_file' => '/path/to/content.php',
 *     'page_script' => '/moop/js/registry.js' or ['/moop/js/script1.js', '/moop/js/script2.js'],
 *     'inline_scripts' => ['const var1 = "value";'] (optional)
 *   ]
 * - $data = [... any variables needed by content file ...]
 * 
 * Example Usage:
 * ───────────────
 * In tools/registry.php:
 *   include_once __DIR__ . '/tool_init.php';
 *   
 *   $display_config = [
 *       'title' => 'PHP Function Registry',
 *       'content_file' => __DIR__ . '/pages/registry.php',
 *       'page_script' => '/' . $site . '/js/registry.js',
 *   ];
 *   
 *   $data = ['site' => $site, ...];
 *   include_once __DIR__ . '/registry-template.php';
 */

// Ensure layout.php is loaded
if (!function_exists('render_display_page')) {
    include_once __DIR__ . '/../includes/layout.php';
}

// Validate required configuration
if (!isset($display_config) || !is_array($display_config)) {
    die('Error: registry-template.php requires $display_config array');
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

// Add inline_scripts to data for layout.php
if (isset($display_config['inline_scripts']) && is_array($display_config['inline_scripts'])) {
    $data['inline_scripts'] = $display_config['inline_scripts'];
}

// Add page_script to data for layout.php
if (!empty($display_config['page_script'])) {
    $data['page_script'] = $display_config['page_script'];
}

// Ensure site variable is always set (required by layout.php)
if (!isset($data['site'])) {
    $data['site'] = 'moop';
}

// Add CSS files for registry pages (if not already set)
if (!isset($data['page_styles'])) {
    $site = $data['site'];
    $data['page_styles'] = [
        '/' . $site . '/css/registry.css',
        '/' . $site . '/css/display.css',
    ];
} else {
    // Merge with default registry styles if partial styles defined
    $site = $data['site'];
    if (!in_array('/' . $site . '/css/registry.css', $data['page_styles'])) {
        $data['page_styles'][] = '/' . $site . '/css/registry.css';
    }
}

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
