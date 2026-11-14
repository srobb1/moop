<?php
/**
 * "View on KEGG": redirect to KEGG's own pathway map with a gene set's KOs coloured (lib/kegg.php).
 *
 * GET organism, assembly, gene_set, pathway (mapNNNNN), gene (optional: a feature whose KOs are marked
 * "this gene"). Built on click, not on the gene page: one pathway is ~30 ms, a gene can have 126.
 *
 * Organism view: organism + pathway only (no assembly, no gene_set). Every gene set the visitor may see,
 * from the cached tables (moop_kegg_organism_pathways()): a KO in every gene set with KEGG data is
 * "all_sets", in only some "some_sets".
 *
 * A gene set the visitor cannot see is a 404, the same as a missing one (notes/PERMISSIONS_PLAN.md):
 * its KOs would otherwise leak into the colouring.
 */
include_once __DIR__ . '/../tools/tool_init.php';
require_once __DIR__ . '/../lib/kegg.php';

/**
 * Send the visitor to KEGG's map: a plain redirect when the link is short, else a page that POSTs the KOs
 * (a big pathway's link is too long for nginx's header buffer and for KEGG's URL limit).
 */
function kegg_map_go(string $pathway, array $colours): void {
    $url = moop_kegg_map_url($pathway, $colours);
    if (strlen($url) <= MOOP_KEGG_MAX_GET) {
        header('Location: ' . $url, true, 302);
        exit;
    }
    $post = moop_kegg_map_post($pathway, $colours);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $h = fn($v) => htmlspecialchars($v, ENT_QUOTES);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>KEGG ' . $h($pathway) . '</title></head>'
       . '<body style="font-family:sans-serif;padding:2rem">'
       . '<form id="kegg" method="post" action="' . $h($post['action']) . '">'
       . '<input type="hidden" name="map" value="' . $h($post['fields']['map']) . '">'
       . '<textarea name="multi_query" hidden>' . $h($post['fields']['multi_query']) . '</textarea>'
       . '<p>Opening KEGG map ' . $h($pathway) . ' with ' . count($colours) . ' KOs coloured&hellip;</p>'
       . '<button type="submit">Open the KEGG map</button></form>'
       . '<script>document.getElementById("kegg").submit();</script></body></html>';
    exit;
}

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

$organism_view = $assembly === '' && $gene_set === '' && $gene === '';
if (!moop_plain_name($organism) || !moop_kegg_valid_pathway($pathway)
    || (!$organism_view && (!moop_plain_name($assembly) || !moop_plain_name($gene_set) || strlen($gene) > 200))) {
    kegg_map_fail(400, 'Invalid parameters.');
}
if ($organism_view) {
    $db = organism_db_path($organism);
    if (!is_file($db)) kegg_map_fail(404, 'Not found.');
    try {
        $t = moop_kegg_organism_pathways($organism, $db);
    } catch (PDOException $e) {
        error_log('kegg_map organism: ' . $e->getMessage());
        kegg_map_fail(500, 'The KEGG data could not be read.');
    }
    // No gene set this visitor may see (with KEGG data): the same 404 as a missing organism
    if (!$t['gene_sets']) kegg_map_fail(404, 'Not found.');
    $colours = [];
    foreach ($t['rows'] as $r) {
        if ($r['pathway'] !== $pathway) continue;
        foreach ($r['kos_found'] as $ko) $colours[$ko] = in_array($ko, $r['kos_in_all'], true) ? 'all_sets' : 'some_sets';
    }
    ksort($colours);
    kegg_map_go($pathway, $colours);
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
kegg_map_go($pathway, moop_kegg_ko_colours($kos, $mine));
