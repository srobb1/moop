<?php
/**
 * MANAGE REGISTRY - Wrapper
 * 
 * Handles admin access verification and renders function registry management
 * using clean architecture layout system.
 */

ob_start();
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Custom handler for registry-specific AJAX actions
function handleRegistryAjax($action) {
    if ($action === 'update_registry') {
        $type = $_POST['type'] ?? 'php';
        $script = $type === 'js' ? 'generate_js_registry.php' : 'generate_registry.php';
        $script_path = __DIR__ . '/../tools/' . $script;
        
        if (!file_exists($script_path)) {
            echo json_encode(['success' => false, 'message' => 'Registry generator script not found']);
            return true;
        }
        
        // Run PHP script via command line
        $cmd = 'php ' . escapeshellarg($script_path) . ' 2>&1';
        exec($cmd, $output, $exitCode);
        $output_text = implode("\n", $output);
        
        // Check if command succeeded
        if ($exitCode === 0) {
            echo json_encode(['success' => true, 'message' => 'Registry updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => trim($output_text)]);
        }
        return true;
    }
    return false;
}

// Handle AJAX requests after admin access verification
ob_start(); // Begin buffering
handleAdminAjax('handleRegistryAjax'); // Handle standard + custom AJAX
ob_end_clean(); // Clear buffered AJAX responses
ob_start(); // Start buffering HTML output

ob_end_flush(); // Output buffered HTML

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');

// Check file permissions for registry files using ConfigManager paths
$docs_path = $config->getPath('docs_path');
$php_registry_html = $docs_path . '/function_registry.html';
$php_registry_md = $docs_path . '/FUNCTION_REGISTRY.md';
$js_registry_html = $docs_path . '/js_function_registry.html';
$js_registry_md = $docs_path . '/JS_FUNCTION_REGISTRY.md';

$php_last_update = getRegistryLastUpdate($php_registry_html, $php_registry_md);
$js_last_update = getRegistryLastUpdate($js_registry_html, $js_registry_md);

// Prepare data for display
$site = $config->getString('site');
$data = [
    'siteTitle' => $config->getString('siteTitle'),
    'site' => $site,
    'config' => $config,
    'php_registry_html' => $php_registry_html,
    'php_registry_md' => $php_registry_md,
    'js_registry_html' => $js_registry_html,
    'js_registry_md' => $js_registry_md,
    'php_last_update' => $php_last_update,
    'js_last_update' => $js_last_update,
    'docs_path' => $docs_path,
    'page_script' => [
        '/' . $site . '/js/permission-manager.js',
        '/' . $site . '/js/modules/manage-registry.js',
    ],
];

$display_config = [
    'content_file' => __DIR__ . '/pages/manage_registry.php',
    'title' => 'Function Registry Management'
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
