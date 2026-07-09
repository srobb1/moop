<?php
/**
 * MOOPmart Preview — Return feature count + first 100 rows for current filters.
 *
 * Aggregate (all selected organisms in one request). The MOOPmart UI now loads
 * the preview progressively via api/moopmart_preview_organism.php (one request
 * per organism); this endpoint remains as a single-shot preview and shares the
 * same per-organism logic via moopmartCollectOrganismRows()/moopmartBuildPreviewRows().
 *
 * POST parameters: same as moopmart_export.php (sources[], feature_types[],
 * feature_id, gene_name, gene_description,
 * ann_criteria_src[], ann_criteria_acc[], ann_criteria_kw[],
 * coord_chr, coord_start, coord_end)
 *
 * Returns JSON: {
 *   count: N,
 *   rows:  [{uniquename, name, description, type, organism_dir,
 *            genome_accession, gene_set_name, chr, start, end, strand}, ...],
 *   by_organism: [{organism, count}, ...]
 * }
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/functions_database.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';

header('Content-Type: application/json');
csrf_protect();

$selected_raw = $_POST['sources'] ?? [];
if (!is_array($selected_raw)) $selected_raw = [$selected_raw];

$all_accessible    = flattenSourcesList(getAccessibleAssemblies());
$accessible_by_key = [];
foreach ($all_accessible as $src) {
    $key = $src['organism'] . '|' . $src['assembly'] . '|' . ($src['gene_set'] ?? '');
    $accessible_by_key[$key] = $src;
}

$selected = empty($selected_raw)
    ? $all_accessible
    : array_values(array_filter(array_map(fn($k) => $accessible_by_key[$k] ?? null, $selected_raw)));

if (empty($selected)) {
    echo json_encode(['count' => 0, 'rows' => [], 'by_organism' => []]);
    exit;
}

$req = moopmartParsePreviewRequest($_POST);

$organism_data = $config->getPath('organism_data');

// Group selected sources by organism
$by_organism = [];
foreach ($selected as $src) {
    $by_organism[$src['organism']][] = $src;
}

// Collect matching mRNA rows across every selected organism
$mrna_features = [];
$gene_count    = 0;
$by_org_counts = [];

$total = 0;

foreach ($by_organism as $org => $org_sources) {
    $res = moopmartCollectOrganismRows(
        $org, $org_sources, $req['filters'], $req['coord_filter'],
        $req['raw_input_ids'], $req['global_filter_reason'], $organism_data, MOOPMART_PREVIEW_ROW_CAP
    );
    $gene_count   += $res['gene_count'];
    $total        += $res['row_count'];
    $mrna_features = array_merge($mrna_features, $res['rows']);
    $by_org_counts[] = ['organism' => $org, 'count' => $res['gene_count']];
}

$preview = array_slice($mrna_features, 0, MOOPMART_PREVIEW_ROW_CAP);

$built = moopmartBuildPreviewRows($preview, $req['annotation_columns'], $req['ann_incl_id'], $req['ann_incl_desc']);

echo json_encode([
    'count'           => $total,
    'gene_count'      => $gene_count,
    'rows'            => $built['rows'],
    'ann_col_headers' => $built['ann_col_headers'],
    'by_organism'     => $by_org_counts,
]);
