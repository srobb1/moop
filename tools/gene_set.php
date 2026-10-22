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
include_once __DIR__ . '/../lib/busco.php';

$organism_data = $config->getPath('organism_data');
$siteTitle     = $config->getString('siteTitle');

// Validate params
$organism_name  = validateOrganismParam($_GET['organism'] ?? '');
$assembly_param = (string)($_GET['assembly'] ?? '');
if ($assembly_param === '') {
    moop_not_found_page('This link does not name an assembly.');
}
$gene_set_param = trim($_GET['gene_set'] ?? '');

if (empty($gene_set_param)) {
    moop_not_found_page('This link does not name a gene set.');
}

// Setup organism context (validates, loads info, checks top-level access)
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
$organism_info    = $organism_context['info'];

// Verify database
$db_path = verifyOrganismDatabase($organism_name, $organism_data);

// Check gene-set-level access
// Not yours: the same answer as a gene set that does not exist (see the not-found page's hint).
if (!has_gene_set_access($organism_name, $assembly_param, $gene_set_param)) {
    moop_not_found_page('There is no gene set "' . $gene_set_param . '" in assembly "' . $assembly_param . '" that you can open.');
}

// Load gene set stats (gene/mRNA counts from DB)
$gene_set_info = getGeneSetStats($assembly_param, $gene_set_param, $db_path);
if (empty($gene_set_info)) {
    moop_not_found_page('There is no gene set "' . $gene_set_param . '" in assembly "' . $assembly_param . '" that you can open.');
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
    'title'        => htmlspecialchars($gene_set_name) . ' — ' . htmlspecialchars($genome_accession),
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

// Optional gene set metadata (source, date_added, note, etc.) and BUSCO completeness, both
// from $gs_dir -- the directory the files were actually found in. geneset.json used to be read
// from the accession directory regardless, the same mistake that broke the Genome button.
$gene_set_meta = loadJsonFile("$gs_dir/geneset.json", []);
// Derived by the pipeline (notes/BUSCO_GENE_SET_SPEC.md). No file -> null -> no card.
$busco = moop_load_busco($gs_dir);

// Annotation Summary: THIS gene set's counts, by type. It used to read the organism-wide cache,
// so on an organism with several gene sets (6 on 2026-10-20) every gene set page showed the
// same, organism-wide numbers under its own name. The per-gene-set counts are built by
// housekeeping's background run; until they exist (or after a reload, until rebuilt) the
// summary is left out rather than filled with the organism's figures.
// Per type, how many GENES carry it -- a distinct count, not the sum of the type's sources
// (one gene with both Pfam and InterPro is one Domains gene).
$annot_type_totals = moop_load_gene_set_annotation_type_genes($organism_name, $genome_accession, $gene_set_name) ?? [];
arsort($annot_type_totals);

$data = [
    'gene_set_info'        => $gene_set_info,
    'gene_set_meta'        => $gene_set_meta,
    // The Expression tool's button appears only when this gene set has expression data the
    // visitor may see (reads each experiment's header line only).
    'has_expression'       => (function () use ($organism_name, $genome_accession, $gene_set_name) {
        require_once __DIR__ . '/../lib/expression_functions.php';
        $dir = moop_expression_bundle_dir($organism_name, $genome_accession, $gene_set_name);
        if (!is_dir($dir)) return false;
        $ov = moop_expression_load_overrides(rtrim(ConfigManager::getInstance()->getPath('metadata_path'), '/') . '/expression_overrides.json');
        return (bool)moop_expression_bundle_experiments($dir, "$organism_name/$genome_accession/$gene_set_name/", get_access_level(), $ov);
    })(),
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
    'busco'                => $busco,
];

include_once __DIR__ . '/display-template.php';
?>
