<?php
/**
 * /api/jbrowse2/get-assembly-definition.php
 * 
 * Returns assembly definition file as JSON
 * Used by test pages to verify assembly definitions
 * 
 * GET /api/jbrowse2/get-assembly-definition.php?organism=ORGANISM&assembly=ASSEMBLY
 */

header('Content-Type: application/json');

$organism = $_GET['organism'] ?? '';
$assembly = $_GET['assembly'] ?? '';

if (empty($organism) || empty($assembly)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing organism or assembly parameter']);
    exit;
}

$def_file = __DIR__ . '/../../metadata/jbrowse2-configs/assemblies/' . 
            "{$organism}_{$assembly}.json";

if (!file_exists($def_file)) {
    http_response_code(404);
    echo json_encode(['error' => "Assembly definition not found: {$organism}/{$assembly}"]);
    exit;
}

$content = file_get_contents($def_file);
$data = json_decode($content, true);

if (!$data) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid assembly definition JSON']);
    exit;
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
