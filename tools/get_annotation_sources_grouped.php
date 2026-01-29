<?php
/**
 * AJAX endpoint to get annotation sources grouped by type
 * Used by advanced search filter modal
 * 
 * Parameters: organisms (comma-separated list) OR organism (single, for backwards compatibility)
 * Returns: JSON with grouped sources (aggregated from all specified organisms)
 */

ob_start();

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/database_queries.php';

ob_end_clean();

header('Content-Type: application/json');

// Accept either single organism or comma-separated list of organisms
$organism_param = $_GET['organism'] ?? '';
$organisms_param = $_GET['organisms'] ?? '';

$organisms = [];
if (!empty($organisms_param)) {
    // Multiple organisms provided as comma-separated list
    $organisms = array_filter(array_map('trim', explode(',', $organisms_param)));
} elseif (!empty($organism_param)) {
    // Single organism provided (backwards compatibility)
    $organisms = [$organism_param];
}

if (empty($organisms)) {
    echo json_encode(['error' => 'Missing organism parameter']);
    exit;
}

$organism_data = $config->getPath('organism_data');

// Aggregate sources from all organisms
$aggregated_sources = [];
$metadata_path = $config->getPath('metadata_path');
$config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFile($config_file, []);

foreach ($organisms as $organism) {
    $db = "$organism_data/$organism/organism.sqlite";
    
    if (!file_exists($db)) {
        continue; // Skip organisms without database
    }
    
    // Get grouped sources for this organism
    $source_types = getAnnotationSourcesByType($db);
    
    // Aggregate counts
    foreach ($source_types as $type => $sources) {
        if (!isset($aggregated_sources[$type])) {
            $aggregated_sources[$type] = [];
        }
        
        foreach ($sources as $source) {
            $source_name = $source['name'];
            if (!isset($aggregated_sources[$type][$source_name])) {
                $aggregated_sources[$type][$source_name] = [
                    'name' => $source_name,
                    'count' => 0
                ];
            }
            // Add counts from this organism
            $aggregated_sources[$type][$source_name]['count'] += $source['count'];
        }
    }
}

// Convert back to indexed array format and add colors/descriptions
$source_types_with_color = [];
foreach ($aggregated_sources as $type => $sources_by_name) {
    $color = 'secondary'; // default
    $description = ''; // default
    if (!empty($annotation_config['annotation_types'][$type]['color'])) {
        $color = $annotation_config['annotation_types'][$type]['color'];
    }
    if (!empty($annotation_config['annotation_types'][$type]['description'])) {
        $description = $annotation_config['annotation_types'][$type]['description'];
    }
    
    $source_types_with_color[$type] = [
        'sources' => array_values($sources_by_name),
        'color' => $color,
        'description' => $description
    ];
}

// Calculate totals
$total_sources = 0;
$total_count = 0;
foreach ($source_types_with_color as $type_data) {
    $total_sources += count($type_data['sources']);
    foreach ($type_data['sources'] as $source) {
        $total_count += $source['count'];
    }
}

echo json_encode([
    'organisms' => $organisms,
    'source_types' => $source_types_with_color,
    'total_sources' => $total_sources,
    'total_annotations' => $total_count
]);

?>
