<?php
/**
 * Example feature IDs for one gene set -- Sequence Retrieval's "Load examples" follows
 * the selected source, so its examples are always IDs found in that gene set.
 *
 * GET parameters: organism, assembly, gene_set (all required)
 *
 * Returns JSON: { ids: [gene, transcript, "transcript:1-60"], gene, transcript }
 * (moop_retrieval_examples()). A gene set the viewer cannot open answers 403 { ids: [] },
 * the same body as one that does not exist, so the endpoint never reveals what is there.
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/home_examples.php';

header('Content-Type: application/json');
header('Cache-Control: private, no-store');

$organism = trim((string)($_GET['organism'] ?? ''));
$assembly = trim((string)($_GET['assembly'] ?? ''));
$gene_set = trim((string)($_GET['gene_set'] ?? ''));

if ($organism === '' || $assembly === '' || $gene_set === ''
    || !has_gene_set_access($organism, $assembly, $gene_set)) {
    http_response_code($organism === '' || $assembly === '' || $gene_set === '' ? 400 : 403);
    echo json_encode(['ids' => []]);
    exit;
}

echo json_encode(moop_retrieval_examples($organism, $assembly, $gene_set, false));
