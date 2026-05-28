<?php
/**
 * Download File API
 *
 * Securely streams an organism assembly file to the user.
 *
 * GET params:
 *   organism  - Organism directory name
 *   assembly  - Assembly directory name
 *   filename  - Filename within the assembly or gene_set directory
 *   gene_set  - Gene set name (optional; when provided, file is served from the gene_set subdir)
 *
 * Security:
 *   - Access checked via has_gene_set_access() (gene_set param) or has_assembly_access()
 *   - Path traversal prevented via realpath() + base-dir check
 *   - Blocked file types rejected by extension
 */

ob_start();
include_once __DIR__ . '/../tools/tool_init.php';
ob_end_clean();

$blocked_exts = array_flip([
    // BLAST nucleotide DB
    'ndb', 'nhr', 'nin', 'njs', 'nog', 'nos', 'not', 'nsq', 'ntf', 'nto',
    // BLAST protein DB
    'pdb', 'phr', 'pin', 'pjs', 'pog', 'pos', 'pot', 'psq', 'ptf', 'pto',
    // Internal system files
    'sqlite', 'json',
]);

$organism = trim($_GET['organism'] ?? '');
$assembly = trim($_GET['assembly'] ?? '');
$gene_set = trim($_GET['gene_set'] ?? '');
$filename = trim($_GET['filename'] ?? '');

if ($organism === '' || $assembly === '' || $filename === '') {
    http_response_code(400);
    exit('Missing required parameters.');
}

// Reject path separators or traversal in gene_set
if ($gene_set !== '' && (strpos($gene_set, '/') !== false || strpos($gene_set, '\\') !== false || strpos($gene_set, '..') !== false)) {
    http_response_code(400);
    exit('Invalid gene_set.');
}

// Reject any path separator or traversal attempt in filename
if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
    http_response_code(400);
    exit('Invalid filename.');
}

// Check extension against blocklist
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (isset($blocked_exts[$ext])) {
    http_response_code(403);
    exit('This file type is not available for download.');
}

// Verify access: gene_set-level when gene_set is provided, assembly-level otherwise
if ($gene_set !== '') {
    if (!has_gene_set_access($organism, $assembly, $gene_set)) {
        http_response_code(403);
        exit('Access denied.');
    }
} else {
    if (!has_assembly_access($organism, $assembly)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// Build and validate filesystem path (prevents path traversal)
$organism_data = $config->getPath('organism_data');
$subdir = $gene_set !== '' ? "$organism/$assembly/$gene_set" : "$organism/$assembly";
$base_dir = realpath("$organism_data/$subdir");

if ($base_dir === false || !is_dir($base_dir)) {
    http_response_code(404);
    exit('Assembly directory not found.');
}

$file_path = realpath("$base_dir/$filename");

if ($file_path === false) {
    http_response_code(404);
    exit('File not found.');
}

// Ensure resolved path is within the assembly directory
if (strpos($file_path, $base_dir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    exit('Invalid file path.');
}

if (!is_file($file_path)) {
    http_response_code(404);
    exit('File not found.');
}

// Map common bioinformatics extensions to MIME types
$mime_map = [
    'fa'    => 'text/plain',
    'fasta' => 'text/plain',
    'faa'   => 'text/plain',
    'fna'   => 'text/plain',
    'ffn'   => 'text/plain',
    'gff'   => 'text/plain',
    'gff3'  => 'text/plain',
    'gtf'   => 'text/plain',
    'bed'   => 'text/plain',
    'vcf'   => 'text/plain',
    'tsv'   => 'text/tab-separated-values',
    'csv'   => 'text/csv',
    'txt'   => 'text/plain',
    'fai'   => 'text/plain',
    'gz'    => 'application/gzip',
    'zip'   => 'application/zip',
    'bz2'   => 'application/x-bzip2',
];
$mime = $mime_map[$ext] ?? (mime_content_type($file_path) ?: 'application/octet-stream');

// Stream the file
$basename = basename($file_path);

if (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $basename) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-store');
header('Pragma: no-cache');

readfile($file_path);
exit;
