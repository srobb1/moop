<?php
/**
 * AJAX endpoint to get annotation sources grouped by type
 * Used by advanced search filter modal
 * 
 * Parameters: organism
 * Returns: JSON with grouped sources
 */

ob_start();

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/database_queries.php';

ob_end_clean();

header('Content-Type: application/json');

$organism = $_GET['organism'] ?? '';

if (empty($organism)) {
    echo json_encode(['error' => 'Missing organism parameter']);
    exit;
}

$organism_data = $config->getPath('organism_data');
$db = "$organism_data/$organism/organism.sqlite";

if (!file_exists($db)) {
    echo json_encode(['error' => 'Database not found for organism']);
    exit;
}

// Get grouped sources
$source_types = getAnnotationSourcesByType($db);

// Load annotation config to get color for each type
$metadata_path = $config->getPath('metadata_path');
$config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFile($config_file, []);

// Add color and description to each type
$source_types_with_color = [];
foreach ($source_types as $type => $sources) {
    $color = 'secondary'; // default
    $description = ''; // default
    if (!empty($annotation_config['annotation_types'][$type]['color'])) {
        $color = $annotation_config['annotation_types'][$type]['color'];
    }
    if (!empty($annotation_config['annotation_types'][$type]['description'])) {
        $description = $annotation_config['annotation_types'][$type]['description'];
    }
    
    $source_types_with_color[$type] = [
        'sources' => $sources,
        'color' => $color,
        'description' => $description
    ];
}

// Calculate totals
$total_sources = 0;
$total_count = 0;
foreach ($source_types as $type_sources) {
    $total_sources += count($type_sources);
    foreach ($type_sources as $source) {
        $total_count += $source['count'];
    }
}

echo json_encode([
    'organism' => $organism,
    'source_types' => $source_types_with_color,
    'total_sources' => $total_sources,
    'total_annotations' => $total_count
]);

?>
