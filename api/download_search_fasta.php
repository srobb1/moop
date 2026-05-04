<?php
/**
 * Download Annotation Search Results as FASTA
 *
 * Accepts a POST with feature uniquenames grouped by organism and returns a
 * combined multi-organism FASTA file extracted from each organism's BLAST
 * FASTA databases. Intended for "Download All" from annotation search pages.
 *
 * POST parameters:
 *   features   - JSON object: { "OrganismName": ["uid1", "uid2", ...], ... }
 *   csrf_token - CSRF token (or X-CSRF-Token header)
 */

ob_start();
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
ob_end_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

csrf_protect();

$features_json = $_POST['features'] ?? '';
if (empty($features_json)) {
    http_response_code(400);
    die('Missing features parameter.');
}

$features_by_organism = json_decode($features_json, true);
if (!is_array($features_by_organism)) {
    http_response_code(400);
    die('Invalid features parameter.');
}

$organism_data  = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();

$all_fasta = '';

foreach ($features_by_organism as $organism => $uniquenames) {
    if (empty($organism) || empty($uniquenames)) continue;

    $organism_dir = "$organism_data/$organism";
    if (!is_dir($organism_dir)) continue;

    // Scan all assembly subdirectories
    $entries = array_diff(scandir($organism_dir), ['.', '..']);
    foreach ($entries as $entry) {
        $assembly_dir = "$organism_dir/$entry";
        if (!is_dir($assembly_dir) || $entry === 'fasta_files') continue;
        if (!has_assembly_access($organism, $entry)) continue;

        $result = extractSequencesForAllTypes($assembly_dir, $uniquenames, $sequence_types, $organism, $entry);
        if ($result['success']) {
            foreach ($result['content'] as $content) {
                $all_fasta .= rtrim($content) . "\n";
            }
        }
    }
}

if (empty(trim($all_fasta))) {
    http_response_code(404);
    die('No sequences found. BLAST databases may not be built for these organisms.');
}

header('Content-Type: application/x-fasta');
header('Content-Disposition: attachment; filename="annotation_search_results.fasta"');
header('Cache-Control: no-cache');
echo $all_fasta;
