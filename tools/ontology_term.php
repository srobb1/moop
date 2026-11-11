<?php
/**
 * Ontology browser -- one term's page, laid out like a gene page (sidebar of sections).
 *   ?o=PLANA&id=PLANA:0000049
 * Sections: overview (definition, synonyms), where it sits (paths, parents, kinds), relationships
 * both ways, the site's written page about it (a content page named after the term), expression
 * records where this site has them (lib/anatomy_expression.php), links out.
 * Data: lib/ontology_browser.php.
 */
include_once __DIR__ . '/tool_init.php';
require_once __DIR__ . '/../lib/ontology_browser.php';
require_once __DIR__ . '/../lib/content_pages.php';
include_once __DIR__ . '/../includes/layout.php';

$ontologies = moop_ob_ontologies();
$prefix     = strtoupper((string) ($_GET['o'] ?? ''));
$id         = moop_ontology_normalise_id((string) ($_GET['id'] ?? ''));
$term       = isset($ontologies[$prefix]) && $id !== '' ? moop_ob_term($prefix, $id) : null;
if (!$term) {
    http_response_code(404);
    echo render_display_page(__DIR__ . '/pages/ontology_term.php', ['term' => null, 'prefix' => $prefix,
        'page_styles' => [moop_url_base() . '/css/ontology-browser.css']], 'Term not found');
    exit;
}
$view = moop_ob_term_view($prefix, $term);
$rels = moop_ob_relations($prefix);

// The written page, in the visitor's language when it has one.
$written_html = '';
if ($view['written']) {
    $lang = moop_current_lang();
    $body = trim((string) ($view['written']['body'][$lang] ?? '')) !== '' ? $view['written']['body'][$lang] : (string) ($view['written']['body']['en'] ?? '');
    $written_html = moop_page_render($body, (string) ($view['written']['format'] ?? 'html'));
}

// Expression records (curated PAGE data) on THIS site for an anatomy term: the term and
// everything in it (is a + part of), like the Anatomical Expression Search's default.
$records = null;
if ((moop_ontology_sources()[$prefix]['purpose'] ?? '') === 'anatomy') {
    require_once __DIR__ . '/../lib/anatomy_expression.php';
    foreach (array_keys(moop_page_sources()) as $org) {
        if (!moop_page_meta($org) || !(moop_session_is_admin() || is_public_organism($org) || has_access('COLLABORATOR', $org))) continue;
        $ids = moop_ontology_expand_in('anatomy', $term['id'], ['BFO:0000050']);
        $res = moop_page_search($org, $ids, [], 1);
        $records = ['organism' => $org, 'total' => (int) $res['total'],
                    'url' => moop_url('tools/anatomy_search.php') . '?' . http_build_query(['organism' => $org, 'term' => $term['id'], 'rel' => 'BFO:0000050'])];
        break;
    }
}

// No records here: the site may name where they are (ontology_browser.<prefix>.records_url).
$records_elsewhere = '';
if ($records === null) {
    $tpl = (string) (ConfigManager::getInstance()->getArray('ontology_browser', [])[$prefix]['records_url'] ?? '');
    if (preg_match('#^https://[^\s"<>]+$#', $tpl) && strpos($tpl, '{id}') !== false) $records_elsewhere = str_replace('{id}', rawurlencode($term['id']), $tpl);
}

$obo = preg_match('/^([A-Za-z]+):(\w+)$/', $term['id'], $m) ? $m[1] . '_' . $m[2] : '';
echo render_display_page(__DIR__ . '/pages/ontology_term.php', [
    'term' => $term, 'prefix' => $prefix, 'ontology_name' => $ontologies[$prefix],
    'release' => (string) (moop_ontology_meta($prefix)['release'] ?? ''), 'view' => $view, 'rels' => $rels,
    'written_html' => $written_html, 'records' => $records, 'records_elsewhere' => $records_elsewhere,
    'links' => $obo === '' ? [] : [
        'OLS (EMBL-EBI)' => 'https://www.ebi.ac.uk/ols4/ontologies/' . strtolower($prefix) . '/classes?obo_id=' . rawurlencode($term['id']),
        'OBO PURL'       => 'http://purl.obolibrary.org/obo/' . $obo,
    ],
    'page_styles' => [moop_url_base() . '/css/parent-nav.css', moop_url_base() . '/css/ontology-browser.css'],
    'page_script' => [moop_url_base() . '/js/modules/parent-nav.js'],
], $term['label'] . ' (' . $term['id'] . ')');
