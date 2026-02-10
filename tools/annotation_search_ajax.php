<?php
/**
 * AJAX endpoint for progressive organism search
 * Searches one organism at a time and returns results
 */

// Start output buffering to catch any errors
ob_start();

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/search_functions.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');

// Clear any output that might have occurred
ob_end_clean();

header('Content-Type: application/json');

// Get parameters
$search_keywords = $_GET['search_keywords'] ?? '';
$organism = $_GET['organism'] ?? '';
$group = $_GET['group'] ?? '';
$assembly = $_GET['assembly'] ?? '';
$quoted_search = isset($_GET['quoted']) && $_GET['quoted'] === '1';
$source_names = $_GET['source_names'] ?? '';  // Comma-separated source names

// Parse source names if provided
$source_filter = [];
if (!empty($source_names)) {
    $source_filter = array_map('trim', explode(',', $source_names));
    error_log('DEBUG: Source filter applied: ' . implode(', ', $source_filter));
} else {
    error_log('DEBUG: No source filter provided');
}

// Validate inputs
if (empty($search_keywords) || empty($organism)) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Check access: Admin has access to everything
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_has_group_access = has_access('COLLABORATOR', $group);
$organism_is_public = is_public_organism($organism);
$user_has_organism_access = has_access('COLLABORATOR', $organism);

if (!$is_admin && !$user_has_group_access && !$organism_is_public && !$user_has_organism_access) {
    echo json_encode(['error' => 'Access denied', 'results' => [], 'debug' => [
        'session_role' => $_SESSION['role'] ?? 'not set',
        'is_admin' => $is_admin
    ]]);
    exit;
}

// Sanitize search input using function from search_functions.php
$search_input = sanitize_search_input($search_keywords, $quoted_search);

// Build database path
$db = "$organism_data/$organism/organism.sqlite";
if (!file_exists($db)) {
    include_once __DIR__ . '/../lib/moop_functions.php';
    logError('Database not found for organism', $organism, [
        'search_term' => $search_keywords,
        'searched_path' => $db
    ]);
    echo json_encode(['error' => 'Database not found for organism', 'results' => []]);
    exit;
}

// Validate database is readable and accessible
$db_validation = validateDatabaseFile($db);
if (!$db_validation['valid']) {
    logError('Database file not accessible', $organism, [
        'search_term' => $search_keywords,
        'database_path' => $db,
        'validation_error' => $db_validation['error']
    ]);
    echo json_encode(['error' => 'Database file not accessible: ' . $db_validation['error'], 'results' => []]);
    exit;
}

// Load organism info and get image path
$organism_data_result = loadOrganismAndGetImagePath($organism, $images_path, $absolute_images_path);
$organism_image_path = $organism_data_result['image_path'];

// Check if searching by feature uniquename first
$results = searchFeaturesByUniquenameForSearch($search_input, $db, '', $assembly);
$uniquename_search = !empty($results);
$warning_message = null;

// If no results by uniquename, search annotations
if (!$uniquename_search) {
    $search_result = searchFeaturesAndAnnotations($search_input, $quoted_search, $db, $source_filter, $assembly);
    $results = $search_result['results'];
    $warning_message = $search_result['warning'];
}

// Format results for JSON
$formatted_results = [];
$incomplete_records = [];

foreach ($results as $row) {
    $species = $row['species'];
    if (!empty($row['subtype']) && $row['subtype'] != 'NULL') {
        $species .= ' ' . $row['subtype'];
    }
    
    // Check for incomplete annotation records (missing source or accession)
    if (!$uniquename_search && (empty($row['annotation_source_name']) || empty($row['annotation_accession']))) {
        $incomplete_records[] = [
            'organism' => $organism,
            'feature_uniquename' => $row['feature_uniquename'],
            'feature_name' => $row['feature_name'] ?? '',
            'annotation_accession' => $row['annotation_accession'] ?? 'MISSING',
            'annotation_source' => $row['annotation_source_name'] ?? 'MISSING'
        ];
    }
    
    $formatted_results[] = [
        'organism' => $organism,
        'genus' => $row['genus'],
        'species' => $species,
        'common_name' => $row['common_name'],
        'feature_type' => $row['feature_type'],
        'feature_uniquename' => $row['feature_uniquename'],
        'feature_name' => $row['feature_name'] ?? '',
        'feature_description' => decodeAnnotationText($row['feature_description'] ?? ''),
        'score' => $row['score'] ?? '',
        'annotation_source_name' => $row['annotation_source_name'] ?? '',
        'annotation_accession' => $row['annotation_accession'] ?? '',
        'annotation_description' => decodeAnnotationText($row['annotation_description'] ?? ''),
        'genome_accession' => $row['genome_accession'] ?? '',
        'uniquename_search' => $uniquename_search
    ];
}

// Log incomplete records for admin review
if (!empty($incomplete_records)) {
    logError('Incomplete annotation records found', $organism, [
        'search_term' => $search_keywords,
        'count' => count($incomplete_records),
        'records' => $incomplete_records
    ]);
}

// Check if results are capped at 2500
$result_count = count($formatted_results);
$is_capped = $result_count >= 2500;

echo json_encode([
    'organism' => $organism,
    'organism_image_path' => $organism_image_path,
    'results' => $formatted_results,
    'count' => $result_count,
    'search_type' => $uniquename_search ? 'Gene/Transcript ID' : ($quoted_search ? 'Quoted' : 'Keyword'),
    'warning' => $warning_message,
    'capped' => $is_capped
]);
