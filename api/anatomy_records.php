<?php
/**
 * Anatomical Expression Search -- the results table's rows, paged/sorted/filtered on the server
 * (DataTables server-side protocol). Every match is reachable: the filter box searches ALL of them.
 *   GET (the search page's own parameters: mode, term, rel, ev, pmid, ids) + draw, start, length,
 *       search[value], order[0][column], order[0][dir]
 *   -> {"draw", "recordsTotal", "recordsFiltered", "data": [[cell html, ...], ...]}
 * Read-only; only the organism this visitor may see (moop_page_visible_organism()).
 */
include_once __DIR__ . '/../tools/tool_init.php';
require_once __DIR__ . '/../lib/anatomy_expression.php';
require_once __DIR__ . '/../lib/crosswalk.php';
header('Content-Type: application/json');

$organism = moop_page_visible_organism();
$draw = (int) ($_GET['draw'] ?? 0);
if ($organism === '') { echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]); exit; }

// Column order = tools/pages/_page_results.php (Transcript, Gene, Expressed in, Evidence, Life stage, Specimen, Paper, Curated)
$cols  = ['accession', null, 'term', 'evidence', 'stage', 'specimen', 'paper', 'curated'];
$oc    = (int) ($_GET['order'][0]['column'] ?? 6);
$order = $cols[$oc] ?? 'paper';
$dir   = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$spec  = moop_page_spec($organism, $_GET);
$res   = moop_page_records($organism, $spec, (string) ($_GET['search']['value'] ?? ''), $order ?? 'paper', $dir,
                           (int) ($_GET['start'] ?? 0), min(500, max(1, (int) ($_GET['length'] ?? 25))));

$ids = [];
foreach ($res['rows'] as $r) array_push($ids, $r['term_id'], $r['stage_id'], $r['specimen_id']);
$labels = moop_ontology_labels($ids, moop_ontology_by_purpose('anatomy'))
        + moop_ontology_labels(array_column($res['rows'], 'evidence_id'), moop_ontology_by_purpose('evidence'));
$L = fn(?string $id) => $id === null || $id === '' ? '' : ($labels[$id] ?? $id);
$genes = moop_crosswalk_gene_links($organism, array_column($res['rows'], 'accession'));
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);

$data = [];
foreach ($res['rows'] as $r) {
    $g = $genes[mb_strtolower($r['accession'])] ?? null;
    $gene = '<span class="text-muted">&ndash;</span>';
    if ($g) {
        $gene = '';
        foreach (array_slice($g['show'], 0, 2) as $i => $x)
            $gene .= ($i ? ' ' : '') . '<a href="' . $h($x['url']) . '"' . ($x['external'] ? ' target="_blank" rel="noopener"' : '') . '>' . $h($x['id']) . '</a>';
        if (count($g['show']) > 2) $gene .= ' <span class="text-muted" title="' . $h(implode(' ', $g['all'])) . '">+' . (count($g['show']) - 2) . '</span>';
    }
    $cell = fn(string $col) => $r[$col] === null || $r[$col] === '' ? '' : moop_term_icon($r[$col], $L($r[$col])) . $h($L($r[$col]));
    $data[] = [
        '<span class="font-monospace small">' . $h($r['accession']) . '</span>',
        '<span class="font-monospace small">' . $gene . '</span>',
        $h($L($r['term_id'])) . ' <span class="text-muted small font-monospace">' . $h($r['term_id']) . '</span>',
        '<span class="small">' . $cell('evidence_id') . '</span>',
        '<span class="small">' . $cell('stage_id') . '</span>',
        '<span class="small">' . $cell('specimen_id') . '</span>',
        '<span class="small"><a href="https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode((string) $r['pmid']) . '/" target="_blank" rel="noopener" title="'
            . $h($r['title']) . '">' . $h(trim(($r['authors'] ?: 'PMID ' . $r['pmid']) . ' ' . $r['year'])) . '</a></span>',
        '<span class="small text-nowrap">' . $h($r['curated_on']) . '</span>',
    ];
}
echo json_encode(['draw' => $draw, 'recordsTotal' => $res['total'], 'recordsFiltered' => $res['filtered'], 'data' => $data],
                 JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
