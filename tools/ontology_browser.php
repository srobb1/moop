<?php
/**
 * Ontology browser -- the tree. A term's row shows its definition; opening it loads the terms
 * directly below (api/ontology_children.php); its name opens the term page (ontology_term.php).
 *   ?o=PLANA            which ontology (default: the first that can be browsed)
 *   &parts=1            also open branches by the ontology's other relations (part of, contained in...)
 * Data: lib/ontology_browser.php. Registry: 'ontology_browser' (only where an ontology is built).
 */
include_once __DIR__ . '/tool_init.php';
require_once __DIR__ . '/../lib/ontology_browser.php';
include_once __DIR__ . '/../includes/layout.php';

$ontologies = moop_ob_ontologies();
$prefix     = strtoupper((string) ($_GET['o'] ?? ''));
if (!isset($ontologies[$prefix])) $prefix = (string) array_key_first($ontologies);
$parts      = !empty($_GET['parts']);
$meta       = $prefix !== '' ? moop_ontology_meta($prefix) : [];
$relations  = $prefix !== '' ? moop_ob_relations($prefix) : [];

echo render_display_page(__DIR__ . '/pages/ontology_browser.php', [
    'ontologies' => $ontologies, 'prefix' => $prefix, 'parts' => $parts, 'relations' => $relations,
    'release'    => (string) ($meta['release'] ?? ''),
    'roots'      => $prefix !== '' ? array_map(fn($r) => moop_ob_tree_row($prefix, $r, $parts, moop_ob_records_organism($prefix)), moop_ob_roots($prefix)) : [],
    'page_styles' => [moop_url_base() . '/css/ontology-browser.css'],
    'page_script' => [moop_url_base() . '/js/modules/ontology-term-box.js', moop_url_base() . '/js/ontology-browser.js'],
    'inline_scripts' => ['const obConfig = ' . json_encode(['prefix' => $prefix, 'parts' => $parts,
        'api' => moop_url('api/ontology_children.php'), 'termUrl' => moop_url('tools/ontology_term.php')], JSON_HEX_TAG | JSON_HEX_AMP) . ';'],
], 'Ontology Browser');
