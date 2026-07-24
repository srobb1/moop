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
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
ob_end_clean();

set_time_limit(300);

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

// First pass: verify at least one organism directory exists, to catch empty results early
$valid_organisms = [];
foreach ($features_by_organism as $organism => $uniquenames) {
    if (empty($organism) || empty($uniquenames)) continue;
    $organism_dir = "$organism_data/$organism";
    if (is_dir($organism_dir)) {
        $valid_organisms[$organism] = $uniquenames;
    }
}

if (empty($valid_organisms)) {
    http_response_code(404);
    die('No sequences found. BLAST databases may not be built for these organisms.');
}

// Build filename from search label and date
$label = trim($_POST['label'] ?? '');
$label = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $label);
$label = preg_replace('/_+/', '_', trim($label, '_'));
$date  = date('Y-m-d');
$filename = $label !== ''
    ? "annotation_search_{$label}_{$date}.fasta"
    : "annotation_search_{$date}.fasta";

// Stream output directly to avoid buffering large FASTA payloads in memory
header('Content-Type: application/x-fasta');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

$found_any = false;

foreach ($valid_organisms as $organism => $uniquenames) {
    $sources_by_group  = getAccessibleAssemblies($organism);
    $organism_sources  = flattenSourcesList($sources_by_group);

    // De-duplicate by gene-set directory. getAccessibleAssemblies() is keyed BY GROUP, so an
    // organism belonging to two groups yields the same gene set once per group — Nematostella
    // returns 4 sources for its 2 real gene sets. Extracting per source therefore wrote every
    // sequence twice. That predates this file's expansion change; it was simply less visible
    // when only transcripts were emitted (2 features -> 4 records instead of 2).
    $seen_paths = [];
    $organism_sources = array_values(array_filter($organism_sources, function ($s) use (&$seen_paths) {
        $key = $s['path'] ?? '';
        if ($key === '' || isset($seen_paths[$key])) return false;
        $seen_paths[$key] = true;
        return true;
    }));

    foreach ($organism_sources as $source) {
        if (!is_dir($source['path'])) continue;

        // Expand to every related sequence type, and scope to this source's assembly and
        // gene set. buildTypedIds() typed each id as itself, so a search returning mRNAs
        // downloaded transcripts only — the same gap that made the per-table FASTA button
        // return no CDS or protein.
        $typed_ids = expandFeaturesToAllSequenceTypes(
            $uniquenames,
            organism_db_path($organism),
            $source['assembly'] ?? '',
            $source['gene_set'] ?? ''
        );
        $result = extractSequencesForAllTypes($source['path'], $typed_ids, $sequence_types, $organism, $source['assembly']);
        if ($result['success']) {
            foreach ($result['content'] as $content) {
                $chunk = rtrim($content) . "\n";
                echo $chunk;
                $found_any = true;
            }
            if (ob_get_level() > 0) ob_flush();
            flush();
        }
    }
}

if (!$found_any) {
    // Headers already sent — log the failure so it's visible in the app log
    logError('No sequences found for any organism in FASTA download', 'download_fasta', ['organisms' => array_keys($valid_organisms)]);
}
