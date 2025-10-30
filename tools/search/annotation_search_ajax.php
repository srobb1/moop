<?php
/**
 * AJAX endpoint for progressive organism search
 * Searches one organism at a time and returns results
 */

// Start output buffering to catch any errors
ob_start();

session_start();
include_once __DIR__ . '/../../access_control.php';
include_once __DIR__ . '/../common_functions.php';

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

// DEBUG - remove after testing
error_log("Session role: " . ($_SESSION['role'] ?? 'not set'));
error_log("Is admin: " . ($is_admin ? 'yes' : 'no'));
error_log("Organism: $organism, Group: $group");

if (!$is_admin && !$user_has_group_access && !$organism_is_public && !$user_has_organism_access) {
    echo json_encode(['error' => 'Access denied', 'results' => [], 'debug' => [
        'session_role' => $_SESSION['role'] ?? 'not set',
        'is_admin' => $is_admin
    ]]);
    exit;
}

// Sanitize search input
function sanitize_search_input($data, $quoted_search) {
    // Remove quotes if quoted search
    if ($quoted_search) {
        $data = trim($data, '"');
    }
    
    $data = preg_replace('/[\<\>\t\;]+/', ' ', $data);
    $data = htmlspecialchars($data);
    
    if (preg_match('/\s+/', $data)) {
        $data_array = explode(' ', $data, 99);
        foreach ($data_array as $key => &$value) {
            if (strlen($value) < 3 && !$quoted_search) {
                unset($data_array[$key]);
            }
        }
        $data = implode(' ', $data_array);
    }
    
    $data = stripslashes($data);
    return $data;
}

$search_input = sanitize_search_input($search_keywords, $quoted_search);

// Build database path
$db = "$organism_data/$organism/genes.sqlite";
if (!file_exists($db)) {
    // Try alternative naming
    $db = "$organism_data/$organism/$organism.genes.sqlite";
    if (!file_exists($db)) {
        echo json_encode(['error' => 'Database not found for organism', 'results' => []]);
        exit;
    }
}

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
    
    $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, a.annotation_accession, a.annotation_description, 
              fa.score, fa.date, ans.annotation_source_name, o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id
    FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o 
    WHERE ans.annotation_source_id = a.annotation_source_id 
      AND f.feature_id = fa.feature_id 
      AND fa.annotation_id = a.annotation_id 
      AND f.organism_id = o.organism_id
      AND $like 
    ORDER BY f.feature_uniquename
    LIMIT 100";
    
    $results = fetchData($query, $terms, $db);
}

// Format results for JSON
$formatted_results = [];
foreach ($results as $row) {
    $species = $row['species'];
    if (!empty($row['subtype']) && $row['subtype'] != 'NULL') {
        $species .= ' ' . $row['subtype'];
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

echo json_encode([
    'organism' => $organism,
    'results' => $formatted_results,
    'count' => count($formatted_results),
    'search_type' => $uniquename_search ? 'Gene/Transcript ID' : ($quoted_search ? 'Quoted' : 'Keyword')
]);
