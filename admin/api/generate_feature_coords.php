<?php
/**
 * Generate feature_coords.tsv for a given assembly.
 * Streams genomic.gff and writes organism|assembly-level feature → genomic coord mapping.
 * Called from Manage BLAST Linkouts admin page.
 */

include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/blast_functions.php';

header('Content-Type: application/json');

$organism = trim($_POST['organism'] ?? '');
$assembly = trim($_POST['assembly'] ?? '');

if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $organism)
 || !preg_match('/^[A-Za-z0-9_\-\.]+$/', $assembly)) {
    echo json_encode(['success' => false, 'message' => 'Invalid organism or assembly name.']);
    exit;
}

$assembly_path = $config->getPath('organism_data') . '/' . $organism . '/' . $assembly;

if (!is_dir($assembly_path)) {
    echo json_encode(['success' => false, 'message' => 'Assembly directory not found.']);
    exit;
}

if (!file_exists($assembly_path . '/genomic.gff')) {
    echo json_encode(['success' => false, 'message' => 'genomic.gff not found in assembly directory.']);
    exit;
}

set_time_limit(0);

$ok = generateFeatureCoordsIndex($assembly_path);

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Generation failed — check that genomic.gff is readable and well-formed.']);
    exit;
}

$tsv_file = $assembly_path . '/feature_coords.tsv';
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
