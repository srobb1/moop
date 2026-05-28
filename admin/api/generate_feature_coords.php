<?php
/**
 * Generate feature_coords.tsv for a given gene set.
 * Streams genomic.gff and writes feature → genomic coord mapping.
 * Called from Manage BLAST Linkouts admin page.
 */

include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/blast_functions.php';

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

if (!file_exists($gene_set_path . '/genomic.gff')) {
    echo json_encode(['success' => false, 'message' => 'genomic.gff not found in gene set directory.']);
    exit;
}

set_time_limit(0);

$ok = generateFeatureCoordsIndex($gene_set_path);

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Generation failed — check that genomic.gff is readable and well-formed.']);
    exit;
}

$tsv_file = $gene_set_path . '/feature_coords.tsv';
$line_count = 0;
if (file_exists($tsv_file)) {
    $fh = fopen($tsv_file, 'r');
    while (fgets($fh) !== false) $line_count++;
    fclose($fh);
}

echo json_encode([
    'success'  => true,
    'message'  => 'Generated feature_coords.tsv',
    'features' => $line_count,
    'modified' => date('Y-m-d H:i', filemtime($tsv_file)),
]);
