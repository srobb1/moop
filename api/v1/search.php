<?php
/**
 * api/v1/search — keyword search for genes: the site's own Search, as genes rather than rows.
 *
 * GET q         (required) words, as typed on the Search page ("piwi", "smedwi-1", "heat shock");
 *               in double quotes for an exact phrase
 *     organism  (required) organism directory name(s), comma-separated, up to 10 -- from api/v1/catalog
 *     assembly, gene_set  (optional, with one organism) narrow to those
 *     in        all (default: gene names, descriptions AND annotations -- GO, domains, orthologs ...)
 *               | names (gene names and descriptions only: fewer, closer matches)
 *     format    json (default) | tsv (one row per gene)
 *
 * The same queries, ranking and permission rules as tools/annotation_search_ajax.php, so an agent
 * gets what a person on the Search page would. That search returns one row per transcript x
 * matching annotation, capped per organism (moop_search_results_limit()); here the rows are rolled
 * up to their GENES (transcript -> gene via parent_feature_id) with up to 3 "matched in" reasons.
 * A capped organism says so: genes may be missing, and the answer is to narrow, not to page.
 * Rate-limit class: heavy (a cold cross-organism search reads a lot of disk).
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/annotation_genes.php';   // moop_v1_scope(), functions_display.php
header('Cache-Control: private, no-store');

const SEARCH_V1_MAX_ORGANISMS = 10;
const SEARCH_V1_GENES_SHOWN   = 500;   // per gene set

$fail = function (int $code, string $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
};
$q      = trim((string) ($_GET['q'] ?? ''));
$orgs   = array_values(array_unique(preg_split('/[\s,;]+/', trim((string) ($_GET['organism'] ?? '')), -1, PREG_SPLIT_NO_EMPTY)));
$format = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
$names_only = ($_GET['in'] ?? '') === 'names';
if ($q === '' || !$orgs) $fail(400, 'q and organism are required.');
if (count($orgs) > SEARCH_V1_MAX_ORGANISMS) $fail(400, 'At most ' . SEARCH_V1_MAX_ORGANISMS . ' organisms per search.');
$one = count($orgs) === 1;
$assembly = $one ? (string) ($_GET['assembly'] ?? '') : '';
$gene_set = $one ? (string) ($_GET['gene_set'] ?? '') : '';

$quoted = strlen($q) > 1 && $q[0] === '"' && substr($q, -1) === '"';
$input  = sanitize_search_input($quoted ? substr($q, 1, -1) : $q, $quoted);
if (!moop_search_input_is_usable($input, $quoted)) {
    $fail(400, 'Search words too short or too common. Use at least one word of 3 or more letters, or an ID.');
}

$limit   = moop_search_results_limit();
$results = [];
$capped  = [];
foreach ($orgs as $org) {
    $scope = moop_v1_scope([$org], $assembly, $gene_set);   // gene sets this visitor may open
    if (!$scope) continue;
    $ids = [];
    foreach ($scope as $g) $ids[$g['assembly'] . "\x1f" . $g['gene_set']] = $g;
    $db = $scope[0]['db'];

    // As tools/annotation_search_ajax.php: unfiltered stays unfiltered when everything is open (the
    // fast path, 2-3x on cold searches); otherwise only the permitted gene sets are searched.
    $narrowed = moop_search_narrow_scope(moop_search_permitted_scope($org), [], $assembly, $gene_set);
    if ($narrowed['blocked']) continue;
    $pairs = $narrowed['pairs'];
    try {
        $rows = searchFeaturesByUniquenameForSearch($input, $db, '', $assembly, $gene_set, $pairs);
        if (!$rows) {
            $rows = ($names_only
                ? searchFeaturesByNameDescription($input, $quoted, $db, $assembly, $gene_set, $pairs, $org)
                : searchFeaturesAndAnnotations($input, $quoted, $db, [], $assembly, $gene_set, $pairs))['results'];
        }
    } catch (Throwable $e) {
        error_log("api/v1 search $org: " . $e->getMessage());
        $results[] = ['organism' => $org, 'error' => 'could not be searched'];
        continue;
    }
    if (count($rows) >= $limit) $capped[] = $org;

    // Rows -> genes, per gene set, in the search's own order (best first).
    $by_set = [];
    foreach ($rows as $r) {
        $k = ($r['genome_accession'] ?? '') . "\x1f" . ($r['gene_set_name'] ?? '');
        if (!isset($ids[$k])) continue;   // not a gene set this visitor may open
        $by_set[$k][] = $r;
    }
    foreach ($by_set as $k => $set_rows) {
        $g = $ids[$k];
        $genes = moop_v1_search_genes($db, (int) $g['gene_set_id'], $set_rows);
        $results[] = ['organism' => $org, 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'],
                      'genes_found' => count($genes), 'shown' => min(count($genes), SEARCH_V1_GENES_SHOWN),
                      'genes' => array_slice($genes, 0, SEARCH_V1_GENES_SHOWN)];
    }
}

$notes = array_values(array_filter([
    $capped ? 'The search stopped at ' . number_format($limit) . ' matching rows in ' . implode(', ', $capped)
              . ': more genes may match. Narrow it (gene_set=, more or quoted words, in=names) -- do not page around it.' : null,
    'Matches include genes whose ANNOTATIONS mention the words (orthologs, domains, GO ...), not only their names. '
    . 'matched_in says why each gene is here; in=names keeps only name and description matches.',
]));

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    $cell = fn($v) => str_replace(["\t", "\r", "\n"], ' ', (string) $v);
    echo "organism\tassembly\tgene_set\tgene\tname\tdescription\tmatched_in\n";
    foreach ($results as $r) {
        foreach ($r['genes'] ?? [] as $gene) {
            $why = implode('; ', array_map(fn($m) => implode(' ', array_filter([$m['source'], $m['accession'], $m['text']], 'strlen')), $gene['matched_in']));
            echo implode("\t", array_map($cell, [$r['organism'], $r['assembly'], $r['gene_set'], $gene['gene'],
                                                 $gene['name'], $gene['description'], $why])) . "\n";
        }
    }
    foreach ($notes as $n) echo "# $n\n";
    exit;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['api_version' => 1, 'q' => $q, 'in' => $names_only ? 'names' : 'all', 'results' => $results,
                  'notes' => $notes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Search rows of one gene set -> genes in first-match order, each with up to 3 reasons.
 * @return list<array{gene:string,name:string,description:string,matched_in:list<array>}>
 */
function moop_v1_search_genes(string $db_file, int $gene_set_id, array $rows): array {
    $uniq = array_values(array_unique(array_column($rows, 'feature_uniquename')));
    $dbh  = getDbConnection($db_file);
    // Each matched feature -> its root (the gene). Transcripts are one level down, proteins/CDS two.
    $st = $dbh->prepare("WITH RECURSIVE up(start, id, parent, depth) AS (
                             SELECT feature_uniquename, feature_id, parent_feature_id, 0 FROM feature
                              WHERE gene_set_id = ? AND feature_uniquename IN (SELECT value FROM json_each(?))
                             UNION ALL
                             SELECT up.start, f.feature_id, f.parent_feature_id, up.depth + 1
                               FROM feature f JOIN up ON f.feature_id = up.parent WHERE up.depth < 4)
                         SELECT up.start, f.feature_uniquename, " . moop_gene_name_sql('f') . " AS name, f.feature_description
                           FROM up JOIN feature f ON f.feature_id = up.id WHERE up.parent IS NULL");
    $st->execute([$gene_set_id, json_encode($uniq)]);
    $root = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$start, $gene, $name, $desc]) {
        $root[$start] = ['gene' => $gene, 'name' => moop_gene_symbol($name, $gene), 'description' => (string) $desc];
    }
    $genes = [];
    foreach ($rows as $r) {
        $g = $root[$r['feature_uniquename']] ?? null;
        if (!$g) continue;
        $e = &$genes[$g['gene']];
        $e ??= $g + ['matched_in' => []];
        $why = ($r['annotation_accession'] ?? '') !== ''
            ? ['source' => (string) $r['annotation_source_name'], 'accession' => (string) $r['annotation_accession'],
               'text' => mb_substr(decodeAnnotationText((string) ($r['annotation_description'] ?? '')), 0, 160)]
            : ['source' => 'gene', 'accession' => '', 'text' => 'name, description or ID'];
        if (count($e['matched_in']) < 3 && !in_array($why, $e['matched_in'], true)) $e['matched_in'][] = $why;
        unset($e);
    }
    return array_values($genes);
}
