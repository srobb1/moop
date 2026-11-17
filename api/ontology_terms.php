<?php
/**
 * Term search box lookup: ontology terms whose name or synonym matches ?q=, best first.
 *
 *   GET api/ontology_terms.php?q=brain[&o=PLANA,BSPO]
 *   -> {"terms":[{"id":"PLANA:0000044","label":"cephalic ganglia","ontology":"PLANA",
 *                 "matched":"brain","kind":"exact"}, ...]}
 *
 * Public: ontology names are public data, and nothing about the site's data is revealed.
 * READ-ONLY: it only searches the built ontology files (lib/ontologies.php). Without ?o= it searches
 * the ontologies configured with 'search' => true; ?o= can name those or any the Ontology Browser
 * shows (built, with a closure) -- a visitor cannot reach any other file.
 * Rate-limited by nginx as an api/ request.
 */
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/ontologies.php';
include_once __DIR__ . '/../lib/ontology_browser.php';
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

$offered = array_keys(array_filter(moop_ontology_sources(), fn($o) => !empty($o['search'])));
// Asked by name, any ontology the Ontology Browser shows may be searched too: its own find box
// names the ontology on screen. GO is 'search' => false (kept out of the default boxes, e.g. the
// anatomy search) and this box used to quietly search PLANA instead and show nothing.
$named   = array_values(array_unique(array_merge($offered, array_keys(moop_ob_ontologies()))));
$asked   = array_filter(explode(',', strtoupper((string) ($_GET['o'] ?? ''))));
$use     = $asked ? array_values(array_intersect($named, $asked)) : $offered;
$q       = (string) ($_GET['q'] ?? '');

$terms = [];
foreach (moop_ontology_search($q, $use, 15) as $t) {
    $terms[] = ['id' => $t['id'], 'label' => $t['label'], 'ontology' => $t['ontology'],
                'matched' => $t['matched'], 'kind' => $t['kind']];
}
echo json_encode(['terms' => $terms], JSON_UNESCAPED_UNICODE);
