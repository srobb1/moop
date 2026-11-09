<?php
/**
 * Build the PAGE curation file(s) (lib/anatomy_expression.php) from the PAGE repository.
 *   php scripts/build_anatomy_expression.php [Organism ...]
 * Prints the counts and the review list (what could not be used, never silently dropped).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/anatomy_expression.php';
$failed = 0;
foreach (array_slice($argv, 1) ?: array_keys(moop_page_sources()) as $org) {
    $t0 = microtime(true);
    $r  = moop_page_build($org);
    if (!$r['ok']) { $failed++; printf("%s FAILED: %s -- the previous file, if any, is unchanged\n", $org, $r['error']); continue; }
    $c = $r['counts'];
    printf("%s: %d rows read -> %d curations, %d accessions, %d terms, %d papers (%d titled)  [%.1fs]\n",
           $org, $c['rows_read'], $c['curations'], $c['accessions'], $c['terms'], $c['publications'], $c['titled'], microtime(true) - $t0);
    foreach ($r['review'] as $kind => $details) {
        printf("  review: %-52s %4d rows, e.g. %s\n", $kind, array_sum($details), implode(' | ', array_slice(array_keys($details), 0, 3)));
    }
}
exit($failed ? 1 : 0);
