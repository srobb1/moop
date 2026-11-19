<?php
/**
 * Ontology browser -- the tree. A term's row shows its definition; opening it loads the terms
 * directly below (api/ontology_children.php); its name opens the term page (ontology_term.php).
 *   ?o=PLANA            which ontology (default: the first that can be browsed)
 *   &parts=1            also open branches by the ontology's other relations (part of, contained in...)
 *   &organism=X[&assembly=Y[&gene_set=Z]]  GO gene counts for that data only (moop_ob_scope());
 *                       the organism / assembly / gene set pages open it this way (tools_config)
 * Data: lib/ontology_browser.php. Registry: 'ontology_browser' (only where an ontology is built).
 */
include_once __DIR__ . '/tool_init.php';
require_once __DIR__ . '/../lib/ontology_browser.php';
include_once __DIR__ . '/../includes/layout.php';

// Not in this page's list: evidence ontologies (ECO). Genes are not tagged with them, so there is
// nothing for a visitor to browse there (Sofia, 2026-11-18). Their term pages still work
// (ontology_term.php uses the full moop_ob_ontologies()).
$ontologies = array_filter(moop_ob_ontologies(),
    fn($p) => (moop_ontology_sources()[$p]['purpose'] ?? '') !== 'evidence', ARRAY_FILTER_USE_KEY);
$prefix     = strtoupper((string) ($_GET['o'] ?? ''));
// Opened from an organism / assembly / gene set page (its tools pass the scope, no ontology):
// GO, the one with gene counts to narrow.
if (!isset($ontologies[$prefix]) && moop_ob_scope() && isset($ontologies['GO'])) $prefix = 'GO';
if (!isset($ontologies[$prefix])) $prefix = (string) array_key_first($ontologies);
$parts      = !empty($_GET['parts']);
$meta       = $prefix !== '' ? moop_ontology_meta($prefix) : [];
$relations  = $prefix !== '' ? moop_ob_relations($prefix) : [];

echo render_display_page(__DIR__ . '/pages/ontology_browser.php', [
    'ontologies' => $ontologies, 'prefix' => $prefix, 'parts' => $parts, 'relations' => $relations,
    'release'    => (string) ($meta['release'] ?? ''),
    'roots'      => $prefix !== '' ? moop_ob_add_gene_counts($prefix, array_map(fn($r) => moop_ob_tree_row($prefix, $r, $parts, moop_ob_records_organism($prefix)), moop_ob_roots($prefix))) : [],
    'genes_missing' => $prefix === 'GO' ? moop_ob_gene_counts_missing() : 0,
    'page_styles' => [moop_url_base() . '/css/ontology-browser.css'],
    'page_script' => [moop_url_base() . '/js/modules/ontology-term-box.js', moop_url_base() . '/js/ontology-browser.js'],
    'scope'      => $prefix === 'GO' ? moop_ob_scope() : [],
    'inline_scripts' => ['const obConfig = ' . json_encode(['prefix' => $prefix, 'parts' => $parts, 'scope' => moop_ob_scope_params(),
        'api' => moop_url('api/ontology_children.php'), 'termUrl' => moop_url('tools/ontology_term.php')], JSON_HEX_TAG | JSON_HEX_AMP) . ';'],
], 'Ontology Browser');
