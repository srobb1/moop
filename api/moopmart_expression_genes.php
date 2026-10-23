<?php
/**
 * MOOPmart -> Expression: the genes the current Data Exporter query matches in ONE gene set.
 *
 * An expression heatmap belongs to one gene set (one quantification against one set of gene
 * models), while an exporter list can span many organisms, assemblies and gene sets. So the UI
 * asks once per selected gene set that has expression data, and sends each to the Expression
 * tool separately -- the list is split, never merged.
 *
 * Same POST parameters as moopmart_export.php, plus:
 *   organism, assembly, gene_set   the gene set to answer for; it must be among the selected
 *                                  sources, readable by this visitor, and have expression data
 *
 * Returns JSON: {
 *   gene_count,                    every gene the query matches in this gene set
 *   genes: [ uniquename, ... ]     the first MOOP_EXPRESSION_TOOL_MAX_GENES of them, in match order
 * }
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/functions_database.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';
require_once __DIR__ . '/../lib/expression_functions.php';

header('Content-Type: application/json');
csrf_protect();

$fail = function (int $status, string $msg) {
    http_response_code($status);
    echo json_encode(['error' => $msg]);
    exit;
};

$organism = (string)($_POST['organism'] ?? '');
$assembly = (string)($_POST['assembly'] ?? '');
$gene_set = (string)($_POST['gene_set'] ?? '');
$key      = "$organism|$assembly|$gene_set";

$selected_raw = $_POST['sources'] ?? [];
if (!is_array($selected_raw)) $selected_raw = [$selected_raw];
if (!in_array($key, $selected_raw, true)) $fail(400, 'That gene set is not part of the selection.');

$source = null;
foreach (flattenSourcesList(getAccessibleGeneSets()) as $src) {
    if ($src['organism'] . '|' . $src['assembly'] . '|' . ($src['gene_set'] ?? '') === $key) { $source = $src; break; }
}
if (!$source) $fail(404, 'Gene set not found.');

$organism_data = $config->getPath('organism_data');
$with_expr = array_filter(moop_expression_gene_sets($organism_data, get_access_level()),
    fn($gs) => "{$gs['organism']}|{$gs['assembly']}|{$gs['gene_set']}" === $key);
if (!$with_expr) $fail(404, 'That gene set has no expression data.');

$req = moopmartParsePreviewRequest($_POST);

// Row cap 0: only the gene list is wanted, so no transcript rows are expanded.
$res = moopmartCollectOrganismRows(
    $organism, [$source], $req['filters'], $req['coord_filter'],
    $req['raw_input_ids'], $req['global_filter_reason'], $organism_data, 0
);

$genes = $res['genes'] ?? [];
echo json_encode([
    'gene_count' => count($genes),
    'genes'      => array_slice($genes, 0, MOOP_EXPRESSION_TOOL_MAX_GENES),
]);
