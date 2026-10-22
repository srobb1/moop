<?php
/**
 * The nearest gene beside a gene, for the gene page's neighbour buttons (Gene Structure card).
 *
 * GET organism, assembly, gene_set, uniquename, side=left|right
 *   left  = lower coordinates on the same sequence, right = higher. The page labels each side
 *   upstream or downstream from the gene's strand; this endpoint works in genome order only.
 *
 * Returns JSON:
 *   {url, gene_id, gap, skipped}  -- gap is bases between the two genes (negative = they
 *                                    overlap); skipped = genes passed over for having no page
 *   {none: true, skipped, chr}    -- nothing further on this sequence that has a page
 *
 * Genes in the GFF but not the database (lncRNA, tRNA -- JBrowse-only by design, see
 * decision_noncoding_genes_jbrowse_only) have no gene page, so they are skipped and counted.
 * Neighbours come from feature_coords.tsv, whose gene ids are the database's, so no GFF-to-
 * database id mapping is needed. Cost is one stream of that file (~0.25 s on the largest gene
 * set), paid only on a click.
 */

include_once __DIR__ . '/../tools/tool_init.php';
require_once __DIR__ . '/../lib/feature_coords.php';

header('Content-Type: application/json');
header('Cache-Control: private, no-store');

$organism   = trim((string)($_GET['organism']   ?? ''));
$assembly   = trim((string)($_GET['assembly']   ?? ''));
$gene_set   = trim((string)($_GET['gene_set']   ?? ''));
$uniquename = trim((string)($_GET['uniquename'] ?? ''));
$side       = (string)($_GET['side'] ?? '');

// The three names become a filesystem path, and has_gene_set_access() answers yes to anything
// for an admin -- so they are checked as names here, not left to the access check.
$is_name = fn($s) => $s !== '' && $s !== '.' && $s !== '..' && preg_match('/^[A-Za-z0-9_.-]+$/', $s);
if (!$is_name($organism) || !$is_name($assembly) || !$is_name($gene_set) || $uniquename === ''
    || !in_array($side, ['left', 'right'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad request']);
    exit;
}
// Not allowed and not there give the same answer: a restricted gene set is not admitted to exist.
if (!has_gene_set_access($organism, $assembly, $gene_set)) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

$config       = ConfigManager::getInstance();
$gene_set_dir = $config->getPath('organism_data') . "/$organism/$assembly/$gene_set";
$db_file      = organism_db_path($organism);
$beside       = is_file($db_file) ? moop_feature_coords_genes_beside($gene_set_dir, $uniquename) : null;
$gs_row       = $beside ? fetchData(
    'SELECT gs.gene_set_id FROM gene_set gs JOIN genome g ON g.genome_id = gs.genome_id
      WHERE g.genome_accession = ? AND gs.gene_set_name = ?', $db_file, [$assembly, $gene_set]) : [];
if (!$beside || empty($gs_row)) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}
$gene_set_id = (int)$gs_row[0]['gene_set_id'];

// Nearest first, in batches: the first candidate the database knows is the answer.
$candidates = $beside[$side];
$skipped    = 0;
foreach (array_chunk($candidates, 100) as $batch) {
    $ids  = array_column($batch, 'gene_id');
    $rows = fetchData('SELECT feature_uniquename FROM feature WHERE gene_set_id = ? AND feature_uniquename IN ('
                      . implode(',', array_fill(0, count($ids), '?')) . ')', $db_file, array_merge([$gene_set_id], $ids));
    $known = array_flip(array_column($rows, 'feature_uniquename'));
    foreach ($batch as $g) {
        if (!isset($known[$g['gene_id']])) { $skipped++; continue; }
        $self = $beside['self'];
        echo json_encode([
            'url'     => "/{$config->getString('site')}/tools/parent.php?" . http_build_query([
                             'organism' => $organism, 'uniquename' => $g['gene_id'],
                             'assembly' => $assembly, 'gene_set' => $gene_set]),
            'gene_id' => $g['gene_id'],
            'gap'     => $side === 'right' ? $g['start'] - $self['end'] - 1 : $self['start'] - $g['end'] - 1,
            'skipped' => $skipped,
        ]);
        exit;
    }
}
echo json_encode(['none' => true, 'skipped' => $skipped, 'chr' => $beside['self']['chr']]);
