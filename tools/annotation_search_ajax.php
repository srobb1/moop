<?php
/**
 * AJAX endpoint for progressive organism search
 * Searches one organism at a time and returns results
 */

// Start output buffering to catch any errors
ob_start();

include_once __DIR__ . '/tool_init.php';

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
$gene_set = $_GET['gene_set'] ?? '';
$quoted_search = isset($_GET['quoted']) && $_GET['quoted'] === '1';
$source_names = $_GET['source_names'] ?? '';

// scope: JSON array of {assembly, gene_set} pairs — overrides individual assembly/gene_set params
$scope_pairs = [];
$scope_json = $_GET['scope'] ?? '';
if (!empty($scope_json)) {
    $decoded = json_decode($scope_json, true);
    if (is_array($decoded)) {
        // Validate each pair has the expected keys
        foreach ($decoded as $pair) {
            if (isset($pair['assembly'], $pair['gene_set'])) {
                $scope_pairs[] = [
                    'assembly' => (string)$pair['assembly'],
                    'gene_set' => (string)$pair['gene_set'],
                ];
            }
        }
    }
}

// Parse source names if provided
$source_filter    = [];
$gene_only_search = !empty($_GET['no_annotations']);  // user explicitly deselected all sources
if (!$gene_only_search && !empty($source_names)) {
    $source_filter = array_map('trim', explode(',', $source_names));
}

// Validate inputs
if (empty($search_keywords) || empty($organism)) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Check access: Admin has access to everything.
// Read the role through moop_session_is_admin() rather than $_SESSION['role'] directly, so
// an admin using "View as PUBLIC" gets a visitor's search results here too. A raw read would
// have quietly kept full search access during the preview — the worst possible outcome, since
// the preview would then report that public users can find data they actually cannot.
$is_admin = moop_session_is_admin();
$user_has_group_access = has_access('COLLABORATOR', $group);
$organism_is_public = is_public_organism($organism);
$user_has_organism_access = has_access('COLLABORATOR', $organism);

if (!$is_admin && !$user_has_group_access && !$organism_is_public && !$user_has_organism_access) {
    echo json_encode(['error' => 'Access denied', 'results' => [], 'debug' => [
        'session_role' => moop_session_role() ?? 'not set',
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
$results = searchFeaturesByUniquenameForSearch($search_input, $db, '', $assembly, $gene_set, $scope_pairs);
$uniquename_search = !empty($results);
$warning_message = null;

// If no results by uniquename, search by annotation or gene fields depending on source selection
if (!$uniquename_search) {
    if ($gene_only_search) {
        $search_result = searchFeaturesByNameDescription($search_input, $quoted_search, $db, $assembly, $gene_set, $scope_pairs);
    } else {
        $search_result = searchFeaturesAndAnnotations($search_input, $quoted_search, $db, $source_filter, $assembly, $gene_set, $scope_pairs);
    }
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
        'feature_description' => htmlspecialchars(decodeAnnotationText($row['feature_description'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'score' => $row['score'] ?? '',
        'annotation_source_name' => $row['annotation_source_name'] ?? '',
        'annotation_accession' => $row['annotation_accession'] ?? '',
        'annotation_description' => htmlspecialchars(decodeAnnotationText($row['annotation_description'] ?? ''), ENT_QUOTES, 'UTF-8'),
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

// Check whether results hit the configured per-organism cap
$result_count = count($formatted_results);
$results_limit = moop_search_results_limit();
$is_capped = $result_count >= $results_limit;

echo json_encode([
    'organism' => $organism,
    'genus' => $organism_data_result['organism_info']['genus'] ?? '',
    'species' => $organism_data_result['organism_info']['species'] ?? '',
    'organism_image_path' => $organism_image_path,
    'results' => $formatted_results,
    'count' => $result_count,
    'search_type' => $uniquename_search ? 'Gene/Transcript ID' : ($quoted_search ? 'Quoted' : 'Keyword'),
    'warning' => $warning_message,
    'capped' => $is_capped,
    // Sent so the results UI and its help can state the real cap rather than a
    // number baked into the JavaScript, which would go stale the moment an admin
    // changes it in Site Configuration.
    'results_limit' => $results_limit
]);
