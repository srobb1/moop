<?php
/**
 * Term search box lookup: ontology terms whose name or synonym matches ?q=, best first.
 *
 *   GET api/ontology_terms.php?q=brain[&o=PLANA,BSPO]
 *   -> {"terms":[{"id":"PLANA:0000044","label":"cephalic ganglia","ontology":"PLANA",
 *                 "matched":"brain","kind":"exact"}, ...]}
 *
 * Public: ontology names are public data, and nothing about the site's data is revealed.
 * READ-ONLY: it only searches the built ontology files (lib/ontologies.php). ?o= can only narrow
 * to ontologies configured with 'search' => true -- a visitor cannot reach any other file.
 * Rate-limited by nginx as an api/ request.
 */
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/ontologies.php';
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

$offered = array_keys(array_filter(moop_ontology_sources(), fn($o) => !empty($o['search'])));
$asked   = array_filter(explode(',', strtoupper((string) ($_GET['o'] ?? ''))));
$use     = $asked ? array_values(array_intersect($offered, $asked)) : $offered;
$q       = (string) ($_GET['q'] ?? '');

$terms = [];
foreach (moop_ontology_search($q, $use, 15) as $t) {
    $terms[] = ['id' => $t['id'], 'label' => $t['label'], 'ontology' => $t['ontology'],
                'matched' => $t['matched'], 'kind' => $t['kind']];
}
echo json_encode(['terms' => $terms], JSON_UNESCAPED_UNICODE);
