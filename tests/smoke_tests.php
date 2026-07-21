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
echo "\n" . str_repeat('-', 60) . "\n";
echo "Smoke tests: $PASS passed, $FAIL failed\n";
if ($FAIL > 0) {
    echo "FAILED:\n  - " . implode("\n  - ", $FAILURES) . "\n";
    exit(1);
}
echo "ALL SMOKE TESTS PASSED\n";
exit(0);
