<?php
/**
 * Download All Annotations as CSV
 *
 * Returns a single CSV containing all annotations for a parent feature
 * and all its children. Reuses the same access control and query logic
 * as the parent display page.
 *
 * GET parameters:
 *   organism   - Organism name (required)
 *   uniquename - Parent feature uniquename (required)
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/parent_functions.php';
include_once __DIR__ . '/../lib/moop_functions.php';

if (empty($_GET['organism']) || empty($_GET['uniquename'])) {
    http_response_code(400);
    die('Missing required parameters.');
}

$uniquename    = $_GET['uniquename'];
$organism_data = $config->getPath('organism_data');

$organism_context = setupOrganismDisplayContext($_GET['organism'], $organism_data, true);
$organism_name    = $organism_context['name'];

$db = verifyOrganismDatabase($organism_name, $organism_data);

// Permission-based genome filtering (same as parent.php)
$group_data           = getGroupData();
$accessible_assemblies = [];
foreach ($group_data as $data) {
    if ($data['organism'] === $organism_name && has_assembly_access($organism_name, $data['assembly'])) {
        $accessible_assemblies[] = $data['assembly'];
    }
}
$accessible_genome_ids = getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db);

if (empty($accessible_genome_ids)) {
    http_response_code(403);
    die('Access denied.');
}

// Resolve to top-level parent
$ancestors = getAncestors($uniquename, $db, $accessible_genome_ids);
$feature_id = null;
$feature_uniquename = $uniquename;

$organism_info = $organism_context['info'];
$parents = !empty($organism_info['feature_types']['parents'])
    ? $organism_info['feature_types']['parents']
    : ['gene', 'pseudogene'];

foreach ($ancestors as $ancestor) {
    $feature_id         = $ancestor['feature_id'];
    $feature_uniquename = $ancestor['feature_uniquename'];
    if (in_array($ancestor['feature_type'], $parents)) {
        break;
    }
}

if (!$feature_id) {
    http_response_code(404);
    die('Feature not found.');
}

// Collect parent + all children IDs
$children       = getChildren($feature_id, $db, $accessible_genome_ids);
$all_feature_ids = [$feature_id];
foreach ($children as $child) {
    $all_feature_ids[] = $child['feature_id'];
}

$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);

// Stream CSV
$filename = $feature_uniquename . '_annotations.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fputcsv($out, ['Feature Uniquename', 'Feature Type', 'Annotation Type', 'Annotation ID', 'Description', 'Score', 'Source']);

foreach ($all_annotations as $fid => $by_type) {
    foreach ($by_type as $annotation_type => $rows) {
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['feature_uniquename'],
                $row['feature_type'],
                $row['annotation_type'],
                $row['annotation_accession'],
                $row['annotation_description'],
                $row['score'],
                $row['annotation_source_name'],
            ]);
        }
    }
}

fclose($out);
