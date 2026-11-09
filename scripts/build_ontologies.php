<?php
/**
 * Build the ontology files (lib/ontologies.php) from their GitHub sources.
 *
 *   php scripts/build_ontologies.php              every configured ontology
 *   php scripts/build_ontologies.php PLANA ECO    just these
 *
 * Run as the web user where possible (sudo -u apache php ...) so the files belong to it; as
 * anyone else the files are made group-writable in the setgid cache folder, which also works.
 * Each ontology is built and swapped on its own: one failing leaves the others, and its own
 * previous file, untouched. Exit 1 if any failed.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/ontologies.php';

$want = array_slice($argv, 1);
$all  = moop_ontology_sources();
if (!$all) { fwrite(STDERR, "No ontologies configured ('ontologies' in config/site_config.php).\n"); exit(1); }
$failed = 0;
foreach ($want ?: array_keys($all) as $prefix) {
    $t0 = microtime(true);
    $r  = moop_ontology_build($prefix);
    $s  = sprintf('%.1fs', microtime(true) - $t0);
    if ($r['ok']) {
        $c = $r['counts'];
        printf("%-6s release %-11s %5d terms (%d named), %5d synonyms, %6d links, %d obsolete, %d rows skipped  [%s]\n",
               $prefix, $r['release'] ?: '?', $c['terms'], $c['labelled'], $c['synonyms'], $c['closure'], $c['obsolete'], $r['skipped'], $s);
    } else {
        $failed++;
        printf("%-6s FAILED: %s  [%s] -- the previous file, if any, is unchanged\n", $prefix, $r['error'], $s);
    }
}
exit($failed ? 1 : 0);
