<?php
/**
 * api/v1/human_genes — start from human genes: the genes here that are their orthologs or closest
 * relatives ("what is the planarian TP53?", "planarian genes for these 50 human genes").
 *
 * GET human     (required) HGNC symbols (TP53, PIWIL1) and/or HGNC ids (HGNC:11998), comma-separated,
 *               up to 200
 *     organism  (required) organism directory name(s), comma-separated, up to 20 -- from api/v1/catalog
 *     assembly, gene_set  (optional, with one organism) narrow to those
 *     format    json (default) | tsv (one row per human gene x gene here)
 *
 * Evidence, strongest first -- the annotation sources every organism carries:
 *   OMA pairwise orthologs (HUMAN), OMA HOG orthologs (HUMAN), OMA group orthologs (HUMAN): orthology
 *   Closest human gene (HGNC): the human gene MOOP's naming picked as closest -- an ORTHOLOG when its
 *     evidence says so ("[OMA ortholog (1:1)]", "[via mouse ortholog ... > Ensembl Compara]"), otherwise
 *     a HOMOLOG found by similarity ("[reciprocal best hit (MMseqs2)]", "[best DIAMOND hit ...]")
 * All four store "SYMBOL: name (...)" as the description, which is what is matched (case-insensitive,
 * whole symbol); the closest-gene rows also match on the HGNC id. Per gene set, per human gene: the
 * genes with their evidence, orthologs first. Rate-limit class: heavy.
 */
include_once __DIR__ . '/../../tools/tool_init.php';
require_once __DIR__ . '/../../lib/annotation_genes.php';   // moop_v1_scope(), functions_display.php
header('Cache-Control: private, no-store');

const HUMAN_V1_SOURCES = [   // source name => [evidence label, rank (lower = stronger)]
    'OMA pairwise orthologs (HUMAN)' => ['OMA pairwise ortholog', 1],
    'OMA HOG orthologs (HUMAN)'      => ['OMA HOG (hierarchical orthologous group) ortholog', 2],
    'OMA group orthologs (HUMAN)'    => ['OMA group ortholog', 3],
    // Split by its own bracketed evidence (moop_v1_human_closest): ortholog 4, similarity only 5.
    'Closest human gene (HGNC)'      => ['closest human gene', 4],
];

$fail = function (int $code, string $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['api_version' => 1, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
};
$split  = fn($v) => array_values(array_unique(preg_split('/[\s,;]+/', trim((string) $v), -1, PREG_SPLIT_NO_EMPTY)));
$asked  = array_slice($split($_GET['human'] ?? ''), 0, 200);
$orgs   = array_slice($split($_GET['organism'] ?? ''), 0, 20);
$format = ($_GET['format'] ?? '') === 'tsv' ? 'tsv' : 'json';
if (!$asked || !$orgs) $fail(400, 'human and organism are required.');
$scope = moop_v1_scope($orgs, (string) ($_GET['assembly'] ?? ''), (string) ($_GET['gene_set'] ?? ''));
if (!$scope) $fail(404, 'No gene set that you can open for ' . implode(', ', $orgs) . '.');

$symbols = [];
$hgnc    = [];
foreach ($asked as $a) {
    if (preg_match('/^HGNC:\d+$/i', $a)) $hgnc[] = strtoupper($a);
    else $symbols[] = strtoupper($a);
}

$results = [];
$found_symbol = [];   // HGNC id => symbol, learned from the closest-gene rows
$by_db = [];
foreach ($scope as $g) $by_db[$g['db']][] = $g;
foreach ($by_db as $db => $sets) {
    try {
        $r = moop_v1_human_genes($db, array_map(fn($g) => (int) $g['gene_set_id'], $sets), $symbols, $hgnc);
    } catch (Throwable $e) {
        error_log("api/v1 human_genes {$sets[0]['organism']}: " . $e->getMessage());
        foreach ($sets as $g) $results[] = ['organism' => $g['organism'], 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'], 'error' => 'could not be read'];
        continue;
    }
    $found_symbol += $r['hgnc_symbols'];
    foreach ($sets as $g) {
        $results[] = ['organism' => $g['organism'], 'assembly' => $g['assembly'], 'gene_set' => $g['gene_set'],
                      'human_genes' => $r['by_set'][(int) $g['gene_set_id']] ?? (object) []];
    }
}
$seen = [];
foreach ($results as $r) foreach (array_keys((array) ($r['human_genes'] ?? [])) as $s) $seen[$s] = true;
$missing = array_values(array_filter(array_merge($symbols, $hgnc), fn($s) => !isset($seen[$s]) && !isset($seen[$found_symbol[$s] ?? ''])));
$notes = array_values(array_filter([
    'Orthologs first (OMA, then a closest human gene backed by orthology); "homolog by similarity" is not shown to be an ortholog. A human gene with several '
    . 'genes here is common (copies, paralogs); each gene\'s record (gene.php) explains its naming.',
    $missing ? 'No ortholog or closest-human-gene link to: ' . implode(', ', $missing) . '. That is not proof the gene is absent: '
             . 'a gene here may be linked to a paralog instead (FOXA2 for FOXA1), or be named by a curator from functional '
             . 'studies with no closest human gene at all (Smed "p53"). '
             . 'Try search.php with the symbol or gene name, and check the symbol is the current HGNC one.' : null,
]));

if ($format === 'tsv') {
    header('Content-Type: text/tab-separated-values; charset=utf-8');
    $cell = fn($v) => str_replace(["\t", "\r", "\n"], ' ', (string) $v);
    echo "human_gene\torganism\tassembly\tgene_set\tgene\tname\tdescription\tbest_evidence\tevidence\n";
    foreach ($results as $r) {
        foreach ((array) ($r['human_genes'] ?? []) as $sym => $genes) {
            foreach ($genes as $gene) {
                echo implode("\t", array_map($cell, [$sym, $r['organism'], $r['assembly'], $r['gene_set'], $gene['gene'], $gene['name'],
                    $gene['description'], $gene['evidence'][0]['source'] ?? '',
                    implode('; ', array_map(fn($e) => $e['source'] . ($e['relation'] !== '' ? " ({$e['relation']})" : ''), $gene['evidence']))])) . "\n";
            }
        }
    }
    foreach ($notes as $n) echo "# $n\n";
    exit;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['api_version' => 1, 'human' => $asked, 'results' => $results, 'notes' => $notes],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * One organism database, all its gene sets in scope at once (the annotation scan is per database).
 * @param list<int> $gene_set_ids
 * @return array{by_set: array<int, array<string, list<array>>>, hgnc_symbols: array<string,string>}
 */
function moop_v1_human_genes(string $db_file, array $gene_set_ids, array $symbols, array $hgnc): array {
    $dbh = getDbConnection($db_file);
    // 1. The asked-for annotations: one pass over the four human sources' rows (no index on the source
    //    column, so this is the scan -- done once per database, not once per gene set: 26 s -> see below).
    $st = $dbh->prepare("SELECT a.annotation_id, s.annotation_source_name, a.annotation_accession, a.annotation_description,
                                upper(substr(a.annotation_description, 1, instr(a.annotation_description, ':') - 1))
                           FROM annotation_source s
                          CROSS JOIN annotation a ON a.annotation_source_id = s.annotation_source_id
                          WHERE s.annotation_source_name IN (SELECT value FROM json_each(?))
                            AND (upper(substr(a.annotation_description, 1, instr(a.annotation_description, ':') - 1)) IN (SELECT value FROM json_each(?))
                                 OR a.annotation_accession IN (SELECT value FROM json_each(?)))");
    $st->execute([json_encode(array_keys(HUMAN_V1_SOURCES)), json_encode($symbols), json_encode($hgnc)]);
    $ann = [];
    $hgnc_symbols = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$id, $src, $acc, $d, $sym]) {
        if (str_starts_with((string) $acc, 'HGNC:')) $hgnc_symbols[$acc] = (string) $sym;
        $ann[(int) $id] = ['sym' => (string) $sym] + moop_v1_human_evidence((string) $src, (string) $d);
    }
    if (!$ann) return ['by_set' => [], 'hgnc_symbols' => $hgnc_symbols];

    // 2. Their features in the gene sets asked for (annotation_id is indexed), up to the gene.
    $st = $dbh->prepare("SELECT fa.annotation_id, f.gene_set_id, COALESCE(f.parent_feature_id, f.feature_id)
                           FROM feature_annotation fa CROSS JOIN feature f ON f.feature_id = fa.feature_id
                          WHERE fa.annotation_id IN (SELECT value FROM json_each(?))
                            AND f.gene_set_id IN (SELECT value FROM json_each(?))");
    $st->execute([json_encode(array_keys($ann)), json_encode($gene_set_ids)]);
    $hits = [];   // gene_set_id => "sym\x1fgene" => evidence list
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$aid, $gsid, $gene]) {
        $a = $ann[(int) $aid];
        $e = ['source' => $a['source'], 'relation' => $a['relation'], 'rank' => $a['rank']];
        $k = $a['sym'] . "\x1f" . (int) $gene;
        if (!in_array($e, $hits[(int) $gsid][$k] ?? [], true)) $hits[(int) $gsid][$k][] = $e;
    }
    $gene_ids = [];
    foreach ($hits as $set) foreach (array_keys($set) as $k) $gene_ids[(int) explode("\x1f", $k)[1]] = true;
    if (!$gene_ids) return ['by_set' => [], 'hgnc_symbols' => $hgnc_symbols];
    $st = $dbh->prepare("SELECT f.feature_id, f.feature_uniquename, " . moop_gene_name_sql('f') . ", f.feature_description
                           FROM feature f WHERE f.feature_id IN (SELECT value FROM json_each(?))");
    $st->execute([json_encode(array_keys($gene_ids))]);
    $info = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$id, $u, $n, $d]) {
        $info[(int) $id] = ['gene' => $u, 'name' => moop_gene_symbol($n, $u), 'description' => (string) $d];
    }

    $by_set = [];
    foreach ($hits as $gsid => $set) {
        $by_human = [];
        foreach ($set as $k => $ev) {
            [$sym, $gid] = explode("\x1f", $k);
            if (!isset($info[(int) $gid])) continue;
            usort($ev, fn($a, $b) => $a['rank'] <=> $b['rank']);
            $by_human[$sym][] = $info[(int) $gid] + ['_best' => $ev[0]['rank'],
                'evidence' => array_map(fn($e) => array_diff_key($e, ['rank' => 1]), $ev)];
        }
        foreach ($by_human as &$genes) {
            usort($genes, fn($a, $b) => [$a['_best'], $a['gene']] <=> [$b['_best'], $b['gene']]);
            $genes = array_map(fn($g) => array_diff_key($g, ['_best' => 1]), $genes);
        }
        unset($genes);
        ksort($by_human);
        $by_set[$gsid] = $by_human;
    }
    return ['by_set' => $by_set, 'hgnc_symbols' => $hgnc_symbols];
}

/**
 * One annotation's evidence: {source label, relation, rank}. OMA rows end "(1:many)" or "(1:1, HOG:...)";
 * the closest-gene rows end "[OMA ortholog (1:1)]" / "[via mouse ortholog (OMA 1:1) > Ensembl Compara
 * (one2one)]" (orthology) or "[reciprocal best hit (MMseqs2)]" / "[best DIAMOND hit ...]" (similarity).
 */
function moop_v1_human_evidence(string $src, string $d): array {
    [$label, $rank] = HUMAN_V1_SOURCES[$src];
    if ($src === 'Closest human gene (HGNC)') {
        $how = preg_match('/\[([^\[\]]*)\]\s*$/', $d, $m) ? trim($m[1]) : '';
        $orth = preg_match('/ortholog|Compara/i', $how);
        return ['source' => $orth ? 'closest human gene: ortholog' : 'closest human gene: homolog by similarity (not shown to be an ortholog)',
                'relation' => $how, 'rank' => $orth ? 4 : 5];
    }
    $rel = preg_match('/\(([^()]*)\)\s*$/', $d, $m) ? trim(preg_replace('/,?\s*(HOG|OMA group)\b.*$/', '', $m[1])) : '';
    return ['source' => $label, 'relation' => $rel, 'rank' => $rank];
}
