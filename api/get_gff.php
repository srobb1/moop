<?php
/**
 * Get GFF3 lines for a gene and all its descendants
 *
 * Returns the gene line plus all child and grandchild feature lines
 * (mRNA, exon, CDS, UTR, and any other sub-features present in the GFF).
 *
 * GET parameters:
 *   organism   - Organism name (required)
 *   assembly   - Assembly accession (required)
 *   uniquename - Gene feature uniquename (required)
 *   gene_set   - Gene set name (optional, defaults to 'v1')
 *
 * Returns: text/plain GFF3
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/moop_functions.php';

header('Content-Type: text/plain');

foreach (['organism', 'assembly', 'uniquename'] as $p) {
    if (empty($_GET[$p])) {
        http_response_code(400);
        echo "# Error: missing required parameter: $p\n";
        exit;
    }
}

$organism_data    = $config->getPath('organism_data');
$organism_context = setupOrganismDisplayContext($_GET['organism'], $organism_data, true);
$organism_name    = $organism_context['name'];

$assembly   = preg_replace('/[^a-zA-Z0-9._\-]/', '', $_GET['assembly']);
$gene_set   = preg_replace('/[^a-zA-Z0-9._\-]/', '', $_GET['gene_set'] ?? 'v1') ?: 'v1';
// escapeshellarg() stops shell injection; `-e` in front of it stops OPTION injection -- a
// value like "-f/some/file" was otherwise read by grep as an option, not a pattern.
$uniquename = trim($_GET['uniquename']);

if (empty($assembly) || empty($uniquename)) {
    http_response_code(400);
    echo "# Error: invalid parameter value\n";
    exit;
}

if (!has_gene_set_access($organism_name, $assembly, $gene_set)) {
    http_response_code(403);
    echo "# Error: access denied\n";
    exit;
}

$gff_file = "$organism_data/$organism_name/$assembly/$gene_set/" . genes_gff_filename();
if (!file_exists($gff_file) || filesize($gff_file) === 0) {
    http_response_code(404);
    echo "# GFF not available for $organism_name / $assembly\n";
    exit;
}

// --- The gene and everything under it: the ONE gene-model reader (lib/gff_gene_model.php) ---
// Ids are matched by whole value (ID, ID after one source prefix, or a RefSeq GeneID), parents by
// Parent= or geneID=. This file used to run its own three greps of the whole GFF, matching with
// substring patterns; the shared reader uses the tabix index when there is one.
require_once __DIR__ . '/../lib/gff_gene_model.php';
$model = moop_gene_model($organism_name, $assembly, $gene_set, $uniquename, '');

// Output GFF3: the record first, then its children, then theirs (parent-before-child order)
echo "##gff-version 3\n";
foreach ($model['lines'] ?? [] as $line) {
    echo $line . "\n";
}
