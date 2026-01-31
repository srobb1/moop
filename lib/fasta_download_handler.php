<?php
/**
 * FASTA File Download Handler
 * 
 * Serves full FASTA files with prepended organism name and assembly accession
 * URL format: fasta_download_handler.php?organism=Org_name&assembly=GCA_xxx&type=cds
 * Downloaded filename: Org_name.GCA_xxx.cds.nt.fa
 */

// Start output buffering FIRST to catch any stray output from includes
ob_start();

session_start();

// Get parameters
$organism = trim($_GET['organism'] ?? '');
$assembly = trim($_GET['assembly'] ?? '');
$genome_directory = trim($_GET['genome_directory'] ?? '');
$type = trim($_GET['type'] ?? '');

include_once __DIR__ . '/../includes/config_init.php';
include_once __DIR__ . '/../includes/access_control.php';
include_once __DIR__ . '/moop_functions.php';

// Get config
$config = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();
$site = $config->getString('site');

// Clean output buffer - discard any stray output from includes
ob_end_clean();

// Validate parameters
if (empty($organism) || empty($assembly) || empty($type)) {
    http_response_code(400);
    die('Error: Missing required parameters (organism, assembly, type).');
}

// Validate sequence type
if (!isset($sequence_types[$type])) {
    http_response_code(400);
    die('Error: Invalid sequence type.');
}

// Check access
if (!has_assembly_access($organism, $assembly)) {
    if (!is_logged_in()) {
        header("Location: /$site/login.php");
    } else {
        header("Location: /$site/access_denied.php");
    }
    exit;
}

// Find FASTA file
$assembly_dir = "$organism_data/$organism/$assembly";

// If genome_directory is provided, use that instead
if (!empty($genome_directory)) {
    $assembly_dir = "$organism_data/$organism/$genome_directory";
}

if (!is_dir($assembly_dir)) {
    http_response_code(404);
    die('Error: Assembly directory not found.');
}

$pattern = $sequence_types[$type]['pattern'];
$files = glob("$assembly_dir/$pattern");

if (empty($files)) {
    http_response_code(404);
    die("Error: FASTA file not found for sequence type '$type'.");
}

$fasta_file = $files[0];

if (!file_exists($fasta_file)) {
    http_response_code(404);
    die('Error: FASTA file does not exist.');
}

// Generate download filename with organism and assembly prefix
$filename = "{$organism}.{$assembly}.{$pattern}";

// Get file size
$file_size = filesize($fasta_file);

// Send download headers
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename={$filename}");
header('Content-Length: ' . $file_size);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Stream the file
readfile($fasta_file);
exit;
?>
