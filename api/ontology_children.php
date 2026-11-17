<?php
/**
 * Ontology browser: the terms directly below one term, for opening a branch of the tree.
 *
 *   GET api/ontology_children.php?o=PLANA&id=PLANA:0000049[&parts=1]
 *   -> {"terms":[{"id","label","definition","relation","children","url",
 *                 "terms_all","records_all","records_own","below":{"is a":3,"part of":6},"records_url",
 *                 "genes_all","genes_own","gene_organisms"}, ...]}   (genes_*: GO only, else null)
 *   terms_all/records_all = the row closed (it + everything below); children/records_own = opened.
 *
 * Below = its subclasses; with parts=1 also the terms related to it by the ontology's other
 * configured relations (part of, contained in, develops from...). Public: ontology terms are
 * public data. READ-ONLY, and only ontologies this site has configured and built can be opened.
 * Rate-limited by nginx as an api/ request.
 */
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/ontology_browser.php';
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

$prefix = strtoupper((string) ($_GET['o'] ?? ''));
$id     = moop_ontology_normalise_id((string) ($_GET['id'] ?? ''));
if (!isset(moop_ob_ontologies()[$prefix]) || $id === '') {
    http_response_code(404);
    echo json_encode(['terms' => []]);
    exit;
}
$rels  = moop_ob_relations($prefix);
$use   = !empty($_GET['parts']) ? array_keys($rels) : [MOOP_OB_ISA];
$org   = moop_ob_records_organism($prefix);
$terms = [];
$rows  = moop_ob_add_gene_counts($prefix, array_map(fn($r) => moop_ob_tree_row($prefix, $r, !empty($_GET['parts']), $org),
                                                    moop_ob_links($prefix, $id, $use)));
foreach ($rows as $row) {
    $r = $row;
    $terms[] = ['id' => $r['id'], 'label' => $r['label'], 'definition' => $r['definition'],
                'relation' => t_plain($rels[$r['relation']] ?? $r['relation']), 'is_a' => $r['relation'] === MOOP_OB_ISA,
                'children' => $r['children'], 'url' => moop_ob_term_url($prefix, $r['id']),
                'terms_all' => $row['terms_all'], 'records_all' => $row['records_all'], 'records_own' => $row['records_own'],
                'below' => array_combine(array_map('t_plain', array_keys($row['below'])), array_values($row['below'])) ?: (object) [],
                'records_url' => $row['records_url'],
                'genes_all' => $row['genes_all'], 'genes_own' => $row['genes_own'], 'gene_organisms' => $row['gene_organisms']];
}
echo json_encode(['terms' => $terms], JSON_UNESCAPED_UNICODE);
