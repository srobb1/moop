<?php
/**
 * Anatomical Expression Search -- where genes were published as expressed (curated records; on
 * simrbase the data source is PAGE, Planarian Anatomy Gene Expression -- set per organism).
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

// Three ways in, one page, one results table: by anatomy term, by publication, by gene / transcript ids.
$mode = in_array($_GET['mode'] ?? '', ['term', 'pub', 'ids'], true) ? $_GET['mode'] : 'term';
$pq   = trim((string) ($_GET['q'] ?? ''));                       // publication words or PMID
$pmid = preg_match('/^\d{4,9}$/', (string) ($_GET['pmid'] ?? '')) ? (string) $_GET['pmid'] : '';
$ids_text = trim((string) ($_GET['ids'] ?? ''));
$papers = []; $paper = null; $id_result = null;
$term = trim((string) ($_GET['term'] ?? ''));
$rel  = (string) ($_GET['rel'] ?? 'BFO:0000050');
if (!isset($relations[$rel])) $rel = isset($relations['BFO:0000050']) ? 'BFO:0000050' : (string) array_key_first($relations);
$evidence_choices = $organism ? moop_page_evidence_choices($organism) : [];
$ev = (string) ($_GET['ev'] ?? '');

$result = null; $term_label = ''; $labels = []; $covered = 0; $genes = [];
if ($organism && $mode === 'pub') {
    if ($pq !== '') $papers = moop_page_find_publications($organism, $pq);
    if ($pmid !== '') {
        $result = moop_page_search($organism, null, ['pmid' => $pmid]);
        foreach (moop_page_find_publications($organism, $pmid) as $p) $paper = $p;
    }
} elseif ($organism && $mode === 'ids' && $ids_text !== '') {
    $typed     = array_slice(preg_split('/[\s,;]+/', $ids_text, -1, PREG_SPLIT_NO_EMPTY), 0, 500);
    $id_result = moop_page_search_ids($organism, $typed);
    $result    = ['rows' => $id_result['rows'], 'total' => count($id_result['rows']), 'capped' => false];
} elseif ($organism && preg_match(MOOP_ONTOLOGY_ID_RE, $term)) {
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
}
if ($result !== null && $result['rows'] && $mode !== 'term') {
    $want = [];
    foreach ($result['rows'] as $r) array_push($want, $r['term_id'], $r['stage_id']);
    $labels = moop_ontology_labels($want, $anatomy)
            + moop_ontology_labels(array_column($result['rows'], 'evidence_id'), moop_ontology_by_purpose('evidence'));
}
// Each transcript's reference gene (Rosetta Stone / crosswalk), linked to its gene page.
if ($result !== null) $genes = moop_crosswalk_gene_links($organism, array_column($result['rows'], 'accession'));

echo render_display_page(__DIR__ . '/pages/anatomy_search.php', [
    'relations' => $relations, 'releases' => $releases, 'organism' => $organism,
    'term' => $term, 'term_label' => $term_label, 'rel' => $rel, 'ev' => $ev,
    'evidence_choices' => $evidence_choices, 'result' => $result, 'labels' => $labels, 'covered' => $covered, 'genes' => $genes,
    'mode' => $mode, 'pq' => $pq, 'pmid' => $pmid, 'papers' => $papers, 'paper' => $paper,
    'ids_text' => $ids_text, 'id_result' => $id_result,
    'crosswalk_name' => (string) (moop_crosswalk_sources()[$organism]['name'] ?? ''),
    'source'         => (string) (moop_page_sources()[$organism]['source'] ?? ''),
    'source_url'     => (string) (moop_page_sources()[$organism]['source_url'] ?? ''),
    'page_script' => [moop_url_base() . '/js/modules/ontology-term-box.js', moop_url_base() . '/js/anatomy-search.js'],
], 'Anatomical Expression Search');
