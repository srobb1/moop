<?php
/**
 * Generate exon_coords.tsv for a given gene set.
 *
 * Streams genes.gff and writes transcript → exon-block mapping. It is what lets
 * Primer BLAST place a cDNA product on the genome, and so what gives a
 * junction-spanning primer a browser link at all (lib/primer/ExonMap.php).
 *
 * Written automatically at JBrowse gene-set registration; this endpoint exists
 * for gene sets registered BEFORE that step was added, which is most of them.
 * Called from Manage BLAST Linkouts.
 */

include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/jbrowse/gene_set_functions.php';

header('Content-Type: application/json');

$organism = trim($_POST['organism'] ?? '');
$assembly = trim($_POST['assembly'] ?? '');
$gene_set = trim($_POST['gene_set'] ?? 'v1');

if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $organism)
 || !preg_match('/^[A-Za-z0-9_\-\.]+$/', $assembly)
 || !preg_match('/^[A-Za-z0-9_\-\.]+$/', $gene_set)) {
    echo json_encode(['success' => false, 'message' => 'Invalid organism, assembly, or gene set name.']);
    exit;
}

$gene_set_path = $config->getPath('organism_data') . '/' . $organism . '/' . $assembly . '/' . $gene_set;

if (!is_dir($gene_set_path)) {
    echo json_encode(['success' => false, 'message' => 'Gene set directory not found.']);
    exit;
}

if (!file_exists($gene_set_path . '/' . genes_gff_filename())) {
    echo json_encode(['success' => false, 'message' => genes_gff_filename() . ' not found in gene set directory.']);
    exit;
}

set_time_limit(0);

$ok = generateExonCoordsIndex($gene_set_path);

$tsv_file = $gene_set_path . '/exon_coords.tsv';

// generateExonCoordsIndex() opens the output file for writing, so a false return
// here is most often a PERMISSION problem rather than a malformed GFF -- and an
// unwritable path is exactly the failure this codebase keeps reporting as
// success. Name both causes so the reader knows where to look.
if (!$ok) {
    echo json_encode([
        'success' => false,
        'message' => 'Generation failed — check that ' . genes_gff_filename() . ' is readable and that '
                   . 'the gene set directory is writable by the web server.',
    ]);
    exit;
}

// A file that exists but holds nothing is a real outcome: a GFF with no exon
// features. Reporting that as success would leave the browser links silently
// missing with every status light green.
if (!file_exists($tsv_file) || filesize($tsv_file) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No exons found in ' . genes_gff_filename() . ' — nothing was written. '
                   . 'This gene set has no exon features to map.',
    ]);
    exit;
}

echo json_encode([
    'success'  => true,
    'message'  => 'Generated exon_coords.tsv',
    'tsv_size' => round(filesize($tsv_file) / 1048576, 1) . ' MB',
    'modified' => date('Y-m-d H:i', filemtime($tsv_file)),
]);
