<?php
/**
 * api/v1/gene_annotations — chosen kinds of annotation for one gene or a list of genes: their protein
 * domains, KEGG pathways, GO terms, protein features (predicted subcellular location, signal peptide,
 * transmembrane helices), or all of them. The gene record (api/v1/gene) has everything for ONE gene; this is
 * the list version, kept to the kinds asked for.
 *
 * GET organism  (required)
 *     ids       (required) gene, transcript or protein IDs, space / comma separated, up to 200 (also: id)
 *     type      domains | pathways | go | protein_features | all -- comma-separated for several
 *               (default domains). protein_features holds DeepLoc's predicted location IN THE CELL, not tissue.
 *     assembly, gene_set  (optional) when an ID is in more than one gene set
 *     go_sources (optional, with go) eggnog | panther | oma | interpro -- only GO terms from these
 *     format    json (default) | tsv (one row per gene x annotation)
 *
 * Each ID resolves to its gene (moop_gene_lookup(), the gene page's rule); annotations of the gene and its
 * transcripts are merged, one entry per (source, accession), with 'on' = the features carrying it. GO terms
 * are ID + name, one entry each with every source in 'sources'. An ID the visitor cannot open is in
 * not_found, the same as one that does not exist. Rate-limit class: heavy (one lookup per ID).
 */
include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['parent_functions', 'gene_lookup', 'go_sources'] as $__lib) require_once __DIR__ . "/../../lib/$__lib.php";

const V1_GENE_ANN_MAX_IDS = 200;
const V1_GENE_ANN_TYPES = ['domains' => 'Domains', 'pathways' => 'Pathways', 'go' => 'Gene Ontology',
                           'protein_features' => 'Protein Features'];

header('Cache-Control: private, no-store');
$format   = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
$organism = trim((string) ($_GET['organism'] ?? ''));
$ids      = array_values(array_unique(preg_split('/[\s,;]+/', trim((string) ($_GET['ids'] ?? $_GET['id'] ?? '')), -1, PREG_SPLIT_NO_EMPTY)));
$too_many = count($ids) > V1_GENE_ANN_MAX_IDS;
$ids      = array_slice($ids, 0, V1_GENE_ANN_MAX_IDS);
$fail = function (int $code, string $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
};
$asked = preg_split('/[\s,;]+/', strtolower(trim((string) ($_GET['type'] ?? 'domains'))), -1, PREG_SPLIT_NO_EMPTY) ?: ['domains'];
if (in_array('all', $asked, true)) $asked = array_keys(V1_GENE_ANN_TYPES);
if ($bad = array_diff($asked, array_keys(V1_GENE_ANN_TYPES))) {
    $fail(400, 'Unknown type: ' . implode(', ', $bad) . '. Use: ' . implode(', ', array_keys(V1_GENE_ANN_TYPES)) . ', all.');
}
$types = array_intersect_key(V1_GENE_ANN_TYPES, array_flip($asked));   // key => annotation type
if ($organism === '' || !$ids) $fail(400, 'organism and ids are required.');
$go_src = moop_go_sources_param((string) ($_GET['go_sources'] ?? ''));
if ($go_src['unknown']) $fail(400, 'Unknown go_sources: ' . implode(', ', $go_src['unknown']) . '. Use: ' . implode(', ', array_keys(moop_go_sources())) . '.');

$db = ConfigManager::getInstance()->getPath('organism_data') . "/$organism/organism.sqlite";
$results = $not_found = $choose = [];
foreach ($ids as $id) {
    $L = moop_gene_lookup($organism, $id, (string) ($_GET['assembly'] ?? ''), (string) ($_GET['gene_set'] ?? ''));
    if ($L['status'] === 'choose') { $choose[] = ['id' => $id, 'gene_sets' => array_map(fn($c) => ['assembly' => $c['assembly'], 'gene_set' => $c['gene_set']], $L['choices'])]; continue; }
    if ($L['status'] !== 'ok') { $not_found[] = $id; continue; }
    $row  = $L['row'];
    $kids = getChildren($row['feature_id'], $db, $L['gene_set_ids']);
    $out  = array_fill_keys(array_keys($types), []);
    foreach (getAllAnnotationsForFeatures(array_merge([$row['feature_id']], array_column($kids, 'feature_id')), $db) as $by_type) {
        foreach ($types as $key => $type) {
            foreach ($by_type[$type] ?? [] as $a) {
                $acc = (string) $a['annotation_accession'];
                if ($key === 'go') {
                    $src = moop_go_source_key((string) $a['annotation_source_name']) ?? (string) $a['annotation_source_name'];
                    if ($go_src['keys'] !== null && !in_array($src, $go_src['keys'], true)) continue;
                    $e = &$out[$key][$acc];
                    $e ??= ['accession' => $acc, 'name' => preg_match('/^(.*?): "/s', (string) $a['annotation_description'], $m) ? $m[1] : (string) $a['annotation_description'],
                            'sources' => [], 'on' => []];
                    if (!in_array($src, $e['sources'], true)) $e['sources'][] = $src;
                } else {
                    $e = &$out[$key][$a['annotation_source_name'] . "\x1f" . $acc];
                    $e ??= ['accession' => $acc, 'description' => (string) $a['annotation_description'],
                            'source' => $a['annotation_source_name'], 'score' => is_numeric($a['score']) ? $a['score'] + 0 : null, 'on' => []];
                }
                if (!in_array($a['feature_uniquename'], $e['on'], true)) $e['on'][] = $a['feature_uniquename'];
                unset($e);
            }
        }
    }
    $results[] = ['asked_for' => $id, 'gene' => $row['feature_uniquename'], 'name' => (string) ($row['feature_name'] ?? ''),
                  'assembly' => $row['genome_accession'], 'gene_set' => $row['gene_set_name']]
               + array_map('array_values', $out);
}

$notes = array_values(array_filter([
    $too_many ? 'Only the first ' . V1_GENE_ANN_MAX_IDS . ' IDs were looked up.' : null,
    $choose ? 'Some IDs are in more than one gene set; ask again with assembly and gene_set.' : null,
    isset($types['protein_features']) ? 'protein_features: DeepLoc gives the predicted location of the protein in the cell, not the tissue where the gene is expressed.' : null,
]));

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    $cell = fn($v) => str_replace(["\t", "\r", "\n"], ' ', (string) $v);
    echo implode("\t", ['asked_for', 'gene', 'assembly', 'gene_set', 'type', 'source', 'accession', 'description', 'score', 'on']) . "\n";
    foreach ($results as $r) {
        foreach (array_keys($types) as $key) {
            foreach ($r[$key] as $e) {
                echo implode("\t", array_map($cell, [$r['asked_for'], $r['gene'], $r['assembly'], $r['gene_set'], $key,
                    isset($e['sources']) ? implode(',', $e['sources']) : $e['source'], $e['accession'],
                    $e['name'] ?? $e['description'], $e['score'] ?? '', implode(' ', $e['on'])])) . "\n";
            }
        }
    }
    foreach ($notes as $n) echo "# $n\n";
    foreach ($not_found as $n) echo "# not found: $n\n";
    exit;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['api_version' => 1, 'organism' => $organism, 'type' => array_keys($types), 'results' => $results,
                  'not_found' => $not_found, 'choose_gene_set' => $choose, 'notes' => $notes]
                 + (isset($types['go']) ? ['go_sources' => ['applied' => $go_src['keys'] ?? 'all', 'legend' => moop_go_sources_legend()]] : []),
                 JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
