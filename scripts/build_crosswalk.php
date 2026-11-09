<?php
/**
 * Build the crosswalk file(s) (lib/crosswalk.php) from the configured mapping folders.
 *   php scripts/build_crosswalk.php [Organism ...]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/crosswalk.php';
$failed = 0;
foreach (array_slice($argv, 1) ?: array_keys(moop_crosswalk_sources()) as $org) {
    $t0 = microtime(true);
    $r  = moop_crosswalk_build($org);
    if (!$r['ok']) { $failed++; printf("%s FAILED: %s -- the previous file, if any, is unchanged\n", $org, $r['error']); continue; }
    $c = $r['counts'];
    printf("%s: %d mappings, %d accessions, %d reference genes, %d sources, %d rows skipped  [%.1fs, %s]\n",
           $org, $c['rows'], $c['accessions'], $c['genes'], $c['sources'], $r['skipped'], microtime(true) - $t0,
           round(filesize(moop_crosswalk_file($org)) / 1048576) . ' MB');
}
exit($failed ? 1 : 0);
