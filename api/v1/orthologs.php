<?php
/**
 * api/v1/orthologs — orthologs in related species for one gene or a list of genes, as a clean
 * table. (The gene record carries them too; this is the direct download, and it takes a list.)
 *
 * GET organism  (required)
 *     ids       (required) gene, transcript or protein IDs, space / comma separated, up to 200
 *               (also accepted: id, for one)
 *     assembly, gene_set  (optional) when an ID is in more than one gene set
 *     family=1  also the rest of each gene's family (genes in the same orthogroup that are not
 *               orthologs) -- the gene page's "Gene family in related species" card
 *     format    json (default) | tsv (one row per pair; first column asked_for)
 *
 * Rows are exactly the gene page cards' TSV rows (moop_clade_rows()), and only genes whose own
 * gene set the visitor may see. An ID the visitor cannot open is listed in not_found, the same
 * as one that does not exist. A gene in no clade simply has no rows.
 * Rate-limit class: heavy (a list runs one lookup per ID).
 */
include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['parent_functions', 'gene_lookup', 'clade_homologs'] as $__lib) require_once __DIR__ . "/../../lib/$__lib.php";

const V1_ORTHOLOGS_MAX_IDS = 200;

header('Cache-Control: private, no-store');
$format   = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
$organism = trim((string) ($_GET['organism'] ?? ''));
$family   = !empty($_GET['family']);
$ids      = preg_split('/[\s,;]+/', trim((string) ($_GET['ids'] ?? $_GET['id'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
$ids      = array_values(array_unique($ids));
$too_many = count($ids) > V1_ORTHOLOGS_MAX_IDS;
$ids      = array_slice($ids, 0, V1_ORTHOLOGS_MAX_IDS);

if ($organism === '' || !$ids) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => 'organism and ids are required.']);
    exit;
}

$db = ConfigManager::getInstance()->getPath('organism_data') . "/$organism/organism.sqlite";
$results = $not_found = $choose = [];
foreach ($ids as $id) {
    $L = moop_gene_lookup($organism, $id, (string) ($_GET['assembly'] ?? ''), (string) ($_GET['gene_set'] ?? ''));
    if ($L['status'] === 'choose') { $choose[] = ['id' => $id, 'gene_sets' => array_map(fn($c) => ['assembly' => $c['assembly'], 'gene_set' => $c['gene_set']], $L['choices'])]; continue; }
    if ($L['status'] !== 'ok') { $not_found[] = $id; continue; }
    $row   = $L['row'];
    $kids  = array_column(getChildren($row['feature_id'], $db, $L['gene_set_ids']), 'feature_uniquename');
    $rows  = moop_clade_rows($organism, (string) $row['gene_set_name'], [$row['feature_uniquename'], $L['ancestor_uniquename']], $kids);
    $results[] = ['asked_for' => $id, 'gene' => $row['feature_uniquename'], 'assembly' => $row['genome_accession'],
                  'gene_set' => $row['gene_set_name'], 'orthologs' => $rows['orthologs']]
               + ($family ? ['gene_family' => $rows['family']] : []);
}

$notes = array_values(array_filter([
    $too_many ? 'Only the first ' . V1_ORTHOLOGS_MAX_IDS . ' IDs were looked up.' : null,
    $choose ? 'Some IDs are in more than one gene set; ask again with assembly and gene_set.' : null,
]));

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    $out = [];
    foreach ($results as $r) {
        foreach (['orthologs' => 'ortholog', 'gene_family' => 'gene_family'] as $k => $what) {
            foreach ($r[$k] ?? [] as $row) {
                if (!$out) $out[] = implode("\t", array_merge(['asked_for', 'row_type'], array_keys($row)));
                $out[] = implode("\t", array_merge([$r['asked_for'], $what], array_values($row)));
            }
        }
    }
    foreach ($notes as $n) $out[] = "# $n";
    foreach ($not_found as $n) $out[] = "# not found: $n";
    echo $out ? implode("\n", $out) . "\n" : '';
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['api_version' => 1, 'organism' => $organism, 'results' => $results, 'not_found' => $not_found,
                  'choose_gene_set' => $choose, 'notes' => $notes],
                 JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
