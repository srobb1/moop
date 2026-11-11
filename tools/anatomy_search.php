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

// One SPEC (what was asked) for the page's totals, the table (api/anatomy_records.php: paged,
// sorted and filtered on the server over EVERY match) and the download -- the table used to get
// at most 5,000 rows and could filter only those.
$result = null; $term_label = ''; $covered = 0; $spec = null;
if ($organism && $mode === 'pub') {
    if ($pq !== '') $papers = moop_page_find_publications($organism, $pq);
    if ($pmid !== '') {
        $spec = moop_page_spec($organism, ['mode' => 'pub', 'pmid' => $pmid]);
        foreach (moop_page_find_publications($organism, $pmid) as $p) $paper = $p;
    }
} elseif ($organism && $mode === 'ids' && $ids_text !== '') {
    $spec = moop_page_spec($organism, ['mode' => 'ids', 'ids' => $ids_text]);
    // An id the crosswalk does not know may still have records under exactly that id.
    $typed  = $spec['meta']['typed'] ?? [];
    $direct = [];
    if ($typed && ($pdb = moop_page_open($organism))) {
        $st = $pdb->prepare('SELECT DISTINCT accession_lc FROM curation WHERE accession_lc IN (' . implode(',', array_fill(0, count($typed), '?')) . ')');
        $st->execute(array_map('mb_strtolower', $typed));
        $direct = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    }
    $id_result = ['matched' => $spec['meta']['matched'] ?? [],
                  'unknown' => array_values(array_filter($typed, fn($i) => !isset(($spec['meta']['matched'] ?? [])[$i]) && !isset($direct[mb_strtolower($i)])))];
} elseif ($organism && preg_match(MOOP_ONTOLOGY_ID_RE, $term)) {
    $spec       = moop_page_spec($organism, ['mode' => 'term', 'term' => $term, 'rel' => $rel, 'ev' => $ev]);
    $covered    = (int) ($spec['meta']['covered'] ?? 0);
    $term_label = moop_ontology_label($term, $anatomy) ?: $term;
}
if ($spec && $spec['ok']) {
    $result = ['total' => moop_page_records($organism, $spec, '', 'paper', 'desc', 0, 1)['total']];
}
// The query the table and the download repeat (only the parameters this mode uses).
$records_query = $mode === 'pub' ? ['mode' => 'pub', 'pmid' => $pmid]
               : ($mode === 'ids' ? ['mode' => 'ids', 'ids' => $ids_text] : ['mode' => 'term', 'term' => $term, 'rel' => $rel, 'ev' => $ev]);

echo render_display_page(__DIR__ . '/pages/anatomy_search.php', [
    'relations' => $relations, 'releases' => $releases, 'organism' => $organism,
    'term' => $term, 'term_label' => $term_label, 'rel' => $rel, 'ev' => $ev,
    'evidence_choices' => $evidence_choices, 'result' => $result, 'covered' => $covered,
    'records_url'  => moop_url('api/anatomy_records.php') . '?' . http_build_query($records_query),
    'download_url' => moop_url('api/anatomy_records_download.php') . '?' . http_build_query($records_query),
    'mode' => $mode, 'pq' => $pq, 'pmid' => $pmid, 'papers' => $papers, 'paper' => $paper,
    'ids_text' => $ids_text, 'id_result' => $id_result,
    'crosswalk_name' => (string) (moop_crosswalk_sources()[$organism]['name'] ?? ''),
    'source'         => (string) (moop_page_sources()[$organism]['source'] ?? ''),
    'source_url'     => (string) (moop_page_sources()[$organism]['source_url'] ?? ''),
    'page_script' => [moop_url_base() . '/js/modules/ontology-term-box.js', moop_url_base() . '/js/anatomy-search.js'],
], 'Anatomical Expression Search');
