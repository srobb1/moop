<?php
/**
 * api/v1/anatomy_genes — genes curated as expressed in an anatomy term (and the terms inside it):
 * "which genes are expressed in the pharynx?". The Anatomical Expression Search, as genes.
 *
 * GET organism  (required) an organism with curated anatomy data -- the catalog's has.curated_anatomy
 *     term      (required) an anatomy term id (PLANA:0000099); find it with ontology_search.php?o=PLANA
 *     rel       the relation that pulls in narrower terms: BFO:0000050 (part of, the default -- as the
 *               search page) or any other the page offers
 *     ev        an evidence group id from the search page (e.g. in situ only); default every kind
 *     gene_set  one reference gene set (schMedS3h1_WBPS19 ...); default all of them
 *     format    json (default) | tsv (one row per gene)
 *
 * Curations are made on the accessions papers used (GenBank, old transcriptome IDs); each is mapped to
 * the reference gene sets through the organism's crosswalk (lib/crosswalk.php) -- the same mapping the
 * search page shows. Per reference gene set: the genes, most-curated first, with how many curations,
 * which terms, evidence, stages and papers. Unmapped accessions are counted, not dropped silently.
 * Access: moop_page_visible_organism() rules via moop_page_open(); crosswalk rows the visitor may not
 * see are already dropped by moop_crosswalk_genes(). Rate-limit class: heavy.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
foreach (['annotation_genes', 'anatomy_expression', 'crosswalk'] as $__lib) require_once __DIR__ . "/../../lib/$__lib.php";
header('Cache-Control: private, no-store');

const ANATOMY_V1_GENES_SHOWN = 2000;   // per gene set

$fail = function (int $code, string $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
};
$org    = trim((string) ($_GET['organism'] ?? ''));
$term   = trim((string) ($_GET['term'] ?? ''));
$only   = trim((string) ($_GET['gene_set'] ?? ''));
$format = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
if ($org === '' || $term === '') $fail(400, 'organism and term are required.');
$scope = moop_v1_scope([$org]);   // also the access check: no gene set you may open = nothing here
if (!$scope || !moop_page_open($org)) $fail(404, "No curated anatomy data that you can open for $org.");
$spec = moop_page_spec($org, ['mode' => 'term', 'term' => $term, 'rel' => $_GET['rel'] ?? 'BFO:0000050', 'ev' => $_GET['ev'] ?? '']);
if (!$spec['ok']) $fail(400, "$term is not an anatomy term id (like PLANA:0000099).");

// Every curation, grouped by accession.
$by_acc = [];
moop_page_records_each($org, $spec, '', function ($r) use (&$by_acc) {
    $by_acc[mb_strtolower($r['accession'])][] = $r;
});
$hubs = moop_crosswalk_sources()[$org]['hubs'] ?? [];
$sets = [];   // hub => scope row (only those this visitor may open)
foreach ($scope as $g) foreach ($hubs as $hub => $h) {
    if ($h['gene_set'] === $g['gene_set'] && $h['assembly'] === $g['assembly'] && ($only === '' || $only === $g['gene_set'])) $sets[$hub] = $g;
}
if (!$sets) $fail(404, $only !== '' ? "$only is not a reference gene set for $org's curations." : "No reference gene set for $org.");

$genes = [];   // hub => gene => aggregate
$unmapped = 0;
$ids = [];
foreach (array_chunk(array_keys($by_acc), 2000) as $chunk) {
    $map = moop_crosswalk_genes($org, $chunk);
    foreach ($chunk as $acc) {
        $hit = false;
        foreach ($map[$acc] ?? [] as $m) {
            if (!isset($sets[$m['hub']])) continue;
            $hit = true;
            $e = &$genes[$m['hub']][$m['ref_id']];
            $e ??= ['gene' => $m['ref_id'], 'curations' => 0, 'accessions' => [], 'terms' => [], 'evidence' => [], 'stages' => [], 'pmids' => []];
            if (!in_array($acc, $e['accessions'], true)) {
                $e['accessions'][] = $acc;
                foreach ($by_acc[$acc] as $r) {
                    $e['curations']++;
                    foreach (['terms' => 'term_id', 'evidence' => 'evidence_id', 'stages' => 'stage_id', 'pmids' => 'pmid'] as $k => $c) {
                        $v = (string) ($r[$c] ?? '');
                        if ($v !== '' && !in_array($v, $e[$k], true)) $e[$k][] = $v;
                        if ($k !== 'pmids' && $v !== '') $ids[$v] = 1;
                    }
                }
            }
            unset($e);
        }
        if (!$hit) $unmapped++;
    }
}
$labels = moop_ontology_labels(array_keys($ids), moop_ontology_by_purpose('anatomy'))
        + moop_ontology_labels(array_keys($ids), moop_ontology_by_purpose('evidence'));
$lab = fn(array $list) => array_map(fn($id) => isset($labels[$id]) ? "$id {$labels[$id]}" : $id, $list);

$results = [];
foreach ($sets as $hub => $g) {
    $list = array_values($genes[$hub] ?? []);
    usort($list, fn($a, $b) => [$b['curations'], $a['gene']] <=> [$a['curations'], $b['gene']]);
    $total = count($list);
    $list  = array_slice($list, 0, ANATOMY_V1_GENES_SHOWN);
    $info  = [];
    if ($list) {
        $st = getDbConnection($g['db'])->prepare("SELECT f.feature_uniquename, " . moop_gene_name_sql('f') . ", f.feature_description FROM feature f
                                                   WHERE f.gene_set_id = ? AND f.feature_uniquename IN (SELECT value FROM json_each(?))");
        $st->execute([(int) $g['gene_set_id'], json_encode(array_column($list, 'gene'))]);
        foreach ($st->fetchAll(PDO::FETCH_NUM) as [$u, $n, $d]) $info[$u] = ['name' => moop_gene_symbol($n, $u), 'description' => (string) $d];
    }
    foreach ($list as &$e) {
        $e = ['gene' => $e['gene']] + ($info[$e['gene']] ?? ['name' => '', 'description' => '']) + ['curations' => $e['curations'],
              'terms' => $lab($e['terms']), 'evidence' => $lab($e['evidence']), 'stages' => $lab($e['stages']),
              'pmids' => array_slice($e['pmids'], 0, 20), 'accessions' => array_slice($e['accessions'], 0, 20)];
    }
    unset($e);
    $results[] = ['organism' => $org, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'],
                  'total' => $total, 'shown' => count($list), 'genes' => $list];
}
$notes = array_values(array_filter([
    'Curated from papers (in situ, single-cell RNA-seq, ...): "expressed in" as the authors reported it, with narrower terms '
    . 'included (' . ($spec['meta']['covered'] ?? 0) . ' of them). curations = how many records; more papers is stronger support.',
    $unmapped ? "$unmapped curated accession(s) map to no gene in these gene sets (old or partial transcripts) and are not listed." : null,
]));

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    $cell = fn($v) => str_replace(["\t", "\r", "\n"], ' ', is_array($v) ? implode('; ', $v) : (string) $v);
    echo "organism\tassembly\tgene_set\tgene\tname\tdescription\tcurations\tterms\tevidence\tstages\tpmids\taccessions\n";
    foreach ($results as $r) foreach ($r['genes'] as $e) {
        echo implode("\t", array_map($cell, [$r['organism'], $r['assembly'], $r['gene_set'], $e['gene'], $e['name'], $e['description'],
            $e['curations'], $e['terms'], $e['evidence'], $e['stages'], $e['pmids'], $e['accessions']])) . "\n";
    }
    foreach ($notes as $n) echo "# $n\n";
    exit;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['api_version' => 1, 'term' => $term, 'term_name' => moop_ontology_labels([$term], moop_ontology_by_purpose('anatomy'))[$term] ?? '',
                  'results' => $results, 'notes' => $notes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
