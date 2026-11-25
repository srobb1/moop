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
$written_jumps = [];   // anchor => ['label', 'count'?], below
if ($view['written']) {
    $lang = moop_current_lang();
    $body = trim((string) ($view['written']['body'][$lang] ?? '')) !== '' ? $view['written']['body'][$lang] : (string) ($view['written']['body']['en'] ?? '');
    $written_html = moop_page_render($body, (string) ($view['written']['format'] ?? 'html'));
    // A content page's own addresses are relative to the site's root (images/..., page.php?p=...),
    // right on page.php but one folder off here under tools/: anchor them to the root.
    $root = moop_url_base() . '/';
    $written_html = preg_replace_callback('/\b(src|href)="(?![a-z][a-z0-9+.-]*:|\/|#)([^"]*)"/i',
        fn($m) => $m[1] . '="' . $root . $m[2] . '"', $written_html);
    // "Jump to" links in the Description header for the long sections the ported Planosphere pages
    // carry (stage and tissue pages): the in situ gene table (with its gene count) and the sequences.
    // Matched by heading text -- these pages are site data written to one pattern (metadata/pages).
    foreach (['ob-insitu' => 'In Situ Hybridization Data', 'ob-seqs' => 'Sequences'] as $anchor => $heading) {
        $n = 0;
        $written_html = preg_replace('#<h2>\s*' . preg_quote($heading, '#') . '\s*</h2>#i', '<h2 id="' . $anchor . '">' . $heading . '</h2>', $written_html, 1, $n);
        if ($n) $written_jumps[$anchor] = ['label' => $heading];
    }
    if (isset($written_jumps['ob-insitu']) && preg_match('#id="ob-insitu".*?<table[^>]*tp-genes.*?</table>#s', $written_html, $tm)) {
        $written_jumps['ob-insitu']['count'] = preg_match_all('#<tr>\s*<td#', $tm[0]);
    }
}

// Expression records (curated PAGE data) on THIS site for an anatomy term: the term and
// everything in it (is a + part of), like the Anatomical Expression Search's default. A life-cycle
// stage (Stage 3, embryo stage) matches the records' stage instead (lib/anatomy_expression.php),
// so its card says "at" rather than "in ... or any of its parts".
$records = null;
$is_stage = (moop_ontology_sources()[$prefix]['purpose'] ?? '') === 'anatomy'
    && in_array($term['id'], moop_ontology_expand($prefix, 'UBERON:0000105', []), true);   // UBERON:0000105 = life cycle stage
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

// GO: the genes carrying this term or a more specific one, per gene set the visitor may see
// (lib/go_gene_counts.php), each row linked straight to those genes in the Data Exporter with its
// gene set already chosen and "more specific terms" ticked -- the same rule, so the numbers match.
$gene_rows = null;
$genes_missing = 0;
if ($prefix === 'GO') {
    require_once __DIR__ . '/../lib/go_gene_counts.php';
    $gene_rows = moop_go_counts_by_gene_set($term['id'], moop_ob_scope());
    $genes_missing = moop_ob_gene_counts_missing();
    $mart = moop_url('tools/moopmart.php');
    // The gene set to open on arrival (a gene page's GO link names its own). Only a row already in
    // the table can match, so this can never show a gene set the visitor may not see.
    $open_asm = (string) ($_GET['open_assembly'] ?? '');
    $open_gs  = (string) ($_GET['open_gene_set'] ?? '');
    foreach ($gene_rows as &$g) {
        $g['open'] = $open_gs !== '' && $g['assembly'] === $open_asm && $g['gene_set'] === $open_gs;
        $g['url'] = $mart . '?' . http_build_query(['organism' => $g['organism'], 'assembly' => $g['assembly'],
                     'gene_set' => $g['gene_set'], 'annotation' => $term['id'], 'annotation_sub' => 1,
                     'annotation_columns' => [MOOP_GO_ANNOTATION_TYPE]]);
        // The table's switch "Include more specific terms" off: the term itself only (exact match).
        $g['url_own'] = $mart . '?' . http_build_query(['organism' => $g['organism'], 'assembly' => $g['assembly'],
                     'gene_set' => $g['gene_set'], 'annotation' => $term['id'], 'annotation_columns' => [MOOP_GO_ANNOTATION_TYPE]]);
    }
    unset($g);
}
// The same selection as a TSV straight from the exporter's download (api/moopmart_export.php, a
// POST with the CSRF token): one row per gene ('wide'), each GO source's terms as columns.
$gene_download = null;
if ($gene_rows) {
    $go_sources = [];
    foreach (array_unique(array_column($gene_rows, 'organism')) as $o) {
        foreach ((moop_load_annotation_sources_cache($o) ?? [])[MOOP_GO_ANNOTATION_TYPE] ?? [] as $src) $go_sources[$src['name']] = true;
    }
    $gene_download = ['action' => moop_url('api/moopmart_export.php'), 'fields' => [
        'output_format' => 'tsv', 'ann_format' => 'wide',
        'ann_criteria_src[]' => '', 'ann_criteria_acc[]' => $term['id'], 'ann_criteria_kw[]' => '',   // + ann_criteria_sub[] per button
        'annotation_columns[]' => array_keys($go_sources)]];
    foreach ($gene_rows as &$g) $g['source_key'] = $g['organism'] . '|' . $g['assembly'] . '|' . $g['gene_set'];
    unset($g);
}
// No records on this site at all: the site may still name where they are listed.
$records_elsewhere = $records === null && (moop_ontology_sources()[$prefix]['purpose'] ?? '') === 'anatomy'
                   ? moop_ob_records_link($prefix, (string) array_key_first(moop_page_sources()), $term['id'], 'BFO:0000050') : '';

$obo = preg_match('/^([A-Za-z]+):(\w+)$/', $term['id'], $m) ? $m[1] . '_' . $m[2] : '';
echo render_display_page(__DIR__ . '/pages/ontology_term.php', [
    'term' => $term, 'prefix' => $prefix, 'ontology_name' => $ontologies[$prefix],
    'release' => (string) (moop_ontology_meta($prefix)['release'] ?? ''), 'view' => $view, 'rels' => $rels,
    'written_html' => $written_html, 'written_jumps' => $written_jumps, 'records' => $records, 'is_stage' => $is_stage, 'records_elsewhere' => $records_elsewhere,
    'term_images' => $term_images, 'scope' => $prefix === 'GO' ? moop_ob_scope() : [], 'gene_rows' => $gene_rows, 'genes_missing' => $genes_missing, 'gene_download' => $gene_download,
    'links' => $obo === '' ? [] : [
        'OLS (EMBL-EBI)' => 'https://www.ebi.ac.uk/ols4/ontologies/' . strtolower($prefix) . '/classes?obo_id=' . rawurlencode($term['id']),
        'OBO PURL'       => 'http://purl.obolibrary.org/obo/' . $obo,
    ],
    'page_styles' => [moop_url_base() . '/css/ontology-browser.css'],
    'page_script' => [moop_url_base() . '/js/ontology-term.js'],
], $term['label'] . ' (' . $term['id'] . ')');
