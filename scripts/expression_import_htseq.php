#!/usr/bin/env php
<?php
/**
 * Convert a directory of per-sample htseq-count files into an expression bundle.
 *
 * This is a CONVERTER, not part of the build: MOOP's builder reads only bundles
 * (notes/EXPRESSION_COUNT_TABLES_PLAN.md), so how data is delivered can change without touching
 * it. When the compute box starts producing bundles itself, this script is simply no longer needed.
 *
 * Usage:
 *   php scripts/expression_import_htseq.php --counts-dir=~/expression_import/nvec/htseq/ \
 *       --out=organisms/Nematostella_vectensis/GCA_033964005.1/NV2/expression/Bazzini_amanitin_timecourse
 *
 * Writes (always regenerated from the count files):
 *   counts.tsv   gene × sample raw counts, NA where a gene is missing from a sample's file
 *   qc.tsv       per sample: reads assigned to genes + every htseq "__" counter
 * Writes only if ABSENT (they are meant to be edited by hand, and are never overwritten):
 *   samples.tsv      sample_id + group (= sample_id) — fill in groups and labels
 *   experiment.json  label = directory name, access_level COLLABORATOR until someone decides
 *   provenance.json  quantifier = htseq-count, the rest left for the person who ran it
 *
 * The count files themselves are only read. Original data stays original.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}
// Batch tool: one experiment's genes × samples matrix is held in memory (a 25k-gene × 100-sample
// experiment is several hundred MB as PHP arrays). The web never runs this.
ini_set('memory_limit', '4G');

$opts = getopt('', ['counts-dir:', 'out:', 'help']);
if (isset($opts['help']) || empty($opts['counts-dir']) || empty($opts['out'])) {
    fwrite(STDERR, "Usage: php scripts/expression_import_htseq.php --counts-dir=DIR --out=BUNDLE_DIR\n");
    exit(2);
}
$counts_dir = rtrim($opts['counts-dir'], '/');
$out        = rtrim($opts['out'], '/');

require_once dirname(__DIR__) . '/lib/expression_functions.php';

try {
    $m = moop_expression_read_count_dir($counts_dir);
} catch (RuntimeException $e) {
    fwrite(STDERR, "Cannot read the count files: " . $e->getMessage() . "\n");
    exit(1);
}
if (!is_dir($out) && !mkdir($out, 0775, true)) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(1);
}

// counts.tsv — ids sorted so the file is stable from run to run.
$ids = array_keys($m['rows']);
sort($ids, SORT_STRING);
$fh = fopen("$out/counts.tsv", 'w');
fwrite($fh, "gene_id\t" . implode("\t", $m['samples']) . "\n");
foreach ($ids as $id) {
    $cells = array_map(fn($v) => $v === null ? 'NA' : (string)(int)$v, $m['rows'][$id]);
    fwrite($fh, "$id\t" . implode("\t", $cells) . "\n");
}
fclose($fh);

// qc.tsv — every "__" counter any file reported, as a column.
$special_cols = [];
foreach ($m['special'] as $counters) $special_cols += array_fill_keys(array_keys($counters), true);
$special_cols = array_keys($special_cols);
sort($special_cols);
$fh = fopen("$out/qc.tsv", 'w');
fwrite($fh, "sample_id\tassigned_to_genes\t" . implode("\t", $special_cols) . "\n");
foreach ($m['samples'] as $i => $name) {
    $assigned = (int)array_sum(array_map(fn($row) => $row[$i] ?? 0, $m['rows']));
    $cells = array_map(fn($c) => (string)($m['special'][$name][$c] ?? 'NA'), $special_cols);
    fwrite($fh, "$name\t$assigned\t" . implode("\t", $cells) . "\n");
}
fclose($fh);

$created = [];
if (!file_exists("$out/samples.tsv")) {
    $lines = ["sample_id\tgroup\treplicate"];
    foreach ($m['samples'] as $name) $lines[] = "$name\t$name\t";
    file_put_contents("$out/samples.tsv", implode("\n", $lines) . "\n");
    $created[] = 'samples.tsv';
}
if (!file_exists("$out/experiment.json")) {
    file_put_contents("$out/experiment.json", json_encode([
        'label' => basename($out), 'summary' => '', 'lab' => '', 'citation' => '', 'project_accession' => '',
        'assay' => 'bulk_rna',
        // Unpublished data is common here; make "who may see this" a decision, not a default.
        'access_level' => 'COLLABORATOR',
        'contact' => '',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $created[] = 'experiment.json';
}
if (!file_exists("$out/provenance.json")) {
    file_put_contents("$out/provenance.json", json_encode([
        'quantifier' => 'htseq-count', 'quantifier_version' => '', 'quantifier_params' => '',
        'aligner' => '', 'aligner_version' => '', 'genome' => '', 'annotation_gff' => '', 'annotation_gff_md5' => '',
        'strandedness' => '', 'run_by' => '', 'run_date' => '',
        'converted_from' => realpath($counts_dir) ?: $counts_dir,
        'converted_at' => gmdate('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $created[] = 'provenance.json';
}

printf("%d samples, %d gene ids → %s/counts.tsv and qc.tsv\n", count($m['samples']), count($ids), $out);
if ($created) echo "Created for you to fill in: " . implode(', ', $created) . "\n";
