<?php
/**
 * Registry Generation API Endpoint
 * Handles requests to generate or update function registries
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the registry type
$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? 'php';

// Validate type
if (!in_array($type, ['php', 'js'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid registry type']);
    exit;
}

// Determine which generator to run
$generator = $type === 'php' ? 'generate_registry_json.php' : 'generate_js_registry_json.php';
$generator_path = __DIR__ . '/../../tools/' . $generator;

if (!file_exists($generator_path)) {
    echo json_encode(['success' => false, 'message' => 'Generator not found']);
    exit;
}

// Run the generator via CLI
$command = 'php ' . escapeshellarg($generator_path) . ' 2>&1';
$output = shell_exec($command);
$returnCode = $output ? 0 : 1;

// Check if generation was successful
if ($returnCode === 0 && stripos($output, 'error') === false && stripos($output, 'failed') === false) {
    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . ' registry generated successfully!',
        'output' => $output
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate ' . $type . ' registry',
        'output' => $output
    ]);
}
?>
