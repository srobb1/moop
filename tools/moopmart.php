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

$organism_data = $config->getPath('organism_data');
$siteTitle     = $config->getString('siteTitle');

$all_accessible = flattenSourcesList(getAccessibleAssemblies());

// Build scope tree with deduplication: organism => assembly => [gene_sets]
$scope_tree    = [];
$organism_info = [];

foreach ($all_accessible as $src) {
    $org = $src['organism'];
    $asm = $src['assembly'];
    $gs  = $src['gene_set'] ?? '';

    if (!isset($scope_tree[$org]))       $scope_tree[$org]       = [];
    if (!isset($scope_tree[$org][$asm])) $scope_tree[$org][$asm] = [];
    if ($gs !== '' && !in_array($gs, $scope_tree[$org][$asm], true)) {
        $scope_tree[$org][$asm][] = $gs;
    }

    if (!isset($organism_info[$org])) {
        $info = loadOrganismInfo($org, $organism_data) ?: [];
        $organism_info[$org] = [
            'genus'       => $info['genus']       ?? '',
            'species'     => $info['species']     ?? '',
            'common_name' => $info['common_name'] ?? '',
        ];
    }
}

ksort($scope_tree);
foreach ($scope_tree as $org => &$asms) {
    ksort($asms);
    foreach ($asms as $asm => &$gene_sets) {
        sort($gene_sets);
    }
}
unset($asms, $gene_sets);

// Load annotation type colors from config
$metadata_path   = $config->getPath('metadata_path');
$ann_config_file = "$metadata_path/annotation_config.json";
$ann_types_config = [];
if (file_exists($ann_config_file)) {
    $ann_types_config = (json_decode(file_get_contents($ann_config_file), true) ?: [])['annotation_types'] ?? [];
}

// Collect annotation sources grouped by type (for the panel) and flat (for the filter dropdown)
$annotation_source_types = []; // [type => ['color'=>..., 'sources'=>[name,...]]]
$annotation_source_names = []; // flat sorted list
$seen_orgs = [];
foreach ($all_accessible as $src) {
    $org = $src['organism'];
    if (isset($seen_orgs[$org])) continue;
    $seen_orgs[$org] = true;
    $cache = "$organism_data/$org/annotation_sources_cache.json";
    if (!file_exists($cache)) continue;
    $data = json_decode(file_get_contents($cache), true) ?: [];
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
ksort($annotation_source_types);
$annotation_source_names = array_keys($annotation_source_names);
sort($annotation_source_names);

// Chr names are loaded dynamically via API when exactly one assembly is selected.
// No pre-loading here — avoids reading N×M cache files on every page load.

echo render_display_page(
    __DIR__ . '/pages/moopmart.php',
    [
        'scope_tree'              => $scope_tree,
        'organism_info'           => $organism_info,
        'annotation_source_types'  => $annotation_source_types,
        'annotation_source_names'  => $annotation_source_names,
        'siteTitle'               => $siteTitle,
        'page_script'             => ["/$site/js/modules/moopmart.js"],
        'inline_scripts'          => [
            'const annotationSources = ' . json_encode($annotation_source_names) . ';',
            "const moopSite = '/$site';",
            "const siteTitle = '"        . addslashes($siteTitle)                . "';",
        ],
    ],
    'MOOPmart: Mega Search'
);
