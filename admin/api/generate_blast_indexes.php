<?php
/**
 * API endpoint to generate BLAST indexes for FASTA files
 * Called via AJAX from organism_checklist.php
 */

include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/blast_functions.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Get parameters
$organism  = $_POST['organism']   ?? '';
$assembly  = $_POST['assembly']   ?? '';
$gene_set  = $_POST['gene_set']   ?? '';   // a gene-set dir, or 'blast_only' (the assembly's own genome)
$fasta_file = $_POST['fasta_file'] ?? '';

// Validate parameters. No invented 'v1' when gene_set is absent: that indexed a gene set nobody named.
if (empty($organism) || empty($assembly) || $gene_set === '' || empty($fasta_file)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$organism_data = $config->getPath('organism_data');

// Resolve to real path in case of symlinks
$organism_data = realpath($organism_data);
if (!$organism_data) {
    echo json_encode([
        'success' => false,
        'message' => 'Could not resolve organism data path'
    ]);
    exit;
}

// Generate BLAST indexes
$result = generateBlastIndexes($organism, $assembly, $fasta_file, $organism_data, $gene_set);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($result);
?>
