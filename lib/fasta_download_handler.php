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

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/download_filename.php';
moop_session_start();

// Get parameters
$organism = trim($_GET['organism'] ?? '');
$assembly = trim($_GET['assembly'] ?? '');
$genome_directory = trim($_GET['genome_directory'] ?? '');
$gene_set = trim($_GET['gene_set'] ?? '');
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

// If gene_set is provided, files live in a subdir of the assembly dir
if (!empty($gene_set)) {
    $assembly_dir = "$assembly_dir/$gene_set";
}

// Prevent path traversal: resolve the real path and confirm it stays within organism_data.
// realpath() works on filesystem paths (not web URLs) and resolves any ../ sequences.
$real_organism_data = realpath($organism_data);
$real_assembly_dir  = realpath($assembly_dir);

if ($real_organism_data === false || $real_assembly_dir === false ||
    strpos($real_assembly_dir, $real_organism_data . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(400);
    die('Error: Invalid assembly path.');
}
$assembly_dir = $real_assembly_dir;

if (!is_dir($assembly_dir)) {
    http_response_code(404);
    die('Error: Assembly directory not found.');
}

// Ask by TYPE, via the shared helper, rather than reaching into the config array — the
// file name is admin-editable (Manage Site Configuration -> Sequence File Types) and this
// is the one place that looks up a single named type rather than iterating all of them.
$filename = sequence_filename($type);
$files    = $filename !== null ? glob("$assembly_dir/$filename") : [];

if (empty($files)) {
    http_response_code(404);
    die("Error: FASTA file not found for sequence type '$type'.");
}

$fasta_file = $files[0];

if (!file_exists($fasta_file)) {
    http_response_code(404);
    die('Error: FASTA file does not exist.');
}

// Download filename.
//
// 🔴 This was building the name from $pattern — a variable that is never assigned in this
// file. It evaluated to '', so the one part that said WHICH sequence you asked for was
// empty and moop_download_filename() dropped it. Every type produced the identical name:
//
//     sequences_Anoura_caudifer_GCA_004027475.1_2026-08-04.fa
//
// Download protein, then CDS, then transcript from the gene set page and the browser hands
// you that name three times, so you get " (1)" and " (2)" and no way to tell which is
// which. Reported from a real Downloads folder 2026-08-04.
//
// Named by TYPE rather than the generic "sequences": the type IS the thing that
// distinguishes these files, so it leads. The gene set joins the scope for the same reason
// it is in the GFF download's name — one assembly can carry more than one.
$pattern      = sequence_filename($type) ?? '';
$pattern_base = preg_replace('/\.(fa|fasta|faa|fna|txt|gz)$/i', '', $pattern);
$pattern_ext  = ltrim(substr($pattern, strlen($pattern_base)), '.') ?: 'fa';
$filename = moop_download_filename($type, [$organism, $assembly, $gene_set], $pattern_ext);

// Get file size
$file_size = filesize($fasta_file);

// Send download headers
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=\"" . addcslashes($filename, '"\\') . "\"");
header('Content-Length: ' . $file_size);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Stream the file.
//
// 🔴 DISCARD THE GUARD BUFFER FIRST. This file opens ob_start() at the top to swallow stray
// output from includes, and readfile() writes into whatever buffer is open — so with it
// still active the ENTIRE file is accumulated in memory before anything reaches the client.
// genome.fa for Anoura_caudifer is 2.2 GB against memory_limit 128M: fatal, and the browser
// gets a 500 with no clue why. protein.aa.fa is 15 MB, fits under the limit, and worked —
// which is exactly why this looked like "genome downloads are broken" rather than a
// buffering bug. Reported 2026-08-04.
//
// ob_end_clean() rather than ob_end_flush(): anything sitting in that buffer is by
// definition stray output, and prepending it to a FASTA would corrupt the file. Headers are
// unaffected — header() queues them independently of the output buffer.
while (ob_get_level() > 0) {
    ob_end_clean();
}
readfile($fasta_file);
exit;
?>
