<?php
/**
 * JBrowse Admin API: Re-prep GFF
 *
 * Re-runs sort + bgzip + tabix for a gene set and updates its gene track JSON.
 * Use after replacing the source genomic.gff with updated data.
 *
 * POST params:
 *   organism   - organism directory name (e.g. Nematostella_vectensis)
 *   assembly   - assembly ID             (e.g. GCA_033964005.1)
 *   gene_set   - gene set name           [default: v1]
 *   text_index - 1 to also rebuild the Trix text-search index
 *   attributes - comma-separated GFF attributes for text-index [default: Name,ID]
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
$gene_set   = trim($_POST['gene_set']   ?? 'v1');
$do_index   = !empty($_POST['text_index']);
$attributes = trim($_POST['attributes'] ?? 'Name,ID');

if (empty($organism) || empty($assembly)) {
    echo json_encode(['success' => false, 'error' => 'Organism and assembly are required']);
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

$config       = ConfigManager::getInstance();
$organisms_dir = $config->getPath('organism_data');
$site_path    = $config->getPath('site_path');

// Validate source GFF exists and is non-empty
$source_gff = "$organisms_dir/$organism/$assembly/$gene_set/genomic.gff";
if (!file_exists($source_gff)) {
    echo json_encode(['success' => false, 'error' => "No genomic.gff found at $source_gff"]);
    exit;
}
if (filesize($source_gff) === 0) {
    echo json_encode(['success' => false, 'error' => "genomic.gff is empty (0 bytes). Replace it with real data first."]);
    exit;
}

// The genomes dir for this gene set must already exist (assembly must be registered)
$genomes_dir = "$site_path/data/genomes/$organism/$assembly";
if (!is_dir($genomes_dir)) {
    echo json_encode(['success' => false, 'error' => "Genomes directory not found: $genomes_dir — register the assembly first"]);
    exit;
}

$log = [];

// force=true rebuilds bgzip/tabix and overwrites the track JSON
$ok = prepareGeneSetForJBrowse($organism, $assembly, $gene_set, $config, $log, true);

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
