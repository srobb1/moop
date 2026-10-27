<?php
/**
 * MOOP smoke tests — fast, dependency-free assertions over the critical paths.
 *
 *   access control      — has_access() level hierarchy + per-resource checks
 *   search-query build   — buildFtsMatchExpr() / ftsPrimaryTerm() / appendScopeFilters()
 *   cache invalidation   — buildPerOrganismFingerprints() / buildConfigFingerprint()
 *
 * These are intentionally plain PHP (no PHPUnit) to match this repo's near-zero-dep
 * philosophy. They use only hermetic inputs (mocked $_SESSION, temp files) so they
 * pass on any deployment without touching real site data.
 *
 * Run:  php tests/smoke_tests.php     (exit 0 = all pass, 1 = one or more failed)
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = dirname(__DIR__);

// Pre-set $_SESSION so access_control.php does NOT call session_start() under CLI.
$_SESSION = [];

require_once "$BASE/includes/access_control.php";   // also bootstraps ConfigManager via config_init.php
require_once "$BASE/lib/database_queries.php";
require_once "$BASE/lib/functions_database.php";  // validateDatabaseIntegrity() + getDbConnection()
require_once "$BASE/lib/functions_data.php";
require_once "$BASE/lib/functions_access.php";  // getTaxonomyTreeUserAccess()

// ----------------------------------------------------------------------------
// Tiny test harness
// ----------------------------------------------------------------------------
$PASS = 0; $FAIL = 0; $FAILURES = [];
function group($name) { echo "\n== $name ==\n"; }
function ok($cond, $label) {
    global $PASS, $FAIL, $FAILURES;
    if ($cond) { $PASS++; echo "  PASS  $label\n"; }
    else       { $FAIL++; $FAILURES[] = $label; echo "  FAIL  $label\n"; }
}

// ----------------------------------------------------------------------------
group('access control — has_access() hierarchy');

$_SESSION = ['access_level' => 'PUBLIC', 'access' => [], 'logged_in' => false];
ok(has_access('PUBLIC') === true,              'PUBLIC visitor is granted PUBLIC');
ok(has_access('COLLABORATOR') === false,       'PUBLIC visitor is denied COLLABORATOR');
ok(has_access('ADMIN') === false,              'PUBLIC visitor is denied ADMIN');

$_SESSION = ['access_level' => 'ADMIN', 'access' => []];
ok(has_access('ADMIN') === true,               'ADMIN is granted ADMIN');
ok(has_access('COLLABORATOR') === true,        'ADMIN satisfies the COLLABORATOR requirement');

$_SESSION = ['access_level' => 'COLLABORATOR', 'access' => ['OrgA' => ['GCA_1' => true]]];
ok(has_access('COLLABORATOR') === true,        'COLLABORATOR with no named resource is granted');
ok(has_access('COLLABORATOR', 'OrgA') === true,'COLLABORATOR is granted a resource in its access list');
ok(has_access('COLLABORATOR', 'OrgZ') === false,'COLLABORATOR is denied a resource NOT in its list');
ok(has_access('ADMIN') === false,              'COLLABORATOR is denied ADMIN (no privilege escalation)');

// IP_IN_RANGE (trusted subnet): full DATA access, but NOT admin.
$_SESSION = ['access_level' => 'IP_IN_RANGE', 'access' => []];
ok(has_access('COLLABORATOR') === true,        'IP_IN_RANGE satisfies the COLLABORATOR requirement');
ok(has_access('COLLABORATOR', 'AnyOrg') === true,'IP_IN_RANGE reaches any organism resource');
ok(has_access('ADMIN') === false,              'IP_IN_RANGE is denied ADMIN (trusted subnet is not admin)');

// has_assembly_access(): ADMIN short-circuit + collaborator per-assembly list.
// Uses a made-up organism so is_public_assembly() reads the real groups file and returns false.
$_SESSION = ['access_level' => 'ADMIN', 'access' => []];
ok(has_assembly_access('Made_Up_Org', 'GCA_x') === true,  'ADMIN can access any assembly');
$_SESSION = ['access_level' => 'COLLABORATOR', 'access' => ['Made_Up_Org' => ['GCA_x' => true]]];
ok(has_assembly_access('Made_Up_Org', 'GCA_x') === true,  'COLLABORATOR can access an assembly in its list');
ok(has_assembly_access('Made_Up_Org', 'GCA_y') === false, 'COLLABORATOR cannot access an assembly not in its list');

// has_gene_set_access(): the finer check, and the ways it must differ from the one above.
//
// These two disagree, and the direction matters: has_assembly_access() is satisfied by the
// assembly KEY EXISTING, so a collaborator granted one gene set passes it for every gene set
// on that assembly. Anything deciding what a user may see has to use the gene-set check --
// getAccessibleOrganismsInGroup() used the assembly one until 2026-07-30 because it predates
// the gene-set layer.
group('gene-set access is finer than assembly access');
$_SESSION = ['access_level' => 'COLLABORATOR',
             'access' => ['Made_Up_Org' => ['GCA_x' => ['gs_alpha']]]];
ok(has_gene_set_access('Made_Up_Org', 'GCA_x', 'gs_alpha') === true,
   'COLLABORATOR reaches a gene set that was granted');
ok(has_gene_set_access('Made_Up_Org', 'GCA_x', 'gs_beta') === false,
   'COLLABORATOR is denied a gene set on the SAME assembly that was not granted');
ok(has_assembly_access('Made_Up_Org', 'GCA_x') === true,
   'the assembly check passes for that same user -- which is why it is the wrong check');

$_SESSION = ['access_level' => 'COLLABORATOR',
             'access' => ['Made_Up_Org' => ['GCA_x' => ['*']]]];
ok(has_gene_set_access('Made_Up_Org', 'GCA_x', 'anything_at_all') === true,
   "'*' grants every gene set on the assembly");

// Legacy shapes must DENY or GRANT, never fatal. in_array() on a non-array is a
// TypeError in PHP 8, i.e. a 500 on every page that resolves access.
$_SESSION = ['access_level' => 'COLLABORATOR',
             'access' => ['Made_Up_Org' => ['GCA_x' => true]]];
ok(has_gene_set_access('Made_Up_Org', 'GCA_x', 'gs_alpha') === true,
   'legacy {asm: true} means the whole assembly, and does not throw');

$_SESSION = ['access_level' => 'COLLABORATOR',
             'access' => ['Made_Up_Org' => ['GCA_1', 'GCA_2']]];
ok(has_gene_set_access('Made_Up_Org', 'GCA_1', 'gs_alpha') === false,
   'legacy {org: [asm,...]} list shape denies rather than throwing');

// ----------------------------------------------------------------------------
group('home page: built from the gene sets the viewer may see, not by organism');
// One organism, one PUBLIC gene set and one PRIVATE one -- the case the home page got
// wrong: it filtered by organism, so a visitor would have seen the private assembly's
// accession, gene-set name and private-only group in quick search and the counts.
$_mixed = [
    ['organism' => 'Mix_Org', 'assembly' => 'GCA_pub',  'gene_set' => 'gs_pub',  'groups' => ['Shared'],     'public' => true],
    ['organism' => 'Mix_Org', 'assembly' => 'GCA_priv', 'gene_set' => 'gs_priv', 'groups' => ['SecretGroup']],
    ['organism' => 'Hidden_Org', 'assembly' => 'GCA_h', 'gene_set' => 'gs_h',    'groups' => ['Shared']],
];
$_qs_for = function () use ($_mixed) {
    $vis  = moop_visible_group_entries($_mixed);
    $orgs = [];
    foreach (array_keys(getTaxonomyTreeUserAccess($_mixed)) as $o) {
        $orgs[] = ['organism' => $o, 'display_name' => $o, 'common_name' => '', 'groups' => [], 'taxon_chain' => []];
    }
    $qs = buildHomeQuickSearch($vis, $orgs, 'moop');
    return ['labels' => array_column($qs['items'], 'label'), 'asm' => $qs['assembly_count'],
            'cards' => array_keys(getIndexDisplayCards($_mixed)), 'orgs' => array_column($orgs, 'organism')];
};
$_SESSION = ['access_level' => 'PUBLIC', 'access' => [], 'logged_in' => false];
$_r = $_qs_for();
ok(!array_intersect(['GCA_priv', 'gs_priv', 'SecretGroup'], $_r['labels']),
   'a visitor\'s quick search lists no private assembly, gene set or private-only group');
ok($_r['asm'] === 1 && $_r['orgs'] === ['Mix_Org'], 'a visitor counts 1 assembly and sees only the organism with a public gene set');
ok($_r['cards'] === ['Shared'], 'a visitor gets only the group holding the public gene set');
$_SESSION = ['access_level' => 'COLLABORATOR', 'logged_in' => true, 'access' => ['Mix_Org' => ['GCA_priv' => ['gs_priv']]]];
$_r = $_qs_for();
ok(in_array('gs_priv', $_r['labels'], true) && !in_array('Hidden_Org', $_r['orgs'], true),
   'a collaborator sees the gene set granted to them, and not an ungranted organism');
$_SESSION = ['access_level' => 'COLLABORATOR', 'logged_in' => true, 'access' => ['Hidden_Org' => ['GCA_other' => ['*']]]];
ok(!in_array('Hidden_Org', getTaxonomyTreeUserAccess($_mixed) ? array_keys(getTaxonomyTreeUserAccess($_mixed)) : [], true),
   'access to a DIFFERENT assembly of an organism does not reveal this one');
$_SESSION = ['access_level' => 'IP_IN_RANGE', 'access' => []];
ok(count(moop_visible_group_entries($_mixed)) === 3, 'the lab network sees every entry');

// The tree sent to the browser: the home page sent the WHOLE tree and hid the rest in JS,
// so an anonymous visitor's page source named all 85 organisms.
$_tree = ['name' => 'Life', 'children' => [
    ['name' => 'Cnidaria', 'children' => [['name' => 'Nv', 'organism' => 'Mix_Org']]],
    ['name' => 'Chiroptera', 'children' => [['name' => 'Bat', 'organism' => 'Hidden_Org']]],
]];
$_p = pruneTaxonomyTreeForAccess($_tree, ['Mix_Org' => true]);
ok(json_encode($_p) === json_encode(['name' => 'Life', 'children' => [['name' => 'Cnidaria', 'children' => [['name' => 'Nv', 'organism' => 'Mix_Org']]]]]),
   'the pruned tree keeps only visible organisms and drops the branch left empty');
ok(strpos(json_encode($_p), 'Hidden_Org') === false && strpos(json_encode($_p), 'Chiroptera') === false,
   'neither a hidden organism nor its lineage reaches the browser');
ok(pruneTaxonomyTreeForAccess($_tree, []) === null, 'nothing visible: no tree at all');

// ----------------------------------------------------------------------------
group('search-query building — FTS expression + scope filters');

ok(buildFtsMatchExpr('wnt8b', false) === '"wnt8b"*',           'single term becomes a quoted prefix query');
ok(buildFtsMatchExpr('hox gene', false) === '"hox"* AND "gene"*','multiple terms AND together as prefixes');
ok(buildFtsMatchExpr('hox gene', true) === '"hox gene"',        'quoted search is a phrase (no prefix star)');
ok(buildFtsMatchExpr('a"b', true) === '"a""b"',                 'embedded double-quote is escaped ("")');
ok(buildFtsMatchExpr('!!!', false) === '',                      'punctuation-only term yields empty (no FTS injection)');
ok(buildFtsMatchExpr('   ', true) === '',                       'blank quoted search yields empty');
ok(ftsPrimaryTerm('hox gene', false) === 'hox',                 'primary ranking term is the first token');

$sql = 'BASE'; $params = [];
appendScopeFilters($sql, $params, 'GCA_1', 'v1', []);
ok(strpos($sql, 'g.genome_accession = ?') !== false
   && strpos($sql, 'gs.gene_set_name = ?') !== false
   && $params === ['GCA_1', 'v1'],                              'single assembly+gene_set binds two params');

$sql = 'BASE'; $params = [];
appendScopeFilters($sql, $params, '', '', [
    ['assembly' => 'A', 'gene_set' => 'g1'],
    ['assembly' => 'B', 'gene_set' => 'g2'],
]);
ok(substr_count($sql, ' OR ') === 1 && $params === ['A', 'g1', 'B', 'g2'],
                                                                'scope_pairs OR the clauses and bind each pair');

// ----------------------------------------------------------------------------
group('cache invalidation — change fingerprints (hermetic temp files)');

$tmp = sys_get_temp_dir() . '/moop_smoke_' . getmypid();
@mkdir("$tmp/OrgA", 0777, true);
$db = "$tmp/OrgA/organism.sqlite";
file_put_contents($db, str_repeat('x', 100));

$fp1 = buildPerOrganismFingerprints($tmp);
ok(isset($fp1['OrgA']),                         'a fingerprint is produced for an organism dir');
ok(buildPerOrganismFingerprints($tmp) === $fp1, 'fingerprint is deterministic for unchanged files');

// The Phase-2 guarantee: a size change with the SAME mtime must still flip the hash
// (rsync -a / cp -p / tar / restore all preserve timestamps).
$mtime = filemtime($db);
file_put_contents($db, str_repeat('x', 200));   // different size
touch($db, $mtime);                              // ...but reset mtime to the old value
clearstatcache();
$fp2 = buildPerOrganismFingerprints($tmp);
ok($fp2['OrgA'] !== $fp1['OrgA'],               'size change with unchanged mtime flips the organism fingerprint');

// Same guarantee for the config (groups) fingerprint.
$groups = "$tmp/groups.json";
file_put_contents($groups, '[]');
$cfp1  = buildConfigFingerprint(null, $groups);
$gmt   = filemtime($groups);
file_put_contents($groups, '[{"organism":"x","assembly":"y","groups":["PUBLIC"]}]');
touch($groups, $gmt);
clearstatcache();
$cfp2  = buildConfigFingerprint(null, $groups);
ok($cfp1 !== $cfp2,                              'groups-file size change (same mtime) flips the config fingerprint');
ok(buildConfigFingerprint(null, $groups) === $cfp2, 'config fingerprint is deterministic for an unchanged file');

// cleanup
@unlink($db); @unlink($groups); @rmdir("$tmp/OrgA"); @rmdir($tmp);

// ----------------------------------------------------------------------------
group('function registries — each watches its own language, not the other');

// A registry's staleness is only meaningful if it watches exactly the files its generator
// reads. Both registries used to share one *.php-only scan, so the JavaScript registry
// reported staleness from PHP timestamps: a .js edit left it claiming to be up to date,
// and any .php edit made it claim JavaScript had changed. Hermetic: a synthetic tree.
require_once "$BASE/lib/registry_sources.php";

$rt = sys_get_temp_dir() . '/moop_regsrc_' . getmypid();
foreach (['js/modules', 'js/vendor', 'lib/jbrowse', 'includes', 'admin/api', 'docs'] as $d) {
    @mkdir("$rt/$d", 0777, true);
}
file_put_contents("$rt/js/app.js", '//');              // in
file_put_contents("$rt/js/modules/mod.js", '//');      // in
file_put_contents("$rt/js/vendor/jquery.js", '//');    // out — not recursive, third-party
file_put_contents("$rt/js/thing.min.js", '//');        // out — minified
file_put_contents("$rt/lib/helper.php", '<?php');      // in
file_put_contents("$rt/lib/jbrowse/deep.php", '<?php');// in — recursion matters
file_put_contents("$rt/includes/inc.php", '<?php');    // in
file_put_contents("$rt/admin/api/ep.php", '<?php');    // in
file_put_contents("$rt/root.php", '<?php');            // in — top-level script
file_put_contents("$rt/docs/function_registry.json", '{}'); // out — generated output

$js_files  = array_map('basename', moop_registry_source_files('js',  $rt));
$php_files = array_map('basename', moop_registry_source_files('php', $rt));
sort($js_files);

ok($js_files === ['app.js', 'mod.js'],
   'JS registry watches js/ and js/modules/ only — not vendor/, not *.min.js');
ok(in_array('deep.php', $php_files) && in_array('inc.php', $php_files) && in_array('ep.php', $php_files),
   'PHP registry recurses lib/, includes/ and admin/api/');
ok(in_array('root.php', $php_files), 'PHP registry includes top-level scripts');
ok(!in_array('function_registry.json', $php_files), 'PHP registry excludes its own generated output');

// The bug this whole group exists for: the two sets must not bleed into each other.
$js_has_php  = array_filter($js_files,  fn($f) => substr($f, -4) === '.php');
$php_has_js  = array_filter($php_files, fn($f) => substr($f, -3) === '.js');
ok(empty($js_has_php), 'no PHP file can make the JavaScript registry look stale');
ok(empty($php_has_js), 'no JavaScript file can make the PHP registry look stale');

// Depth-first cleanup: files first, then directories from the deepest up.
foreach (["$rt/*/*/*", "$rt/*/*", "$rt/*"] as $pattern) {
    foreach (glob($pattern) ?: [] as $path) {
        if (is_file($path)) unlink($path);
    }
}
foreach (['js/modules','js/vendor','lib/jbrowse','admin/api','js','lib','includes','admin','docs'] as $d) {
    @rmdir("$rt/$d");
}
@rmdir($rt);

// ----------------------------------------------------------------------------
group('JBrowse reconciliation — orphan detection vs. an unavailable data directory');

// getOrphanedJBrowseRegistrations() walks the derived JBrowse artifacts back to their
// source. It takes the organisms path as an argument, so an unreachable data directory
// can be simulated without touching the real one.
$_jb_total = countJBrowseRegistrations();
if ($_jb_total < 2) {
    ok(true, 'skipped — needs at least 2 JBrowse registrations on this deployment');
} else {
    $_org_path = ConfigManager::getInstance()->getPath('organism_data');

    // Healthy: source data present. Any orphan here is a real finding, not a false positive.
    $_orphans_ok = getOrphanedJBrowseRegistrations($_org_path);
    ok(count($_orphans_ok) < $_jb_total,
       'with data present, not every registration is reported orphaned');

    // Unavailable data directory (unmounted share, wrong organism_data path): every
    // registration looks broken at once. That is ONE infrastructure problem, and the
    // systemic flag is what stops the UI offering to unregister all of them.
    $_orphans_gone = getOrphanedJBrowseRegistrations('/nonexistent/mount/organisms');
    ok(count($_orphans_gone) === $_jb_total,
       'with the data directory unavailable, every registration is detected');
    ok(($_jb_total > 1 && count($_orphans_gone) === $_jb_total) === true,
       'that case is flagged as systemic, not as N separate broken assemblies');
    ok(($_jb_total > 1 && count($_orphans_ok) === $_jb_total) === false,
       'the healthy case is NOT flagged as systemic');
}

// ----------------------------------------------------------------------------
group('editable config — admin-page settings actually reach the app');

// ConfigManager merges config_editable.json over site_config.php defaults, but ONLY for
// keys in its $editableConfigKeys whitelist. A settings page whose key is missing from
// that list writes to disk and is then silently ignored — the page reports a clean save
// while nothing changes. That is exactly what happened to blast_linkouts.
$cfgtmp = sys_get_temp_dir() . '/moop_cfgtest_' . getmypid();
@mkdir($cfgtmp);
copy("$BASE/config/site_config.php",  "$cfgtmp/site_config.php");
copy("$BASE/config/tools_config.php", "$cfgtmp/tools_config.php");
file_put_contents("$cfgtmp/config_editable.json", json_encode([
    'blast_linkouts' => [
        'gene_page_label' => 'SMOKE_LABEL',
        'external'        => [['label' => 'SmokeLink', 'url_template' => 'https://example.org/{fasta_id}']],
    ],
], JSON_PRETTY_PRINT));

// A second, non-singleton instance so the live config loaded above is left untouched.
$cm2 = (new ReflectionClass('ConfigManager'))->newInstanceWithoutConstructor();
$cm2->initialize("$cfgtmp/site_config.php", "$cfgtmp/tools_config.php");
$bl = $cm2->getArray('blast_linkouts', []);

ok(($bl['gene_page_label'] ?? null) === 'SMOKE_LABEL',
   'a saved blast_linkouts label overrides the site_config default');
ok(count($bl['external'] ?? []) === 1,
   'saved external BLAST linkouts are loaded, not discarded');
ok(($bl['jbrowse_hsp_max_link'] ?? null) === 10,
   'sub-keys the admin never saved keep their site_config default (deep merge)');

// The BLAST queue settings (Site Configuration -> BLAST Queue) must survive the whitelist.
$_qsave = $cm2->saveEditableConfig(['blast_max_runtime' => 99, 'blast_queue_max_wait' => 33,
    'blast_memory_budget_mb' => 2048, 'blast_max_concurrent' => 3, 'blast_max_output_mb' => 15], $cfgtmp);
$cm3 = (new ReflectionClass('ConfigManager'))->newInstanceWithoutConstructor();
$cm3->initialize("$cfgtmp/site_config.php", "$cfgtmp/tools_config.php");
ok(($_qsave['success'] ?? false)
   && $cm3->getInt('blast_max_runtime') === 99 && $cm3->getInt('blast_queue_max_wait') === 33
   && $cm3->getInt('blast_memory_budget_mb') === 2048 && $cm3->getInt('blast_max_concurrent') === 3
   && $cm3->getInt('blast_max_output_mb') === 15,
   'saved BLAST queue settings are loaded back, not dropped by the whitelist');

// saveEditableConfig() rebuilds the file from the same whitelist, so an unlisted key is
// deleted the next time any other admin settings page is saved.
$saved = $cm2->saveEditableConfig(['siteTitle' => 'Smoke Title'], $cfgtmp);
$after = json_decode(file_get_contents("$cfgtmp/config_editable.json"), true);
ok(!empty($saved['success']),
   'saving unrelated site settings succeeds');
ok(($after['blast_linkouts']['gene_page_label'] ?? null) === 'SMOKE_LABEL',
   'saving unrelated site settings preserves blast_linkouts');

array_map('unlink', glob("$cfgtmp/*") ?: []); @rmdir($cfgtmp);

// ----------------------------------------------------------------------------
group('BLAST programs — the dropdown and the JS filter must know the same set');

require_once dirname(__DIR__) . '/lib/blast_functions.php';

$programs = blast_programs();
ok(count($programs) >= 6, 'blast_programs() defines the program list');

foreach ($programs as $pid => $prog) {
    ok(!empty($prog['label']) && !empty($prog['summary']) && !empty($prog['when'])
       && !empty($prog['query']) && !empty($prog['db']),
       "$pid declares label, summary, query, db and when");
}

// js/modules/utilities.js partitions the programs by the query type they accept, and
// filterBlastPrograms() disables everything not in the matching list. A program in
// NEITHER list is therefore disabled for protein AND for nucleotide — permanently
// unreachable. That is not hypothetical: it is exactly how blastn-short was greyed
// out for the nucleotide queries it exists to serve, per the comment in that file.
//
// Adding a program is now a one-line edit to blast_programs(), which makes it EASIER
// to reintroduce, so the invariant is asserted rather than trusted.
$utils = file_get_contents(dirname(__DIR__) . '/js/modules/utilities.js');
$list = function ($name) use ($utils) {
    if (!preg_match('/const ' . $name . '\s*=\s*\[([^\]]*)\]/', $utils, $m)) {
        return null;
    }
    return array_values(array_filter(array_map(
        fn($s) => trim($s, " \t'\"" ),
        explode(',', $m[1])
    ), fn($s) => $s !== ''));
};

$protein_progs    = $list('proteinPrograms');
$nucleotide_progs = $list('nucleotidePrograms');

ok(is_array($protein_progs) && is_array($nucleotide_progs),
   'both program lists are found in js/modules/utilities.js');

foreach (array_keys($programs) as $pid) {
    $in = (int)in_array($pid, $protein_progs ?? [], true)
        + (int)in_array($pid, $nucleotide_progs ?? [], true);
    ok($in === 1, "$pid appears in exactly one JS query-type list (found in $in)");
}

// The reverse direction too: a JS list naming a program the page no longer offers is
// dead weight, and hides the fact that the real one was never added.
foreach (array_merge($protein_progs ?? [], $nucleotide_progs ?? []) as $pid) {
    ok(isset($programs[$pid]), "JS list entry '$pid' is a program the page actually offers");
}

// A SECOND partition, in updateDatabaseList(), splits the same programs by the kind of
// DATABASE they can search — which is not the same split as the query type above
// (tblastn takes a protein query but a nucleotide database, and sits on opposite sides
// of the two lists). Its fallback is `return true`, so a program in neither list is not
// disabled: it silently offers every database including the incompatible ones. That
// fails quietly rather than loudly, which is why it is worth asserting.
$db_protein = null; $db_nucleotide = null;
if (preg_match('/\[([^\]]*)\]\.includes\(program\)\)\s*return db\.type === \x27protein\x27/', $utils, $m)) {
    $db_protein = array_map(fn($s) => trim($s, " \t'\""), explode(',', $m[1]));
}
if (preg_match('/\[([^\]]*)\]\.includes\(program\)\)\s*return db\.type === \x27nucleotide\x27/', $utils, $m)) {
    $db_nucleotide = array_map(fn($s) => trim($s, " \t'\""), explode(',', $m[1]));
}

ok(is_array($db_protein) && is_array($db_nucleotide),
   'both database-compatibility lists are found in updateDatabaseList()');

foreach (array_keys($programs) as $pid) {
    $in = (int)in_array($pid, $db_protein ?? [], true)
        + (int)in_array($pid, $db_nucleotide ?? [], true);
    ok($in === 1, "$pid appears in exactly one JS database-type list (found in $in)");
}

// ----------------------------------------------------------------------------
group('group/taxonomy suggestions — curated groups drifting from the tree');

require_once "$BASE/lib/group_taxonomy_check.php";

// Hermetic fixture: 3 "bats" under Chiroptera, 2 anemones under Actiniaria, all inside
// Metazoa. No site data is read.
$gt_tree = ['name' => 'Life', 'children' => [
    ['name' => 'Metazoa', 'children' => [
        ['name' => 'Chordata', 'children' => [
            ['name' => 'Chiroptera', 'children' => [
                ['name' => 'Bat one',   'organism' => 'Bat_one'],
                ['name' => 'Bat two',   'organism' => 'Bat_two'],
                ['name' => 'Bat three', 'organism' => 'Bat_three'],
            ]],
        ]],
        ['name' => 'Cnidaria', 'children' => [
            ['name' => 'Actiniaria', 'children' => [
                ['name' => 'Anemone one', 'organism' => 'Anemone_one'],
                ['name' => 'Anemone two', 'organism' => 'Anemone_two'],
            ]],
        ]],
    ]],
]];

$mk = function ($org, $groups) {
    return ['organism' => $org, 'assembly' => 'GCA_1', 'gene_set' => 'gs1', 'groups' => $groups];
};

// All three bats tagged: nothing to suggest.
$gt_clean = [
    $mk('Bat_one', ['Bats']), $mk('Bat_two', ['Bats']), $mk('Bat_three', ['Bats']),
    $mk('Anemone_one', ['Cnidaria']), $mk('Anemone_two', ['Cnidaria']),
];
$r = moop_gt_compute($gt_clean, $gt_tree);
ok(count($r['suggestions']) === 0,          'a consistent set produces no suggestions');
ok(isset($r['groups_checked']['Bats']) && $r['groups_checked']['Bats']['rank'] === 'Chiroptera',
                                            'informal group "Bats" is matched to the Chiroptera rank by cover');
ok(($r['groups_checked']['Bats']['basis'] ?? '') === 'cover',
                                            'that match is recorded as basis=cover, not name');

// One bat left untagged — the case a NAME-based check can never see, because no group
// is called "Chiroptera".
$gt_missing = [
    $mk('Bat_one', ['Bats']), $mk('Bat_two', ['Bats']), $mk('Bat_three', ['Bats']),
    $mk('Bat_four', []),
];
$gt_tree_4 = $gt_tree;
$gt_tree_4['children'][0]['children'][0]['children'][0]['children'][] =
    ['name' => 'Bat four', 'organism' => 'Bat_four'];
$r = moop_gt_compute($gt_missing, $gt_tree_4);
ok(count($r['suggestions']) === 1,          'an untagged organism under the cover rank is suggested');
ok(($r['suggestions'][0]['group'] ?? '') === 'Bats',
                                            'the suggestion names the GROUP (Bats), never the rank');
ok(($r['suggestions'][0]['organism'] ?? '') === 'Bat_four',
                                            'the suggestion names the untagged organism');

// A group whose name IS a rank: the Scolanthus/Cnidaria shape.
$gt_name = [
    $mk('Anemone_one', ['Cnidaria']),
    $mk('Anemone_two', []),
];
$r = moop_gt_compute($gt_name, $gt_tree);
$named = array_values(array_filter($r['suggestions'], function ($s) { return $s['group'] === 'Cnidaria'; }));
ok(count($named) === 1 && $named[0]['basis'] === 'name',
                                            'a group named after a rank is checked by name');

// Guard: a small, polyphyletic group must NOT drag in its whole containing rank.
// "Odd pair" = one bat + one anemone, jointly contained only by Metazoa (5 organisms).
$gt_poly = [
    $mk('Bat_one', ['Odd pair']), $mk('Anemone_one', ['Odd pair']),
    $mk('Bat_two', []), $mk('Bat_three', []), $mk('Anemone_two', []),
];
$r = moop_gt_compute($gt_poly, $gt_tree);
$poly = array_filter($r['suggestions'], function ($s) { return $s['group'] === 'Odd pair'; });
ok(count($poly) === 0,                      'a 2-member polyphyletic group suggests nothing (Fish/Chordata guard)');
ok(!isset($r['groups_checked']['Odd pair']),'…and that group is not claimed as taxonomic at all');

// Guard: a single-member group must not adopt a broad rank as its cover.
$gt_single = [
    $mk('Bat_one', ['Solo']),
    $mk('Bat_two', []), $mk('Bat_three', []),
];
$r = moop_gt_compute($gt_single, $gt_tree);
$solo = array_filter($r['suggestions'], function ($s) { return $s['group'] === 'Solo'; });
ok(count($solo) === 0,                      'a 1-member group produces no cover suggestions');

// Dismissals suppress a suggestion without touching membership.
$ex = [['organism' => 'Bat_four', 'group' => 'Bats', 'reason' => 'deliberate', 'by' => 't', 'at' => 'now']];
$r  = moop_gt_compute($gt_missing, $gt_tree_4, $ex);
ok(count($r['suggestions']) === 0,          'a dismissed suggestion stops being suggested');
ok(count($r['dismissed']) === 1,            '…and is still listed as an accepted difference');
ok(($r['dismissed'][0]['reason'] ?? '') === 'deliberate',
                                            '…carrying the reason it was dismissed for');

// A suggestion must never name a group that does not exist, or the chip would ask the
// admin to tick a checkbox that is not there.
$r = moop_gt_compute($gt_missing, $gt_tree_4);
$existing = [];
foreach ($gt_missing as $e) { foreach ($e['groups'] as $g) { $existing[$g] = true; } }
$phantom = array_filter($r['suggestions'], function ($s) use ($existing) { return !isset($existing[$s['group']]); });
ok(count($phantom) === 0,                   'every suggested group already exists in the groups file');

// ----------------------------------------------------------------------------
group('track sheet → track JSON — no metadata column is dropped on the way');

// Registration reads a Google Sheet, GoogleSheetsParser turns each row into track data, and a
// track type copies the metadata fields it publishes into the track JSON. Until 2026-09-14 the
// parser silently lost six of them for EVERY track: headers were only lowercased, so
// "developmental-stage" became a key nothing read, and cleanTrackData() returned a fixed
// eight-field list without citation, project, accession, date or analyst. Nvec's sheet had
// 1,138 stage values and not one reached a track.
require_once dirname(__DIR__) . '/lib/jbrowse/GoogleSheetsParser.php';
require_once dirname(__DIR__) . '/lib/jbrowse/TrackTypes/BigWigTrack.php';

// Headers as the real sheet has them: mixed case, one hyphenated, Windows line endings.
$_ts_header = ['TRACK_ID', 'NAME', 'technique', 'CATEGORY', 'institute', 'source', 'experiment',
               'developmental-stage', 'tissue', 'condition', 'summary', 'citation', 'project',
               'accession', 'date', 'analyst', 'SCIPRJ', 'ACCESS', 'biosample', 'NGS_file', 'MLONG',
               'TRACK_PATH', 'Notes'];
$_ts_row    = ['t1.pos.bw', 'sample-1 +', 'RNASeq', 'Gene Expression', 'Institute A', 'Lab A',
               'Experiment A', '0hpf', '', 'time post fertilization', 'Summary A', 'PMID:1', 'PRJNA1',
               'SRR1', '2019-01-01', 'analyst-a', 'SCI-1', 'Public', 'SAMN1', 'ngs-1', 'MOLNG-1',
               'https://tracks.example.org/t1.pos.bw', ''];
$_ts_tsv    = implode("\t", $_ts_header) . "\r\n" . implode("\t", $_ts_row) . "\r\n";
$_ts_expect = [
    'technique' => 'RNASeq', 'institute' => 'Institute A', 'source' => 'Lab A',
    'experiment' => 'Experiment A', 'developmental_stage' => '0hpf',
    'condition' => 'time post fertilization', 'summary' => 'Summary A', 'citation' => 'PMID:1',
    'project' => 'PRJNA1', 'accession' => 'SRR1', 'date' => '2019-01-01', 'analyst' => 'analyst-a',
];

$_ts_parser = new GoogleSheetsParser();
$_ts_tracks = $_ts_parser->parseTracks($_ts_tsv, 'OrgA', 'GCA_1');
$_ts_track  = $_ts_tracks['regular'][0] ?? [];
ok(count($_ts_tracks['regular']) === 1, 'the sheet row parses to one track');
foreach ($_ts_expect as $_ts_f => $_ts_v) {
    ok(($_ts_track[$_ts_f] ?? null) === $_ts_v, "parser carries $_ts_f to the track data");
}

// parseTSV() is the other header path (column validation); it must normalize the same way.
$_ts_rows = $_ts_parser->parseTSV($_ts_tsv);
ok(array_key_exists('developmental_stage', $_ts_rows[0] ?? []),
   'parseTSV() reads the hyphenated header as developmental_stage too');

// End to end for the track type that matters most today, RNA-seq bigWigs: the fields must reach
// google_sheets_metadata in the JSON, and an empty cell must not be written at all. A stub
// resolver and dry_run keep it hermetic — no network, no file written.
$_ts_resolver = new class {
    public function isRemote($path) { return true; }
    public function toWebUri($path) { return $path; }
    public function toFilesystemPath($path) { return $path; }
    public function fileExists($path) { return false; }
};
ob_start();
(new BigWigTrack($_ts_resolver, ConfigManager::getInstance()))
    ->generate($_ts_track, 'OrgA', 'GCA_1', ['dry_run' => true]);
$_ts_out  = ob_get_clean();
$_ts_json = preg_match('/Metadata: (\{.*\})\s*$/s', $_ts_out, $_ts_m) ? json_decode($_ts_m[1], true) : null;
$_ts_gsm  = $_ts_json['metadata']['google_sheets_metadata'] ?? [];
ok(is_array($_ts_json), 'a BigWigTrack dry run emits the track JSON');
foreach (['developmental_stage', 'citation', 'project', 'accession', 'date', 'analyst'] as $_ts_f) {
    ok(($_ts_gsm[$_ts_f] ?? null) === $_ts_expect[$_ts_f], "the bigWig track JSON gets $_ts_f");
}
ok(!array_key_exists('tissue', $_ts_gsm), 'an empty sheet cell is not written as an empty field');

// Any column, any order, empty columns ignored. Until 2026-10-23 eleven track types each kept
// their own list of column names (three different lists), so a NEW column was silently dropped.
// Now every column that is not a control column is kept, by its header name.
$_nc_tsv = "Chip-Target\tTRACK_PATH\tUnused Empty\tNAME\tTRACK_ID\tStage\tAccess\tTechnique\n"
         . "H3K4me3\thttps://tracks.example.org/c1.bw\t\tChIP one\tc1\tembryo\tPublic\tChIPSeq\n";
$_nc = ($_ts_parser->parseTracks($_nc_tsv, 'OrgA', 'GCA_1')['regular'][0] ?? []);
ok(($_nc['sheet_metadata'] ?? null) === ['chip_target' => 'H3K4me3', 'stage' => 'embryo', 'technique' => 'ChIPSeq'],
   'a new column is kept by name, columns in any order, an empty column adds nothing');
ok(GoogleSheetsParser::sheetMetadata($_nc) === $_nc['sheet_metadata'], 'sheetMetadata() returns exactly those fields');
ok(!array_intersect(array_keys($_nc['sheet_metadata'] ?? []), GoogleSheetsParser::CONTROL_COLUMNS),
   'control columns (track id, name, path, access...) are not repeated as description');
ob_start();
(new BigWigTrack($_ts_resolver, ConfigManager::getInstance()))->generate($_nc, 'OrgA', 'GCA_1', ['dry_run' => true]);
$_nc_out = ob_get_clean();
$_nc_json = preg_match('/Metadata: (\{.*\})\s*$/s', $_nc_out, $_nc_m) ? json_decode($_nc_m[1], true) : null;
ok(($_nc_json['metadata']['google_sheets_metadata']['chip_target'] ?? null) === 'H3K4me3',
   'the new column reaches the track JSON with no code change');

// CATEGORY may be a folder path: "A / B / C" is three nested folders in JBrowse's track list.
ok(GoogleSheetsParser::categoryPath('Gene Expression / Reitzel Lab /  Light removal 2019') === ['Gene Expression', 'Reitzel Lab', 'Light removal 2019'],
   'a CATEGORY path becomes one folder per level, spaces tidied');
ok(GoogleSheetsParser::categoryPath('Gene Expression') === ['Gene Expression'], 'a plain category stays one folder, as before');
ok(GoogleSheetsParser::categoryPath('A //B/ ') === ['A', 'B'] && GoogleSheetsParser::categoryPath('', 'Quantitative') === ['Quantitative'],
   'empty levels are dropped; an empty cell gets the track type\'s default');
$_cp = $_nc; $_cp['category'] = 'Gene Expression / Reitzel Lab / Light removal 2019';
ob_start();
(new BigWigTrack($_ts_resolver, ConfigManager::getInstance()))->generate($_cp, 'OrgA', 'GCA_1', ['dry_run' => true]);
$_cp_out = ob_get_clean();
$_cp_json = preg_match('/Metadata: (\{.*\})\s*$/s', $_cp_out, $_cp_m) ? json_decode($_cp_m[1], true) : null;
ok(($_cp_json['category'] ?? null) === ['Gene Expression', 'Reitzel Lab', 'Light removal 2019'], 'a bigWig track gets the nested category');

// A combo sits in the deepest folder its members share, and carries the fields they all agree on.
require_once dirname(__DIR__) . '/lib/jbrowse/TrackTypes/ComboTrack.php';
$_m1 = ['track_id' => 'a', 'category' => 'Gene Expression / Reitzel Lab / Light removal', 'sheet_metadata' => ['source' => 'Reitzel Lab', 'stage' => 'adult', 'accession' => 'SRR1']];
$_m2 = ['track_id' => 'b', 'category' => 'Gene Expression / Reitzel Lab / Light removal', 'sheet_metadata' => ['source' => 'Reitzel Lab', 'stage' => 'adult', 'accession' => 'SRR2']];
$_m3 = ['track_id' => 'c', 'category' => 'Gene Expression / Reitzel Lab / Colour', 'sheet_metadata' => ['source' => 'Reitzel Lab']];
$_d = ComboTrack::describe(['groups' => ['g1' => ['tracks' => [$_m1]], 'g2' => ['tracks' => [$_m2]]]]);
ok($_d['category'] === ['Gene Expression', 'Reitzel Lab', 'Light removal', 'Grouped tracks'], 'a combo of one study goes in a "Grouped tracks" folder inside that study\'s folder');
ok($_d['sheet'] === ['source' => 'Reitzel Lab', 'stage' => 'adult'], 'it carries the fields its members agree on, not ones that differ (accession)');
ok(ComboTrack::describe(['groups' => ['g' => ['tracks' => [$_m1, $_m3]]]])['category'] === ['Gene Expression', 'Reitzel Lab', 'Grouped tracks'],
   'members from two studies put it in their shared folder');
$_m4 = ['track_id' => 'd', 'category' => 'Epigenetics', 'sheet_metadata' => []];
ok(ComboTrack::describe(['groups' => ['g' => ['tracks' => [$_m1, $_m4]]]])['category'] === ['Grouped tracks'],
   'members with no folder in common leave it in a top-level Grouped tracks folder');

// Track list order: each study's "Grouped tracks" folder comes first; lab and study folders keep sheet order.
require_once "$BASE/lib/jbrowse/config_functions.php";
$_ot = fn($name, $cat, $type = 'QuantitativeTrack') => ['name' => $name, 'type' => $type, 'category' => $cat];
$_ord = moop_jbrowse_order_tracks([
    $_ot('genes', ['Gene Models']),
    $_ot('g1', ['Gene Expression', 'Gibson Lab', 'Sex']),
    $_ot('r1', ['Gene Expression', 'Reitzel Lab', 'Light']),
    $_ot('r2', ['Gene Expression', 'Reitzel Lab', 'Light']),
    $_ot('a-combo', ['Gene Expression', 'Reitzel Lab', 'Light', 'Grouped tracks'], 'MultiQuantitativeTrack'),
    $_ot('z-combo', ['Gene Expression', 'Gibson Lab', 'Sex', 'Grouped tracks'], 'MultiQuantitativeTrack'),
], ['Gene Models' => 0, 'Gene Expression' => 1]);
ok(array_column($_ord, 'name') === ['genes', 'z-combo', 'g1', 'a-combo', 'r1', 'r2'],
   'grouped tracks lead their own study folder; Gibson stays before Reitzel, as in the sheet');
$_sp = moop_jbrowse_split_individual_tracks([
    $_ot('genes', ['Gene Models']),
    $_ot('g1', ['Gene Expression', 'Gibson Lab', 'Sex']),
    $_ot('lone', ['Gene Expression', 'Smith Lab', 'Hourly']),
    $_ot('z-combo', ['Gene Expression', 'Gibson Lab', 'Sex', 'Grouped tracks'], 'MultiQuantitativeTrack'),
]);
ok(array_column($_sp, 'category', 'name') === [
       'genes' => ['Gene Models'],
       'g1' => ['Gene Expression', 'Gibson Lab', 'Sex', 'Individual tracks'],
       'lone' => ['Gene Expression', 'Smith Lab', 'Hourly'],
       'z-combo' => ['Gene Expression', 'Gibson Lab', 'Sex', 'Grouped tracks']],
   'single tracks beside grouped ones go in "Individual tracks"; folders without grouped tracks are left alone');
ok(array_column(moop_jbrowse_order_tracks($_sp, ['Gene Models' => 0, 'Gene Expression' => 1]), 'name') === ['genes', 'z-combo', 'g1', 'lone'],
   'Grouped tracks is listed before Individual tracks');

// And no track type may grow its own list again.
$_ts_lists = [];
foreach (glob(dirname(__DIR__) . '/lib/jbrowse/TrackTypes/*.php') as $_ts_file) {
    if (preg_match("/\[\s*'technique'\s*,\s*'institute'/", file_get_contents($_ts_file))) $_ts_lists[] = basename($_ts_file);
}
ok($_ts_lists === [], 'no track type keeps its own list of sheet columns'
   . ($_ts_lists ? ' -- ' . implode(', ', $_ts_lists) : ''));

// ----------------------------------------------------------------------------
group('sheet sync refreshes what the sheet says about existing tracks');
// A sync skips tracks that already have a JSON file, so before 2026-10-23 an edited NAME,
// CATEGORY, DESCRIPTION or descriptive column never reached them without "force".
require_once dirname(__DIR__) . '/lib/jbrowse/TrackGenerator.php';
$_sd_gen  = new TrackGenerator(ConfigManager::getInstance());
$_sd_file = sys_get_temp_dir() . '/moop_sd_' . getmypid() . '.json';
$_sd_def  = ['trackId' => 'track_abc', 'name' => 'old name', 'category' => ['Gene Expression'],
             'adapter' => ['type' => 'BigWigAdapter', 'bigWigLocation' => ['uri' => 'https://t/x.bw']],
             'metadata' => ['access_level' => 'PUBLIC', 'description' => '', 'file_size' => 'remote',
                            'google_sheets_metadata' => ['technique' => 'RNASeq', 'tissue' => 'whole']]];
file_put_contents($_sd_file, json_encode($_sd_def));
$_sd_row = ['track_id' => 't1', 'name' => 'new name', 'category' => 'Gene Expression',
            'description' => 'now described', 'sheet_metadata' => ['technique' => 'RNASeq', 'tissue' => 'whole animal', 'stage' => 'adult']];
ok($_sd_gen->syncExistingDescription($_sd_file, $_sd_row, true) === ['name', 'description', 'metadata']
   && json_decode(file_get_contents($_sd_file), true) === $_sd_def, 'dry run reports the changes and writes nothing');
ok($_sd_gen->syncExistingDescription($_sd_file, $_sd_row) === ['name', 'description', 'metadata'], 'a real sync applies them');
$_sd_new = json_decode(file_get_contents($_sd_file), true);
ok($_sd_new['name'] === 'new name' && $_sd_new['metadata']['description'] === 'now described'
   && $_sd_new['metadata']['google_sheets_metadata'] === ['technique' => 'RNASeq', 'tissue' => 'whole animal', 'stage' => 'adult'],
   'name, description and the descriptive columns follow the sheet');
ok($_sd_new['adapter'] === $_sd_def['adapter'] && $_sd_new['metadata']['file_size'] === 'remote'
   && $_sd_new['metadata']['access_level'] === 'PUBLIC' && $_sd_new['trackId'] === 'track_abc',
   'the file location, generated facts and access are left alone');
ok($_sd_gen->syncExistingDescription($_sd_file, $_sd_row) === [], 'a second sync changes nothing');
$_sd_row['category'] = 'Gene Expression / Reitzel Lab';
ok($_sd_gen->syncExistingDescription($_sd_file, $_sd_row) === ['category']
   && json_decode(file_get_contents($_sd_file), true)['category'] === ['Gene Expression', 'Reitzel Lab'],
   'a CATEGORY changed to a folder path reaches an existing track');
$_sd_row['sheet_metadata'] = [];
$_sd_gen->syncExistingDescription($_sd_file, $_sd_row);
ok(array_key_exists('google_sheets_metadata', json_decode(file_get_contents($_sd_file), true)['metadata']),
   'emptied descriptive cells keep the sheet-track marker, so clean still recognises the track');
file_put_contents($_sd_file, json_encode(['name' => 'Genes', 'metadata' => ['gene_set' => 'NV2']]));
ok($_sd_gen->syncExistingDescription($_sd_file, $_sd_row) === [], 'a gene set track is never touched');
@unlink($_sd_file);

// ----------------------------------------------------------------------------
group('sheet sync clean — only removes tracks a sheet wrote');

// On 2026-10-01 a sheet was registered for Congeria and synced with the page defaults. The
// clean step unlinked every track JSON whose id was not a sheet row — including
// COKUS1KC_genes.json, which gene-set registration writes into the same gff/ directory and
// which is never in a sheet. The gene models vanished from the browser with no error. The
// same comparison ignored combo ids, so a sync deleted every combo it had just created.
require_once dirname(__DIR__) . '/lib/jbrowse/TrackGenerator.php';

$_cl_gene  = ['metadata' => ['gene_set' => 'GS1', 'is_primary_gene_track' => true]];
$_cl_sheet = ['metadata' => ['google_sheets_metadata' => ['technique' => 'RNASeq']]];
ok(TrackGenerator::isSheetOriginTrack('gff', $_cl_gene) === false,   'a gene-set track is not sheet-origin');
ok(TrackGenerator::isSheetOriginTrack('gff', $_cl_sheet) === true,   'a gff track with sheet metadata is sheet-origin');
ok(TrackGenerator::isSheetOriginTrack('bigwig', []) === false,       'a track of unknown origin is kept, not assumed to be from a sheet');
ok(TrackGenerator::isSheetOriginTrack('combo', []) === true,         'combo tracks only ever come from a sheet');

$_cl_existing = [
    ['track_id' => 'GS1_genes',  'type' => 'gff',    'file' => 'a', 'sheet_origin' => false],
    ['track_id' => 'kept.bw',    'type' => 'bigwig', 'file' => 'b', 'sheet_origin' => true],
    ['track_id' => 'gone.bw',    'type' => 'bigwig', 'file' => 'c', 'sheet_origin' => true],
    ['track_id' => 'my_combo',   'type' => 'combo',  'file' => 'd', 'sheet_origin' => true],
    ['track_id' => 'old_combo',  'type' => 'combo',  'file' => 'e', 'sheet_origin' => true],
];
$_cl_orphans = array_column(TrackGenerator::selectOrphanedTracks($_cl_existing, ['kept.bw', 'My_Combo']), 'track_id');
ok(!in_array('GS1_genes', $_cl_orphans, true), 'clean never selects the gene annotation track');
ok(!in_array('kept.bw', $_cl_orphans, true),   'clean keeps a sheet track still in the sheet');
ok(!in_array('my_combo', $_cl_orphans, true),  'clean keeps a combo still in the sheet (file name is the lowercased id)');
ok($_cl_orphans === ['gone.bw', 'old_combo'],  'clean selects exactly the sheet tracks that left the sheet');

// The script must hand clean the combo ids and the dry-run flag; both were missing.
$_cl_script = file_get_contents(dirname(__DIR__) . '/scripts/generate_tracks_from_sheet.php');
ok(preg_match('/cleanOrphanedTracks\([^;]*\$options\[\'dry_run\'\]\)/s', $_cl_script) === 1,
   'the sync script passes --dry-run through to clean');
ok(strpos($_cl_script, "array_column(\$tracks['combo'], 'track_id')") !== false,
   'the sync script counts combo ids as being in the sheet');

// Force and clean are separate choices in the endpoint; one ticked-by-default box used to mean both.
$_cl_sync = file_get_contents(dirname(__DIR__) . '/admin/api/jbrowse_sync_tracks.php');
ok(strpos($_cl_sync, '--force --clean') === false, 'the sync endpoint no longer ties --clean to force');

// A sheet can only be registered or synced for an assembly JBrowse knows.
require_once dirname(__DIR__) . '/lib/functions_data.php';
ok(isJBrowseAssemblyRegistered('../../etc', 'x') === false,         'a traversal name is never a registered assembly');
ok(isJBrowseAssemblyRegistered('No_such_organism', 'GCA_0') === false, 'an unregistered assembly is refused');
foreach (['jbrowse_register_sheet.php', 'jbrowse_sync_tracks.php'] as $_cl_ep) {
    ok(strpos(file_get_contents(dirname(__DIR__) . "/admin/api/$_cl_ep"), 'isJBrowseAssemblyRegistered(') !== false,
       "$_cl_ep refuses an assembly that is not registered in JBrowse");
}

// ----------------------------------------------------------------------------
group('annotation sources — a source with no annotations is not listed');

// A database can hold an annotation_source row with nothing loaded under it (10 of 85 did
// on 2026-10-01). The pickers offered it with a count of 0, and every empty TYPE took an
// equal slice of the search pool away from the types that have results.
$_as_db = sys_get_temp_dir() . '/moop_as_' . getmypid() . '.sqlite';
@unlink($_as_db);
$_as_pdo = new PDO('sqlite:' . $_as_db);
$_as_pdo->exec("CREATE TABLE annotation_source (annotation_source_id INTEGER PRIMARY KEY, annotation_source_name TEXT, annotation_type TEXT);
                CREATE TABLE annotation (annotation_id INTEGER PRIMARY KEY, annotation_source_id INTEGER, annotation_accession TEXT, annotation_description TEXT);
                CREATE TABLE feature_annotation (feature_annotation_id INTEGER PRIMARY KEY, feature_id INTEGER, annotation_id INTEGER);
                INSERT INTO annotation_source VALUES (1, 'InterPro', 'Domains'), (2, 'EggNOG', 'Orthologs');
                INSERT INTO annotation VALUES (1, 1, 'IPR000436', 'Sushi');
                INSERT INTO feature_annotation VALUES (1, 10, 1);");
$_as_pdo = null;

$_as_names = array_column(getAnnotationSources($_as_db), 'name');
ok($_as_names === ['InterPro'],                       'getAnnotationSources() leaves out a source with no annotations');
$_as_types = getAnnotationTypesFromDB($_as_db);
ok(isset($_as_types['Domains']),                      'a type with annotations is reported');
ok(!isset($_as_types['Orthologs']),                   'a type whose only source is empty is not reported');
@unlink($_as_db);

// Cache files written before the fix still list empty sources; every reader filters.
$_as_cache = ['Orthologs' => [['name' => 'EggNOG', 'count' => '0']],
              'Domains'   => [['name' => 'InterPro', 'count' => '8'], ['name' => 'Pfam', 'count' => 0]]];
$_as_kept = moop_drop_empty_annotation_sources($_as_cache);
ok(array_keys($_as_kept) === ['Domains'],             'a type left with no sources is dropped from the cache view');
ok(array_column($_as_kept['Domains'], 'name') === ['InterPro'], 'an empty source is dropped, its siblings kept');

// The search type list narrows to types that have annotations — but never to nothing.
$_as_present = ['Domains' => true, 'Orthologs' => true];
ok(array_keys(moop_types_with_annotations($_as_present, ['Domains'])) === ['Domains'],
   'search drops a type the cache says is empty');
ok(moop_types_with_annotations($_as_present, ['Something else']) === $_as_present,
   'a cache naming none of the declared types does not leave search with no types');

// ----------------------------------------------------------------------------
group('gene page — no GFF means no gene structure, not a fatal error');

// The Gene Structure help reuses a legend defined inside the Gene Structure card, which is
// drawn only when a gene model exists. Outside that condition the help read an undefined
// variable and the page died — for every gene of a gene set with no GFF (15 organisms).
$_gp_src   = file_get_contents(dirname(__DIR__) . '/tools/pages/parent.php');
// Anchored on the help_modal() CALL, not the id alone: the id also appears earlier, on the
// trigger button inside the card.
$_gp_call  = strpos($_gp_src, "help_modal(\n        'gene-model-help'");
$_gp_guard = $_gp_call === false ? false : strrpos(substr($_gp_src, 0, $_gp_call), 'if (!empty($gene_model)) {');
ok($_gp_call !== false && $_gp_guard !== false, 'the gene structure help call and a gene-model guard before it are both present');
ok($_gp_call !== false && $_gp_guard !== false
   && strpos(substr($_gp_src, $_gp_guard, $_gp_call - $_gp_guard), "\n}\n") === false,
   'the gene structure help is built only when there is a gene model');

// ----------------------------------------------------------------------------
group('time zone setting — applied when valid, ignored when not');

// PHP with no date.timezone runs in UTC whatever the machine clock says, which showed every
// admin time five hours ahead on a Central-time host. The site setting fixes that; a blank
// or mistyped value must leave PHP's own zone alone rather than break the site.
$_tz_before = date_default_timezone_get();
ok(moop_is_valid_timezone('America/Chicago') && !moop_is_valid_timezone('Mars/Olympus') && !moop_is_valid_timezone(''),
   'only real time zone identifiers are accepted');
ok(moop_apply_timezone('Mars/Olympus') === false && date_default_timezone_get() === $_tz_before,
   'an unknown time zone changes nothing');
ok(moop_apply_timezone('') === false && date_default_timezone_get() === $_tz_before,
   'an empty setting leaves the server default in place');
ok(moop_apply_timezone('America/Chicago') === true && date_default_timezone_get() === 'America/Chicago',
   'a valid time zone is applied');
date_default_timezone_set($_tz_before);

// ----------------------------------------------------------------------------
group('permission checker — credentials must not be readable by other users');

// On 2026-09-16 /var/www/moop-site-data/users.json was mode 664 in a world-traversable
// directory: every local user on the host could read the bcrypt hashes. The checker
// reported NO issue, for two compounding reasons — no rule covered the file at all, and
// the 'writable' branch tests world-WRITE (0002) and never world-READ. These assertions
// fail against the code as it stood before the 'sensitive' axis was added.
require_once dirname(__DIR__) . '/lib/permission_check.php';

$_pc_dir  = sys_get_temp_dir() . '/moop_pc_' . getmypid();
@mkdir($_pc_dir, 0777, true);
$_pc_file = $_pc_dir . '/users.json';
file_put_contents($_pc_file, '{}');

// A credential file the web server also writes — exactly users.json's situation.
$_pc_item = [
    'name' => 'Credential Files (web-written)',
    'type' => 'file',
    'check_mode' => 'writable',
    'sensitive' => true,
    'required_perms' => '640',
    'required_group' => 'apache',
];

chmod($_pc_file, 0664); clearstatcache(true, $_pc_file);   // group-writable AND world-readable
$_pc = performPermissionCheck($_pc_file, $_pc_item, 'apache');
$_pc_world = array_filter($_pc['issues'], function ($i) {
    return stripos($i, 'other users on the host') !== false;
});
ok(!empty($_pc_world),          'a world-readable credential file is flagged (664)');
ok($_pc['severity'] === 'high', 'and it is high severity, not a footnote');

chmod($_pc_file, 0660); clearstatcache(true, $_pc_file);   // same file, world bits cleared
$_pc_ok = performPermissionCheck($_pc_file, $_pc_item, 'apache');
$_pc_ok_world = array_filter($_pc_ok['issues'], function ($i) {
    return stripos($i, 'other users on the host') !== false;
});
ok(empty($_pc_ok_world),        'the same file at 660 is not flagged — the check is world-access, not an exact mode');

// A non-sensitive writable file at 664 must stay clean: 664 is normal for site data.
$_pc_plain = ['name' => 'Site Data Backup Files', 'type' => 'file', 'required_perms' => '664',
              'required_group' => 'apache'];
chmod($_pc_file, 0664); clearstatcache(true, $_pc_file);
$_pc_p = performPermissionCheck($_pc_file, $_pc_plain, 'apache');
$_pc_p_world = array_filter($_pc_p['issues'], function ($i) {
    return stripos($i, 'other users on the host') !== false;
});
ok(empty($_pc_p_world),         'a NON-sensitive 664 file is still fine — no blanket tightening');

// The advice. A world-open sensitive DIRECTORY must be told 2770, never 640: `chmod 640`
// on a directory strips the traverse bit and locks the web server out of the keys it
// exists to read. The old 'secret' branch printed 640 regardless of type.
$_pc_fix_dir = moop_permission_fix_commands([
    'path' => '/tmp/moop_certs', 'type' => 'directory', 'check_mode' => 'secret',
    'sensitive' => true, 'current_perms' => '2755', 'exists' => true, 'is_readable' => true,
], 'smr');
ok(in_array('sudo chmod 2770 ' . escapeshellarg('/tmp/moop_certs'), $_pc_fix_dir, true),
   'a world-open sensitive directory is told 2770');
ok(!in_array('sudo chmod 640 ' . escapeshellarg('/tmp/moop_certs'), $_pc_fix_dir, true),
   'and is NOT told 640, which would strip its traverse bit');

$_pc_fix_file = moop_permission_fix_commands([
    'path' => $_pc_file, 'type' => 'file', 'check_mode' => 'writable', 'sensitive' => true,
    'current_perms' => '664', 'exists' => true, 'is_readable' => true, 'is_writable' => true,
], 'smr');
ok(in_array('sudo chmod 640 ' . escapeshellarg($_pc_file), $_pc_fix_file, true),
   'a world-readable sensitive file is told 640');

// moop_permission_dir_mode: the dashboard's number, derived not hardcoded.
$_pc_sens = ['/var/www/moop-site-data' => true, '/var/www/html/moop/config/secrets.php' => true];
ok(moop_permission_dir_mode('/var/www/moop-site-data', $_pc_sens) === '2770',
   'the site-data backup directory is advised 2770, not 2775');
ok(moop_permission_dir_mode('/var/www/html/moop/config', $_pc_sens) === '2770',
   'a directory CONTAINING a credential file is advised 2770 too');
ok(moop_permission_dir_mode('/var/www/html/moop/logs', $_pc_sens) === '2775',
   'an ordinary writable directory is still advised 2775');

// An explicit check_mode on a rule must win over the name lookup, in BOTH places that ask.
ok(moop_permission_item_mode(['name' => 'Credential Files (web-written)', 'check_mode' => 'writable']) === 'writable',
   "an explicit check_mode wins over the rule's name");
ok(moop_permission_item_mode(['name' => 'Logs Directory']) === 'writable',
   'and a rule without one still resolves by name');

@unlink($_pc_file);
@rmdir($_pc_dir);

// ----------------------------------------------------------------------------
group('organism severity — graded by impact, not by count');

// The bug this guards: severity used to be `all_pass ? complete : (pass_count > 0 ?
// incomplete : critical)`, which made 'critical' unreachable -- it needed ALL TEN checks
// to fail at once. Measured on the real site, the minimum pass_count was 9 and 'critical'
// fired for 0 of 85 organisms, so an empty database wore the same amber badge as a missing
// .fai index. These assertions fail against that old expression.
$_sev_all = ['has_assemblies'=>true,'has_fasta'=>true,'has_blast_indexes'=>true,
             'has_fai_index'=>true,'has_database'=>true,'database_valid'=>true,
             'directories_match_db'=>true,'assemblies_in_groups'=>true,
             'in_taxonomy_tree'=>true,'metadata_complete'=>true];
$_sev = function(array $overrides) use ($_sev_all) {
    $c = array_merge($_sev_all, $overrides);
    return ['checks' => $c, 'all_pass' => !in_array(false, $c, true),
            'pass_count' => count(array_filter($c)), 'total_count' => count($c)];
};

ok(moop_organism_severity($_sev([])) === 'complete',
   'every check passing is complete');
ok(moop_organism_severity($_sev(['database_valid' => false])) === 'critical',
   'an invalid/empty database is CRITICAL even at 9 of 10 passing');
ok(moop_organism_severity($_sev(['has_database' => false])) === 'critical',
   'no database at all is critical');
ok(moop_organism_severity($_sev(['has_assemblies' => false])) === 'critical',
   'no assemblies is critical');
ok(moop_organism_severity($_sev(['has_fasta' => false])) === 'critical',
   'no FASTA is critical');
ok(moop_organism_severity($_sev(['has_fai_index' => false])) === 'incomplete',
   'a missing .fai index is only incomplete -- it does not stop the organism serving data');
ok(moop_organism_severity($_sev(['has_blast_indexes' => false])) === 'incomplete',
   'a missing BLAST index is only incomplete');
ok(moop_organism_severity($_sev(['in_taxonomy_tree' => false, 'assemblies_in_groups' => false])) === 'incomplete',
   'group/tree membership is admin config, not a data failure');
// All three states must be reachable -- that is the whole point of the change.
ok(count(array_unique([
       moop_organism_severity($_sev([])),
       moop_organism_severity($_sev(['database_valid' => false])),
       moop_organism_severity($_sev(['has_fai_index' => false])),
   ])) === 3, 'all three severity states are reachable');
// A check the caller never supplied is not a failure.
ok(moop_organism_severity(['checks' => ['has_fai_index' => false], 'all_pass' => false]) === 'incomplete',
   'an ABSENT critical check is not treated as failed');

// ----------------------------------------------------------------------------
group('database integrity — emptiness is a data issue');

// Hermetic: a temp SQLite database with the real table names and no rows. Guards the
// class where BOTH halves of the data load and the join table stays empty, which every
// count on every admin page reported as healthy.
$_db_dir  = sys_get_temp_dir() . '/moop_dbtest_' . getmypid();
@mkdir($_db_dir, 0700, true);
$_mk = function(string $name, callable $fill) use ($_db_dir) {
    $f = "$_db_dir/$name.sqlite";
    @unlink($f);
    $h = new PDO('sqlite:' . $f);
    foreach ([
        'organism'    => 'organism_id INTEGER PRIMARY KEY',
        'genome'      => 'genome_id INTEGER PRIMARY KEY',
        'gene_set'    => 'gene_set_id INTEGER PRIMARY KEY, genome_id INTEGER',
        'feature'     => 'feature_id INTEGER PRIMARY KEY, feature_type TEXT, organism_id INTEGER, gene_set_id INTEGER',
        'annotation_source' => 'annotation_source_id INTEGER PRIMARY KEY',
        'annotation'  => 'annotation_id INTEGER PRIMARY KEY, annotation_source_id INTEGER, annotation_accession TEXT',
        'feature_annotation' => 'feature_id INTEGER, annotation_id INTEGER',
    ] as $t => $cols) $h->exec("CREATE TABLE $t ($cols)");
    $fill($h);
    $h = null;
    return $f;
};

// A database where everything loaded.
$_healthy = $_mk('healthy', function (PDO $h) {
    $h->exec("INSERT INTO organism (organism_id) VALUES (1)");
    $h->exec("INSERT INTO genome (genome_id) VALUES (1)");
    $h->exec("INSERT INTO gene_set (gene_set_id, genome_id) VALUES (1, 1)");
    $h->exec("INSERT INTO feature (feature_id, feature_type, organism_id, gene_set_id) VALUES (1,'gene',1,1)");
    $h->exec("INSERT INTO annotation_source (annotation_source_id) VALUES (1)");
    $h->exec("INSERT INTO annotation (annotation_id, annotation_source_id, annotation_accession) VALUES (1,1,'GO:1')");
    $h->exec("INSERT INTO feature_annotation (feature_id, annotation_id) VALUES (1,1)");
});
$_r = validateDatabaseIntegrity($_healthy);
ok($_r['valid'] === true, 'a fully populated database is valid');
ok($_r['data_issue_codes'] === [], 'and reports no issue codes');

// Annotations loaded, join table empty -- the silent case.
$_unlinked = $_mk('unlinked', function (PDO $h) {
    $h->exec("INSERT INTO organism (organism_id) VALUES (1)");
    $h->exec("INSERT INTO genome (genome_id) VALUES (1)");
    $h->exec("INSERT INTO gene_set (gene_set_id, genome_id) VALUES (1, 1)");
    $h->exec("INSERT INTO feature (feature_id, feature_type, organism_id, gene_set_id) VALUES (1,'gene',1,1)");
    $h->exec("INSERT INTO annotation_source (annotation_source_id) VALUES (1)");
    $h->exec("INSERT INTO annotation (annotation_id, annotation_source_id, annotation_accession) VALUES (1,1,'GO:1')");
});
$_r = validateDatabaseIntegrity($_unlinked);
ok(in_array('annotations-unlinked', $_r['data_issue_codes'], true),
   'annotations with an empty feature_annotation are flagged');
ok($_r['valid'] === false, 'and that makes the database invalid');

// No features at all.
$_nofeat = $_mk('nofeat', function (PDO $h) {
    $h->exec("INSERT INTO organism (organism_id) VALUES (1)");
    $h->exec("INSERT INTO genome (genome_id) VALUES (1)");
    $h->exec("INSERT INTO gene_set (gene_set_id, genome_id) VALUES (1, 1)");
    $h->exec("INSERT INTO annotation_source (annotation_source_id) VALUES (1)");
    $h->exec("INSERT INTO annotation (annotation_id, annotation_source_id, annotation_accession) VALUES (1,1,'GO:1')");
});
$_r = validateDatabaseIntegrity($_nofeat);
ok(in_array('empty-feature', $_r['data_issue_codes'], true), 'a database with no features is flagged');

// The twin arrays must stay index-aligned -- the dashboard picks a message BY the index
// its code matched, so a drift here would attribute the wrong message to an organism.
foreach ([$_healthy, $_unlinked, $_nofeat] as $_f) {
    $_r = validateDatabaseIntegrity($_f);
    ok(count($_r['data_issues']) === count($_r['data_issue_codes']),
       'data_issues and data_issue_codes stay the same length (' . basename($_f) . ')');
}

array_map('unlink', glob("$_db_dir/*.sqlite"));
@rmdir($_db_dir);

// ----------------------------------------------------------------------------
group('Gene naming: the isoform a name was taken from');
require_once dirname(__DIR__) . '/lib/extract_search_helpers.php';
require_once dirname(__DIR__) . '/lib/parent_functions.php';
// gene 1 -> mRNA 2 (G-RA) -> cds 3 -> protein 4 (P1:pep); mRNA 5 (G-RB) -> cds 6 -> protein 7 (P2:pep).
// Protein ids deliberately unlike their mRNA ids -- they often are, and a bare protein id that
// happened to equal its mRNA id would pass through the mRNA path and hide a broken match.
$_kids = [
    ['feature_id' => 2, 'feature_uniquename' => 'G-RA',     'feature_type' => 'mRNA',    'parent_feature_id' => 1],
    ['feature_id' => 3, 'feature_uniquename' => 'G-RA:cds', 'feature_type' => 'cds',     'parent_feature_id' => 2],
    ['feature_id' => 4, 'feature_uniquename' => 'P1:pep',   'feature_type' => 'protein', 'parent_feature_id' => 3],
    ['feature_id' => 5, 'feature_uniquename' => 'G-RB',     'feature_type' => 'mRNA',    'parent_feature_id' => 1],
    ['feature_id' => 6, 'feature_uniquename' => 'G-RB:cds', 'feature_type' => 'cds',     'parent_feature_id' => 5],
    ['feature_id' => 7, 'feature_uniquename' => 'P2:pep',   'feature_type' => 'protein', 'parent_feature_id' => 6],
];
$_want = ['protein' => 'P2:pep', 'mrna' => 'G-RB'];
ok(moop_resolve_naming_protein('P2:pep', $_kids, 1) === $_want, 'MOOP protein id resolves to its protein and transcript');
// The pipeline reads the DEPOSITOR's FASTA, whose ids carry no ':pep'.
ok(moop_resolve_naming_protein('P2', $_kids, 1) === $_want, 'a bare id resolves to the same isoform, not to nothing');
ok(moop_resolve_naming_protein('P1', $_kids, 1)['mrna'] === 'G-RA', 'the other isoform is not confused with it');
ok(moop_resolve_naming_protein('G-RB', $_kids, 1) === $_want, 'an mRNA id resolves to the protein beneath it');
ok(moop_resolve_naming_protein('G-RC', $_kids, 1) === null, 'an id not on the page resolves to null');
ok(moop_resolve_naming_protein('P2:pep', $_kids, 99) === null, 'a protein of another gene resolves to null');
ok(moop_gene_naming_label('protein') !== 'Protein', 'the naming-protein label does not repeat the overview\'s Protein line');

// ----------------------------------------------------------------------------
group('Sequence Retrieval: ID ranges -- every request kept, bad ones reported');
// Each entry the user typed is one request. Two ranges on one ID used to collapse to the
// first, and a reversed range vanished with only "No valid feature IDs found".
$_p = parseFeatureIds("T-RA:1-30\nT-RA:31-40");
ok($_p['valid'] && $_p['requests'] === [
        ['id' => 'T-RA', 'start' => 1,  'end' => 30],
        ['id' => 'T-RA', 'start' => 31, 'end' => 40]],
   'two ranges on one ID are two requests');
$_same = array_map(fn($s) => parseFeatureIds($s)['requests'], ['T-RA:1-30', 'T-RA:1..30', 'T-RA 1-30', 'T-RA 1..30']);
ok(array_unique(array_map('json_encode', $_same)) === [json_encode([['id' => 'T-RA', 'start' => 1, 'end' => 30]])],
   'the four documented range forms are equivalent');
ok(parseFeatureIds('P1:pep:5-9')['requests'] === [['id' => 'P1:pep', 'start' => 5, 'end' => 9]],
   'an ID containing a colon keeps it; only the trailing range is split off');
ok(parseFeatureIds('G, T-RA')['requests'] === [['id' => 'G', 'start' => null, 'end' => null], ['id' => 'T-RA', 'start' => null, 'end' => null]],
   'plain IDs are requests with no range');
$_p = parseFeatureIds("G\nT-RA:30-1");
ok(!$_p['valid'] && strpos($_p['error'], 'T-RA:30-1') !== false, 'a reversed range is an error that names the entry');
$_p = parseFeatureIds('T-RA:0-10');
ok(!$_p['valid'] && strpos($_p['error'], 'T-RA:0-10') !== false, 'positions start at 1: a 0 start is an error');

// The batch handed to blastdbcmd for ONE FASTA: each request applied to the ID and to its
// descendants, keeping only IDs that live in this FASTA.
$_kids = ['G' => ['G-RA', 'G-RA:cds', 'P1:pep']];
ok(buildSequenceBatch([['id' => 'G', 'start' => 1, 'end' => 30]], ['P1:pep'], $_kids) === ['P1:pep 1-30'],
   'a gene\'s range reaches its protein; the gene itself has no sequence here');
ok(buildSequenceBatch(parseFeatureIds("G-RA:1-30\nG-RA:31-40\nG-RA")['requests'], ['G-RA'], $_kids)
       === ['G-RA 1-30', 'G-RA 31-40', 'G-RA'],
   'two ranges and a whole sequence of one ID are all extracted');
ok(buildSequenceBatch(parseFeatureIds("G\nG-RA")['requests'], ['G-RA', 'G-RB'], ['G' => ['G-RA', 'G-RB']])
       === ['G-RA', 'G-RB'],
   'an ID asked for twice (directly and via its gene) is extracted once');

// ----------------------------------------------------------------------------
group('Genomic regions: one definition for gene page, Data Exporter and Sequence Retrieval');
require_once dirname(__DIR__) . '/lib/genomic_regions.php';
$_plus  = ['chr' => 'c1', 'start' => 1001, 'end' => 2000, 'strand' => '+'];
$_minus = ['chr' => 'c1', 'start' => 1001, 'end' => 2000, 'strand' => '-'];
$_rg = fn($r) => $r === null ? null : [$r['start'], $r['end'], $r['strand']];
ok($_rg(moop_genomic_region($_plus, 'body')) === [1001, 2000, '+'], 'body is the locus');
ok($_rg(moop_genomic_region($_plus, 'upstream', 100)) === [901, 1000, '+'], '+ strand: upstream is before the start');
ok($_rg(moop_genomic_region($_plus, 'downstream', 100)) === [2001, 2100, '+'], '+ strand: downstream is after the end');
ok($_rg(moop_genomic_region($_minus, 'upstream', 100)) === [2001, 2100, '-'], '- strand: upstream lies at HIGHER coordinates');
ok($_rg(moop_genomic_region($_minus, 'downstream', 100)) === [901, 1000, '-'], '- strand: downstream lies at lower coordinates');
ok($_rg(moop_genomic_region($_plus, 'body', 0, 1, 60)) === [1001, 1060, '+'], '+ strand: range 1-60 is the first 60 bases');
ok($_rg(moop_genomic_region($_minus, 'body', 0, 1, 60)) === [1941, 2000, '-'], '- strand: range 1-60 counts from the 5\' end (the high coordinate)');
ok($_rg(moop_genomic_region($_plus, 'body', 0, 990, 5000)) === [1990, 2000, '+'], 'a range past the end stops at the end');
ok(moop_genomic_region($_plus, 'body', 0, 1001, 1100) === null, 'a range starting past the end is empty');
$_edge = ['chr' => 'c1', 'start' => 50, 'end' => 80, 'strand' => '+'];
ok($_rg(moop_genomic_region($_edge, 'upstream', 100)) === [1, 49, '+'], 'upstream stops at position 1');
ok(moop_genomic_region(['start' => 1] + $_edge, 'upstream', 100) === null, 'nothing upstream of position 1');
ok(moop_genomic_region($_plus, 'upstream', 0) === null, 'a 0 bp flank is no record');

// Where a feature sits: a transcript's OWN span, never its gene's (feature_coords.tsv rows
// all carry the gene's span, which is exactly the trap).
$_gsd = sys_get_temp_dir() . '/moop_loci_' . getmypid();
@mkdir($_gsd);
file_put_contents("$_gsd/feature_coords.tsv", "G-RA\tG\tc1\t100\t900\t-\nG-RB\tG\tc1\t100\t900\t-\n");
file_put_contents("$_gsd/exon_coords.tsv", "G-RA\tc1\t-\t100,500\t100,200;400,500\nG-RB\tc1\t-\t300,900\t300,900\n");
$_l = moop_feature_loci($_gsd, ['G', 'G-RA', 'G-RB', 'nope']);
ok([$_l['G']['start'], $_l['G']['end'], $_l['G']['kind']] === [100, 900, 'gene'], 'a gene\'s locus comes from feature_coords.tsv');
ok([$_l['G-RA']['start'], $_l['G-RA']['end'], $_l['G-RA']['kind']] === [100, 500, 'transcript'], 'a transcript\'s span is its own, from exon_coords.tsv');
ok(!isset($_l['nope']), 'an ID in neither file has no locus');
array_map('unlink', glob("$_gsd/*")); @rmdir($_gsd);

// One letter case everywhere: a soft-masked (lowercase) source comes out uppercase from the
// direct .fai reader too, as it already did from blastdbcmd and extractFastaRegion().
require_once dirname(__DIR__) . '/lib/fasta_index.php';
$_fa = sys_get_temp_dir() . '/moop_case_' . getmypid() . '.fa';
file_put_contents($_fa, ">T1 desc\nACGTacgtAC\nGT\n");
file_put_contents("$_fa.fai", "T1\t12\t9\t10\t11\n");
$_got = moop_fasta_fetch_with_headers($_fa, ['T1']);
ok(($_got['T1']['seq'] ?? null) === 'ACGTACGTACGT', 'soft-masked lowercase is uppercased by the .fai reader');
ok(extractFastaRegion($_fa, "$_fa.fai", 'T1', 5, 8) === 'ACGT', '...and by the region reader');
@unlink($_fa); @unlink("$_fa.fai");

ok(moop_genomic_fasta_record('G', 'upstream', 500, ['chr' => 'c1', 'start' => 1, 'end' => 3, 'strand' => '-'], 'ACG')
       === ">G upstream_500bp c1:1-3(-)\nACG\n", 'header: ID, part, location and strand');

// A gene set that names chromosomes differently from its genome (Ensembl "2L" vs RefSeq
// "NC_004354.4") got empty genomic output with no word why. Now each skip is said.
$_ad = sys_get_temp_dir() . '/moop_chr_' . getmypid();
@mkdir($_ad); @mkdir("$_ad/gs");
file_put_contents("$_ad/genome.fa", ">c1\nACGTACGTAC\n");
file_put_contents("$_ad/genome.fa.fai", "c1\t10\t4\t10\t11\n");
file_put_contents("$_ad/gs/feature_coords.tsv", "G1\tG1\tc1\t2\t5\t+\nG2\tG2\t2L\t2\t5\t+\nG3\tG3\t2L\t1\t3\t-\n");
ok(moop_genome_missing_seqnames($_ad, ['c1', '2L', '2L', '']) === ['2L'], 'a sequence name not in the genome is found, once; a blank is not a name');
$_r = moop_retrieve_genomic([['id' => 'G1', 'start' => null, 'end' => null], ['id' => 'G2', 'start' => null, 'end' => null],
                             ['id' => 'G3', 'start' => null, 'end' => null]], "$_ad/gs", $_ad);
ok(strpos($_r['fasta'], '>G1 genomic c1:2-5(+)') === 0 && strpos($_r['fasta'], 'G2') === false, 'Sequence Retrieval: the matching gene is cut, the others are not');
ok(count($_r['notes']) === 1 && strpos($_r['notes'][0], "2 features skipped: sequence '2L' is not in this assembly's genome") === 0,
   'Sequence Retrieval: the skipped genes are reported, not dropped silently');
$_n = moop_genomic_skip_notes(['no_coords' => ['A', 'B', 'C', 'D'], 'no_exons' => ['E']]);
ok($_n === ['4 features skipped: no genome coordinates in this gene set (e.g. A, B, C, …).',
            "1 feature skipped: no exons found for them in the gene set's GFF (e.g. E)."], 'no coordinates and no exons are reported too');
ok(moop_genomic_skip_notes([]) === [], 'nothing skipped -> no notes');

// Sequence Retrieval flanks for a protein, CDS or transcript ID: the ISOFORM's own span, with
// the gene named in the header. A protein ID used to give nothing at all, silently.
$_adb = "$_ad/organism.sqlite";
$_p = new PDO("sqlite:$_adb");
$_p->exec("CREATE TABLE feature (feature_id INTEGER PRIMARY KEY, feature_uniquename TEXT, feature_name TEXT,
           feature_description TEXT, feature_type TEXT, parent_feature_id INTEGER, gene_set_id INTEGER)");
$_p->exec("INSERT INTO feature VALUES (1,'G1','Abc1','','gene',NULL,1),(2,'T1','T1','','mRNA',1,1),
           (3,'T1:cds','','','CDS',2,1),(4,'T1:pep','','','protein',3,1),
           (5,'B1','B1','','gene',NULL,1),(6,'B1p','','','protein',5,1),(7,'Z9','','','mRNA',NULL,1)");
$_p = null;
$_an = moop_genomic_anchors(['G1', 'T1', 'T1:pep', 'T1:cds', 'B1p', 'nope'], $_adb, [1]);
ok($_an['T1:pep'] === ['locus' => 'T1', 'gene' => 'G1', 'gene_name' => 'Abc1', 'transcript' => 'T1'], 'a protein is cut at its transcript, under its gene');
ok($_an['T1:cds']['locus'] === 'T1' && $_an['T1']['locus'] === 'T1', 'a CDS and a transcript: the transcript');
ok($_an['G1']['locus'] === 'G1' && $_an['G1']['transcript'] === null, 'a gene: itself');
ok($_an['B1p']['locus'] === 'B1' && $_an['B1p']['gene_name'] === null, 'a bacterial protein -> gene chain: the gene; a name that is just its ID is not repeated');
ok(!isset($_an['nope']), 'an ID not in the database has no anchor');
file_put_contents("$_ad/genome.fa", ">c1\nACGTACGTACGTACGTACGT\n");
file_put_contents("$_ad/genome.fa.fai", "c1\t20\t4\t20\t21\n");
file_put_contents("$_ad/gs/feature_coords.tsv", "T1\tG1\tc1\t5\t16\t+\n");
file_put_contents("$_ad/gs/exon_coords.tsv", "T1\tc1\t+\t8,12\t8,9;11,12\n");
$_req = fn(string $id, ?int $s = null, ?int $e = null) => ['id' => $id, 'start' => $s, 'end' => $e];
$_r = moop_retrieve_genomic([$_req('T1:pep'), $_req('Z9')], "$_ad/gs", $_ad, 2, 0, $_an + ['Z9' => ['locus' => 'Z9', 'gene' => 'Z9', 'gene_name' => null, 'transcript' => null]]);
ok(strpos($_r['fasta'], ">T1:pep gene=G1 gene_name=Abc1 transcript=T1 genomic c1:8-12(+)\n") === 0,
   'protein ID: its transcript\'s own span (not the gene\'s 5-16), gene and transcript in the header');
ok(strpos($_r['fasta'], ">T1:pep gene=G1 gene_name=Abc1 transcript=T1 upstream_2bp c1:6-7(+)\n") !== false, '...and upstream of the isoform\'s start');
ok(strpos($_r['fasta'], 'Z9') === false && ($_r['notes'][0] ?? '') === '1 feature skipped: no genome coordinates in this gene set (e.g. Z9).',
   'an ID in the database but not on the genome is reported, not dropped silently');
$_r = moop_retrieve_genomic([$_req('T1:pep', 1, 2)], "$_ad/gs", $_ad, 2, 0, $_an);
ok(strpos($_r['fasta'], ' genomic ') === false && strpos($_r['fasta'], 'upstream_2bp') !== false
   && strpos($_r['notes'][0] ?? '', 'A range on a protein or CDS ID') === 0,
   'a range on a protein is in amino acids: no genomic cut (a note says why), flanks still given');

// exon_coords.tsv is keyed for the database's ID too: an Ensembl GFF's "transcript:FBtr1"
// must be found as FBtr1. The builder had its own rna-/cds-/gene- list, which missed it, so no
// Drosophila transcript could be placed on the genome.
require_once "$BASE/lib/jbrowse/gene_set_functions.php";
$_xd = sys_get_temp_dir() . '/moop_exbuild_' . getmypid();
@mkdir($_xd);
file_put_contents("$_xd/" . genes_gff_filename(), implode("\n", [
    "c1\ts\tgene\t100\t900\t.\t+\t.\tID=gene:G1",
    "c1\ts\tmRNA\t100\t900\t.\t+\t.\tID=transcript:FBtr1;Parent=gene:G1",
    "c1\ts\texon\t100\t200\t.\t+\t.\tParent=transcript:FBtr1",
    "c1\ts\tgene\t1000\t1300\t.\t-\t.\tID=gene-X",
    "c1\ts\tmRNA\t1000\t1300\t.\t-\t.\tID=rna-XM_1;Parent=gene-X",
    "c1\ts\texon\t1000\t1300\t.\t-\t.\tParent=rna-XM_1",
]) . "\n");
generateExonCoordsIndex($_xd);
$_keys = array_map(fn($l) => explode("\t", $l)[0], file("$_xd/exon_coords.tsv", FILE_IGNORE_NEW_LINES));
ok($_keys === ['transcript:FBtr1', 'FBtr1', 'rna-XM_1', 'XM_1'], 'exon_coords.tsv: the GFF ID and the database ID, Ensembl (transcript:) and NCBI (rna-) alike');
array_map('unlink', glob("$_xd/*")); @rmdir($_xd);

// Data Exporter exon mode: exon lines usually carry only Parent= (NV2 exported no exons at all),
// and the GFF gene ID may be prefixed or a GeneID. The same id rules as the gene page.
require_once "$BASE/lib/moopmart_functions.php";
$_gff = sys_get_temp_dir() . '/moop_exons_' . getmypid() . '.gff';
file_put_contents($_gff, implode("\n", [
    "##gff-version 3",
    "c1\ts\tgene\t100\t900\t.\t+\t.\tID=gene:G1",
    "c1\ts\tmRNA\t100\t900\t.\t+\t.\tID=transcript:T1;Parent=gene:G1",
    "c1\ts\texon\t100\t200\t.\t+\t.\tParent=transcript:T1",
    "c1\ts\texon\t700\t900\t.\t+\t.\tParent=transcript:T1",
    "c1\ts\tmRNA\t100\t900\t.\t+\t.\tID=transcript:T2;Parent=gene:G1",
    "c1\ts\texon\t300\t400\t.\t+\t.\tParent=transcript:T2",
    "c1\ts\tgene\t1000\t1300\t.\t-\t.\tID=gene-B1;Dbxref=GeneID:4242",
    "c1\ts\tCDS\t1000\t1300\t.\t-\t0\tID=cds-B1;Parent=gene-B1",
    "c1\ts\tgene\t2000\t2100\t.\t+\t.\tID=Other",
    "c1\ts\texon\t2000\t2100\t.\t+\t.\tParent=Other",
]) . "\n");
$_ex = moopmartGetExonCoords($_gff, ['G1', '4242']);
ok(array_map(fn($e) => [$e['start'], $e['end']], $_ex['G1'] ?? []) === [[100, 200], [700, 900]],
   'exons with no ID= are found, for a gene: prefixed gene, first isoform only');
ok(array_map(fn($e) => [$e['type'], $e['start']], $_ex['4242'] ?? []) === [['CDS', 1000]],
   'a bacterial gene -> CDS (no transcript), matched by its GeneID');
ok(!isset($_ex['Other']), 'a gene that was not asked for is not collected');

// Exon mode numbers 5'->3' along the gene, each type on its own counter -- on the minus strand
// exon1 is the HIGHEST-coordinate exon. (It was one shared counter in coordinate order.)
$_ed = sys_get_temp_dir() . '/moop_exnum_' . getmypid();
@mkdir($_ed);
file_put_contents("$_ed/genome.fa", ">c1\nAAAAACCCCCGGGGGTTTTTACGTA\nCCCCC\n");
file_put_contents("$_ed/genome.fa.fai", "c1\t30\t4\t25\t26\n");
file_put_contents("$_ed/genes.gff", implode("\n", [
    "c1\ts\tgene\t1\t25\t.\t-\t.\tID=M1",
    "c1\ts\tmRNA\t1\t25\t.\t-\t.\tID=M1.t;Parent=M1",
    "c1\ts\texon\t1\t5\t.\t-\t.\tParent=M1.t",
    "c1\ts\texon\t11\t15\t.\t-\t.\tParent=M1.t",
    "c1\ts\tCDS\t12\t14\t.\t-\t0\tParent=M1.t",
    "c1\ts\texon\t21\t25\t.\t-\t.\tParent=M1.t",
    "c1\ts\tCDS\t22\t24\t.\t-\t0\tParent=M1.t",
]) . "\n");
$_h = fopen('php://memory', 'w+');
moopmartStreamGenomicFasta([['uniquename' => 'M1', 'chr' => 'c1', 'start' => 1, 'end' => 25, 'strand' => '-']], $_ed, "$_ed/genes.gff", 'exons', 0, $_h);
rewind($_h); $_fa_out = stream_get_contents($_h); fclose($_h);
preg_match_all('/^>(\S+) (\S+)/m', $_fa_out, $_m);
ok($_m[1] === ['M1.CDS1', 'M1.exon1', 'M1.CDS2', 'M1.exon2', 'M1.exon3'], "exons numbered 5'->3' on the minus strand, each type on its own counter");
ok($_m[2][1] === 'c1:21-25(-)' && strpos($_fa_out, ">M1.exon1 c1:21-25(-)\nTACGT\n") !== false, 'exon1 is the highest-coordinate exon, reverse-complemented');
// The batch reader cuts what extractFastaRegion() cuts, clipped at the contig end.
$_g = moop_genome_open($_ed, ['c1', 'nope']);
ok(moop_genome_missing($_g, ['c1', 'nope', '']) === ['nope'], 'an opened genome knows which names it lacks');
ok((moop_genomic_sequence($_g, ['chr' => 'c1', 'start' => 24, 'end' => 40, 'strand' => '+'])['seq'] ?? '') === extractFastaRegion("$_ed/genome.fa", "$_ed/genome.fa.fai", 'c1', 24, 40)
   && extractFastaRegion("$_ed/genome.fa", "$_ed/genome.fa.fai", 'c1', 24, 40) === 'TACCCCC', 'batch cut == single cut, across a line break and clipped at the end');
moop_genome_close($_g);
array_map('unlink', glob("$_ed/*")); @rmdir($_ed);
@unlink($_gff);
array_map('unlink', glob("$_ad/gs/*")); @rmdir("$_ad/gs"); array_map('unlink', glob("$_ad/*")); @rmdir($_ad);

// ----------------------------------------------------------------------------
group('feature_coords.tsv: one reader, every lookup');
require_once "$BASE/lib/feature_coords.php";
$_fc = sys_get_temp_dir() . '/moop_fc_' . getmypid();
@mkdir($_fc);
file_put_contents("$_fc/feature_coords.tsv", implode("\n", [
    "G10:pep\tG10\tc2\t50\t60\t+",
    "G10-RA\tG10\tc2\t50\t60\t+",
    "G1-RA\tG1\tc1\t100\t900\t- ",
    "G1-RB\tG1\tc1\t100\t900\t-",
    "B5\tB5\tc10\t5\t9",
    "short\tG9\tc3",
]) . "\n");
ok(moop_feature_coords_by_feature($_fc, ['G1-RB', 'G10-RA', 'nope']) ===
   ['G10-RA' => ['gene_id' => 'G10', 'chr' => 'c2', 'start' => 50, 'end' => 60, 'strand' => '+'],
    'G1-RB'  => ['gene_id' => 'G1', 'chr' => 'c1', 'start' => 100, 'end' => 900, 'strand' => '-']], 'by feature id (column 1)');
ok(moop_feature_coords_by_gene($_fc, ['G1', 'B5', 'G9']) ===
   ['G1' => ['chr' => 'c1', 'start' => 100, 'end' => 900, 'strand' => '-'],
    'B5' => ['chr' => 'c10', 'start' => 5, 'end' => 9, 'strand' => '.']],
   'by gene id: first row wins, strand trimmed in every lookup, a 5-column row counts, a short row does not');
ok(moop_feature_coords_by_gene($_fc, []) === [] && moop_feature_coords_by_feature($_fc, []) === [], 'an empty list asks for nothing');
ok((moop_feature_coords_find($_fc, 'G1')['id'] ?? '') === 'G1-RA', 'find: a gene id finds its first row...');
ok((moop_feature_coords_find($_fc, 'G10-RA')['gene_id'] ?? '') === 'G10', '...a feature id finds its own row');
ok(moop_feature_coords_find($_fc, 'G') === null && moop_feature_coords_find($_fc, '1-R') === null, '...and a substring of an id is not that id');
ok((moop_feature_coords_first($_fc)['id'] ?? '') === 'G10-RA', 'first: a derived id (X:pep) is skipped');
ok(moop_feature_coords_seqnames($_fc) === ['c1', 'c2', 'c3', 'c10'], 'sequence names: distinct, natural order');
ok(moop_feature_coords_seqnames("$_fc/none") === null && moop_feature_coords_find("$_fc/none", 'G1') === null, 'no file: null, not an error');
require_once "$BASE/lib/gff_gene_model.php";
ok(moop_feature_coords_loc($_fc, 'G1') === ['seqname' => 'c1', 'start' => 100, 'end' => 900, 'strand' => '-', 'loc_string' => 'c1:100-900'],
   'the gene page\'s Location reads through it');
array_map('unlink', glob("$_fc/*")); @rmdir($_fc);

// ----------------------------------------------------------------------------
group('GFF gene model: whole-value id matching, one reader');
require_once dirname(__DIR__) . '/lib/gff_gene_model.php';
$_a = moop_gff_attrs('ID=rna-XM_1.2;Parent=gene-FHDC1;Dbxref=GeneID:102421115,Genbank:XM_1.2');
ok(moop_gff_id_matches(['ID' => 'NV2g1.1'], 'NV2g1.1'), 'an identical id matches, version included');
ok(moop_gff_id_matches($_a, 'XM_1.2'), 'an id after one source prefix (rna-) matches');
ok(moop_gff_id_matches(['ID' => 'gene:ENSG1'], 'ENSG1'), '...and after gene:');
ok(moop_gff_id_matches($_a, '102421115'), 'a RefSeq gene matches by its exact GeneID');
ok(!moop_gff_id_matches(['ID' => 'X10'], 'X1'), 'X1 does NOT match X10 (the old strpos did)');
ok(!moop_gff_id_matches(['ID' => 'g.10'], 'g.1'), 'a .1 version never matches .10');
ok(!moop_gff_id_matches(['ID' => 'gene-A', 'Dbxref' => 'GeneID:1024211150'], '102421115'), 'a GeneID must match whole');
ok(moop_gff_parents(moop_gff_attrs('ID=t1;Parent=a,b')) === ['a', 'b'], 'Parent= may list several parents');
ok(moop_gff_parents(moop_gff_attrs('ID=SlugcT1.1;geneID=SlugcG1')) === ['SlugcG1'], 'geneID= links a transcript with no Parent=');
ok(moop_gff_parents(moop_gff_attrs('ID=SlugcG1;geneID=SlugcG1')) === [], '...but not a gene to itself');

// End to end on a made-up gene set: gene X1 overlaps its neighbour X10, whose line comes FIRST.
$_od = sys_get_temp_dir() . '/moop_gm_' . getmypid();
@mkdir("$_od/OrgA/asm1/gs1", 0777, true);
file_put_contents("$_od/OrgA/asm1/gs1/genes.gff", implode("\n", [
    "c1\tx\tgene\t100\t900\t.\t+\t.\tID=X10",
    "c1\tx\tmRNA\t100\t900\t.\t+\t.\tID=X10-RA;Parent=X10",
    "c1\tx\texon\t100\t900\t.\t+\t.\tParent=X10-RA",
    "c1\tx\tgene\t200\t500\t.\t-\t.\tID=X1",
    "c1\tx\tmRNA\t200\t500\t.\t-\t.\tID=X1-RA;Parent=X1",
    "c1\tx\texon\t200\t300\t.\t-\t.\tParent=X1-RA",
    "c1\tx\tCDS\t220\t300\t.\t-\t0\tParent=X1-RA",
    "c1\tx\tgene\t1000\t2000\t.\t+\t.\tID=G2",
    "c1\tx\ttranscript\t1000\t2000\t.\t+\t.\tID=G2.1;geneID=G2",
    "c1\tx\texon\t1000\t2000\t.\t+\t.\tParent=G2.1",
]) . "\n");
$_cfg = ConfigManager::getInstance();
$_rp = new ReflectionProperty($_cfg, 'config'); $_rp->setAccessible(true);
$_saved = $_rp->getValue($_cfg);
$_c = $_saved; $_c['organism_data'] = $_od; $_c['genomes_directory'] = "$_od/none"; $_rp->setValue($_cfg, $_c);
$_m = moop_gene_model('OrgA', 'asm1', 'gs1', 'X1');
ok($_m && $_m['gff_id'] === 'X1' && array_column($_m['isoforms'], 'id') === ['X1-RA'],
   'X1\'s model is X1, not its overlapping neighbour X10 that comes first');
ok($_m && $_m['isoforms'][0]['cds'] === [['start' => 220, 'end' => 300]] && $_m['loc']['strand'] === '-',
   'its exons, CDS and strand come with it');
$_m = moop_gene_model('OrgA', 'asm1', 'gs1', 'G2');
ok($_m && array_column($_m['isoforms'], 'id') === ['G2.1'], 'a geneID= transcript is drawn under its gene');
ok(moop_gene_model('OrgA', 'asm1', 'gs1', 'X') === null, 'an id that is only a PREFIX of real ids finds nothing');
$_rp->setValue($_cfg, $_saved);
array_map('unlink', glob("$_od/OrgA/asm1/gs1/*")); @rmdir("$_od/OrgA/asm1/gs1"); @rmdir("$_od/OrgA/asm1"); @rmdir("$_od/OrgA"); @rmdir($_od);

// ----------------------------------------------------------------------------
group('Annotation scores: what each source\'s Score means, from the shipped table');
require_once dirname(__DIR__) . '/lib/annotation_scores.php';
$_sc = moop_score_config_normalize(json_decode(file_get_contents(dirname(__DIR__) . '/metadata/annotation_scores.json.example'), true));
$_k = fn($type, $source) => moop_score_kind($type, $source, $_sc);
ok($_k('Orthologs', 'OMA HOG orthologs (HUMAN)') === 'oma_relation', 'an OMA ortholog score is a relationship code, for any partner species');
ok($_k('Orthologs', 'EggNOG') === 'evalue', 'EggNOG in the same table is an E-value — the meaning changes row by row');
ok($_k('Domains', 'InterProScan (ProSiteProfiles)') === 'profile_score', 'a profile score is not read as an E-value');
ok($_k('Domains', 'InterProScan (InterPro)') === 'none', 'InterPro entries carry no score');
ok($_k('Gene Ontology', 'InterProScan (InterPro2GO)') === 'none', 'GO terms carry no score');
ok($_k('Closest Gene', 'Closest human gene (HGNC)') === 'closest_human', 'closest HUMAN gene uses the 7-tier scale');
ok($_k('Closest Gene', 'Closest mouse gene') === 'closest_species', 'closest gene in another species uses its own 4-rank scale');
ok($_k('Mystery Type', 'Some New Tool') === '', 'a source no rule covers has no kind (shown raw, listed for the admin)');
$_f = fn($score, $type, $source) => moop_format_score($score, $_k($type, $source), $_sc);
$_r = $_f(4.0, 'Orthologs', 'OMA pairwise orthologs (MOUSE)');
ok($_r['display'] === 'many:many' && $_r['export'] === 'many:many' && $_r['order'] === '4', 'OMA 4 shows and exports "many:many", sorts as 4');
ok($_f(9.0, 'Orthologs', 'OMA HOG orthologs (MOUSE)')['display'] === '9', 'a code with no words is shown as stored, not hidden');
ok($_f(2.66983e-05, 'Domains', 'InterProScan (CDD)')['display'] === '2.7e-5', 'an E-value is shortened');
ok($_f(2.66983e-05, 'Domains', 'InterProScan (CDD)')['export'] === '2.66983E-5' || $_f(2.66983e-05, 'Domains', 'InterProScan (CDD)')['export'] === (string)2.66983e-05, 'a download keeps the full E-value');
ok($_f(0.999424, 'Domains', 'SignalP')['display'] === '99.9%', 'a probability of 0.9994 reads 99.9%, not a rounded-up 100%');
ok($_f(1.0, 'Domains', 'DeepTMHMM')['display'] === '1 segment' && $_f(7.0, 'Domains', 'DeepTMHMM')['display'] === '7 segments', 'counts carry their unit, singular and plural');
ok(strpos($_f(null, 'Domains', 'InterProScan (Pfam)')['display'], '—') !== false, 'no score shows a dash');
$_bad = moop_score_config_normalize(['kinds' => ['k' => ['display' => 'evalue']], 'rules' => [['kind' => 'k'], ['source' => 'X', 'kind' => 'missing'], ['source' => 'Y', 'kind' => 'k']]]);
ok(count($_bad['rules']) === 1, 'a rule matching everything, or naming a missing kind, is dropped rather than applied');
// Fallbacks (type only) are a separate, later tier: wherever one sits in the file, it cannot
// shadow a source rule. As one ordered list, "Domains -> E-value" moved above ProSite would
// have silently turned every ProSite profile score into an "E-value".
$_tier = moop_score_config_normalize(['kinds' => ['e' => ['display' => 'evalue'], 'p' => ['display' => 'number']],
    'rules' => [['type' => 'Domains', 'kind' => 'e'], ['source' => 'InterProScan (ProSiteProfiles)', 'kind' => 'p']]]);
ok(moop_score_kind('Domains', 'InterProScan (ProSiteProfiles)', $_tier) === 'p', 'a fallback listed first does not shadow a source rule');
ok(moop_score_kind('Domains', 'InterProScan (Pfam)', $_tier) === 'e', 'the fallback still catches sources no rule names');
ok(moop_score_kind('Protein Features', 'InterProScan (SignalP_EUK)', $_sc) === 'none' && moop_score_kind('Protein Features', 'SignalP', $_sc) === 'signalp',
   'InterProScan\'s SignalP runs (no score) are not confused with standalone SignalP (a probability)');

// ----------------------------------------------------------------------------
group('naming tag: stripped from descriptions (shared fixture with the JS suite)');
require_once "$BASE/lib/functions_display.php";
foreach (json_decode(file_get_contents("$BASE/tests/fixtures/naming_tags.json"), true)['cases'] as [$_in, $_want]) {
    ok(moop_strip_naming_tag($_in) === $_want, json_encode($_in));
}

ok(moop_gene_symbol('HIVEP3', 'h1SMcG0000013') === 'HIVEP3' && moop_gene_symbol(' trp-1 ', 'g') === 'trp-1', 'symbol: a real symbol is shown, trimmed');
ok(moop_gene_symbol('', 'g') === '' && moop_gene_symbol(null, 'g') === '', 'symbol: blank → none');
ok(moop_gene_symbol('COKUS1KC_0003911-RA', 'COKUS1KC_0003911') === '', 'symbol: the gene\'s own id with a transcript suffix is not a symbol (Congeria unnamed genes)');
ok(moop_gene_symbol('t1.1', 'g1', ['t1.1', 't2.1']) === '', 'symbol: a child\'s id is not a symbol');
ok(moop_gene_symbol('LOC5512485', 'g') === '' && moop_gene_symbol('LOCK1', 'g') === 'LOCK1', 'symbol: an NCBI LOC placeholder is not shown; a real name starting LOC is');

// ----------------------------------------------------------------------------
group('expression: bundles (TPM tables) → yes/no');
// ----------------------------------------------------------------------------
// notes/EXPRESSION_COUNT_TABLES_PLAN.md. A made-up gene set: g1 has two transcripts, g2 one,
// g3 none in the table. All on temp files; no site data.
require_once "$BASE/lib/expression_functions.php";
$_xd = sys_get_temp_dir() . '/moop_expr_test_' . getmypid();
@mkdir($_xd);
$_xw = function (string $name, string $body) use ($_xd) { file_put_contents("$_xd/$name", $body); return "$_xd/$name"; };
$_xthrows = function (callable $fn) { try { $fn(); return false; } catch (RuntimeException $e) { return true; } };

$_m = moop_expression_read_matrix($_xw('a.tsv', "transcript_id\tS1\tS2\tS3\n\"t1.1\"\t2\t0\tNA\nt2.1\t3\t0\t\nt3.1\t0.5\t0.2\t9\n"));
ok($_m['samples'] === ['S1', 'S2', 'S3'], 'matrix: the id column header is not a sample; quotes stripped');
ok($_m['rows']['t1.1'] === [2.0, 0.0, null] && $_m['rows']['t2.1'][2] === null, 'matrix: NA and empty are null, 0 stays 0 — "not measured" is not "not expressed"');
$_r = moop_expression_read_matrix($_xw('r.tsv', "S1\tS2\ng1\t1\t2\n"));
ok($_r['samples'] === ['S1', 'S2'] && $_r['rows']['g1'] === [1.0, 2.0], 'matrix: R write.table header (no id cell) — every header cell is a sample');
ok($_xthrows(fn() => moop_expression_read_matrix($_xw('d.tsv', "id\tS1\ng1\t1\ng1\t2\n"))), 'matrix: a duplicated id is refused, not overwritten');
ok($_xthrows(fn() => moop_expression_read_matrix($_xw('n.tsv', "id\tS1\ng1\t-1\n"))), 'matrix: a negative TPM is refused');
ok($_xthrows(fn() => moop_expression_read_matrix($_xw('g.tsv', "id\tS1\tS2\ng1\t1\n"))), 'matrix: a short row is refused');

$_s = moop_expression_read_samples($_xw('s.tsv', "sample_id\tgroup\treplicate\tstage\nS3\tlate\t1\t48h\nS1\tearly\t1\t2h\nS2\tearly\t2\t2h\n"), $_m['samples']);
ok(array_column($_s['samples'], 'name') === ['S3', 'S1', 'S2'], 'samples.tsv: its row order is the display order');
ok($_s['samples'][0]['attrs'] === ['stage' => '48h'], 'samples.tsv: every other column is kept as an attribute');
ok($_xthrows(fn() => moop_expression_read_samples($_xw('s2.tsv', "sample_id\tgroup\nS1\ta\nS2\ta\n"), $_m['samples'])), 'samples.tsv: a sample in the table but not listed is refused');
ok($_xthrows(fn() => moop_expression_read_samples($_xw('s3.tsv', "sample_id\tgroup\nS1\ta\nS2\ta\nS3\ta\nS9\ta\n"), $_m['samples'])), 'samples.tsv: a listed sample not in the table is refused');
$_s0 = moop_expression_read_samples(null, ['A', 'B']);
ok($_s0['samples'][1]['group'] === 'B' && $_s0['warnings'], 'no samples.tsv: each sample is its own group, with a warning');

$_genes = ['g1' => true, 'g2' => true, 'g3' => true];
$_tx    = ['t1.1' => 'g1', 't2.1' => 'g1', 't3.1' => 'g2'];
$_mt = moop_expression_match_ids(['t1.1', 't2.1', 't3', 'ERCC-1'], $_genes, $_tx);
ok($_mt['level'] === 'transcript' && $_mt['exact'] === 2 && $_mt['by_version'] === 1 && $_mt['unmatched'] === ['ERCC-1'],
   'match: transcript level detected; a missing ".1" still matches; spike-ins are reported unmatched');
ok(moop_expression_match_ids(['g1', 'g2'], $_genes, $_tx)['level'] === 'gene', 'match: gene-level table detected');
$_amb = moop_expression_match_ids(['x'], [], ['x.1' => 'g1', 'x.2' => 'g1']);
ok($_amb['map'] === [], 'match: a versionless id that could be either of two transcripts is NOT guessed');

// Experiments as moop_expression_bundle_experiments() returns them: samples in display order.
$_smp = [['name' => 'S3', 'group_name' => 'late'], ['name' => 'S1', 'group_name' => 'early'], ['name' => 'S2', 'group_name' => 'early']];
$_e3  = ['detect_threshold' => 3.0, 'samples' => $_smp];
$_e1  = ['detect_threshold' => 1.0, 'samples' => $_smp];
$_gm = moop_expression_group_means($_smp, [null, 5.0, 0.0]);
ok($_gm === [['group' => 'late', 'mean' => null, 'n' => 0], ['group' => 'early', 'mean' => 2.5, 'n' => 2]], 'group means: replicates averaged; a group with nothing measured is null, not 0');
ok(moop_expression_call($_e3, [null, 5.0, 0.0])['call'] === 'no', 'call: early mean 2.5 is below this experiment\'s threshold of 3 → no');
ok(moop_expression_call($_e1, [null, 5.0, 0.0])['call'] === 'yes', 'call: the same values pass the default 1 TPM threshold → yes');
$_c = moop_expression_call($_e3, [9.0, 0.5, 0.2]);
ok($_c['call'] === 'yes' && $_c['top_group'] === 'late' && $_c['top_mean'] === 9.0, 'call: reports the highest group');
ok(moop_expression_call($_e3, null)['call'] === 'unknown', 'call: no row is "unknown", never "no"');

// --- counts path: exon-union lengths, counts → TPM, a whole bundle, the htseq converter's reader
$_ec = $_xw('exon_coords.tsv', "t1.1\tc\t+\t1,300\t1,100;201,300\nt2.1\tc\t+\t51,250\t51,150;241,250\nt3.1\tc\t-\t1,50\t1,50\n");
$_len = moop_expression_feature_lengths($_ec, $_tx);
ok($_len['t1.1'] === 200 && $_len['t2.1'] === 110, 'lengths: a transcript is the sum of its exons');
ok($_len['g1'] === 250, 'lengths: a gene is the UNION of its transcripts\' exons (1-150 ∪ 201-300 = 250), overlap not counted twice');
$_t = moop_expression_counts_to_tpm(['a' => [10.0, null], 'b' => [30.0, 4.0], 'c' => [5.0, 5.0]], ['a' => 100, 'b' => 300]);
ok(abs($_t['rows']['a'][0] - 500000) < 1e-6 && abs($_t['rows']['b'][0] - 500000) < 1e-6, 'TPM: equal reads per base → equal TPM, whatever the gene length');
ok($_t['rows']['a'][1] === null && abs($_t['rows']['b'][1] - 1e6) < 1e-6, 'TPM: a null count stays null and is left out of the sample total');
ok($_t['no_length'] === ['c'] && !isset($_t['rows']['c']), 'TPM: a feature with no exon length gets no TPM and is reported');
ok($_xthrows(fn() => moop_expression_read_matrix($_xw('c.tsv', "gene_id\tS1\ng1\t2.5\n"), true)), 'counts.tsv: a fractional "count" is refused');

$_hd = "$_xd/htseq"; @mkdir($_hd);
file_put_contents("$_hd/s.10.counts", "g1\t5\ng2\t1\n__no_feature\t100\n");
file_put_contents("$_hd/s.2.counts",  "g1\t3\n__no_feature\t7\n__ambiguous\t2\n");
$_h = moop_expression_read_count_dir($_hd);
ok($_h['samples'] === ['s.2', 's.10'], 'htseq dir: samples in natural order (s.2 before s.10), extension dropped');
ok($_h['rows']['g2'] === [null, 1.0] && !isset($_h['rows']['__no_feature']), 'htseq dir: a gene missing from one file is null there; __ counters are not genes');
ok($_h['special']['s.2'] === ['__no_feature' => 7, '__ambiguous' => 2], 'htseq dir: __ counters kept for the QC report');

// --- the gene page's words: detected yes/no + off/low/medium/high
$_lv = fn($m, $t = 1.0) => moop_expression_level(['call' => $m === null ? 'unknown' : ($m >= $t ? 'yes' : 'no'), 'top_mean' => $m, 'threshold' => $t]);
ok($_lv(0.5) === 'off' && $_lv(1.0) === 'low' && $_lv(9.99) === 'low', 'level: below 1 TPM is off; 1 to <10 is low');
ok($_lv(10.0) === 'medium' && $_lv(99.9) === 'medium' && $_lv(100.0) === 'high', 'level: 10 to <100 is medium; 100 and up is high');
ok($_lv(2.0, 3.0) === 'off', 'level: "off" follows the experiment\'s own threshold, so it can never disagree with "not detected"');
ok($_lv(null) === 'no data', 'level: a gene missing from an experiment is "no data", never "off"');

$_sx = fn($label) => ['label' => $label, 'meta' => ['citation' => 'PMID:1'], 'detect_threshold' => 1.0,
    'samples' => [['name' => 'A', 'group_name' => 'a'], ['name' => 'B', 'group_name' => 'b']]];
$_gs = moop_expression_summarize([$_sx('hi'), $_sx('off'), $_sx('none')], [0 => [2.0, 250.0], 1 => [0.1, 0.2]]);
ok(array_column($_gs['experiments'], 'level') === ['high', 'off', 'no data'], 'summary: one word per experiment, in order');
ok($_gs['detected'] === 1 && $_gs['with_data'] === 2, 'summary: "detected in 1 of 2" — an experiment without this gene is not counted as a "no"');
ok($_gs['experiments'][0]['top_group'] === 'b' && $_gs['experiments'][0]['top_mean'] === 250.0, 'summary: names the highest condition');

// --- the gene page's "Download TSV"
$_tx3 = ['label' => 'Dev', 'slug' => 'a_dev', 'samples' => [
    ['name' => 'S2', 'group_name' => 'early', 'replicate' => '1'],
    ['name' => 'S1', 'group_name' => "la\tte", 'replicate' => '']]];
$_tsv = explode("\n", rtrim(moop_expression_gene_tsv('g1', [3 => $_tx3, 4 => $_tx3], [3 => [2.5, null]]), "\n"));
ok($_tsv[0] === "gene\texperiment\texperiment_id\tsample\tcondition\treplicate\tvalue\tunit", 'tsv: header names every column');
ok(($_tsv[1] ?? '') === "g1\tDev\ta_dev\tS2\tearly\t1\t2.5\tTPM", 'tsv: one row per sample, in display order, with the raw value and its unit (TPM when unstated)');
ok(($_tsv[2] ?? '') === "g1\tDev\ta_dev\tS1\tla te\t\tNA\tTPM", 'tsv: a missing value is NA, never 0; a tab inside a name cannot shift columns');
$_tsv = explode("\n", rtrim(moop_expression_gene_tsv('g1', [0 => ['unit' => 'CPM'] + $_tx3], [0 => [7.0, 1.0]]), "\n"));
ok(substr($_tsv[1] ?? '', -4) === "\tCPM", 'tsv: a single-cell experiment\'s rows say CPM');

// --- units: experiment.json "unit", shown beside every value
ok(moop_expression_unit([]) === 'TPM' && moop_expression_unit(['unit' => '']) === 'TPM', 'unit: absent or empty reads as TPM, the spec default');
ok(moop_expression_unit(['unit' => 'CPM']) === 'CPM' && moop_expression_unit(['unit' => ' FPKM ']) === 'FPKM', 'unit: the stated unit, trimmed');
ok(moop_expression_unit(['unit' => '<b>x</b>']) === 'TPM' && moop_expression_unit(['unit' => 'counts per million reads mapped']) === 'TPM',
   'unit: anything that is not a short unit name is not printed into the page');
ok(moop_expression_units_label([['unit' => 'TPM'], ['unit' => 'CPM'], ['unit' => 'TPM']]) === 'TPM / CPM'
   && moop_expression_units_label([['unit' => 'TPM']]) === 'TPM', 'unit: a mixed selection names each unit once');
ok(in_array('unit', MOOP_EXPRESSION_OVERRIDABLE, true), 'unit: correctable by an override, like the other experiment fields');
$_gu = moop_expression_summarize([['unit' => 'CPM'] + $_sx('sc')], [0 => [2.0, 5.0]]);
ok(($_gu['experiments'][0]['unit'] ?? null) === 'CPM', 'unit: the gene page summary carries each experiment\'s unit');
ok(count($_tsv) === 3, 'tsv: an experiment without this gene contributes no rows');

// --- bars on the gene page
$_grp = fn(array $m) => array_map(fn($k, $v) => ['group' => $k, 'mean' => $v, 'n' => 1], array_keys($m), $m);
$_sv = moop_expression_sparkline_svg($_grp(['zygote' => 1.2, 'gastrula' => 265.0, 'polyp' => 0.0]), 'high');
ok(substr_count($_sv, 'class="expr-bar"') === 3 && strpos($_sv, '<polyline') === false && strpos($_sv, '<path') === false,
   'bars: one bar per condition, never a line');
ok(strpos($_sv, '<title>gastrula: 265 TPM</title>') !== false && strpos($_sv, '<title>zygote: 1.2 TPM</title>') !== false, 'bars: each bar\'s hover names its condition and value');
ok(preg_match('/<title>polyp: 0\.0 TPM<\/title>.*?height="1"\/>/', $_sv) === 1, 'bars: a measured zero is a hairline, not missing');
ok(moop_expression_sparkline_svg($_grp(['a' => 0.1, 'b' => 0.5]), 'off') === '' && moop_expression_sparkline_svg([], 'no data') === '',
   'bars: an "off" gene draws nothing — 0.1 vs 0.5 TPM is not a pattern');
$_lo = moop_expression_sparkline_svg($_grp(['a' => 2.0, 'b' => 5.0]), 'low');
ok(preg_match('/<title>b: 5\.0 TPM<\/title>.*?height="11"\/>/', $_lo) === 1, 'bars: scale never below 10 TPM, so a low gene\'s tallest bar is half height, not full');
$_nd = moop_expression_sparkline_svg([['group' => 'x', 'mean' => null, 'n' => 0], ['group' => 'y', 'mean' => 50.0, 'n' => 1]], 'medium');
ok(strpos($_nd, 'expr-bar-none') !== false && strpos($_nd, '<title>x: no data</title>') !== false, 'bars: an unmeasured condition is marked "no data", distinct from zero');
ok(strpos(moop_expression_sparkline_svg($_grp(['<b>&' => 50.0]), 'medium'), '&lt;b&gt;&amp;: 50 TPM') !== false, 'bars: condition names are escaped once');

// --- reading bundles straight from disk (notes/EXPRESSION_BUNDLE_SPEC.md) + overrides
$_old_log = ini_set('error_log', '/dev/null');   // skipped experiments are logged; keep test output clean
$_root = "$_xd/bundles"; @mkdir($_root);
$_mk = function (string $slug, array $files) use ($_root) {
    @mkdir("$_root/$slug");
    foreach ($files as $n => $body) file_put_contents("$_root/$slug/$n", $body);
};
// tpm.tsv columns deliberately in a different order from samples.tsv
$_mk('a_dev', ['experiment.json' => '{"label":"Dev","access_level":"PUBLIC","citation":"PMID:1"}',
    'samples.tsv' => "sample_id\tgroup\treplicate\nS2\tearly\t1\nS1\tlate\t1\n",
    'tpm.tsv'     => "gene_id\tS1\tS2\ng1\t250\t2\ng2\tNA\t0\n"]);
$_mk('b_secret', ['experiment.json' => '{"label":"Secret","access_level":"COLLABORATOR","assay":"bulk_rna"}',
    'samples.tsv' => "sample_id\tgroup\nS1\tx\n", 'tpm.tsv' => "gene_id\tS1\ng1\t5\n"]);
$_mk('c_broken', ['experiment.json' => '{"label":"Broken","access_level":"PUBLIC"}',
    'samples.tsv' => "sample_id\tgroup\nS1\tx\nS9\ty\n", 'tpm.tsv' => "gene_id\tS1\ng1\t5\n"]);
$_mk('d_nolabel', ['experiment.json' => '{"access_level":"PUBLIC"}',
    'samples.tsv' => "sample_id\tgroup\nS1\tx\n", 'tpm.tsv' => "gene_id\tS1\ng1\t5\n"]);
$_mk('e_half', ['experiment.json' => '{"label":"Half-copied","access_level":"PUBLIC"}']);
$_mk('f_mixedcase', ['experiment.json' => '{"label":"Mixed","access_level":"Public"}',
    'samples.tsv' => "sample_id\tgroup\nS1\tx\n", 'tpm.tsv' => "gene_id\tS1\ng1\t5\n"]);
$_mk('g_typo', ['experiment.json' => '{"label":"Typo","access_level":"COLABORATOR"}',
    'samples.tsv' => "sample_id\tgroup\nS1\tx\n", 'tpm.tsv' => "gene_id\tS1\ng1\t5\n"]);
$_p = 'Org/Asm/GS/';

$_be = moop_expression_bundle_experiments($_root, $_p, 'PUBLIC', []);
ok(array_column($_be, 'slug') === ['a_dev', 'f_mixedcase'], 'bundles: PUBLIC sees the public, well-formed experiments ("Public" matches case-insensitively) — broken, unlabelled and half-copied ones are skipped');
ok(array_column(moop_expression_bundle_experiments($_root, $_p, 'COLLABORATOR', []), 'slug') === ['a_dev', 'b_secret', 'f_mixedcase'], 'bundles: COLLABORATOR also sees the COLLABORATOR one');
ok(!in_array('g_typo', array_column(moop_expression_bundle_experiments($_root, $_p, 'IP_IN_RANGE', []), 'slug'), true)
   && in_array('g_typo', array_column(moop_expression_bundle_experiments($_root, $_p, 'ADMIN', []), 'slug'), true), 'bundles: a misspelled access level fails closed (ADMIN only)');
$_bv = moop_expression_bundle_values($_be[0], ['g1', 'g2', 'g9']);
ok($_bv['g1'] === [2.0, 250.0], 'bundles: values come back in samples.tsv order (S2, S1), not tpm.tsv column order');
ok($_bv['g2'] === [0.0, null] && !isset($_bv['g9']), 'bundles: NA is null, 0 is 0, an absent gene is absent');

$_ov = [$_p . 'a_dev' => ['access_level' => 'COLLABORATOR']];
ok(array_column(moop_expression_bundle_experiments($_root, $_p, 'PUBLIC', $_ov), 'slug') === ['f_mixedcase'], 'override: setting a PUBLIC experiment to COLLABORATOR hides it from PUBLIC');
$_ov2 = [$_p . 'b_secret' => ['access_level' => 'PUBLIC', 'label' => 'Renamed', 'assay' => 'overridden']];
$_be2 = moop_expression_bundle_experiments($_root, $_p, 'PUBLIC', $_ov2);
ok(array_column($_be2, 'label') === ['Dev', 'Renamed', 'Mixed'], 'override: label and access_level replace the bundle\'s values');
ok($_be2[1]['meta']['assay'] === 'bulk_rna', 'override: a field outside the allowlist (assay) changes nothing');
ok(moop_expression_bundle_experiments($_root, $_p, 'PUBLIC', [$_p . 'a_dev' => ['access_level' => '', 'label' => null]])[0]['label'] === 'Dev',
   'override: an empty value keeps the default (the config_editable.json convention)');
ok(moop_expression_bundle_experiments($_root, $_p, 'ADMIN', null) === [], 'override: an unreadable overrides file shows NOTHING — fail closed, never back to the bundle default');
file_put_contents("$_xd/ov_bad.json", '{"not json');
ok(moop_expression_load_overrides("$_xd/ov_bad.json") === null && moop_expression_load_overrides("$_xd/none.json") === [],
   'override: a corrupt file loads as null (fail closed); a missing file is simply no overrides');
$_pr = moop_expression_override_problems([$_p . 'gone' => [], $_p . 'a_dev' => ['colour' => 'red'], 'Other/X/Y/z' => []], $_p, ['a_dev']);
ok(count($_pr) === 2 && strpos($_pr[0], 'no such experiment') !== false && strpos($_pr[1], "'colour'") !== false,
   'override: the checker reports a leftover entry and a field that cannot be overridden; other gene sets are not its business');

$_sum = moop_expression_summarize([0 => $_be[0]], [0 => $_bv['g1']]);
ok($_sum['experiments'][0]['level'] === 'high' && $_sum['experiments'][0]['top_group'] === 'late', 'bundles: the gene page summary works the same on bundle data');
ini_set('error_log', $_old_log === false ? '' : $_old_log);
exec('rm -rf ' . escapeshellarg($_root));

array_map('unlink', glob("$_hd/*")); @rmdir($_hd);
array_map('unlink', glob("$_xd/*")); @rmdir($_xd);

// ----------------------------------------------------------------------------
group('Sequence type -> BLAST dbtype is decided by config KEY, not filename');
// The protein FASTA used to be recognised by strpos($filename, 'protein'). A renamed
// pattern then indexed protein as nucleotide -- silently. Prove the key decides.
require_once "$BASE/lib/blast_functions.php";
ok(sequence_type_for_filename('protein.aa.fa') === 'protein', 'protein pattern maps to the protein type');
ok(sequence_type_for_filename('geneset.json') === null,       'a non-sequence file maps to no type');
ok(sequence_type_blast_dbtype('protein') === 'prot',          'protein type -> prot');
ok(sequence_type_blast_dbtype('transcript') === 'nucl',       'transcript type -> nucl');

$_cm   = ConfigManager::getInstance();
$_prop = new ReflectionProperty(ConfigManager::class, 'config');
$_prop->setAccessible(true);
$_saved = $_prop->getValue($_cm);
$_renamed = $_saved;
$_renamed['sequence_types']['protein']['pattern'] = 'peptides.fa';
$_prop->setValue($_cm, $_renamed);
ok(sequence_type_blast_dbtype((string)sequence_type_for_filename('peptides.fa')) === 'prot',
   'renamed protein pattern (no "protein" in the name) is still indexed as prot');
$_bd = sys_get_temp_dir() . '/moop_blast_smoke_' . getmypid();
@mkdir("$_bd/Org/Asm/GS", 0700, true);
file_put_contents("$_bd/Org/Asm/GS/notes.txt", "not a fasta\n");
$_r = generateBlastIndexes('Org', 'Asm', 'notes.txt', $_bd, 'GS');
ok(str_starts_with($_r['message'], 'Not a configured sequence FASTA'),
   'generateBlastIndexes refuses a file that is not a configured sequence FASTA');
exec('rm -rf ' . escapeshellarg($_bd));
$_prop->setValue($_cm, $_saved);

// ----------------------------------------------------------------------------
group('Data directories the web builds into need SGID + the web group');
// performPermissionCheck() only checked SGID/group in its 'writable' branch, and the
// organism tree is 'data' -- so only the Organism Setup Checklist's private copy of the
// rule ever enforced it. Hermetic: a temp dir, its own group standing in for the web group.
require_once "$BASE/lib/permission_check.php";
$_pd = sys_get_temp_dir() . '/moop_perm_smoke_' . getmypid();
@mkdir($_pd, 0700, true);
chmod($_pd, 0775);
// Same lookup the checker uses: posix when present, else `stat` (CLI PHP here has no posix).
$_grp = function_exists('posix_getgrgid') ? (posix_getgrgid(filegroup($_pd))['name'] ?? '')
                                          : trim((string)shell_exec('stat -c %G ' . escapeshellarg($_pd)));
foreach (['Organism Directory', 'Assembly Subdirectory: O/A', 'Gene Set Subdirectory: O/A/G'] as $_name) {
    clearstatcache();
    $_c = performPermissionCheck($_pd, ['name' => $_name, 'type' => 'directory', 'required_group' => $_grp], $_grp);
    ok((bool)preg_grep('/Missing the SGID bit/', $_c['issues']), "$_name: missing SGID is reported");
    ok(in_array("sudo find '$_pd' -type d -exec chmod g+rwxs {} +", moop_permission_fix_commands($_c, ''), true),
       "$_name: fix touches directories only (find -type d), never chmod -R");
}
$_c = performPermissionCheck($_pd, ['name' => 'Organism Directory', 'type' => 'directory', 'required_group' => 'no-such-group-xyz'], 'no-such-group-xyz');
ok((bool)preg_grep('/should be no-such-group-xyz/', $_c['issues']), 'wrong group is reported');
chmod($_pd, 02775); clearstatcache();
$_c = performPermissionCheck($_pd, ['name' => 'Organism Directory', 'type' => 'directory', 'required_group' => $_grp], $_grp);
ok(empty($_c['issues']), 'SGID + correct group: clean');
$_c = performPermissionCheck($_pd, ['name' => 'Some Other Data Directory', 'type' => 'directory'], $_grp);
ok(!preg_grep('/SGID/', $_c['issues'] ?? []), 'non-build data directories are not held to SGID');
@rmdir($_pd);

// ----------------------------------------------------------------------------
group('Organism health lists shared by the dashboard and the setup checklist');
// The checklist kept private copies of these and disagreed with the dashboard: its
// "ungrouped" passed an organism if ANY entry had a group. One ungrouped assembly is
// invisible to users, so the rule is per ASSEMBLY.
require_once "$BASE/lib/functions_data.php";
$_cache = [
    'Allgrouped' => ['assemblies' => ['A1', 'A2'], 'overall_status' => ['checks' => ['in_taxonomy_tree' => true]]],
    'Halfgrouped'=> ['assemblies' => ['B1', 'B2'], 'overall_status' => ['checks' => ['in_taxonomy_tree' => false]]],
    'Nogroups'   => ['assemblies' => ['C1'],       'overall_status' => ['checks' => []]],
];
$_groups = [
    ['organism' => 'Allgrouped',  'assembly' => 'A1', 'groups' => ['G']],
    ['organism' => 'Allgrouped',  'assembly' => 'A2', 'groups' => ['G']],
    ['organism' => 'Halfgrouped', 'assembly' => 'B1', 'groups' => ['G']],
    ['organism' => 'Halfgrouped', 'assembly' => 'B2', 'groups' => []],
];
ok(getUngroupedOrganisms($_cache, $_groups) === ['Halfgrouped', 'Nogroups'],
   'ungrouped is per assembly: one ungrouped assembly makes the organism ungrouped');
ok(getOrganismsNotInTree($_cache) === ['Halfgrouped'],
   'not-in-tree reads the cache flag; a missing flag is not reported as absent');

// ----------------------------------------------------------------------------
group('Missing FASTA is reported per gene set, by type');
// Manage Organisms said only "Missing gene-set FASTA" and tinted all 24 transcriptome-only
// assemblies as missing data (genome.fa was in the same list). assembly_fasta_gaps() names
// the missing type per gene set; genome is the assembly's, and a non-gene-set dir (Schmidtea's
// empty *_orig) is not a gene set missing everything.
require_once "$BASE/lib/functions_filesystem.php";
$_cm   = ConfigManager::getInstance();
$_prop = new ReflectionProperty(ConfigManager::class, 'config');
$_prop->setAccessible(true);
$_saved = $_prop->getValue($_cm);
$_st = ['protein'    => ['pattern' => 'protein.aa.fa',    'label' => 'Protein'],
        'transcript' => ['pattern' => 'transcript.nt.fa', 'label' => 'mRNA'],
        'cds'        => ['pattern' => 'cds.nt.fa',        'label' => 'CDS'],
        'genome'     => ['pattern' => 'genome.fa',        'label' => 'Genome']];
$_prop->setValue($_cm, array_merge($_saved, ['sequence_types' => $_st]));
$_fd = sys_get_temp_dir() . '/moop_fastagaps_' . getmypid();
@mkdir("$_fd/Full/GS", 0700, true);
@mkdir("$_fd/Full/Partial", 0700, true);
@mkdir("$_fd/Empty/old_orig", 0700, true);
foreach (['protein.aa.fa', 'transcript.nt.fa', 'cds.nt.fa'] as $_f) touch("$_fd/Full/GS/$_f");
touch("$_fd/Full/Partial/protein.aa.fa");
touch("$_fd/Full/genome.fa");
$_g = assembly_fasta_gaps("$_fd/Full", $_st);
ok($_g['has_genome'] === true, 'genome.fa at the assembly level is found');
ok($_g['gene_sets'] === ['GS' => [], 'Partial' => ['transcript', 'cds']],
   'each gene set lists exactly the types it lacks; a complete sibling does not hide them');
$_g = assembly_fasta_gaps("$_fd/Empty", $_st);
ok($_g['has_genome'] === false && $_g['gene_sets'] === [],
   'an assembly with only a non-FASTA dir has NO gene sets (not one missing everything)');
exec('rm -rf ' . escapeshellarg($_fd));
$_prop->setValue($_cm, $_saved);

// ----------------------------------------------------------------------------
group('A partial gene set grades Incomplete; issue colours have one source');
// Health said "Complete" beside a gene set with no CDS: no check asked per gene set.
// gene_sets_complete does, and it is NOT critical -- the data that is there still works.
$_asm = fn(array $gene_sets, bool $genome) => ['gaps' => ['has_genome' => $genome, 'gene_sets' => $gene_sets], 'fasta_files' => ['protein' => ['found' => true]]];
$_org = fn(array $asms) => [
    'path' => '/nonexistent', 'assemblies' => array_keys($asms), 'has_db' => false,
    'fasta_validation' => ['assemblies' => $asms],
    'blast_validation' => array_map(fn() => ['databases' => []], $asms),
    'fai_validation'   => array_map(fn() => ['genome_fa_exists' => false, 'fai_exists' => false], $asms),
    'in_taxonomy_tree' => true, 'json_validation' => [],
];
$_st = fn(array $asms) => getOrganismOverallStatus('X', $_org($asms), [], '', [])['checks']['gene_sets_complete'];
ok($_st(['A' => $_asm(['GS' => []], false)]) === true,
   'a complete gene set passes -- with no genome.fa (transcriptome-only is valid)');
ok($_st(['A' => $_asm(['GS' => []], true), 'B' => $_asm(['GS2' => ['cds']], false)]) === false,
   'one gene set lacking CDS fails the check, even beside a complete assembly');
ok($_st(['A' => $_asm(['GS' => []], true), 'B' => $_asm([], false)]) === false,
   'an assembly with no gene set fails the check');

// The checks, their issue tags and their checklist labels are ONE list (they were three, and
// a check missing from one silently did not show there).
$_cd = moop_organism_check_defs();
ok(array_keys(getOrganismOverallStatus('X', $_org(['A' => $_asm(['GS' => []], true)]), [], '', [])['checks']) === array_keys($_cd),
   'getOrganismOverallStatus() computes exactly the defined checks');
ok(!array_diff(array_column($_cd, 'issue'), array_keys(moop_organism_issue_defs())), 'every check raises an issue that has a label and severity');
// The health card decides "any issue" from the counts alone, so every count needs its alert.
$_card = file_get_contents("$BASE/admin/pages/_data_health_card.php");
ok(!array_filter(array_keys(moop_data_health_empty()['health_alerts']), fn($k) => strpos($_card, "\$health_alerts['$k']") === false),
   'every health-alert count is shown by the health card');
// Both pages that show the card must hand it the health data whole -- dropping that once left
// Manage Organisms with no health card at all, and no suite renders a page to notice.
foreach (['admin/admin.php', 'admin/manage_organisms.php'] as $_pg) {
    ok(strpos(file_get_contents("$BASE/$_pg"), '] + $_health;') !== false, "$_pg passes the health data to its page");
}
ok(moop_organism_severity($_sev(['gene_sets_complete' => false])) === 'incomplete',
   'a gene-set gap grades Incomplete (amber), never Critical');

$_defs = moop_organism_issue_defs();
ok(!array_filter($_defs, fn($d) => !in_array($d['severity'], ['critical', 'incomplete', 'info'], true)),
   'every issue has a known severity');
ok(!array_diff(array_values(moop_db_content_issue_tags()), array_keys($_defs)),
   'every database-content tag has an issue definition (else its badge vanishes)');
ok($_defs['groups']['severity'] === 'incomplete' && $_defs['no-genome']['severity'] === 'info'
   && $_defs['db-content']['severity'] === 'critical',
   'groups is amber, no-genome grey, an empty / unlinked DB red');
ok(moop_severity_color('critical') === 'danger' && moop_severity_color('incomplete') === 'warning',
   'severity -> colour: red is critical, amber incomplete');

// ----------------------------------------------------------------------------
group('Browser tab titles: "Page — Site" on every page');
// Three separators and a missing site name across ~30 hand-written titles; the layout
// now adds the suffix once, and tolerates a caller that still adds it.
require_once "$BASE/includes/layout.php";
ok(moop_tab_title('Primer BLAST', 'S M') === 'Primer BLAST — S M', 'a bare page name gets the site suffix');
ok(moop_tab_title('BLAST Search - S M', 'S M') === 'BLAST Search — S M', 'an old "-" suffix is normalised, not doubled');
ok(moop_tab_title('Search — S M', 'S M') === 'Search — S M', 'an em-dash suffix is not doubled');
ok(moop_tab_title('A &amp; B', 'S M') === 'A & B — S M', 'pre-escaped text is decoded (the layout escapes once)');
ok(moop_tab_title('', 'S M') === 'S M', 'no page name: the site name alone');

// ----------------------------------------------------------------------------
group('Missing genes.gff is flagged only where a genome exists');
// All 20 gene sets the checklist listed sat on transcriptome-only assemblies, where a GFF
// cannot exist. A gene set on an assembly WITH a genome must still be caught.
require_once "$BASE/lib/organism_checklist.php";
$_gd = sys_get_temp_dir() . '/moop_gff_' . getmypid();
$_st = ['protein' => ['pattern' => 'protein.aa.fa', 'label' => 'Protein'], 'genome' => ['pattern' => 'genome.fa', 'label' => 'Genome']];
@mkdir("$_gd/Org/WithGenome/GS1", 0700, true);
@mkdir("$_gd/Org/Transcriptome/GS2", 0700, true);
touch("$_gd/Org/WithGenome/genome.fa");
touch("$_gd/Org/WithGenome/GS1/protein.aa.fa");
touch("$_gd/Org/Transcriptome/GS2/protein.aa.fa");
$_miss = array_column(organism_checklist_gene_sets_missing_gff($_gd, ['Org'], $_st), 'path');
ok($_miss === ['Org/WithGenome/GS1'], 'flags the gene set on a genome assembly, not the transcriptome-only one');
exec('rm -rf ' . escapeshellarg($_gd));

// ----------------------------------------------------------------------------
group('home page examples: ranked by the agreed rules, never hard-coded');
require_once "$BASE/lib/home_examples.php";
$_c = fn(array $o) => $o + ['organism' => 'Z', 'assembly' => 'a', 'gene_set' => 'g', 'complete' => true,
    'gs_full' => true, 'browse' => true, 'gene_tier' => 3, 'common' => 'x', 'group_ok' => true];
ok(moop_home_examples_cmp($_c(['organism' => 'B']), $_c(['organism' => 'A', 'complete' => false])) < 0,
   'a Complete organism beats an incomplete one, whatever the name');
ok(moop_home_examples_cmp($_c(['gene_tier' => 3, 'common' => '']), $_c(['gene_tier' => 1])) < 0,
   'a rich gene (rule 4) outranks a common name (rule 5): least important rules relax first');
ok(moop_home_examples_cmp($_c(['organism' => 'A']), $_c(['organism' => 'B'])) < 0,
   'ties go alphabetically, so the examples are stable');
// The admin override: anything the audience cannot open is dropped; an empty result
// falls back to the automatic set rather than showing nothing.
$_auto = ['organisms' => [['text' => 'Auto org', 'kind' => 'organism']], 'genes' => []];
$_ent  = [['organism' => 'Pub_Org', 'assembly' => 'GCA_1', 'gene_set' => 'gs1', 'groups' => ['Fish']]];
$_r = moop_home_examples_apply_override($_auto, ['organisms' => ['Fish', 'Secret Bat', 'pub org']], $_ent);
ok(array_column($_r['organisms'], 'text') === ['Fish', 'pub org'],
   'override keeps what the audience can open, drops the rest');
$_r = moop_home_examples_apply_override($_auto, ['organisms' => ['Secret Bat']], $_ent);
ok(array_column($_r['organisms'], 'text') === ['Auto org'], 'an override with nothing openable falls back to automatic');

// ----------------------------------------------------------------------------
group('gene set page: annotation counts are the GENE SET\'s, never the organism\'s');
$_gd = sys_get_temp_dir() . '/moop_gsac_' . getmypid();
@mkdir("$_gd/OrgB", 0777, true);
$_cfg = ConfigManager::getInstance();
$_rp = new ReflectionProperty($_cfg, 'config'); $_rp->setAccessible(true);
$_saved = $_rp->getValue($_cfg);
$_c = $_saved; $_c['organism_data'] = $_gd; $_c['cache_path'] = $_gd; $_rp->setValue($_cfg, $_c);
ok(moop_load_gene_set_annotation_counts('OrgB', 'A1', 'gs1') === null, 'no cache yet -> null (the page leaves the summary out)');
touch("$_gd/OrgB/organism.sqlite", time() - 100);
// A pre-2026-10-22 file: term counts, no 'count' marker. Must read as "not built", never as
// gene counts -- that is the whole reason the file was renamed and marked.
file_put_contents(moop_gene_set_annotation_counts_file('OrgB'), json_encode([
    'A1/gs1' => ['Homologs' => [['name' => 'UniProt', 'count' => 5]]],
]));
ok(moop_load_gene_set_annotation_counts('OrgB', 'A1', 'gs1') === null, 'a file without count=genes is refused');
file_put_contents(moop_gene_set_annotation_counts_file('OrgB'), json_encode([
    'count'   => 'genes',
    'sources' => [
        'A1/gs1' => ['Homologs' => [['name' => 'UniProt', 'count' => 5], ['name' => 'Empty', 'count' => 0]]],
        'A2/gs1' => ['Homologs' => [['name' => 'UniProt', 'count' => 9]]],
        'A1/gs2' => [],
    ],
    'type_genes' => ['A1/gs1' => ['Homologs' => 4, 'Domains' => 0], 'A2/gs1' => ['Homologs' => 9], 'A1/gs2' => []],
]));
ok(moop_load_gene_set_annotation_type_genes('OrgB', 'A1', 'gs1') === ['Homologs' => 4],
   'per-type gene totals read back, a zero type dropped');
ok(moop_load_gene_set_annotation_type_genes('OrgB', 'A9', 'gs1') === null, 'an unknown gene set -> null');
$_r = moop_load_gene_set_annotation_counts('OrgB', 'A1', 'gs1');
ok($_r === ['Homologs' => [['name' => 'UniProt', 'count' => 5]]], "this gene set's counts, an empty source dropped");
ok((moop_load_gene_set_annotation_counts('OrgB', 'A2', 'gs1')['Homologs'][0]['count'] ?? null) === 9,
   'two gene sets with the SAME name on different assemblies stay apart');
ok(moop_load_gene_set_annotation_counts('OrgB', 'A1', 'gs2') === [], 'a gene set with no annotations is empty, not "not built"');
touch("$_gd/OrgB/organism.sqlite", time() + 100);
ok(moop_load_gene_set_annotation_counts('OrgB', 'A1', 'gs1') === null,
   'database newer than the cache (a reload) -> null, never the old numbers');
$_rp->setValue($_cfg, $_saved);
array_map('unlink', glob("$_gd/OrgB/*")); @rmdir("$_gd/OrgB"); @rmdir($_gd);
// The grouping both builders share: config order first, counts as integers.
$_g = moop_group_annotation_sources([['name' => 'b', 'type' => 'Zeta', 'count' => '3'], ['name' => 'a', 'type' => 'Zeta', 'count' => '1']]);
ok($_g === ['Zeta' => [['name' => 'b', 'count' => 3], ['name' => 'a', 'count' => 1]]], 'rows grouped by type, counts as integers, row order kept');
// A viewer who may open only some gene sets gets THEIR sum, never the organism's figure.
$_s = moop_sum_annotation_source_counts([
    ['Homologs' => [['name' => 'UniProt', 'count' => 5], ['name' => '123', 'count' => 1]]],
    ['Homologs' => [['name' => 'UniProt', 'count' => 2], ['name' => 'Pfam', 'count' => 9]], 'GO' => [['name' => 'GO', 'count' => 4]]],
]);
ok($_s === ['Homologs' => [['name' => 'Pfam', 'count' => 9], ['name' => 'UniProt', 'count' => 7], ['name' => '123', 'count' => 1]],
            'GO' => [['name' => 'GO', 'count' => 4]]],
   'gene set counts summed per source, highest first, a numeric name stays a string');
ok(moop_sum_annotation_source_counts([]) === [], 'no gene sets -> nothing');
ok(moop_sum_type_genes([['Homologs' => 4, 'Domains' => 2], ['Homologs' => 9]]) === ['Homologs' => 13, 'Domains' => 2],
   'per-type gene totals add across gene sets (a gene belongs to one gene set)');

// ----------------------------------------------------------------------------
group('OS security updates: the report is parsed, never mistaken for "all clear"');
require_once "$BASE/lib/housekeeping.php";
$_dnf = "RHSA-2026:1 Important/Sec. nginx-2:1.20.1-28.el9_8.5.x86_64\n"
      . "RHSA-2026:1 Important/Sec. nginx-core-2:1.20.1-28.el9_8.5.x86_64\n"
      . "RHSA-2026:2 Critical/Sec.  webkit2gtk3-jsc-2.54.1-1.el9_8.x86_64\n"
      . "RHSA-2026:3 Low/Sec.       kernel-5.14.0-687.29.1.el9_8.x86_64\n"
      . "RHSA-2026:4 Important/Sec. kernel-core-5.14.0-687.30.1.el9_8.x86_64\n";
$_r = moop_parse_security_report("# generated: " . date('c') . "\n# running_kernel: k1\n# manager: dnf\n# newest_kernel: k1\n$_dnf# exit: 0\n");
ok(count($_r['advisories']) === 4 && $_r['by_severity'] === ['Critical' => 1, 'Important' => 2, 'Low' => 1],
   'counts ADVISORIES, not package lines, worst first');
ok(array_keys($_r['watched']) === ['nginx', 'Linux kernel'], 'site packages grouped by product; a desktop library is not one');
ok($_r['watched']['Linux kernel']['severity'] === 'Important', "a product is reported at its WORST advisory's severity");
ok($_r['reboot'] === false && $_r['stale'] === false && $_r['error'] === null, 'running the newest kernel, fresh report, no error');
$_r = moop_parse_security_report("# generated: 2020-01-01T00:00:00+00:00\n# running_kernel: k1\n# manager: dnf\n# newest_kernel: k2\n# exit: 0\n");
ok($_r['stale'] === true && $_r['reboot'] === true, 'an old report is stale; a newer installed kernel means a reboot is pending');
$_r = moop_parse_security_report("# generated: " . date('c') . "\n# manager: dnf\nError: rpmdb open failed\n# exit: 1\n");
ok($_r['error'] !== null && $_r['advisories'] === [] && $_r['command'] === null,
   'a failed package manager is an ERROR, never zero updates (the SELinux case)');
$_r = moop_parse_apt_security("Inst nginx [1.22.1-9] (1.22.1-9+deb12u1 Debian-Security:12/stable-security [amd64])\n"
      . "Inst vim [2] (3 Debian:12.5/stable [amd64])\nInst libssl3 [3.0.1] (3.0.2 Debian-Security:12/stable-security [amd64])");
ok($_r['packages'] === 2 && array_keys($_r['watched']) === ['nginx', 'OpenSSL'], 'apt: only -security origins count; libssl3 is OpenSSL');

// ----------------------------------------------------------------------------
group('third-party libraries: the version is only trusted when the file matches its checksum');
$_ld = sys_get_temp_dir() . '/moop_libaudit_' . getmypid();
@mkdir("$_ld/js", 0777, true); @mkdir("$_ld/vendor/composer", 0777, true);
file_put_contents("$_ld/js/a.js", 'A');
file_put_contents("$_ld/js/b.js", 'tampered');
$_rows = [
    ['js/a.js', 'https://x/lib-1.2.3/a.js', hash('sha256', 'A'), 'none', 'npm:lib@1.2.3'],
    ['js/b.js', 'https://x/other-2.0.0/b.js', hash('sha256', 'B'), 'none', 'npm:other@2.0.0'],
    ['js/c.js', 'https://x/third-9.9.9/c.js', hash('sha256', 'C'), 'none', 'npm:third@1.0.0'],
];
file_put_contents("$_ld/vendor/composer/installed.json", json_encode([
    'packages' => [['name' => 'firebase/php-jwt', 'version' => 'v7.0.2'], ['name' => 'phpcompatibility/php-compatibility', 'version' => '9.3.5']],
    'dev-package-names' => ['phpcompatibility/php-compatibility'],
]));
$_inv = [];
foreach (moop_library_inventory($_ld, $_rows) as $_p) $_inv["{$_p['ecosystem']}:{$_p['name']}@{$_p['version']}"] = $_p['problems'];
ok(($_inv['npm:lib@1.2.3'] ?? null) === [], 'a file matching its checksum, URL naming its version: no problem');
ok(count($_inv['npm:other@2.0.0'] ?? []) === 1 && strpos($_inv['npm:other@2.0.0'][0], 'checksum') !== false,
   'a changed file is reported unverified, not assumed to be the listed version');
ok(count($_inv['npm:third@1.0.0'] ?? []) === 2, 'a URL that does not name the version, and a missing file, are both reported');
ok(isset($_inv['Packagist:firebase/php-jwt@7.0.2']) && !isset($_inv['Packagist:phpcompatibility/php-compatibility@9.3.5']),
   'Composer runtime packages are checked ("v" dropped); dev-only tools are not -- they never ship');
array_map('unlink', glob("$_ld/js/*")); @unlink("$_ld/vendor/composer/installed.json");
@rmdir("$_ld/js"); @rmdir("$_ld/vendor/composer"); @rmdir("$_ld/vendor"); @rmdir($_ld);
// Unlisted third-party files: a fixture proves the scan finds one, the real tree must have none.
$_ud = sys_get_temp_dir() . '/moop_unlisted_' . getmypid();
@mkdir("$_ud/js/vendor", 0777, true); @mkdir("$_ud/css", 0777, true);
file_put_contents("$_ud/js/vendor/listed.js", 'x'); file_put_contents("$_ud/js/vendor/new-lib.js", 'x');
file_put_contents("$_ud/css/other.min.css", 'x'); file_put_contents("$_ud/css/ours.css", 'x');
ok(moop_unlisted_vendor_files($_ud, [['js/vendor/listed.js', '', '', '', '']]) === ['css/other.min.css', 'js/vendor/new-lib.js'],
   'a vendor-folder file or a stray .min.css that is not listed is found; our own .css is not');
array_map('unlink', array_merge(glob("$_ud/js/vendor/*"), glob("$_ud/css/*"))); @rmdir("$_ud/js/vendor"); @rmdir("$_ud/js"); @rmdir("$_ud/css"); @rmdir($_ud);
ok(moop_unlisted_vendor_files($BASE) === [],
   'every third-party file in this checkout is in scripts/vendor_assets.txt (add a line when adding a library)');
ok(moop_external_asset_refs($BASE) === [],
   'no page loads a script or stylesheet from another site (self-host it and list it in scripts/vendor_assets.txt)');
$_vr = moop_vendor_asset_rows();
ok(count($_vr) > 0 && count(array_filter($_vr, fn($r) => !preg_match('/^\w+:.+@[^@]+$/', $r[4]))) === 0,
   'every line of scripts/vendor_assets.txt names its ecosystem:package@version');

// ----------------------------------------------------------------------------
group('search: annotation source filter carries names exactly (JSON, no trim, commas survive)');
require_once "$BASE/lib/functions_validation.php";
$_names = ['Closest human gene (Ensembl, no HGNC record)', 'Ensembl Danio rerio ', 'Say "hi" <b>', 'UniProt'];
ok(moop_parse_source_names(json_encode($_names)) === $_names,
   'a JSON list round-trips: the comma, the trailing space, quotes and angle brackets are all kept');
ok(moop_parse_source_names('') === [] && moop_parse_source_names(null) === [] && moop_parse_source_names('[not json') === [],
   'empty, missing or malformed -> no filter, not an error');
ok(moop_parse_source_names(json_encode(['A', '', 3, ['x'], 'B'])) === ['A', 'B'], 'only non-empty strings are names');
$_old = ini_set('error_log', '/dev/null');
ok(moop_parse_source_names('UniProt,Ensembl Danio rerio ') === ['UniProt', 'Ensembl Danio rerio '],
   'the old comma string is still accepted (a page open from before), and not trimmed');
ini_set('error_log', $_old === false ? '' : $_old);

// ----------------------------------------------------------------------------
group('organism page gene set comparison: assembly stats from a .fai, formatted sizes');
require_once "$BASE/lib/gene_set_summary.php";
$_fai = tempnam(sys_get_temp_dir(), 'fai');
// lengths 50,30,10,5,5 (total 100): the running sum first reaches 50 at the first sequence.
file_put_contents($_fai, "s1\t50\t0\t60\t61\ns2\t30\t0\t60\t61\ns3\t10\t0\t60\t61\ns4\t5\t0\t60\t61\ns5\t5\t0\t60\t61\n");
ok(moop_fai_stats($_fai) === ['sequences' => 5, 'total_length' => 100, 'n50' => 50, 'l50' => 1], 'N50 is the length where half the total is reached; L50 how many it took');
file_put_contents($_fai, "a\t40\t0\t60\t61\nb\t30\t0\t60\t61\nc\t30\t0\t60\t61\n");
ok((moop_fai_stats($_fai)['n50'] ?? null) === 30 && (moop_fai_stats($_fai)['l50'] ?? null) === 2, 'N50/L50 when the half-way point falls in the second sequence');
// The pipeline's assembly_stats.json: the scaffold four required, the rest optional.
$_ad = sys_get_temp_dir() . '/moop_as_' . getmypid(); @mkdir($_ad);
file_put_contents("$_ad/assembly_stats.json", json_encode(['version' => 1, 'sequences' => 30, 'total_length' => 252273000,
    'n50' => 16735929, 'l50' => 7, 'contig_n50' => 803730, 'gap_bases' => 114400, 'gap_pct' => 0, 'gc_pct' => 41]));
$_as = moop_load_assembly_stats($_ad);
ok(($_as['l50'] ?? null) === 7 && ($_as['contig_n50'] ?? null) === 803730 && ($_as['gc_pct'] ?? null) === 41.0,
   'assembly_stats.json read: scaffold + contig figures, GC accepted as a whole number');
ok(moop_format_small_pct($_as['gap_pct']) === '0.05%', 'gap share from gap_bases (0.045%), not the rounded 0 in the file');
ok(moop_format_small_pct(0.0042) === '<0.01%' && moop_format_small_pct(0.0) === '0.0%' && moop_format_small_pct(2.34) === '2.3%',
   'a gap share too small to show reads <0.01%, never 0.00%');
file_put_contents("$_ad/assembly_stats.json", json_encode(['sequences' => 30, 'total_length' => 1, 'n50' => '16735929', 'l50' => 7]));
ok(moop_load_assembly_stats($_ad) === null, 'a required figure missing or not an integer -> null (the .fai fallback is used)');
@unlink("$_ad/assembly_stats.json"); @rmdir($_ad);
file_put_contents($_fai, '');
ok(moop_fai_stats($_fai) === null, 'an empty .fai gives no stats, not zeros');
@unlink($_fai);
// Which types count as homology: by name unless annotation_config.json flags a type either way.
$_md = sys_get_temp_dir() . '/moop_hom_' . getmypid(); @mkdir($_md);
$_cfg = ConfigManager::getInstance();
$_rp = new ReflectionProperty($_cfg, 'config'); $_rp->setAccessible(true);
$_saved = $_rp->getValue($_cfg);
$_c = $_saved; $_c['metadata_path'] = $_md; $_rp->setValue($_cfg, $_c);
$_types = ['Homologs', 'Orthologs', 'RBBH Homolog', 'Paralogs', 'Gene Ontology', 'Protein Features', 'Ortholog groups'];
file_put_contents("$_md/annotation_config.json", '{}');
ok(moop_homology_types($_types) === ['Homologs', 'Orthologs', 'RBBH Homolog', 'Ortholog groups'],
   'by name: homolog/ortholog types count, paralogs, GO and DeepLoc do not');
file_put_contents("$_md/annotation_config.json", json_encode(['annotation_types' => [
    'RBBH Homolog' => ['homology' => false], 'Paralogs' => ['homology' => true]]]));
ok(moop_homology_types($_types) === ['Homologs', 'Orthologs', 'Paralogs', 'Ortholog groups'],
   'a homology flag in annotation_config.json overrides the name, either way');
ok(moop_homology_flags() === ['Paralogs' => true, 'RBBH Homolog' => false], 'flags are recorded, so a changed flag forces a rebuild');
$_rp->setValue($_cfg, $_saved);
@unlink("$_md/annotation_config.json"); @rmdir($_md);
ok(moop_format_bp(2689411905) === '2.69 Gb' && moop_format_bp(16735929) === '16.7 Mb'
   && moop_format_bp(10739) === '10.7 kb' && moop_format_bp(512) === '512 bp', 'sizes read as Gb / Mb / kb / bp');

// ----------------------------------------------------------------------------
group('BUSCO: busco.json read defensively; no file or nothing usable means no card');
require_once "$BASE/lib/busco.php";
require_once "$BASE/lib/help_ui.php";
$_bd = sys_get_temp_dir() . '/moop_busco_' . getmypid();
@mkdir($_bd);
@unlink("$_bd/busco.json");
ok(moop_load_busco($_bd) === null, 'no busco.json -> null (the page shows no card)');
$_run = fn(array $o = []) => $o + ['lineage' => 'metazoa_odb10', 'role' => 'auto-selected', 'version' => '5.4.4',
    'mode' => 'proteins', 'n' => 954, 'complete' => 84.1, 'single' => 77.3, 'duplicated' => 6.8,
    'fragmented' => 5.3, 'missing' => 10.6, 'complete_n' => 802, 'single_n' => 737, 'duplicated_n' => 65,
    'fragmented_n' => 51, 'missing_n' => 101, 'dataset_date' => '2024-01-08', 'date' => '2026-08-04'];
$_bad = $_run(['lineage' => 'eukaryota_odb10', 'role' => 'generic']);
unset($_bad['fragmented_n']);
file_put_contents("$_bd/busco.json", json_encode(['protein_fasta_md5' => 'x',
    'runs' => [$_run(), $_bad, $_run(['lineage' => 'mollusca_odb10', 'role' => 'fixed', 'n' => 5295])],
    'warnings' => ['protein.aa.fa is newer than the BUSCO results', 42, '']]));
$_b = moop_load_busco($_bd);
ok(is_array($_b) && array_column($_b['runs'], 'lineage') === ['metazoa_odb10', 'mollusca_odb10'],
   'a run missing a number is dropped; the others are kept in file order');
ok(($_b['runs'][0]['single_n'] ?? null) === 737 && ($_b['runs'][1]['n'] ?? null) === 5295, 'counts come through as integers');
ok(($_b['warnings'] ?? null) === ['protein.aa.fa is newer than the BUSCO results'], 'only non-empty string warnings are kept');
ok(moop_busco_notation($_b['runs'][0]) === 'C:84.1%[S:77.3%,D:6.8%],F:5.3%,M:10.6%,n:954',
   "BUSCO's own notation, character for character");
file_put_contents("$_bd/busco.json", json_encode(['runs' => [$_bad]]));
ok(moop_load_busco($_bd) === null, 'a file with no usable run is treated as no file');
file_put_contents("$_bd/busco.json", '{not json');
ok(moop_load_busco($_bd) === null, 'malformed JSON -> null, not an error');
// The card: every segment's value is printed (nothing depends on colour or hover), and the
// lineage string -- produced on another machine -- is escaped.
$busco = ['runs' => [$_run(['lineage' => '<b>x</b>'])], 'warnings' => ['<i>w</i>']];
$busco_subject = 'gene set'; $busco_color = '#e11d48';
ob_start(); include "$BASE/includes/busco_card.php"; $_html = ob_get_clean();
ok(strpos($_html, '<b>x</b>') === false && strpos($_html, '&lt;b&gt;x&lt;/b&gt;') !== false
   && strpos($_html, '<i>w</i>') === false, 'lineage and warnings are escaped');
preg_match_all('/class="busco-seg [^"]+" style="flex-grow:([0-9.]+);/', $_html, $_m);
ok(count($_m[1]) === 4 && abs(array_sum(array_map('floatval', $_m[1])) - 100.0) < 0.3, 'four segments summing to 100%');
ok(substr_count($_html, '(737)') === 1 && substr_count($_html, '(101)') === 1, 'counts printed in the legend');
@unlink("$_bd/busco.json"); @rmdir($_bd);
unset($busco, $busco_subject, $busco_color);

// ----------------------------------------------------------------------------
group('gene page: an ID in two gene sets is asked about, never guessed');
$_fdb = sys_get_temp_dir() . '/moop_fgs_' . getmypid() . '.sqlite';
$_p = new PDO("sqlite:$_fdb");
$_p->exec("CREATE TABLE feature (feature_id INTEGER PRIMARY KEY, feature_uniquename TEXT, gene_set_id INTEGER)");
$_p->exec("INSERT INTO feature VALUES (1,'G1',1),(2,'G1',2),(3,'G2',1),(4,'G1',3)");
$_p = null;
ok(moop_feature_gene_set_ids('G1', $_fdb, [1, 2]) === [1, 2], 'shared ID -> both gene sets (the page shows a chooser)');
ok(moop_feature_gene_set_ids('G1', $_fdb, [2]) === [2], 'scoped by the link to one gene set -> just that one');
ok(moop_feature_gene_set_ids('G1', $_fdb, [1, 2]) === [1, 2],
   'gene set 3 holds G1 too, but the viewer cannot open it -> not counted');
ok(moop_feature_gene_set_ids('G2', $_fdb, [2]) === [], 'not in the named gene set -> none (404, same as unknown)');
ok(moop_feature_gene_set_ids('G1', $_fdb, []) === [], 'no accessible gene sets -> none');
@unlink($_fdb);

// ----------------------------------------------------------------------------
group('track sheets: a track is defined once; combo members are references; problems reported');
require_once "$BASE/lib/jbrowse/GoogleSheetsParser.php";
$_rows = [
    "TRACK_ID\tNAME\tTRACK_PATH\tACCESS",
    "t1\tTrack one\t/a/t1.bw\tPUBLIC",                        // 2: defines t1
    "t2\tTrack two\t/a/t2.bw\tCOLLABORATOR",                  // 3
    "t3\tTrack three\t/a/t3.bw\t",                            // 4: no access set
    "# Pair one/two",                                         // 5: '/' in a name
    "## blues: 0",                                            // 6: a group named "0"
    "t1",                                                     // 7: reference only
    "## greens: other",
    "t2\tTrack two\t/a/t2.bw\tCOLLABORATOR",                  // 9: identical full copy -- fine
    "### end",
    "# Pair one/two",                                         // 11: same name again
    "## reds: x",
    "t1",
    "### end",
    "# Has a ghost",                                          // 15
    "## blues: g",
    "nope",                                                   // 17: undefined reference
    "### end",
    "# Unset member",
    "## blues: u",
    "t1",
    "t3",
    "### end",
    "t1\tTrack ONE renamed\t/a/t1.bw\tPUBLIC",                // 24: disagreeing copy
];
$_r = (new GoogleSheetsParser())->parseTracks(implode("\n", $_rows), 'Org', 'Asm');
ok(array_column($_r['regular'], 'track_id') === ['t1', 't2', 't3'], 'each track once, in sheet order');
ok($_r['regular'][0]['name'] === 'Track one', 'the FIRST full row defines a track; a later copy does not replace it');
$_c = array_column($_r['combo'], null, 'track_id');
ok(isset($_c['pair_one_two']) && !isset($_c['pair_one/two']), "'/' in a combo name becomes '_' (the id is a file name)");
ok(($_c['pair_one_two']['groups'][0]['tracks'][0]['TRACK_PATH'] ?? '') === '/a/t1.bw',
   'a TRACK_ID-only member row is filled in from the definition, and a group named "0" keeps it');
ok(($_c['pair_one_two']['groups']['other']['tracks'][0]['name'] ?? '') === 'Track two', 'a full copy as a member resolves to the definition');
ok(($_c['pair_one_two']['access_level'] ?? '') === 'COLLABORATOR', 'combo takes its strictest member level (PUBLIC + COLLABORATOR)');
ok(($_c['unset_member']['access_level'] ?? 'x') === '', 'a member with no level makes the combo not set');
ok(count(array_filter($_r['combo'], fn($c) => $c['track_id'] === 'pair_one_two')) === 1, 'a duplicate combo name is not built twice');
$_w = implode("\n", $_r['warnings']);
ok(strpos($_w, 'line 11: combo "Pair one/two" has the same name as line 5') !== false, 'duplicate combo name reported with both lines');
ok(strpos($_w, 'line 17: combo "Has a ghost" refers to TRACK_ID nope') !== false, 'reference to an undefined track reported');
ok(strpos($_w, 'group "g" has no tracks') !== false, 'a group left empty is reported');
ok(strpos($_w, 'line 24: t1 repeats line 2 with a different name') !== false, 'a disagreeing copy is reported, naming the column');
ok(strpos($_w, 'line 9') === false, 'an identical copy is not reported');

// ----------------------------------------------------------------------------
group('Data Exporter: each row carries its own annotations; gene-level ones are seen');
require_once "$BASE/lib/moopmart_functions.php";
// G1 has two isoforms with different hits plus one hit on the gene itself; G2 has no transcript
// and its hit sits on the gene (Bradyrhizobium's shape). Shared function, so preview == download.
$_mdb = sys_get_temp_dir() . '/moop_mm_' . getmypid() . '.sqlite';
@unlink($_mdb);
$_p = new PDO("sqlite:$_mdb");
$_p->exec("CREATE TABLE organism (organism_id INTEGER PRIMARY KEY, genus TEXT, species TEXT);
           CREATE TABLE genome (genome_id INTEGER PRIMARY KEY, organism_id INTEGER, genome_name TEXT, genome_accession TEXT);
           CREATE TABLE gene_set (gene_set_id INTEGER PRIMARY KEY, genome_id INTEGER, gene_set_name TEXT);
           CREATE TABLE feature (feature_id INTEGER PRIMARY KEY, feature_name TEXT, feature_description TEXT,
                                 feature_type TEXT, feature_uniquename TEXT, parent_feature_id INTEGER, gene_set_id INTEGER);
           CREATE TABLE annotation_source (annotation_source_id INTEGER PRIMARY KEY, annotation_source_name TEXT);
           CREATE TABLE annotation (annotation_id INTEGER PRIMARY KEY, annotation_source_id INTEGER,
                                    annotation_accession TEXT, annotation_description TEXT);
           CREATE TABLE feature_annotation (feature_id INTEGER, annotation_id INTEGER);
           INSERT INTO organism VALUES (1,'Testus','fixture');
           INSERT INTO genome VALUES (1,1,'asm','ASM1');
           INSERT INTO gene_set VALUES (1,1,'gs1');
           INSERT INTO feature VALUES (1,'','','gene','G1',NULL,1),(2,'','','mRNA','M1',1,1),(3,'','','mRNA','M2',1,1),
                                      (4,'','','gene','G2',NULL,1),(5,'','','cds','C2',4,1);
           INSERT INTO annotation_source VALUES (1,'S');
           INSERT INTO annotation VALUES (1,1,'X1','d1'),(2,1,'X2','d2'),(3,1,'Z','dz'),(4,1,'Y','dy');
           INSERT INTO feature_annotation VALUES (2,1),(3,2),(1,3),(4,4);");
$_p = null;
$_genes = moopmartQueryFeatures([1], $_mdb);
foreach ($_genes as &$_g) { $_g['db_path'] = $_mdb; $_g['organism_dir'] = 'Testus_fixture'; }
unset($_g);
$_rows = moopmartExpandToMrnaRows($_genes, $_mdb);
$_by = [];
foreach (moopmartBuildPreviewRows($_rows, ['S'], true, false, true, 'wide')['rows'] as $_r) {
    $_by[$_r['mrna_id'] ?: $_r['uniquename']] = $_r['ID:S'];
}
ok(($_by['M1'] ?? null) === 'X1; Z', "an isoform row shows its own hit plus the gene's, not its sibling's (got " . var_export($_by['M1'] ?? null, true) . ')');
ok(($_by['M2'] ?? null) === 'X2; Z', 'the sibling isoform likewise');
ok(($_by['G2'] ?? null) === 'Y', 'a gene with no transcript shows the hit stored on the gene itself');
$_long = moopmartBuildPreviewRows($_rows, ['S'], true, false, true, 'long')['rows'];
ok(array_column(array_values(array_filter($_long, fn($r) => $r['mrna_id'] === 'M1')), 'annotation_id') === ['X1', 'Z'],
   'long format: one line per annotation the isoform actually has');
$_hit = fn($acc) => array_column(moopmartQueryFeatures([1], $_mdb,
    ['annotation_criteria' => [['src' => 'S', 'acc' => $acc, 'kw' => '']]]), 'uniquename');
ok($_hit('Y') === ['G2'], 'the annotation filter finds a hit stored on the gene itself');
ok($_hit('X2') === ['G1'], 'the filter still finds a hit stored on an isoform (whole gene kept, every isoform)');
@unlink($_mdb);

// ----------------------------------------------------------------------------
group('JBrowse: a new genes.gff stales the track, the coordinates AND the search index');
// Only the track index used to be checked, so coordinates and the search index went stale in
// silence -- and no gene set had a search index at all without anything saying so.
require_once "$BASE/lib/jbrowse/gene_track_index.php";
if (!defined('MOOP_JBROWSE_TEXT_INDEX_ATTRIBUTES')) define('MOOP_JBROWSE_TEXT_INDEX_ATTRIBUTES', 'Name,ID,Alias');
$_bgz = moop_find_executable('bgzip'); $_tbx = moop_find_executable('tabix');
if ($_bgz === '' || $_tbx === '') {
    ok(true, 'SKIPPED: bgzip/tabix not installed');
} else {
    $_jb = sys_get_temp_dir() . '/moop_jbfresh_' . getmypid();
    @mkdir("$_jb/org/gs", 0777, true); @mkdir("$_jb/dg/trix", 0777, true);
    file_put_contents("$_jb/org/gs/genes.gff", "##gff-version 3\nc1\ts\tgene\t1\t9\t.\t+\t.\tID=G1\n");
    symlink("$_jb/org/gs/genes.gff", "$_jb/dg/annotations.gff3");
    exec(escapeshellarg($_bgz) . ' -c ' . escapeshellarg("$_jb/org/gs/genes.gff") . ' > ' . escapeshellarg("$_jb/dg/annotations.gff3.gz")
         . ' && ' . escapeshellarg($_tbx) . ' -p gff ' . escapeshellarg("$_jb/dg/annotations.gff3.gz"));
    foreach (['org/gs/feature_coords.tsv', 'org/gs/exon_coords.tsv', 'dg/trix/annotations.gff3.gz.ix'] as $_f) file_put_contents("$_jb/$_f", "x\n");
    $_tj = "$_jb/track.json";
    $_track = fn(array $meta, bool $linked = true) => file_put_contents($_tj, json_encode(
        ['metadata' => $meta] + ($linked ? ['textSearching' => ['textSearchAdapter' => ['type' => 'TrixTextSearchAdapter']]] : [])));
    $_t = time() - 1000;   // GFF oldest, then .gz/.tbi, then what is derived from them
    touch("$_jb/org/gs/genes.gff", $_t); touch("$_jb/dg/annotations.gff3.gz", $_t + 10); touch("$_jb/dg/annotations.gff3.gz.tbi", $_t + 20);
    foreach (['org/gs/feature_coords.tsv', 'org/gs/exon_coords.tsv', 'dg/trix/annotations.gff3.gz.ix'] as $_f) touch("$_jb/$_f", $_t + 30);
    $_parts = fn() => array_map(fn($p) => $p['part'] . ':' . $p['kind'], gene_set_jbrowse_problems("$_jb/dg/annotations.gff3", $_tj));

    $_track(['text_index_attributes' => 'Name,ID,Alias']);
    ok($_parts() === [], 'everything newer than genes.gff, index linked, current attributes: nothing to do');

    touch("$_jb/org/gs/genes.gff", $_t + 25);   // a new genes.gff, newer than .gz and .tbi, older than coords
    ok($_parts() === ['track:stale'], 'genes.gff newer than the .gz: the track is stale');
    touch("$_jb/org/gs/genes.gff", $_t + 40);   // ...and newer than the coordinate files
    ok(gene_track_index_problem("$_jb/dg/annotations.gff3") !== null
       && $_parts() === ['track:stale', 'coords:stale', 'coords:stale'], 'and the coordinate files are stale too -- not just the track');

    touch("$_jb/org/gs/genes.gff", $_t);
    touch("$_jb/org/gs/feature_coords.tsv", $_t - 5);   // track current, coords left behind (a failed build)
    ok(gene_track_index_problem("$_jb/dg/annotations.gff3") === null && $_parts() === ['coords:stale'],
       'coordinates older than genes.gff while the track is current -- the case the old check could not see');
    touch("$_jb/org/gs/feature_coords.tsv", $_t + 30);

    touch("$_jb/dg/trix/annotations.gff3.gz.ix", $_t + 5);
    ok($_parts() === ['search:stale'], 'a search index older than the gene track is stale');
    touch("$_jb/dg/trix/annotations.gff3.gz.ix", $_t + 30);
    $_track(['text_index_attributes' => 'Name,ID']);
    ok($_parts() === ['search:stale'], 'a search index built without Alias is stale');
    $_track(['text_index_attributes' => 'Name,ID,Alias'], false);
    ok($_parts() === ['search:missing'], 'an index the track does not link to is no index');
    unlink("$_jb/dg/trix/annotations.gff3.gz.ix");
    ok($_parts() === ['search:missing'], 'no index file: missing');

    exec('rm -rf ' . escapeshellarg($_jb));
}

// ----------------------------------------------------------------------------
group('Inline handlers: PHP values go in as JSON, never inside a quoted JS string');
// The browser decodes an attribute's HTML entities BEFORE it runs the JavaScript, so
// htmlspecialchars() cannot protect a value echoed inside f('...') in an onclick: an
// apostrophe closes the string (the button dies; an onsubmit confirm() dies and the form
// submits unconfirmed), a backslash vanishes, and a crafted name runs its own code. The safe
// form echoes htmlspecialchars(json_encode($x)) as the WHOLE argument, unquoted. (No PHP
// close tag in these comments: one ends PHP mode even inside a // comment.) This walks every handler attribute and
// fails on PHP output that lands inside a '...' or "..." JS literal. count() is allowed: a
// number cannot break out of a string.
$_php_in_js_string = function (string $attr): array {
    $bad = [];
    $attr = preg_replace('/^on[a-z]+="(.*)"$/s', '$1', $attr);   // the value only, not its own quotes
    preg_match_all('/<\?(?:=|php echo)\s*(.*?)\s*;?\s*\?>/s', $attr, $blocks);
    $marked = preg_replace('/<\?(?:=|php echo).*?\?>/s', "\x01", $attr);
    $js = html_entity_decode($marked, ENT_QUOTES | ENT_HTML5);
    $quote = null; $i_block = 0;
    for ($i = 0, $n = strlen($js); $i < $n; $i++) {
        $c = $js[$i];
        if ($c === "\x01") {
            $expr = $blocks[1][$i_block++] ?? '';
            if ($quote !== null && !preg_match('/^count\(/', $expr)) $bad[] = $expr;
        } elseif ($quote !== null && $c === '\\') {
            $i++;
        } elseif ($c === "'" || $c === '"') {
            $quote = $quote === null ? $c : ($quote === $c ? null : $quote);
        }
    }
    return $bad;
};
ok($_php_in_js_string("onclick=\"f('<?= htmlspecialchars(\$name) ?>')\"") === ['htmlspecialchars($name)'],
   'the broken form is caught: htmlspecialchars output inside a quoted JS string');
ok($_php_in_js_string("onsubmit=\"return confirm('Delete &quot;<?= htmlspecialchars(\$t, ENT_QUOTES) ?>&quot;?')\"") === ['htmlspecialchars($t, ENT_QUOTES)'],
   'caught inside a message too, with entity-encoded quotes around it');
ok($_php_in_js_string("onclick=\"f(<?= htmlspecialchars(json_encode(\$name)) ?>, <?= \$idx ?>)\"") === [],
   'the safe form passes: the JSON string literal is the whole argument');
ok($_php_in_js_string("onclick=\"if(confirm('Fetch all <?= count(\$organisms) ?> organisms?')) go()\"") === [],
   'a count() inside a message is allowed');
$_handler_hits = [];
exec('git -C ' . escapeshellarg($BASE) . ' ls-files -- "*.php"', $_tracked);
foreach ($_tracked as $_rel) {
    if (strpos($_rel, 'vendor/') === 0) continue;
    $_src = (string)@file_get_contents("$BASE/$_rel");
    if (!preg_match_all('/\bon[a-z]+="[^"]*"/', $_src, $_attrs, PREG_OFFSET_CAPTURE)) continue;
    foreach ($_attrs[0] as [$_attr, $_off]) {
        foreach ($_php_in_js_string($_attr) as $_expr) {
            $_handler_hits[] = $_rel . ':' . (substr_count($_src, "\n", 0, $_off) + 1) . " $_expr";
        }
    }
}
ok(count($_tracked) > 100 && $_handler_hits === [],
   'no PHP value is written inside a quoted string in an inline handler'
   . ($_handler_hits ? ":\n      " . implode("\n      ", $_handler_hits) : ''));
// The same mistake in the two other ways handlers get built: concatenated in a PHP string
// (onclick="f(\'' . $x . '\')") and in a JS template literal (onclick="f('${x}')").
// Either way the safe form is the JSON-encoded value, escaped for the attribute.
$_built_hits = [];
exec('git -C ' . escapeshellarg($BASE) . ' ls-files -- "*.php" "js/*.js"', $_built_files);
foreach ($_built_files as $_rel) {
    if (strpos($_rel, 'vendor/') !== false || strpos($_rel, 'tests/') === 0) continue;   // tests quote the pattern
    $_is_js = substr($_rel, -3) === '.js';
    foreach (file("$BASE/$_rel") ?: [] as $_n => $_line) {
        $_bad = $_is_js ? preg_match('/\bon[a-z]+="[^"]*\'\$\{/', $_line)
                        : preg_match("/\\bon[a-z]+=\"[^\"]*\\\\''\\s*\\./", $_line);
        if ($_bad) $_built_hits[] = "$_rel:" . ($_n + 1);
    }
}
ok($_built_hits === [],
   'no handler built in a PHP string or JS template puts a value inside quotes'
   . ($_built_hits ? ":\n      " . implode("\n      ", $_built_hits) : ''));

// ----------------------------------------------------------------------------
group('Organism cache staleness: one test, used by the dashboard, Manage Organisms and Quick Links');
require_once "$BASE/lib/organism_cache.php";
require_once "$BASE/lib/functions_data.php";
$_sd = sys_get_temp_dir() . '/moop_stale_' . getmypid();
@mkdir("$_sd/orgs/Alpha", 0777, true); @mkdir("$_sd/orgs/Beta", 0777, true);
file_put_contents("$_sd/orgs/Alpha/organism.sqlite", 'a'); file_put_contents("$_sd/orgs/Beta/organism.sqlite", 'b');
file_put_contents("$_sd/tree.json", '{}'); file_put_contents("$_sd/groups.json", '[]');
$_cache = ['config_fingerprint' => buildConfigFingerprint("$_sd/tree.json", "$_sd/groups.json"),
           'org_fingerprints'   => buildPerOrganismFingerprints("$_sd/orgs"),
           'schema_version'     => ORGANISM_CACHE_SCHEMA_VERSION];
$_st = fn($c) => moop_organism_cache_staleness($c, "$_sd/orgs", "$_sd/tree.json", "$_sd/groups.json");
$_r = $_st($_cache);
ok(!$_r['stale'] && $_r['changed'] === [] && $_r['deleted'] === [] && $_r['reasons'] === [], 'nothing changed: not stale');
file_put_contents("$_sd/orgs/Alpha/organism.sqlite", 'a rebuilt database');
exec('rm -rf ' . escapeshellarg("$_sd/orgs/Beta"));
$_r = $_st($_cache);
ok($_r['stale'] && $_r['changed'] === ['Alpha'] && $_r['deleted'] === ['Beta'] && $_r['reasons'] === [],
   'a rebuilt database is changed; a removed organism is deleted');
file_put_contents("$_sd/groups.json", '[{"organism":"Alpha"}]');
$_r = $_st($_cache);
ok($_r['config_changed'] && $_r['changed'] === ['Alpha'] && count($_r['reasons']) === 1,
   'a config change and a changed organism are BOTH reported (the old rule hid the organism)');
$_r = $_st(['schema_version' => ORGANISM_CACHE_SCHEMA_VERSION - 1] + $_cache);
ok($_r['stale'] && $_r['format_outdated'], 'a cache of an older format is stale whatever its fingerprints say');
$_r = $_st(array_diff_key($_cache, ['config_fingerprint' => 1]));
ok($_r['stale'] && $_r['format_outdated'] && $_r['changed'] === [], 'a cache with no fingerprints is stale, without listing every organism');
ok($_st([])['stale'] === false, 'no cache at all is "not built", not stale -- callers say so in their own words');
exec('rm -rf ' . escapeshellarg($_sd));

// ----------------------------------------------------------------------------
group('Gene neighbours: the genes beside a gene, in genome order');
require_once "$BASE/lib/feature_coords.php";
$_nd = sys_get_temp_dir() . '/moop_nbr_' . getmypid();
@mkdir($_nd, 0777, true);
// g2 is nested inside g1, g3 overlaps g2's end, g9 is on another sequence; transcript rows
// repeat their gene's span, as the real file does.
file_put_contents("$_nd/feature_coords.tsv", implode("\n", [
    "t3\tg3\tchr1\t450\t900\t+", "g1\tg1\tchr1\t100\t1000\t+", "t1\tg1\tchr1\t100\t1000\t+",
    "g9\tg9\tchr2\t1\t50\t-", "g2\tg2\tchr1\t200\t500\t-", "g4\tg4\tchr1\t2000\t2500\t+", "g3\tg3\tchr1\t450\t900\t+",
]) . "\n");
$_b = moop_feature_coords_genes_beside($_nd, 'g2');
ok(array_column($_b['left'], 'gene_id') === ['g1'] && array_column($_b['right'], 'gene_id') === ['g3', 'g4'],
   'ordered by start: a nested gene sits between its host and the next; other sequences are left out');
ok($_b['self']['strand'] === '-' && $_b['self']['start'] === 200, "the gene's own span and strand come back too");
$_walk = ['g1']; while (($_n = moop_feature_coords_genes_beside($_nd, end($_walk))['right'][0]['gene_id'] ?? null)) $_walk[] = $_n;
ok($_walk === ['g1', 'g2', 'g3', 'g4'], 'stepping right from the first gene visits every gene on the sequence once');
ok(moop_feature_coords_genes_beside($_nd, 'g9')['left'] === [] && moop_feature_coords_genes_beside($_nd, 'nope') === null,
   'a gene alone on its sequence has no neighbours; an unknown gene is null');
array_map('unlink', glob("$_nd/*")); @rmdir($_nd);

// ----------------------------------------------------------------------------
group('Group chips: one sorted list per organism, on every page');
$_gm = moop_organism_groups_map([
    ['organism' => 'Scolanthus', 'groups' => ['Sea anemone', 'Cnidaria']],
    ['organism' => 'Nematostella', 'groups' => ['Cnidaria']],
    ['organism' => 'Nematostella', 'groups' => ['Sea anemone', 'Cnidaria', 'public', 'PUBLIC']],
    ['organism' => 'Lonely', 'groups' => []],
    ['organism' => 'Mixed', 'groups' => ['bats', 'Bats 10', 'Bats 9', 'Ants']],
]);
ok($_gm['Scolanthus'] === $_gm['Nematostella'] && $_gm['Nematostella'] === ['Cnidaria', 'Sea anemone'],
   'two organisms in the same groups list them in the same order, whatever order the entries gave');
ok($_gm['Lonely'] === [] && !in_array('public', $_gm['Nematostella'], true),
   'an organism with no groups gets an empty list; the legacy PUBLIC keyword is skipped in any case');
ok($_gm['Mixed'] === ['Ants', 'bats', 'Bats 9', 'Bats 10'], 'alphabetical ignoring case, numbers in natural order');

// ----------------------------------------------------------------------------
group('Expression tool: heatmap shading, drawing and gene lookup');
require_once "$BASE/lib/expression_functions.php";
$_sh = fn($t) => round(moop_expression_shade($t, 'level', 0), 3);
ok([$_sh(0), $_sh(0.5), $_sh(1), $_sh(10), $_sh(100), $_sh(1000), $_sh(5000)] === [0.0, 0.125, 0.25, 0.5, 0.75, 1.0, 1.0],
   'level: 1 / 10 / 100 / 1000 TPM at exactly 1/4, 1/2, 3/4, 1 (the gene page\'s bands); 0..1 TPM rises evenly');
ok(moop_expression_shade(5, 'pattern', 5) === 0.5 && moop_expression_shade(200, 'pattern', 400) === 0.5,
   'pattern: scaled to the gene\'s own highest -- but never to less than the 10 TPM floor (a silent gene stays pale)');
ok(moop_expression_shade_colour(0.0) === '#f8fafc' && moop_expression_shade_colour(1.0) === '#0c3648', 'shades run white to deep teal');
$_exps = [0 => ['label' => 'Dev', 'samples' => [['group_name' => 'egg'], ['group_name' => 'egg'], ['group_name' => 'larva']]],
          1 => ['label' => 'Metals', 'samples' => [['group_name' => 'control']]]];
$_mx = ['g1' => [0 => moop_expression_group_means($_exps[0]['samples'], [1.0, 3.0, 50.0]), 1 => null],
        'g2' => [0 => moop_expression_group_means($_exps[0]['samples'], [0.0, 0.0, 0.0]), 1 => moop_expression_group_means($_exps[1]['samples'], [7.0])]];
$_svg = moop_expression_heatmap_svg(['g1', 'g2'], $_exps, $_mx);
ok(substr_count($_svg, '<rect x=') === 6, 'one cell per gene per condition (2 genes x 3 conditions)');
ok(substr_count($_svg, 'fill="url(#exprNoData)"') === 1 && strpos($_svg, 'g1 · Metals · control: no data') !== false,
   'an experiment without the gene is hatched "no data", never drawn as zero');
$_svg_cpm = moop_expression_heatmap_svg(['g2'], [1 => ['unit' => 'CPM'] + $_exps[1]], ['g2' => [1 => $_mx['g2'][1]]]);
ok(strpos($_svg_cpm, 'g2 · Metals · control: 7.0 CPM (average of 1)') !== false, 'a cell names its experiment\'s unit (CPM for single-cell)');
ok(strpos($_svg, 'g1 · Dev · egg: 2.0 TPM (average of 2)') !== false && strpos($_svg, 'g2 · Dev · egg: 0.0 TPM') !== false,
   'each cell says its average and how many samples, and a measured zero is a value, not "no data"');
ok(strpos($_svg, 'expr-hm-sym') === false, 'no symbol column when no gene in the list has a symbol');
$_svg = moop_expression_heatmap_svg(['g1', 'g2'], $_exps, $_mx, 'level', ['g2' => 'SOD1']);
ok(substr_count($_svg, 'class="expr-hm-sym"') === 1 && strpos($_svg, 'g2 (SOD1) · Metals') !== false,
   'a symbol is drawn only for the gene that has one, and named in its cells\' hover');
$_svg = moop_expression_heatmap_svg(['g1', 'g2'], $_exps, $_mx, 'level', [], fn($g) => "/moop/tools/parent.php?uniquename=$g&x=1");
ok(substr_count($_svg, '<a class="expr-hm-link" href="/moop/tools/parent.php?uniquename=g1&amp;x=1">') === 1
   && substr_count($_svg, 'class="expr-hm-link"') === 2,
   'each gene label links to its gene page (href escaped), one link per gene');
$_long_cond = str_repeat('a long condition name ', 3);   // 66 characters
$_svg = moop_expression_heatmap_svg(['g1'], [0 => ['label' => 'A very long experiment name indeed', 'samples' => [['group_name' => 'x']]],
                                             1 => ['label' => 'Second experiment', 'samples' => [['group_name' => $_long_cond]]]],
                                    ['g1' => [0 => null, 1 => null]]);
ok(strpos($_svg, '>A very long experiment name indeed</text>') !== false && strpos($_svg, '>Second experiment</text>') !== false,
   'experiment names are shown whole, however narrow their columns');
preg_match_all('/class="expr-hm-exp" x="[^"]*" y="(\d+)"/', $_svg, $_ys);
ok(count(array_unique($_ys[1])) === 2, 'each experiment name has its own line, so a long one never overprints the next');
ok(strpos($_svg, '<title>' . htmlspecialchars($_long_cond, ENT_QUOTES) . '</title>') !== false && strpos($_svg, '…</text>') !== false,
   'a condition name past 40 characters is cut, whole in its hover; shorter ones are drawn whole');

// Gene lookup in a throwaway database: transcript -> gene, and a self-loop must not hang.
$_edb = sys_get_temp_dir() . '/moop_expr_' . getmypid() . '.sqlite';
@unlink($_edb);
$_pdo = new PDO("sqlite:$_edb");
$_pdo->exec("CREATE TABLE feature (feature_id INTEGER PRIMARY KEY, feature_uniquename TEXT, feature_name TEXT, feature_type TEXT,
             parent_feature_id INTEGER, gene_set_id INTEGER)");
$_pdo->exec("INSERT INTO feature VALUES (1,'G1','SOD1','gene',NULL,7), (2,'G1.t1','G1.t1','mRNA',1,7), (3,'G1.t1:pep','','protein',2,7),
             (4,'LOOP','LOOP','gene',4,7), (5,'G2','G2.t1','gene',NULL,7), (6,'G2.t1','','mRNA',5,7), (7,'G9','X','gene',NULL,8)");
$_pdo = null;
ok(moop_expression_root_genes(['G1.t1', 'G1.t1:pep', 'G1', 'LOOP', 'G9'], $_edb, 7)
   === ['G1.t1' => 'G1', 'G1.t1:pep' => 'G1', 'G1' => 'G1', 'LOOP' => 'LOOP'],
   'transcripts and proteins resolve to their gene; a self-parented feature stops; another gene set is not searched');
ok(moop_expression_gene_symbols(['G1', 'G2'], $_edb, 7) === ['G1' => 'SOD1'],
   'a symbol only where the gene has a real one -- a transcript id in feature_name is not a symbol');
@unlink($_edb);

// ----------------------------------------------------------------------------
group('Wikipedia extracts: brackets the API emptied are removed');
require_once "$BASE/lib/wikipedia_functions.php";
ok(moop_wikipedia_clean_extract('An echinoderm () is any animal of the phylum Echinodermata (), which')
   === 'An echinoderm is any animal of the phylum Echinodermata, which',
   'an emptied pronunciation bracket goes, with the space before it');
ok(moop_wikipedia_clean_extract('molluscs or mollusks (). 86,600') === 'molluscs or mollusks. 86,600', 'before a full stop too');
ok(moop_wikipedia_clean_extract('Cnidaria (; from Greek knide) is') === 'Cnidaria (from Greek knide) is',
   'a bracket that still has words keeps them, minus the separator left at its start');
ok(moop_wikipedia_clean_extract('the sea (or ocean) is') === 'the sea (or ocean) is', 'a real bracket is untouched');

// ----------------------------------------------------------------------------
group('Metadata free text: provenance / note');
ok(moop_text_with_links('From ftp://ftp.ncbi.nlm.nih.gov/genomes/x/.') ===
   'From <a href="ftp://ftp.ncbi.nlm.nih.gov/genomes/x/" target="_blank" rel="noopener">ftp://ftp.ncbi.nlm.nih.gov/genomes/x/</a>.',
   'a URL becomes a link; the full stop after it stays text');
ok(moop_text_with_links('(see https://doi.org/10.1/x)') ===
   '(see <a href="https://doi.org/10.1/x" target="_blank" rel="noopener">https://doi.org/10.1/x</a>)',
   'a closing bracket after a URL is not part of it');
ok(strpos(moop_text_with_links('<b>x</b> https://a.b/"onmouseover="y'), '<b>') === false
   && strpos(moop_text_with_links('https://a.b/"onmouseover="y'), 'href="https://a.b/"') !== false,
   'text is escaped, and a quote ends the URL -- it cannot add an attribute to the link');
ok(moop_text_with_links("two\nlines, no url") === "two\nlines, no url", 'plain text passes through; line breaks are kept for CSS');

// ----------------------------------------------------------------------------
group('ConfigManager: dotted keys and numbers reach every getter');
// Only getBoolean() understood 'a.b' until 2026-10-23; getArray('jbrowse2.trusted_tracks_servers')
// silently returned [] and housekeeping checked a JWT key path it never actually read. And
// getString() returned the DEFAULT for a bare int from site_config.php.
$_cfg = ConfigManager::getInstance();
$_rp = new ReflectionProperty($_cfg, 'config'); $_rp->setAccessible(true);
$_saved = $_rp->getValue($_cfg);
$_c = $_saved;
$_c['jbrowse2'] = ['trusted_tracks_servers' => ['https://t.example'], 'jwt_private_key' => '/k/priv.pem', 'n' => 3];
$_c['smoke_int'] = 7;
$_c['smoke.literal'] = 'literal wins';
$_rp->setValue($_cfg, $_c);
ok($_cfg->getArray('jbrowse2.trusted_tracks_servers') === ['https://t.example'], 'getArray reads a dotted key');
ok($_cfg->getPath('jbrowse2.jwt_private_key') === '/k/priv.pem', 'getPath reads a dotted key');
ok($_cfg->getInt('jbrowse2.n') === 3, 'getInt reads a dotted key');
ok($_cfg->getString('jbrowse2.missing', 'd') === 'd' && $_cfg->getString('smoke_int.x', 'd') === 'd',
   'a missing or non-array path gives the default');
ok($_cfg->getString('smoke.literal') === 'literal wins', 'a literal key containing a dot still wins');
ok($_cfg->getString('smoke_int', 'default') === '7', 'getString returns a bare int as text, not the default');
$_rp->setValue($_cfg, $_saved);

// ----------------------------------------------------------------------------
group('JBrowse: track metadata is flat, so the faceted selector can filter on it');
// JBrowse's faceted selector and its search skip object-valued metadata keys, and every
// descriptive field sat inside google_sheets_metadata -- so nothing useful could be filtered.
require_once "$BASE/lib/jbrowse/config_functions.php";
$_m = moop_jbrowse_browser_metadata([
    'management_track_id' => 'MOLNG-1.neg.bw', 'access_level' => 'PUBLIC', 'file_path' => '/x/y.bw',
    'is_remote' => true, 'added_date' => '2026-10-21', 'description' => '',
    'total_reads' => '1200', 'mapped_reads' => 'not_calculated',
    'google_sheets_metadata' => ['technique' => 'RNASeq', 'source' => "Yanai  Lab\n", 'tissue' => '  ',
                                 'stages' => ['polyp', '', 'adult'], 'nested' => ['a' => ['b' => 1]]],
]);
ok($_m['technique'] === 'RNASeq' && $_m['source'] === 'Yanai Lab', 'sheet fields are lifted to the top level, whitespace tidied');
ok(!isset($_m['tissue']) && !isset($_m['description']), 'blank values are dropped');
ok(($_m['stages'] ?? '') === 'polyp, adult', 'a list becomes text');
$_obj = array_filter($_m, fn($v) => is_array($v) || is_object($v));
ok($_obj === [], 'no value is an object -- JBrowse would skip it');
ok(!array_intersect(array_keys($_m), ['management_track_id', 'access_level', 'file_path', 'is_remote', 'added_date', 'google_sheets_metadata']),
   'internal bookkeeping is not sent to the browser');
ok(($_m['total_reads'] ?? null) === 1200 && !isset($_m['mapped_reads']), 'read counts kept when they are numbers');

// ----------------------------------------------------------------------------
group('JWT keys: every check looks at the files the signing code uses');
// Until 2026-10-23 six config settings (certs_directory / jwt_private_key / jwt_public_key,
// top-level and under jbrowse2) looked like they moved the keys, the health checks read them,
// and the signing code ignored them all. Now one function names the files.
require_once "$BASE/lib/jbrowse/track_token.php";
require_once "$BASE/lib/permission_check.php";
$_k = moop_jwt_key_paths();
ok($_k['dir'] === "$BASE/certs" && $_k['private'] === "$BASE/certs/jwt_private_key.pem"
   && $_k['public'] === "$BASE/certs/jwt_public_key.pem", 'the key pair is <moop>/certs/');

$_items = moop_build_permission_items(ConfigManager::getInstance(), [
    'web_group' => 'apache', 'moop_owner' => 'smr', 'metadata_path' => "$BASE/metadata",
    'organism_data' => "$BASE/organisms", 'cache_path' => '', 'site_path' => $BASE,
    'absolute_images_path' => "$BASE/images", 'docs_path' => "$BASE/docs"]);
$_by = array_column($_items, 'paths', 'name');
ok(($_by['JWT Certificates Directory'] ?? null) === [$_k['dir']], 'the permissions check inspects that directory');
ok(($_by['JWT Key Files'] ?? null) === [$_k['private'], $_k['public']], 'and those two key files');

ok(ConfigManager::getInstance()->getPath('certs_directory') === ''
   && ConfigManager::getInstance()->getPath('jbrowse2.jwt_private_key') === ''
   && ConfigManager::getInstance()->getPath('jwt_public_key') === '',
   'no config setting pretends to move the keys');

// No reader may go back to a config value or a path of its own.
$_strays = [];
foreach (array_merge(glob("$BASE/{lib,admin,api,tools,includes}/*.php", GLOB_BRACE), glob("$BASE/{lib,admin,api}/*/*.php", GLOB_BRACE),
                     ["$BASE/setup.php", "$BASE/setup-check.php"]) as $_f) {
    if ($_f === "$BASE/lib/jbrowse/track_token.php") continue;   // the one place that names them
    $_src = file_get_contents($_f);
    if (preg_match('/(certs_directory|[\'"]jwt_(private|public)_key[\'"]\s*\]|getPath\(\s*[\'"][^\'"]*jwt_|certs\/jwt_(private|public)_key\.pem[\'"])/', $_src, $_m)) {
        $_strays[] = str_replace("$BASE/", '', $_f) . ': ' . $_m[1];
    }
}
ok($_strays === [], 'nothing reads the key paths from config or spells them out itself'
   . ($_strays ? ' -- ' . implode('; ', $_strays) : ''));

// track_token.php is also deployed alone on the tracks server and is read by setup-check.php
// before `composer install`: including it must not need Composer.
exec(PHP_BINARY . ' -r ' . escapeshellarg('require "' . $BASE . '/lib/jbrowse/track_token.php"; '
     . 'echo count(array_filter(get_included_files(), fn($f) => strpos($f, "/vendor/") !== false));'), $_o, $_rc);
ok($_rc === 0 && trim(implode('', $_o)) === '0', 'track_token.php loads without Composer; the JWT library loads on first use');

if (is_readable($_k['private']) && is_readable($_k['public'])) {
    $_tok = generateTrackToken('Org', 'Asm', 'Org/Asm/x.bw');
    $_dec = verifyTrackToken($_tok);
    ok($_dec && $_dec->file === 'Org/Asm/x.bw', 'a token signed with that private key verifies with that public key');
}

// ----------------------------------------------------------------------------
group('BLAST queue: it cannot get stuck');
// lib/blast_queue.php. State is flock()s, which the kernel drops when a process dies, so the
// things to prove are: a free unit is always takeable, nothing is held half-way, a dead
// waiter is swept rather than blocking the line, and a broken queue runs the search anyway.
require_once "$BASE/lib/blast_queue.php";
$_qd = sys_get_temp_dir() . '/moop_bq_' . getmypid();
@mkdir($_qd, 0777, true);
$_cfg = ConfigManager::getInstance();
$_rp = new ReflectionProperty($_cfg, 'config'); $_rp->setAccessible(true);
$_saved = $_rp->getValue($_cfg);
$_c = $_saved; $_c['cache_path'] = $_qd;
$_c['blast_memory_budget_mb'] = 4 * BLAST_QUEUE_UNIT_MB;   // 4 units
$_c['blast_queue_max_wait'] = 5;
$_rp->setValue($_cfg, $_c);

$_dir = blast_queue_dir();
ok($_dir !== '' && is_dir("$_dir/units") && is_dir("$_dir/wait") && is_dir("$_dir/run"), 'queue directories are created');
ok(blast_queue_settings()['units'] === 4, 'the memory budget is divided into units');

$_a = blast_queue_take_units($_dir, 4, 3);
ok(is_array($_a) && count($_a) === 3, 'three of four free units can be taken');
ok(blast_queue_take_units($_dir, 4, 2) === null, 'two more cannot -- only one is free');
$_b = blast_queue_take_units($_dir, 4, 1);
ok(is_array($_b) && count($_b) === 1, 'all or nothing: the failed attempt kept none of what it touched');
foreach (array_merge($_a, $_b) as $_fh) { flock($_fh, LOCK_UN); fclose($_fh); }
chmod("$_dir/units/u000", 0444);
$_c4 = blast_queue_take_units($_dir, 4, 4);
ok(is_array($_c4) && count($_c4) === 4, 'a unit file the web user cannot write is still takeable (locks need read only)');
foreach ($_c4 as $_fh) { flock($_fh, LOCK_UN); fclose($_fh); }

// A waiter whose process died: its file is there, nobody holds it, and it is old.
touch("$_dir/wait/0000000001.000000-deaddeaddeaddead", time() - 10);
$_live = fopen("$_dir/wait/0000000002.000000-1111111111111111", 'x'); flock($_live, LOCK_EX);
touch("$_dir/wait/0000000002.000000-1111111111111111", time() - 10);
ok(blast_queue_waiters($_dir) === ['0000000002.000000-1111111111111111'],
   'a dead waiter is swept from the line; a live one stays');
ok(!file_exists("$_dir/wait/0000000001.000000-deaddeaddeaddead"), "the dead waiter's file is deleted");
ok((blast_queue_status('1111111111111111')['position'] ?? null) === 1, 'status reports a place in line');
flock($_live, LOCK_UN); fclose($_live); unlink("$_dir/wait/0000000002.000000-1111111111111111");

$_r = blast_queue_run(fn() => 'ran', 2, blast_queue_ticket(null));
ok(($_r['ran'] ?? false) && $_r['result'] === 'ran', 'a job runs when there is room');
ok(count(glob("$_dir/wait/*")) === 0 && count(glob("$_dir/run/*")) === 0, 'and leaves nothing behind');
$_r = blast_queue_run(fn() => 'big', 99, blast_queue_ticket(null));
ok(($_r['result'] ?? '') === 'big', 'a job bigger than the whole budget runs alone, it does not wait forever');

$_all = blast_queue_take_units($_dir, 4, 4);
$_t0 = microtime(true);
$_r = blast_queue_run(fn() => 'never', 1, blast_queue_ticket(null));
ok($_r['ran'] === false && strpos($_r['error'], 'busy') !== false && microtime(true) - $_t0 < 8,
   'with every unit held, a request gives up after blast_queue_max_wait with a "busy" message');
ok(count(glob("$_dir/wait/*")) === 0, 'a request that gave up leaves the line');
foreach ($_all as $_fh) { flock($_fh, LOCK_UN); fclose($_fh); }

chmod("$_dir/units/u001", 0000);
$_r = blast_queue_run(fn() => 'unqueued', 2, blast_queue_ticket(null));
ok(($_r['result'] ?? '') === 'unqueued' || posix_geteuid() === 0,
   'an unopenable unit file means a broken queue: the search runs unqueued, it does not wait');
chmod("$_dir/units/u001", 0644);

ok(blast_queue_ticket('../../etc') !== '../../etc' && strlen(blast_queue_ticket(null)) === 16,
   'a ticket from the browser is used only if it is plain hex');
ok(blast_queue_timed_out(124) && blast_queue_timed_out(137) && !blast_queue_timed_out(1),
   'timeout exit codes are recognised');

$_rp->setValue($_cfg, $_saved);
exec('rm -rf ' . escapeshellarg($_qd));

// ----------------------------------------------------------------------------
echo "\n" . str_repeat('-', 60) . "\n";
echo "Smoke tests: $PASS passed, $FAIL failed\n";
if ($FAIL > 0) {
    echo "FAILED:\n  - " . implode("\n  - ", $FAILURES) . "\n";
    exit(1);
}
echo "ALL SMOKE TESTS PASSED\n";
exit(0);
