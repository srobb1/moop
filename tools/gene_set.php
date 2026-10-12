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
// Try accession directory first (the actual on-disk name), fall back to genome_name
$genome_directory = $genome_accession;   // the directory the files were actually found in
$all_fasta_files = getAssemblyFastaFiles($organism_name, $genome_accession);
if (empty($all_fasta_files) && $genome_name && $genome_name !== $genome_accession) {
    $all_fasta_files = getAssemblyFastaFiles($organism_name, $genome_name);
    if (!empty($all_fasta_files)) $genome_directory = $genome_name;
}
$fasta_files = array_values(array_filter($all_fasta_files, fn($f) => ($f['gene_set'] ?? '') === $gene_set_name));

// The ASSEMBLY's genome FASTA, offered here as well as on the assembly page: someone
// working with a gene set usually wants the sequence it was called against. gene_set=''
// is what marks it assembly-level.
//
// This used to be worked out in the page, with a second lookup that tried genome_name
// FIRST, then sent genome_name as the download directory whenever any file was found --
// even when it had been found under the accession. genome_name is not a directory
// ("COKUS1KC" vs "GCA_027627225.1"), so the Genome button on the gene set page returned
// 400 "Invalid assembly path" for 69 of 72 gene sets that have a genome (measured
// 2026-10-12; 72 of 72 work after). The directory is now
// the one the lookup above actually succeeded in.
$genome_file = null;
foreach ($all_fasta_files as $gtype => $ginfo) {
    if (($ginfo['gene_set'] ?? '') === '') {
        $genome_file = ['type' => $gtype, 'info' => $ginfo];
        break;
    }
}

$gs_dir  = "$organism_data/$organism_name/$genome_directory/$gene_set_name";
$gff_name = genes_gff_filename();
$has_gff = file_exists("$gs_dir/$gff_name") && filesize("$gs_dir/$gff_name") > 0;

$display_config = [
    'title'        => htmlspecialchars($gene_set_name) . ' — ' . htmlspecialchars($genome_accession) . ' - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/gene_set.php',
    'page_script'  => [
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
$gene_set_meta = loadJsonFile($gene_set_meta_file, []);

// Load annotation type summary from organism-level cache (grouped by type)
$annot_type_totals = [];
$annot_cache = moop_load_annotation_sources_cache($organism_name);
if ($annot_cache !== null) {
    foreach ($annot_cache as $type => $sources) {
        $annot_type_totals[$type] = array_sum(array_column($sources, 'count'));
    }
    arsort($annot_type_totals);
}

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
    'genome_file'          => $genome_file,
    'genome_directory'     => $genome_directory,
    'has_gff'              => $has_gff,
    'site'                 => $site,
    'siteTitle'            => $siteTitle,
    'config'               => $config,
    'db_path'              => $db_path,
    'images_path'          => $config->getString('images_path'),
    'absolute_images_path' => $config->getPath('absolute_images_path'),
    'annot_type_totals'    => $annot_type_totals,
];

include_once __DIR__ . '/display-template.php';
?>
