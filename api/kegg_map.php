<?php
/**
 * "View on KEGG": redirect to KEGG's own pathway map with a gene set's KOs coloured (lib/kegg.php).
 *
 * GET organism, assembly, gene_set, pathway (mapNNNNN), gene (optional: a feature whose KOs are marked
 * "this gene"). Built on click, not on the gene page: one pathway is ~30 ms, a gene can have 126.
 *
 * A gene set the visitor cannot see is a 404, the same as a missing one (notes/PERMISSIONS_PLAN.md):
 * its KOs would otherwise leak into the colouring.
 */
include_once __DIR__ . '/../tools/tool_init.php';
require_once __DIR__ . '/../lib/kegg.php';

function kegg_map_fail(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg . "\n";
    exit;
}

$organism = (string)($_GET['organism'] ?? '');
$assembly = (string)($_GET['assembly'] ?? '');
$gene_set = (string)($_GET['gene_set'] ?? '');
$pathway  = (string)($_GET['pathway']  ?? '');
$gene     = (string)($_GET['gene']     ?? '');

if (!moop_plain_name($organism) || !moop_plain_name($assembly) || !moop_plain_name($gene_set)
    || !moop_kegg_valid_pathway($pathway) || ($gene !== '' && strlen($gene) > 200)) {
    kegg_map_fail(400, 'Invalid parameters.');
}
if ($gene_set === MOOP_BLAST_ONLY || !moop_dataset_visible($organism, $assembly, $gene_set)) {
    kegg_map_fail(404, 'Not found.');
}

$db = organism_db_path($organism);
if (!is_file($db)) kegg_map_fail(404, 'Not found.');

try {
    $dbh = getDbConnection($db);
    $st  = $dbh->prepare('SELECT gs.gene_set_id FROM gene_set gs JOIN genome g ON g.genome_id = gs.genome_id
                           WHERE g.genome_accession = ? AND gs.gene_set_name = ?');
    $st->execute([$assembly, $gene_set]);
    $gene_set_id = $st->fetchColumn();
    if ($gene_set_id === false) kegg_map_fail(404, 'Not found.');
    $kos  = moop_kegg_gene_set_pathway_kos($db, (int)$gene_set_id, $pathway);
    $mine = $gene !== '' ? moop_kegg_feature_kos($db, (int)$gene_set_id, $gene) : [];
} catch (PDOException $e) {
    error_log('kegg_map: ' . $e->getMessage());
    kegg_map_fail(500, 'The KEGG data could not be read.');
}

// No KO in this gene set for the pathway: KEGG's plain map, nothing coloured, is still the right answer.
header('Location: ' . moop_kegg_map_url($pathway, moop_kegg_ko_colours($kos, $mine)), true, 302);
exit;
