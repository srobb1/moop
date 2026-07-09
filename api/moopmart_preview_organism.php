<?php
/**
 * MOOPmart Per-Organism Preview — feature count + preview rows for ONE organism.
 *
 * Same POST parameters as moopmart_preview.php, plus:
 *   organism — the organism directory name to scope this request to
 *
 * Called by the MOOPmart UI in a JS fan-out (one request per organism,
 * concurrency-limited) so the preview populates progressively instead of
 * blocking on a single request that loops every organism server-side. Mirrors
 * the AnnotationSearch fan-out pattern used by the search pages.
 *
 * Returns JSON: {
 *   organism, gene_count, count,
 *   rows: [ {...preview row...} ],   // up to 100 for this organism
 *   ann_col_headers: [ ... ]
 * }
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/functions_database.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';

header('Content-Type: application/json');
csrf_protect();

$empty = fn($org) => json_encode([
    'organism' => $org, 'gene_count' => 0, 'count' => 0, 'rows' => [], 'ann_col_headers' => [],
]);

$organism = trim($_POST['organism'] ?? '');
if ($organism === '') { echo $empty(''); exit; }

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

// Scope to this organism's accessible sources only
$org_sources = array_values(array_filter($selected, fn($s) => $s['organism'] === $organism));
if (empty($org_sources)) { echo $empty($organism); exit; }

$req = moopmartParsePreviewRequest($_POST);

$organism_data = $config->getPath('organism_data');

$res = moopmartCollectOrganismRows(
    $organism, $org_sources, $req['filters'], $req['coord_filter'],
    $req['raw_input_ids'], $req['global_filter_reason'], $organism_data, MOOPMART_PREVIEW_ROW_CAP
);

$built = moopmartBuildPreviewRows($res['rows'], $req['annotation_columns'], $req['ann_incl_id'], $req['ann_incl_desc']);

echo json_encode([
    'organism'        => $organism,
    'gene_count'      => $res['gene_count'],
    'count'           => $res['row_count'],
    'rows'            => $built['rows'],
    'ann_col_headers' => $built['ann_col_headers'],
]);
