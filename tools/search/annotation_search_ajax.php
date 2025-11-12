<?php
/**
 * AJAX endpoint for progressive organism search
 * Searches one organism at a time and returns results
 */

// Start output buffering to catch any errors
ob_start();

session_start();
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/search_functions.php';
include_once __DIR__ . '/../moop_functions.php';

// Clear any output that might have occurred
ob_end_clean();

header('Content-Type: application/json');

// Get parameters
$search_keywords = $_GET['search_keywords'] ?? '';
$organism = $_GET['organism'] ?? '';
$group = $_GET['group'] ?? '';
$quoted_search = isset($_GET['quoted']) && $_GET['quoted'] === '1';

// Validate inputs
if (empty($search_keywords) || empty($organism)) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Check access: Admin has access to everything
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_has_group_access = has_access('Collaborator', $group);
$organism_is_public = is_public_organism($organism);
$user_has_organism_access = has_access('Collaborator', $organism);

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
$db = "$organism_data/$organism/genes.sqlite";
if (!file_exists($db)) {
    // Try alternative naming
    $db = "$organism_data/$organism/$organism.genes.sqlite";
    if (!file_exists($db)) {
        include_once __DIR__ . '/../moop_functions.php';
        logError('Database not found for organism', $organism, [
            'search_term' => $search_keywords,
            'searched_paths' => [
                "$organism_data/$organism/genes.sqlite",
                "$organism_data/$organism/$organism.genes.sqlite"
            ]
        ]);
        echo json_encode(['error' => 'Database not found for organism', 'results' => []]);
        exit;
    }
}

// Validate database is readable and accessible
$db_validation = validateDatabaseFile($db);
if (!$db_validation['valid']) {
    include_once __DIR__ . '/../../error_logger.php';
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
$columns = ["f.feature_uniquename"];
list($like, $terms) = buildLikeConditions($columns, $search_input, false);

$query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id
FROM feature f, organism o
WHERE f.organism_id = o.organism_id
  AND $like
ORDER BY f.feature_uniquename
LIMIT 100";

$results = fetchData($query, $terms, $db);

$uniquename_search = !empty($results);

// If no results by uniquename, search annotations
if (!$uniquename_search) {
    $columns = ["a.annotation_description", "f.feature_name", "f.feature_description", "a.annotation_accession"];
    
    if ($quoted_search) {
        list($like, $terms) = buildLikeConditions($columns, $search_input, true);
    } else {
        list($like, $terms) = buildLikeConditions($columns, $search_input, false);
    }
    
    // Extract primary search term for relevance scoring in CASE statement
    // For quoted searches: use the exact phrase
    // For non-quoted: use first word
    $primary_term = $quoted_search ? $search_input : explode(' ', trim($search_input))[0];
    $case_pattern = "%" . $primary_term . "%";
    
    $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, a.annotation_accession, a.annotation_description, 
              fa.score, fa.date, ans.annotation_source_name, o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id
    FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o 
    WHERE ans.annotation_source_id = a.annotation_source_id 
      AND f.feature_id = fa.feature_id 
      AND fa.annotation_id = a.annotation_id 
      AND f.organism_id = o.organism_id
      AND $like 
    ORDER BY 
      CASE 
        WHEN f.feature_name LIKE ? THEN 1
        WHEN f.feature_description LIKE ? THEN 2
        WHEN a.annotation_description LIKE ? THEN 3
        ELSE 4
      END,
      f.feature_uniquename
    LIMIT 100";
    
    // Add CASE parameters to the terms array
    $terms[] = $case_pattern;
    $terms[] = $case_pattern;
    $terms[] = $case_pattern;
    
    $results = fetchData($query, $terms, $db);
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
        'feature_description' => $row['feature_description'] ?? '',
        'annotation_source' => $row['annotation_source_name'] ?? '',
        'annotation_accession' => $row['annotation_accession'] ?? '',
        'annotation_description' => $row['annotation_description'] ?? '',
        'uniquename_search' => $uniquename_search
    ];
}

// Log incomplete records for admin review
if (!empty($incomplete_records)) {
    include_once __DIR__ . '/../../error_logger.php';
    logError('Incomplete annotation records found', $organism, [
        'search_term' => $search_keywords,
        'count' => count($incomplete_records),
        'records' => $incomplete_records
    ]);
}

echo json_encode([
    'organism' => $organism,
    'organism_image_path' => $organism_image_path,
    'results' => $formatted_results,
    'count' => count($formatted_results),
    'search_type' => $uniquename_search ? 'Gene/Transcript ID' : ($quoted_search ? 'Quoted' : 'Keyword')
]);
