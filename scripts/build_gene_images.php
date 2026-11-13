<?php
/**
 * Build organisms/<Organism>/gene_images.sqlite from organisms/<Organism>/.images/images.tsv
 * (lib/gene_images.php; format in docs/ORGANISM_SIDE_FILES.md §2).
 *   php scripts/build_gene_images.php [Organism ...]     (default: every organism with .images/images.tsv)
 * Run as the files' owner. Prints the counts and the review list (what could not be used).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/gene_images.php';

$orgs = array_slice($argv, 1);
if (!$orgs) {
    foreach (glob(rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/*/.images/images.tsv') ?: [] as $f) {
        $orgs[] = basename(dirname($f, 2));
    }
}
if (!$orgs) { echo "No organism has .images/images.tsv\n"; exit(0); }

$known  = moop_images_known_terms();
if (!$known) echo "Note: no anatomy ontology built -- terms are not checked (scripts/build_ontologies.php)\n";
$failed = 0;
foreach ($orgs as $org) {
    $t0 = microtime(true);
    $r  = moop_images_build($org, $known);
    if (!$r['ok']) { $failed++; printf("%s FAILED: %s -- the previous file, if any, is unchanged\n", $org, $r['error']); }
    else {
        $c = $r['counts'];
        printf("%s: %d rows read -> %d images, %d gene/transcript IDs, %d data set(s), %d tagged with %d terms  [%.1fs]\n",
               $org, $c['rows_read'], $c['images'], $c['accessions'], $c['data_sets'], $c['tagged'], $c['terms'], microtime(true) - $t0);
    }
    foreach ($r['review'] as $kind => $details) {
        printf("  review: %-60s %6d rows, e.g. %s\n", $kind, array_sum($details), implode(' | ', array_slice(array_keys($details), 0, 3)));
    }
}
exit($failed ? 1 : 0);
