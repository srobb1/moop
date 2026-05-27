<?php
/**
 * JBrowse Admin API: Register Gene Set
 *
 * Prepares a gene set's GFF for JBrowse and registers the gene annotation track.
 * The assembly must already be registered before calling this endpoint.
 *
 * POST params:
 *   organism   - organism directory name (e.g. Anoura_caudifer)
 *   assembly   - assembly ID (e.g. GCA_004027475.1)
 *   gene_set   - gene set name (e.g. v1, OGS1.0)
 *   force      - 1 to re-build even if files already exist
 *   text_index - 1 to also build the Trix text-search index
 *   attributes - comma-separated GFF attributes for text-index [default: Name,ID]
 *
 * Steps performed:
 *   1. Create data/genomes/{org}/{asm}/{gene_set}/
 *   2. Symlink annotations.gff3 → source genomic.gff
 *   3. Sort + bgzip + tabix → annotations.gff3.gz + .tbi
 *   4. Create/update gene track JSON in jbrowse2-configs/tracks/
 *   5. Add track ID to assembly JSON primaryGeneTracks
 *   6. Generate feature_coords.tsv (for BLAST linkouts + MOOPmart)
 *   7. Optionally build Trix text-search index
 */

require_once __DIR__ . '/../../admin/admin_init.php';
require_once __DIR__ . '/../../lib/jbrowse/gene_set_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$organism   = trim($_POST['organism']   ?? '');
$assembly   = trim($_POST['assembly']   ?? '');
$gene_set   = trim($_POST['gene_set']   ?? '');
$force      = !empty($_POST['force']);
$do_index   = !empty($_POST['text_index']);
$attributes = trim($_POST['attributes'] ?? 'Name,ID');

if (empty($organism) || empty($assembly) || empty($gene_set)) {
    echo json_encode(['success' => false, 'error' => 'Organism, assembly, and gene_set are required']);
    exit;
}

foreach (['organism' => $organism, 'assembly' => $assembly, 'gene_set' => $gene_set] as $param => $val) {
    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $val)) {
        echo json_encode(['success' => false, 'error' => "Invalid $param: $val"]);
        exit;
    }
}

if (!preg_match('/^[A-Za-z0-9_]+(,[A-Za-z0-9_]+)*$/', $attributes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid attributes format']);
    exit;
}

$config    = ConfigManager::getInstance();
$meta_path = $config->getPath('metadata_path');
$org_data  = $config->getPath('organism_data');

// Assembly must be registered first
$asm_json = "$meta_path/jbrowse2-configs/assemblies/{$organism}_{$assembly}.json";
if (!file_exists($asm_json)) {
    echo json_encode(['success' => false, 'error' => "Assembly not registered: {$organism}/{$assembly}. Register the assembly in JBrowse first."]);
    exit;
}

// Gene set directory must exist
$gs_dir = "$org_data/$organism/$assembly/$gene_set";
if (!is_dir($gs_dir)) {
    echo json_encode(['success' => false, 'error' => "Gene set directory not found: $gs_dir"]);
    exit;
}

$log = [];
$ok  = prepareGeneSetForJBrowse($organism, $assembly, $gene_set, $config, $log, $force);

$text_index_result = null;
if ($ok && $do_index) {
    $text_index_result = buildGeneSetTextIndex($organism, $assembly, $gene_set, $attributes, $config);
    $log[] = $text_index_result['success']
        ? "Text-index OK (attributes: $attributes)"
        : 'Text-index skipped: ' . ($text_index_result['error'] ?? 'unknown error');
}

echo json_encode([
    'success'           => $ok,
    'output'            => implode("\n", $log),
    'text_index_result' => $text_index_result,
]);
