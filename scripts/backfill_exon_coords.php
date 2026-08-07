<?php
/**
 * Backfill exon_coords.tsv across every gene set that lacks one.
 *
 * The index is written automatically at JBrowse gene-set registration, so only
 * gene sets registered BEFORE that step existed need this -- which on this
 * deployment was 71 of 72. Safe to re-run: --force is required to rewrite a
 * gene set that already has one.
 *
 * Usage:
 *   php scripts/backfill_exon_coords.php [--force] [--only=Organism_name]
 */

$BASE = dirname(__DIR__);
require_once $BASE . '/includes/config_init.php';          // genes_gff_filename()
require_once $BASE . '/lib/jbrowse/gene_set_functions.php';

$force = in_array('--force', $argv, true);
$only  = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--only=') === 0) {
        $only = substr($arg, 7);
    }
}

$gffs = glob($BASE . '/organisms/*/*/*/' . genes_gff_filename()) ?: [];
sort($gffs);

$done = $skipped = $failed = 0;
$started = microtime(true);

echo "Scanning " . count($gffs) . " gene sets...\n\n";

foreach ($gffs as $n => $gff) {
    $dir   = dirname($gff);
    $label = implode('/', array_slice(explode('/', $dir), -3));

    if ($only !== null && strpos($label, $only) === false) {
        continue;
    }

    $tsv = $dir . '/exon_coords.tsv';
    if (file_exists($tsv) && !$force) {
        $skipped++;
        continue;
    }

    // Progress per gene set, not one spinner for eleven gigabytes: this streams
    // 11.3 GB of GFF and a single "working..." would say nothing for minutes.
    printf("[%2d/%2d] %-62s ", $n + 1, count($gffs), substr($label, 0, 62));
    flush();

    $t  = microtime(true);
    $ok = generateExonCoordsIndex($dir);
    $el = microtime(true) - $t;

    if (!$ok || !file_exists($tsv)) {
        echo "FAILED\n";
        $failed++;
        continue;
    }

    // An empty file is a real outcome (a GFF with no exon features) and must not
    // be reported as success -- it would leave the browser links missing with
    // every status light green.
    $rows = (int)trim(shell_exec('wc -l < ' . escapeshellarg($tsv)));
    if ($rows === 0) {
        printf("EMPTY — no exon features in the GFF (%.1fs)\n", $el);
        $failed++;
        continue;
    }

    printf("%7s rows  %6.1f MB  %5.1fs\n", number_format($rows), filesize($tsv) / 1048576, $el);
    $done++;
}

printf("\n%s\ngenerated: %d   skipped (already present): %d   failed: %d   total %.1f min\n",
    str_repeat('-', 60), $done, $skipped, $failed, (microtime(true) - $started) / 60);

exit($failed > 0 ? 1 : 0);
