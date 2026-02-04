<?php
/**
 * MAFFT Analysis API Endpoint
 * POST /api/galaxy/mafft.php
 * 
 * Runs MAFFT multiple sequence alignment on selected sequences
 * Returns job status and history ID for polling results
 */

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Load configuration and access control
    require_once __DIR__ . '/../../includes/access_control.php';
    require_once __DIR__ . '/../../lib/galaxy/index.php';
    
    // Get config instance
    $config = ConfigManager::getInstance();
    $galaxy_settings = $config->getArray('galaxy_settings', []);
    
    // Check if Galaxy is enabled
    if (empty($galaxy_settings['enabled'])) {
        throw new Exception('Galaxy integration is not enabled');
    }
    
    // Check API key is configured
    if (empty($galaxy_settings['api_key'])) {
        throw new Exception('Galaxy API key not configured. Set GALAXY_API_KEY environment variable.');
    }
    
    // Parse request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required parameters
    if (empty($input['sequences'])) {
        throw new Exception('No sequences provided');
    }
    
    if (!is_array($input['sequences'])) {
        throw new Exception('Sequences must be an array');
    }
    
    // Get current user (for now, use test user)
    // TODO: Implement proper authentication when User class is available
    $userId = $_SESSION['user_id'] ?? 1;  // Default to user 1 if not in session
    
    // Initialize MAFFT tool
    $mafft = new MAFFTTool($galaxy_settings);
    
    // Extract options (optional)
    $options = isset($input['options']) && is_array($input['options']) ? $input['options'] : [];
    
    // Run alignment
    $result = $mafft->align($userId, $input['sequences'], $options);
    
    // Return result
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
