<?php
/**
 * PAGE: Planarian Anatomy Gene Expression -- search by anatomy term.
 * Plan: notes/PLANOSPHERE_PAGE_ROSETTA_PLAN.md. Step 1 (this page so far): pick a term with the
 * ontology term box, choose a relationship, and see what the search would cover. Step 2 adds the
 * curated expression records under it. Not in any menu until then.
 */
include_once __DIR__ . '/tool_init.php';
require_once __DIR__ . '/../lib/ontologies.php';
include_once __DIR__ . '/../includes/layout.php';

$relations = moop_ontology_relations('anatomy');
$built     = array_filter(moop_ontology_by_purpose('anatomy'), fn($p) => moop_ontology_meta($p) !== []);
$releases  = [];
foreach ($built as $p) $releases[$p] = moop_ontology_meta($p)['release'] ?? '';

echo render_display_page(__DIR__ . '/pages/anatomy_search.php', [
    'relations'   => $relations,
    'releases'    => $releases,
    'page_script' => [moop_url_base() . '/js/modules/ontology-term-box.js', moop_url_base() . '/js/anatomy-search.js'],
], 'PAGE: search by anatomy');
