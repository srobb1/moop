<?php
/**
 * MOOP search RANKING tests — does the right gene come first?
 *
 * Every relevance metric used during development counts string matches, and none of them
 * can see a TIE. That is exactly where the bugs live. Three separate metrics passed a
 * result set that was visibly wrong to a biologist:
 *
 *   - "100 of the top 100 contain the term" is blind to quality: it scored a clean 100/100
 *     on a result set that had silently dropped an entire annotation source.
 *   - "whole word" penalises correct results: it counts PAX3 as a miss for "pax".
 *   - "literal substring" penalises correct stemming: a plural search matching singular
 *     records scores 1/100, which is the behaviour the porter tokenizer exists to provide.
 *
 * The failure that prompted this file: searching "nexin" returned CYTIP, cytohesin 1
 * interacting protein, ABOVE SNX17, "sorting nexin 17". CYTIP matched on one ortholog call
 * and has nothing to do with nexins. Every tier tied, so an ALPHABETICAL tiebreak on the
 * feature id decided it. All three metrics above scored that result 100/100.
 *
 * So these tests assert ORDERINGS on a hand-built fixture, not counts. Each case isolates
 * one tier, and every case runs TWICE -- once against an index carrying
 * annotation_type_code (the quota pool) and once without it (the bm25 fallback) -- because
 * the two paths must not disagree about what is most relevant.
 *
 * Hermetic: builds its own SQLite file in the system temp dir, touches no site data.
 *
 * Run:  php tests/search_ranking_tests.php    (exit 0 = all pass)
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = dirname(__DIR__);
$_SESSION = [];
require_once "$BASE/includes/access_control.php";
require_once "$BASE/lib/functions_database.php";   // moop_annotation_date_expr(), getDbConnection()
require_once "$BASE/lib/functions_json.php";       // loadJsonFile(), used by the curated type order
require_once "$BASE/lib/database_queries.php";

$PASS = 0; $FAIL = 0; $FAILURES = [];
function group($name) { echo "\n== $name ==\n"; }
function ok($cond, $label) {
    global $PASS, $FAIL, $FAILURES;
    if ($cond) { $PASS++; echo "  PASS  $label\n"; }
    else       { $FAIL++; $FAILURES[] = $label; echo "  FAIL  $label\n"; }
}

/**
 * Build a throwaway organism database.
 *
 * $with_type_code selects which search path the query planner will take:
 *   true  -> feature_annotation_search carries annotation_type_code, so the quota pool runs
 *   false -> it does not, so the code must fall back to the bm25 pool
 * Both must produce the same ordering; that is the point of running every case twice.
 */
function build_fixture(array $genes, $with_type_code) {
    $path = tempnam(sys_get_temp_dir(), 'moop_rank_') . '.sqlite';
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE organism (organism_id INTEGER PRIMARY KEY, genus TEXT, species TEXT,
                                      common_name TEXT, subtype TEXT)");
    $db->exec("CREATE TABLE genome (genome_id INTEGER PRIMARY KEY, genome_accession TEXT, genome_name TEXT)");
    $db->exec("CREATE TABLE gene_set (gene_set_id INTEGER PRIMARY KEY, gene_set_name TEXT, genome_id INTEGER)");
    $db->exec("CREATE TABLE feature (feature_id INTEGER PRIMARY KEY, feature_uniquename TEXT,
                                     feature_name TEXT, feature_description TEXT, feature_type TEXT,
                                     organism_id INTEGER, gene_set_id INTEGER)");
    $db->exec("CREATE TABLE annotation_source (annotation_source_id INTEGER PRIMARY KEY,
                                     annotation_source_name TEXT, annotation_type TEXT, annotation_date DATE)");
    $db->exec("CREATE TABLE annotation (annotation_id INTEGER PRIMARY KEY, annotation_source_id INTEGER,
                                     annotation_accession TEXT, annotation_description TEXT)");
    $db->exec("CREATE TABLE feature_annotation (feature_annotation_id INTEGER PRIMARY KEY,
                                     feature_id INTEGER, annotation_id INTEGER, score REAL)");

    $db->exec("INSERT INTO organism VALUES (1,'Testus','organismus','Test bat','')");
    $db->exec("INSERT INTO genome   VALUES (1,'GCA_000000001.1','testAsm1')");
    $db->exec("INSERT INTO gene_set VALUES (1,'testGeneSet',1)");
    // Two sources of DIFFERENT types, so the quota pool has more than one bucket to fill.
    $db->exec("INSERT INTO annotation_source VALUES (1,'TestOrthologs','Orthologs','2026-01-01')");
    $db->exec("INSERT INTO annotation_source VALUES (2,'TestDomains','Domains','2026-01-01')");

    $fid = $aid = $faid = 0;
    foreach ($genes as $g) {
        $fid++;
        $ins = $db->prepare("INSERT INTO feature VALUES (?,?,?,?,'mRNA',1,1)");
        $ins->execute([$fid, $g['uniquename'], $g['name'], $g['description']]);
        foreach ($g['annotations'] as $i => $text) {
            $aid++; $faid++;
            $src = ($i % 2) + 1;
            $db->prepare("INSERT INTO annotation VALUES (?,?,?,?)")
               ->execute([$aid, $src, 'ACC' . $aid, $text]);
            $db->prepare("INSERT INTO feature_annotation VALUES (?,?,?,NULL)")
               ->execute([$faid, $fid, $aid]);
        }
    }

    $cols = $with_type_code
        ? "feature_name, feature_description, annotation_description, annotation_accession, annotation_type_code"
        : "feature_name, feature_description, annotation_description, annotation_accession";
    $db->exec("CREATE VIRTUAL TABLE feature_annotation_search USING fts5($cols,
                   content='', tokenize='porter unicode61')");
    $sel = $with_type_code
        ? "fa.feature_annotation_id, f.feature_name, f.feature_description, a.annotation_description,
           a.annotation_accession,
           'atype' || lower(replace(replace(replace(ans.annotation_type,' ',''),'-',''),'_','')) || 'z'"
        : "fa.feature_annotation_id, f.feature_name, f.feature_description, a.annotation_description,
           a.annotation_accession";
    $into = $with_type_code
        ? "rowid, feature_name, feature_description, annotation_description, annotation_accession, annotation_type_code"
        : "rowid, feature_name, feature_description, annotation_description, annotation_accession";
    $db->exec("INSERT INTO feature_annotation_search($into)
               SELECT $sel FROM feature_annotation fa
               JOIN feature f ON f.feature_id = fa.feature_id
               JOIN annotation a ON a.annotation_id = fa.annotation_id
               JOIN annotation_source ans ON ans.annotation_source_id = a.annotation_source_id");

    $db->exec("CREATE VIRTUAL TABLE feature_search USING fts5(feature_name, feature_description,
                   content='', tokenize='porter unicode61')");
    $db->exec("INSERT INTO feature_search(rowid, feature_name, feature_description)
               SELECT feature_id, feature_name, feature_description FROM feature");
    $db = null;
    return $path;
}

/** Distinct gene uniquenames, in the order the search returned them. */
function ranked($term, $path) {
    $res = searchFeaturesAndAnnotations($term, false, $path);
    $seen = [];
    foreach ($res['results'] as $row) {
        $seen[$row['feature_uniquename']] = true;
    }
    return array_keys($seen);
}

/** Assert $a appears before $b, on BOTH search paths. */
function ranks_above($term, $genes, $a, $b, $label) {
    foreach ([true => 'quota pool', false => 'bm25 fallback'] as $with => $path_name) {
        $path = build_fixture($genes, (bool)$with);
        $order = ranked($term, $path);
        $ia = array_search($a, $order, true);
        $ib = array_search($b, $order, true);
        $good = $ia !== false && $ib !== false && $ia < $ib;
        ok($good, "$label [$path_name]"
            . ($good ? '' : "  (got: " . implode(' , ', array_slice($order, 0, 4)) . ")"));
        unlink($path);
    }
}

// ---------------------------------------------------------------------------------------
group('the nexin case — the gene\'s own description beats an annotation mentioning the term');

// CYTIP is not a nexin. It carries ONE ortholog call that names one. SNX17 IS a nexin and
// says so in its description. Before the feature_description tier existed, every tier tied
// and the alphabetical uniquename decided it -- so CYTIP won purely by sorting first.
$nexin = [
    ['uniquename' => 'AAA_013_000004', 'name' => 'CYTIP', 'description' => 'cytohesin 1 interacting protein',
     'annotations' => ['Snx27: Sorting nexin 27']],
    ['uniquename' => 'AAA_043_000010', 'name' => 'SNX17', 'description' => 'sorting nexin 17',
     'annotations' => ['SNX31: sorting nexin 31', 'snx17: sorting nexin 17', 'sorting nexin 17']],
];
ranks_above('nexin', $nexin, 'AAA_043_000010', 'AAA_013_000004',
    'SNX17 ("sorting nexin 17") outranks CYTIP, which only has a nexin ORTHOLOG');

// ---------------------------------------------------------------------------------------
group('tier order — name beats description beats annotation');

$tiers = [
    ['uniquename' => 'G_ANNOT', 'name' => 'AAA1', 'description' => 'unrelated protein',
     'annotations' => ['widget binding factor']],
    ['uniquename' => 'G_DESC',  'name' => 'BBB1', 'description' => 'widget associated protein',
     'annotations' => ['unrelated domain']],
    ['uniquename' => 'G_NAME',  'name' => 'WIDGET1', 'description' => 'unrelated protein',
     'annotations' => ['unrelated domain']],
];
ranks_above('widget', $tiers, 'G_NAME', 'G_DESC', 'a gene NAMED with the term outranks one described with it');
ranks_above('widget', $tiers, 'G_DESC', 'G_ANNOT', 'a gene DESCRIBED with the term outranks an annotation-only match');

// ---------------------------------------------------------------------------------------
group('tie-break — among equal matches, the gene that carries a name');

// Both match identically via the same annotation text; only one has a name. The named one
// must come first, because a reader can identify it -- but this is a TIE-BREAK, so it must
// never outrank a genuinely better match (asserted in the tier test above).
$named = [
    ['uniquename' => 'H_UNNAMED', 'name' => '',      'description' => '', 'annotations' => ['sprocket domain protein']],
    ['uniquename' => 'H_NAMED',   'name' => 'SPRK1', 'description' => '', 'annotations' => ['sprocket domain protein']],
];
ranks_above('sprocket', $named, 'H_NAMED', 'H_UNNAMED', 'a named gene outranks an unnamed one on an equal match');

// ---------------------------------------------------------------------------------------
group('literal beats stem-only — the porter tokenizer stems the QUERY too');

// "transpos" stems toward "transpo" and so also matches TRANSPORT. Rows literally
// containing what the user typed must outrank rows reached only through the stem.
$stem = [
    ['uniquename' => 'S_STEM',    'name' => 'TRNS1', 'description' => 'transport associated protein',
     'annotations' => ['solute transport domain']],
    ['uniquename' => 'S_LITERAL', 'name' => 'TPS1',  'description' => 'transposase family protein',
     'annotations' => ['transposase domain']],
];
ranks_above('transpos', $stem, 'S_LITERAL', 'S_STEM', 'a literal "transpos" match outranks a stem-only TRANSPORT match');

// ---------------------------------------------------------------------------------------
echo "\n" . str_repeat('-', 60) . "\n";
echo "Search ranking tests: $PASS passed, $FAIL failed\n";
if ($FAIL) {
    foreach ($FAILURES as $f) echo "  FAILED: $f\n";
    echo "SOME RANKING TESTS FAILED\n";
    exit(1);
}
echo "ALL RANKING TESTS PASSED\n";
exit(0);
