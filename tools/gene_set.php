<?php
/**
 * GENE SET DISPLAY PAGE
 *
 * Shows stats, downloads, and annotation search for a single gene set.
 *
 * URL Parameters:
 * - organism: Organism name (required)
 * - assembly:  Assembly accession (required)
 * - gene_set:  Gene set name (required)
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/functions_data.php';

$organism_data = $config->getPath('organism_data');
$siteTitle     = $config->getString('siteTitle');

// Validate params
$organism_name  = validateOrganismParam($_GET['organism'] ?? '');
$assembly_param = validateAssemblyParam($_GET['assembly'] ?? '', "/$site/access_denied.php");
$gene_set_param = trim($_GET['gene_set'] ?? '');

if (empty($gene_set_param)) {
    die("Error: Missing gene_set parameter.");
}

// Setup organism context (validates, loads info, checks top-level access)
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
$organism_info    = $organism_context['info'];

// Verify database
$db_path = verifyOrganismDatabase($organism_name, $organism_data);

// Check gene-set-level access
if (!has_gene_set_access($organism_name, $assembly_param, $gene_set_param)) {
    header("Location: /$site/access_denied.php");
    exit;
}

// Load gene set stats (gene/mRNA counts from DB)
$gene_set_info = getGeneSetStats($assembly_param, $gene_set_param, $db_path);
if (empty($gene_set_info)) {
    die("Error: Gene set '" . htmlspecialchars($gene_set_param) . "' not found for assembly '" . htmlspecialchars($assembly_param) . "'.");
}

$genome_accession = $gene_set_info['genome_accession'];
$genome_name      = $gene_set_info['genome_name'];
$gene_set_id      = $gene_set_info['gene_set_id'];
$gene_set_name    = $gene_set_info['gene_set_name'];

// Get FASTA download files scoped to this gene_set only
$all_fasta_files = getAssemblyFastaFiles($organism_name, $genome_name ?: $genome_accession);
$fasta_files = array_values(array_filter($all_fasta_files, fn($f) => ($f['gene_set'] ?? '') === $gene_set_name));

$display_config = [
    'title'        => htmlspecialchars($gene_set_name) . ' — ' . htmlspecialchars($genome_accession) . ' - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/gene_set.php',
    'page_script'  => [
        "/$site/js/modules/search-utils.js",
        "/$site/js/gene_set-display.js",
    ],
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const assemblyAccession = '" . addslashes($genome_accession) . "';",
        "const geneSetName = '" . addslashes($gene_set_name) . "';",
        "const organismName = '" . addslashes($organism_name) . "';",
        "const siteTitle = '" . addslashes($siteTitle) . "';",
    ],
];

// Load optional gene set metadata (source, date_added, note, etc.)
$gene_set_meta_file = "$organism_data/$organism_name/$genome_accession/$gene_set_name/geneset.json";
$gene_set_meta = file_exists($gene_set_meta_file)
    ? (json_decode(file_get_contents($gene_set_meta_file), true) ?? [])
    : [];

$data = [
    'gene_set_info'        => $gene_set_info,
    'gene_set_meta'        => $gene_set_meta,
    'organism_name'        => $organism_name,
    'organism_info'        => $organism_info,
    'genome_accession'     => $genome_accession,
    'genome_name'          => $genome_name,
    'gene_set_name'        => $gene_set_name,
    'gene_set_id'          => $gene_set_id,
    'fasta_files'          => $fasta_files,
    'site'                 => $site,
    'siteTitle'            => $siteTitle,
    'config'               => $config,
    'db_path'              => $db_path,
    'images_path'          => $config->getString('images_path'),
    'absolute_images_path' => $config->getPath('absolute_images_path'),
];

include_once __DIR__ . '/display-template.php';
?>
