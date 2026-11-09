<?php
/**
 * "What does this term include?" -- the terms a search on it would cover.
 *
 *   GET api/ontology_expand.php?term=PLANA:0000036&rel=BFO:0000050[&purpose=anatomy]
 *   -> {"term":{"id","label"},"relation":{"id","label"},"count":12,"terms":[{"id","label"},...]}
 *
 * Public and READ-ONLY (ontology names are public). Only relationships the purpose is configured
 * to offer are accepted; anything else is answered with "is a" only. At most 2,000 terms listed.
 */
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/ontologies.php';
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

$purpose = in_array($_GET['purpose'] ?? 'anatomy', ['anatomy', 'phenotype', 'evidence'], true) ? $_GET['purpose'] ?? 'anatomy' : 'anatomy';
$term    = trim((string) ($_GET['term'] ?? ''));
$rels    = moop_ontology_relations($purpose);
$rel     = (string) ($_GET['rel'] ?? 'rdfs:subClassOf');
if (!isset($rels[$rel])) $rel = 'rdfs:subClassOf';
if (!preg_match(MOOP_ONTOLOGY_ID_RE, $term)) { http_response_code(400); echo json_encode(['error' => 'no term']); exit; }

$prefixes = moop_ontology_by_purpose($purpose);
$ids      = moop_ontology_expand_in($purpose, $term, [$rel]);
$labels   = moop_ontology_labels($ids, $prefixes);
$terms    = [];
foreach ($ids as $id) if ($id !== $term) $terms[] = ['id' => $id, 'label' => $labels[$id] ?? ''];
usort($terms, fn($a, $b) => strcasecmp($a['label'] ?: $a['id'], $b['label'] ?: $b['id']));
echo json_encode([
    'term'     => ['id' => $term, 'label' => $labels[$term] ?? ''],
    'relation' => ['id' => $rel, 'label' => $rels[$rel] ?? $rel],
    'count'    => count($terms),
    'terms'    => array_slice($terms, 0, 2000),
], JSON_UNESCAPED_UNICODE);
