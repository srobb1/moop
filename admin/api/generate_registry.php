<?php
/**
 * Registry Generation API Endpoint
 * Handles requests to generate or update function registries
 */

// Admin auth required - must come before any other includes
require_once __DIR__ . '/../admin_init.php';

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

// Run the generator via CLI.
//
// exec() rather than shell_exec() so the generator's EXIT STATUS is actually available.
// This used to infer success as `$output ? 0 : 1` — i.e. "it printed something, so it
// worked" — and then decide by substring-matching the output for "error"/"failed". That
// happened to behave correctly only because PHP fatals contain the word "error" and the
// generators' own failure path prints "Error writing JSON file". A successful run whose
// output merely mentioned the word would have been reported as a failure, and a generator
// that exited non-zero while printing a normal-looking message would have been reported as
// a success. The exit status answers the question directly.
$command    = 'php ' . escapeshellarg($generator_path) . ' 2>&1';
$outputLines = [];
$returnCode  = 1;
exec($command, $outputLines, $returnCode);
$output = implode("\n", $outputLines);

if ($returnCode === 0) {
    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . ' registry generated successfully!',
        'output' => $output
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate ' . $type . ' registry (exit code ' . $returnCode . ')',
        'output' => $output
    ]);
}
?>
