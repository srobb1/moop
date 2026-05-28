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
$uniquename = trim($_GET['uniquename']);   // escapeshellarg handles shell safety below

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

$gff_file = "$organism_data/$organism_name/$assembly/$gene_set/genomic.gff";
if (!file_exists($gff_file) || filesize($gff_file) === 0) {
    http_response_code(404);
    echo "# GFF not available for $organism_name / $assembly\n";
    exit;
}

// --- Collect GFF lines for gene and all descendants ---

// Level 1: the gene line itself
// Handles bare IDs (ID=UNIQUENAME) and type-prefixed IDs (ID=gene:UNIQUENAME).
$gene_lines = [];
exec('grep -m1 -E ' . escapeshellarg('ID=[^;:]*:?' . preg_quote($uniquename) . '(;|$)') . ' ' . escapeshellarg($gff_file), $gene_lines);

// Level 2: direct children (mRNA, ncRNA, pseudogenic_transcript, etc.)
$child_lines = [];
exec('grep -E ' . escapeshellarg('Parent=[^;:]*:?' . preg_quote($uniquename) . '(;|$)') . ' ' . escapeshellarg($gff_file), $child_lines);

// Extract child IDs for the grandchild lookup
$child_ids = [];
foreach ($child_lines as $line) {
    $parts = explode("\t", $line);
    if (count($parts) >= 9 && preg_match('/ID=([^;]+)/', $parts[8], $m)) {
        $child_ids[] = $m[1];
    }
}

// Level 3: grandchildren (exon, CDS, UTR, etc.) — all fetched in one grep pass
$grandchild_lines = [];
if (!empty($child_ids)) {
    $patterns = array_map(fn($id) => '-e ' . escapeshellarg('Parent=' . $id . ';'), $child_ids);
    exec('grep -F ' . implode(' ', $patterns) . ' ' . escapeshellarg($gff_file), $grandchild_lines);
    if (empty($grandchild_lines)) {
        $patterns = array_map(fn($id) => '-e ' . escapeshellarg('Parent=' . $id), $child_ids);
        exec('grep -F ' . implode(' ', $patterns) . ' ' . escapeshellarg($gff_file), $grandchild_lines);
    }
}

// Output GFF3: gene first, then children, then grandchildren (parent-before-child order)
echo "##gff-version 3\n";
foreach (array_merge($gene_lines, $child_lines, $grandchild_lines) as $line) {
    echo $line . "\n";
}
