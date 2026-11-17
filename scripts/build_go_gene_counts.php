<?php
/**
 * Count genes per GO term for each organism (lib/go_gene_counts.php; docs/ONTOLOGIES.md).
 *
 *   php scripts/build_go_gene_counts.php                      every stale organism, no time limit
 *   php scripts/build_go_gene_counts.php Acropora_millepora   just these (recounted even if current)
 *   php scripts/build_go_gene_counts.php --force              every organism, current or not
 *
 * Housekeeping does the same in its background run, a few minutes per run. Needs GO built first
 * (scripts/build_ontologies.php GO). Run as the web user where possible (sudo -u apache php ...).
 * Exit 1 if any organism failed.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/go_gene_counts.php';

$args  = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$only  = array_values(array_filter($args, fn($a) => $a !== '--force'));
$organism_data = ConfigManager::getInstance()->getPath('organism_data');

$t0 = microtime(true);
$r  = moop_go_counts_refresh($organism_data, PHP_INT_MAX, $only, $force || (bool) $only);
if (!$r['go']) { fwrite(STDERR, "GO is not built on this site: php scripts/build_ontologies.php GO\n"); exit(1); }
foreach ($r['built'] as $org => $b) {
    printf("%-40s %3d gene set(s) %6d terms  [%.1fs]\n", $org, $b['gene_sets'], $b['terms'], $b['seconds']);
}
foreach ($r['failed'] as $org => $err) printf("%-40s FAILED: %s\n", $org, $err);
printf("%d counted, %d already current, %d failed  [%.0fs]\n", count($r['built']), $r['current'], count($r['failed']), microtime(true) - $t0);
exit($r['failed'] ? 1 : 0);
