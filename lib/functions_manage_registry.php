<?php
/**
 * MANAGE REGISTRY FUNCTIONS
 * 
 * Functions for managing PHP and JavaScript function registries
 */

/**
 * Handle registry-specific AJAX actions
 * 
 * Registered as custom handler for handleAdminAjax()
 * 
 * @param string $action The AJAX action to handle
 * @return bool True if action was handled, false otherwise
 */
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

?>
