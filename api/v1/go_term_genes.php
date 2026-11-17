<?php
/**
 * api/v1/go_term_genes — the genes in one gene set annotated with a GO term, each flagged with
 * where its GO annotation came from, and optionally limited to some of those sources.
 *
 * GET term        (required) GO:0005515
 *     organism, assembly, gene_set  (required) one gene set, from api/v1/catalog
 *     sub=1       the term or any more specific one (is a + part of), as the GO term page counts
 *     go_sources  only these sources, e.g. panther, or oma,panther -- eggnog | panther | oma | interpro
 *                 (lib/go_sources.php; empty = all four)
 *
 * Returns { total, genes: [{gene, name, description, terms: [GO ids matched, up to 3], n_terms,
 * sources: [keys]}], labels: {GO id: name}, go_sources: {applied, legend} }.
 *
 * Same genes as the GO term page (moop_go_term_gene_list(), lib/go_gene_counts.php) when no source
 * is chosen. A gene set the visitor cannot open: 404, as if absent. Rate-limit class: heavy (~3 s).
 */
include_once __DIR__ . '/../../tools/tool_init.php';
include_once __DIR__ . '/../../lib/functions_database.php';
require_once __DIR__ . '/../../lib/go_gene_counts.php';
require_once __DIR__ . '/../../lib/go_sources.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
$json = function (int $code, array $body) {
    http_response_code($code);
    echo json_encode(['api_version' => 1] + $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
};

$term = (string) ($_GET['term'] ?? '');
$org  = (string) ($_GET['organism'] ?? '');
$asm  = (string) ($_GET['assembly'] ?? '');
$gs   = (string) ($_GET['gene_set'] ?? '');
if (!preg_match('/^GO:\d{7}$/', $term) || $org === '' || $asm === '' || $gs === '') {
    $json(400, ['error' => 'term (GO:0000000), organism, assembly and gene_set are required.']);
}
$src = moop_go_sources_param((string) ($_GET['go_sources'] ?? ''));
if ($src['unknown']) {
    $json(400, ['error' => 'Unknown go_sources: ' . implode(', ', $src['unknown']) . '. Use: ' . implode(', ', array_keys(moop_go_sources())) . '.']);
}

// The gene set must be one this visitor may open (as api/go_term_genes.php).
$row = null;
foreach (getAccessibleGeneSets($org, $asm, $gs) as $by_org) {
    foreach ($by_org[$org] ?? [] as $r) {
        if ($r['assembly'] === $asm && $r['gene_set'] === $gs) { $row = $r; break 2; }
    }
}
if (!$row) $json(404, ['error' => 'No such gene set that you can open.']);

$names = $src['keys'] === null ? null : moop_go_source_names(organism_db_path($org), $src['keys']);
$out   = moop_go_term_gene_list($row, $term, !empty($_GET['sub']), 3, $names, true);
if (isset($out['error'])) $json(500, ['error' => $out['error']]);

$genes = array_map(function ($g) {
    $keys = array_values(array_unique(array_map(fn($n) => moop_go_source_key($n) ?? $n, $g[5] ?? [])));
    return ['gene' => $g[0], 'name' => $g[1], 'description' => $g[2], 'terms' => $g[3], 'n_terms' => $g[4], 'sources' => $keys];
}, $out['genes']);

$json(200, [
    'term' => $term, 'organism' => $org, 'assembly' => $asm, 'gene_set' => $gs, 'sub' => !empty($_GET['sub']),
    'total' => $out['total'], 'genes' => $genes, 'labels' => (object) $out['labels'],
    'go_sources' => ['applied' => $src['keys'] ?? 'all', 'legend' => moop_go_sources_legend()],
]);
