<?php
/**
 * MOOP Mega Search (MOOPmart)
 *
 * Filter-based bulk export of gene features, annotations, and sequences
 * across organisms, assemblies, and gene sets.
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';
include_once __DIR__ . '/../lib/moop_functions.php';
require_once __DIR__ . '/../lib/expression_functions.php';

$organism_data = $config->getPath('organism_data');
$siteTitle     = $config->getString('siteTitle');

// The organism selector: tree, display info and what to pre-select from where the visitor came
// (organism / assembly / gene set / group / organisms[]). Shared with Annotation Search.
require_once __DIR__ . '/../lib/scope_selector.php';
$scope          = moop_scope_selector_data();
$scope_context  = moop_scope_context_from_request($scope);
$all_accessible = flattenSourcesList($scope['sources_by_group']);

// Load annotation type colors and descriptions from config
$metadata_path   = $config->getPath('metadata_path');
$ann_config_file = "$metadata_path/annotation_config.json";
$ann_types_config = [];
$ann_type_info    = [];
if (file_exists($ann_config_file)) {
    $ann_cfg = loadJsonFile($ann_config_file, []);
    $ann_types_config = $ann_cfg['annotation_types'] ?? [];
    foreach ($ann_types_config as $type => $data) {
        $ann_type_info[$type] = [
            'color'       => $data['color']       ?? 'secondary',
            'description' => $data['description'] ?? '',
        ];
    }
}

// Collect annotation sources grouped by type (for the panel) and flat (for the filter dropdown)
$annotation_source_types = []; // [type => ['color'=>..., 'sources'=>[name,...]]]
$annotation_source_names = []; // flat sorted list
$seen_orgs = [];
foreach ($all_accessible as $src) {
    $org = $src['organism'];
    if (isset($seen_orgs[$org])) continue;
    $seen_orgs[$org] = true;
    $data = moop_load_annotation_sources_cache($org);
    if ($data === null) continue;
    foreach ($data as $type => $sources) {
        if (!isset($annotation_source_types[$type])) {
            $annotation_source_types[$type] = [
                'color'   => $ann_types_config[$type]['color'] ?? 'secondary',
                'sources' => [],
            ];
        }
        foreach ($sources as $s) {
            $name = $s['name'];
            if (!in_array($name, $annotation_source_types[$type]['sources'], true)) {
                $annotation_source_types[$type]['sources'][] = $name;
            }
            $annotation_source_names[$name] = true;
        }
    }
}
$ann_order = array_keys($ann_type_info);
uksort($annotation_source_types, function ($a, $b) use ($ann_order) {
    $ai = array_search($a, $ann_order);
    $bi = array_search($b, $ann_order);
    if ($ai === false && $bi === false) return strcmp($a, $b);
    if ($ai === false) return 1;
    if ($bi === false) return -1;
    return $ai - $bi;
});
$annotation_source_names = array_keys($annotation_source_names);
sort($annotation_source_names);

// Curated anatomical expression (e.g. PAGE) is offered as one more annotation COLUMN for the
// organisms that have it. Not a filter source ($annotation_source_names): the filters query
// organism.sqlite, and these terms live in their own file.
$anatomy_organisms = moopmartAnatomyOrganisms(array_keys($seen_orgs));
if ($anatomy_organisms) {
    $annotation_source_types[MOOPMART_ANATOMY_TYPE] = ['color' => 'success', 'sources' => [MOOPMART_ANATOMY_SOURCE]];
}

// Gene sets this visitor can open that have expression data, as "org|asm|gene_set" keys (the
// source-key format). Step 4 offers "Send to Expression" only for these: a heatmap is one gene set.
$expression_gene_sets = array_map(fn($gs) => "{$gs['organism']}|{$gs['assembly']}|{$gs['gene_set']}",
    moop_expression_gene_sets($config->getPath('organism_data')));

// Chr names are loaded dynamically via API when exactly one assembly is selected.
// No pre-loading here — avoids reading N×M cache files on every page load.

echo render_display_page(
    __DIR__ . '/pages/moopmart.php',
    [
        'ann_type_info'           => $ann_type_info,
        'scope'                   => $scope,
        'annotation_source_types'  => $annotation_source_types,
        'anatomy_organisms'        => $anatomy_organisms,
        'annotation_source_names'  => $annotation_source_names,
        'siteTitle'               => $siteTitle,
        // scope-selector BEFORE moopmart.js: moopmart.js calls initScopeSelector().
        'page_script'             => [moop_url_base() . "/js/modules/scope-selector.js",
                                      moop_url_base() . "/js/modules/moopmart.js"],
        'inline_scripts'          => [
            'const annotationSources = ' . json_encode($annotation_source_names) . ';',
            "const moopSite = '" . moop_url_base() . "';",
            "const siteTitle = '"        . addslashes($siteTitle)                . "';",
            'const scopeContext = '      . json_encode($scope_context, JSON_HEX_TAG | JSON_HEX_AMP) . ';',
            'const expressionGeneSets = ' . json_encode($expression_gene_sets) . ';',
            'const expressionMaxGenes = ' . MOOP_EXPRESSION_TOOL_MAX_GENES      . ';',
        ],
    ],
    'MOOPmart — Data Exporter'
);
