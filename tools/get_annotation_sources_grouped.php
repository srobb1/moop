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

// Optional: one gene set of a single organism (the gene set page).
$assembly_param = trim((string)($_GET['assembly'] ?? ''));
$gene_set_param = trim((string)($_GET['gene_set'] ?? ''));
if ($gene_set_param !== '' && (count($organisms) !== 1 || $assembly_param === ''
        || !has_gene_set_access($organisms[0], $assembly_param, $gene_set_param))) {
    $gene_set_param = '';   // not a gene set this viewer may see: organism-level, as before
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

    // Scoped to ONE gene set (the gene set page passes assembly + gene_set): that gene set's own
    // sources and counts, so the filter neither shows organism-wide numbers nor offers a source
    // that has nothing in this gene set. Built in the background by housekeeping; until then,
    // fall through to the organism's sources -- a superset, so nothing the user could pick is
    // missing, only the counts are wider.
    $gs_counts = ($gene_set_param !== '' && count($organisms) === 1)
        ? moop_load_gene_set_annotation_counts($organism, $assembly_param, $gene_set_param)
        : null;
    if ($gs_counts !== null) {
        $source_types = $gs_counts;
    // Use per-organism cache to avoid re-running the expensive COUNT aggregate query.
    // Cache is invalidated automatically when organism.sqlite is newer than the cache file.
    } elseif (($cache_file = moop_annotation_sources_cache_file($organism)) !== ''
              && file_exists($cache_file) && filemtime($cache_file) >= filemtime($db)) {
        $cached = loadJsonFile($cache_file, []);
        $source_types = is_array($cached) ? $cached : [];
    } else {
        $source_types = getAnnotationSourcesByType($db);
        if ($cache_file !== '') file_put_contents($cache_file, json_encode($source_types));
    }
    // A cache written before empty sources were excluded still lists them.
    $source_types = moop_drop_empty_annotation_sources($source_types);

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
