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
require_once __DIR__ . '/../lib/anatomy_expression.php';
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
    // A content page's own addresses are relative to the site's root (images/..., page.php?p=...),
    // right on page.php but one folder off here under tools/: anchor them to the root.
    $root = moop_url_base() . '/';
    $written_html = preg_replace_callback('/\b(src|href)="(?![a-z][a-z0-9+.-]*:|\/|#)([^"]*)"/i',
        fn($m) => $m[1] . '="' . $root . $m[2] . '"', $written_html);
}

// Expression records (curated PAGE data) on THIS site for an anatomy term: the term and
// everything in it (is a + part of), like the Anatomical Expression Search's default.
$records = null;
if ((moop_ontology_sources()[$prefix]['purpose'] ?? '') === 'anatomy') {
    $org = moop_ob_records_organism($prefix);
    if ($org !== '') {
        $ids = moop_ontology_expand_in('anatomy', $term['id'], ['BFO:0000050']);
        $spec = ['ok' => true, 'mode' => 'term', 'term_ids' => $ids, 'filters' => [], 'accessions' => null, 'meta' => []];
        $records = ['organism' => $org, 'total' => moop_page_records($org, $spec, '', 'paper', 'desc', 0, 1)['total'],
                    'url' => moop_ob_records_link($prefix, $org, $term['id'], 'BFO:0000050')];
    }
}

// Each listed term's records too (the term + everything part of it, as above), linked to them.
// A term page lists ~5-30 terms; each count is one expansion + one indexed COUNT (~0.04 s).
if ($records !== null) {
    $org = $records['organism'];
    $counted = [];
    $count_for = function (string $tid) use ($org, &$counted): int {
        if (!isset($counted[$tid])) {
            $spec = ['ok' => true, 'mode' => 'term', 'term_ids' => moop_ontology_expand_in('anatomy', $tid, ['BFO:0000050']),
                     'filters' => [], 'accessions' => null, 'meta' => []];
            $counted[$tid] = moop_page_records($org, $spec, '', 'paper', 'desc', 0, 1)['total'];
        }
        return $counted[$tid];
    };
    $url_for = fn(string $tid) => moop_ob_records_link($prefix, $org, $tid, 'BFO:0000050');
    foreach (['parents', 'kinds'] as $k)
        foreach ($view[$k] as &$r) { $r['records'] = $count_for($r['id']); $r['records_url'] = $url_for($r['id']); } unset($r);
    foreach (['outgoing', 'incoming'] as $k)
        foreach ($view[$k] as &$list) foreach ($list as &$r) { $r['records'] = $count_for($r['id']); $r['records_url'] = $url_for($r['id']); } unset($list, $r);
}

// Images tagged with this term or any of its parts (gene images, lib/gene_images.php) -- the same reach
// as the image gallery's term filter, which the card links to. Only images the visitor may see.
$term_images = null;
if ((moop_ontology_sources()[$prefix]['purpose'] ?? '') === 'anatomy') {
    require_once __DIR__ . '/../lib/gene_images.php';
    foreach (glob(rtrim($config->getPath('organism_data'), '/') . '/*/gene_images.sqlite') ?: [] as $f) {
        $org = basename(dirname($f));
        $imgs = moop_images_for_terms($org, moop_ontology_expand_in('anatomy', $term['id'], array_keys(moop_ontology_relations('anatomy'))), 1000);
        if (!$imgs) continue;
        $term_images = ['organism' => $org, 'total' => count($imgs), 'sample' => array_slice($imgs, 0, 6),
                        'url' => moop_url('tools/image_gallery.php') . '?' . http_build_query(['organism' => $org, 'term' => $term['id']])];
        break;
    }
}

// No records on this site at all: the site may still name where they are listed.
$records_elsewhere = $records === null && (moop_ontology_sources()[$prefix]['purpose'] ?? '') === 'anatomy'
                   ? moop_ob_records_link($prefix, (string) array_key_first(moop_page_sources()), $term['id'], 'BFO:0000050') : '';

$obo = preg_match('/^([A-Za-z]+):(\w+)$/', $term['id'], $m) ? $m[1] . '_' . $m[2] : '';
echo render_display_page(__DIR__ . '/pages/ontology_term.php', [
    'term' => $term, 'prefix' => $prefix, 'ontology_name' => $ontologies[$prefix],
    'release' => (string) (moop_ontology_meta($prefix)['release'] ?? ''), 'view' => $view, 'rels' => $rels,
    'written_html' => $written_html, 'records' => $records, 'records_elsewhere' => $records_elsewhere,
    'term_images' => $term_images,
    'links' => $obo === '' ? [] : [
        'OLS (EMBL-EBI)' => 'https://www.ebi.ac.uk/ols4/ontologies/' . strtolower($prefix) . '/classes?obo_id=' . rawurlencode($term['id']),
        'OBO PURL'       => 'http://purl.obolibrary.org/obo/' . $obo,
    ],
    'page_styles' => [moop_url_base() . '/css/parent-nav.css', moop_url_base() . '/css/ontology-browser.css'],
    'page_script' => [moop_url_base() . '/js/modules/parent-nav.js'],
], $term['label'] . ' (' . $term['id'] . ')');
