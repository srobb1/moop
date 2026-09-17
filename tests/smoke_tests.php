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

// The drift guard. Each track type keeps its own list of the sheet fields it publishes, and the
// parser must carry every one of them — a field a track type asks for but the parser drops is
// exactly this bug. Asserted rather than trusted: adding a column to one track type's list
// looks complete from inside that file.
$_ts_fields = defined('GoogleSheetsParser::METADATA_FIELDS') ? GoogleSheetsParser::METADATA_FIELDS : [];
ok(!empty($_ts_fields), 'GoogleSheetsParser::METADATA_FIELDS lists the fields it carries');
$_ts_lists = 0;
foreach (glob(dirname(__DIR__) . '/lib/jbrowse/TrackTypes/*.php') as $_ts_file) {
    preg_match_all("/\[\s*'technique'[^\]]*\]/", file_get_contents($_ts_file), $_ts_found);
    foreach ($_ts_found[0] as $_ts_list) {
        $_ts_lists++;
        preg_match_all("/'([a-z0-9_]+)'/", $_ts_list, $_ts_names);
        $_ts_missing = array_diff($_ts_names[1], $_ts_fields);
        ok(empty($_ts_missing), basename($_ts_file) . ' publishes only fields the parser carries'
           . (empty($_ts_missing) ? '' : ' (dropped: ' . implode(', ', $_ts_missing) . ')'));
    }
}
ok($_ts_lists > 0, "found the track types' metadata field lists to check");

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
echo "\n" . str_repeat('-', 60) . "\n";
echo "Smoke tests: $PASS passed, $FAIL failed\n";
if ($FAIL > 0) {
    echo "FAILED:\n  - " . implode("\n  - ", $FAILURES) . "\n";
    exit(1);
}
echo "ALL SMOKE TESTS PASSED\n";
exit(0);
