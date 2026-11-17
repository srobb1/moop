<?php
/**
 * The term page's gene list: the genes of ONE gene set annotated to a GO term.
 *
 *   GET api/go_term_genes.php?term=GO:0006629&organism=X&assembly=Y&gene_set=Z[&sub=1]
 *   -> {"total":1142,"genes":[[uniquename, name, description, [matched GO ids], n matched], ...],
 *       "labels":{"GO:...":"name"}}
 *   sub=1: the term or a more specific one (is a + part of) -- the same genes as the count and the
 *   Data Exporter link beside it (lib/go_gene_counts.php, moop_go_term_gene_list()).
 *
 * One gene set per request, by design (no all-organisms list). Access: the gene set must be one
 * this visitor may see, else 404 -- never says whether it exists. READ-ONLY. A broad term takes
 * ~2-3 s, so it is in rate_limit_heavy (config/site_config.php).
 */
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/functions_database.php';
require_once __DIR__ . '/../lib/go_gene_counts.php';
header('Content-Type: application/json');
header('Cache-Control: private, max-age=300');

$term = (string) ($_GET['term'] ?? '');
$org  = (string) ($_GET['organism'] ?? '');
$asm  = (string) ($_GET['assembly'] ?? '');
$gs   = (string) ($_GET['gene_set'] ?? '');
$row  = null;
if ($org !== '' && $asm !== '' && $gs !== '' && preg_match('/^GO:\d{7}$/', $term)) {
    foreach (getAccessibleGeneSets($org, $asm, $gs) as $by_org) {
        foreach ($by_org[$org] ?? [] as $r) {
            if ($r['assembly'] === $asm && $r['gene_set'] === $gs) { $row = $r; break 2; }
        }
    }
}
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}
$out = moop_go_term_gene_list($row, $term, !empty($_GET['sub']));
if (isset($out['error'])) http_response_code(500);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
