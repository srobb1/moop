<?php
/**
 * AJAX endpoint to get all annotation sources for an organism
 * Used by search help modal to show available sources and counts
 * 
 * Parameters: organism
 * Returns: JSON array of sources with counts
 */

// Start output buffering
ob_start();

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/database_queries.php';

// Clear any output
ob_end_clean();

header('Content-Type: application/json');

// Get organism parameter
$organism = $_GET['organism'] ?? '';

if (empty($organism)) {
    echo json_encode(['error' => 'Missing organism parameter']);
    exit;
}

// Build database path
$organism_data = $config->getPath('organism_data');
$db = "$organism_data/$organism/organism.sqlite";

if (!file_exists($db)) {
    echo json_encode(['error' => 'Database not found for organism']);
    exit;
}

// Get all annotation sources for this organism
$sources = getAnnotationSources($db);

// Format response
echo json_encode([
    'organism' => $organism,
    'sources' => $sources,
    'count' => count($sources)
]);

?>
