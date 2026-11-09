<?php
/**
 * PAGE: Planarian Anatomy Gene Expression -- search by anatomy term.
 * Plan: notes/PLANOSPHERE_PAGE_ROSETTA_PLAN.md. Pick a term (ontology term box) and a relationship;
 * the search covers the term and everything related to it (lib/ontologies.php), and lists the
 * curations recorded in any of those terms (lib/anatomy_expression.php). Evidence filter groups
 * come from ECO itself. Gene links (Rosetta Stone) come in the next step. Not in a menu yet.
 */
include_once __DIR__ . '/tool_init.php';
require_once __DIR__ . '/../lib/anatomy_expression.php';
require_once __DIR__ . '/../lib/crosswalk.php';
include_once __DIR__ . '/../includes/layout.php';

$relations = moop_ontology_relations('anatomy');
$anatomy   = moop_ontology_by_purpose('anatomy');
$releases  = [];
foreach ($anatomy as $p) if ($m = moop_ontology_meta($p)) $releases[$p] = $m['release'] ?? '';

// The organism with PAGE curations this visitor may see (PAGE is Schmidtea mediterranea today).
$organism = '';
foreach (array_keys(moop_page_sources()) as $o) {
    if (moop_page_meta($o) && (moop_session_is_admin() || is_public_organism($o) || has_access('COLLABORATOR', $o))) { $organism = $o; break; }
}

$term = trim((string) ($_GET['term'] ?? ''));
$rel  = (string) ($_GET['rel'] ?? 'BFO:0000050');
if (!isset($relations[$rel])) $rel = isset($relations['BFO:0000050']) ? 'BFO:0000050' : (string) array_key_first($relations);
$evidence_choices = $organism ? moop_page_evidence_choices($organism) : [];
$ev = (string) ($_GET['ev'] ?? '');

$result = null; $term_label = ''; $labels = []; $covered = 0; $genes = [];
if ($organism && preg_match(MOOP_ONTOLOGY_ID_RE, $term)) {
    $ids     = moop_ontology_expand_in('anatomy', $term, [$rel]);
    $covered = count($ids) - 1;
    $filters = [];
    foreach ($evidence_choices as $c) if ($c['id'] === $ev) $filters['evidence'] = $c['members'];
    $result  = moop_page_search($organism, $ids, $filters);
    $want    = [$term];
    foreach ($result['rows'] as $r) array_push($want, $r['term_id'], $r['stage_id'], $r['specimen_id']);
    $labels  = moop_ontology_labels($want, $anatomy)
             + moop_ontology_labels(array_column($result['rows'], 'evidence_id'), moop_ontology_by_purpose('evidence'));
    $term_label = $labels[$term] ?? $term;
    // Each transcript's reference gene (Rosetta Stone / crosswalk), linked to its gene page.
    $genes = moop_crosswalk_gene_links($organism, array_column($result['rows'], 'accession'));
}

echo render_display_page(__DIR__ . '/pages/anatomy_search.php', [
    'relations' => $relations, 'releases' => $releases, 'organism' => $organism,
    'term' => $term, 'term_label' => $term_label, 'rel' => $rel, 'ev' => $ev,
    'evidence_choices' => $evidence_choices, 'result' => $result, 'labels' => $labels, 'covered' => $covered, 'genes' => $genes,
    'page_script' => [moop_url_base() . '/js/modules/ontology-term-box.js', moop_url_base() . '/js/anatomy-search.js'],
], 'PAGE: search by anatomy');
