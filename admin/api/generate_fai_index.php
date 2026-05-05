<?php
/**
 * API endpoint to generate a samtools FAI index for a genome FASTA file.
 * Called via AJAX from organism_checklist.php.
 */

include_once __DIR__ . '/../admin_init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$organism = $_POST['organism'] ?? '';
$assembly = $_POST['assembly'] ?? '';

if (empty($organism) || empty($assembly)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$organism_data = $config->getPath('organism_data');
$organism_data = realpath($organism_data);
if (!$organism_data) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Could not resolve organism data path']);
    exit;
}

// Validate that the assembly dir resolves under organism_data (path traversal guard)
$assembly_dir = realpath("$organism_data/$organism/$assembly");
if (!$assembly_dir || strpos($assembly_dir, $organism_data) !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid organism or assembly path']);
    exit;
}

$fasta = "$assembly_dir/genome.fa";
if (!file_exists($fasta)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'genome.fa not found in assembly directory']);
    exit;
}

if (!is_writable($assembly_dir)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Assembly directory is not writable']);
    exit;
}

// Locate samtools
$samtools = trim(shell_exec('which samtools 2>/dev/null'));
if (empty($samtools)) {
    $samtools = '/usr/local/bin/samtools';
}
if (!is_executable($samtools)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'samtools not found or not executable']);
    exit;
}

$cmd    = escapeshellarg($samtools) . ' faidx ' . escapeshellarg($fasta) . ' 2>&1';
$output = [];
$rc     = 0;
exec($cmd, $output, $rc);

header('Content-Type: application/json');
if ($rc === 0 && file_exists("$fasta.fai")) {
    echo json_encode(['success' => true, 'message' => 'FAI index generated successfully']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'samtools faidx failed: ' . implode(' ', $output),
    ]);
}
?>
