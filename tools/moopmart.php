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

$organism_data = $config->getPath('organism_data');
$siteTitle     = $config->getString('siteTitle');

$all_accessible = flattenSourcesList(getAccessibleAssemblies());

// Build scope tree with deduplication: organism => assembly => [gene_sets]
$scope_tree     = [];
$organism_info  = [];
$assembly_names = [];  // [organism][assembly] = human-readable name if set

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

    if (!isset($assembly_names[$org][$asm])) {
        $gn = $src['genome_name'] ?? '';
        $assembly_names[$org][$asm] = ($gn && $gn !== $asm) ? $gn : '';
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

// Pre-filter to incoming organisms when launched from a toolbox
$scope_context = null;
$incoming_orgs = $_POST['organisms'] ?? $_GET['organisms'] ?? null;
if (!empty($incoming_orgs)) {
    $incoming_orgs = array_values(array_filter(
        is_array($incoming_orgs) ? $incoming_orgs : [$incoming_orgs],
        fn($o) => isset($scope_tree[$o])
    ));
    if (!empty($incoming_orgs)) {
        $scope_context = ['organisms' => $incoming_orgs];
    }
}

// Load annotation type colors and descriptions from config
$metadata_path   = $config->getPath('metadata_path');
$ann_config_file = "$metadata_path/annotation_config.json";
$ann_types_config = [];
$ann_type_info    = [];
if (file_exists($ann_config_file)) {
    $ann_cfg = json_decode(file_get_contents($ann_config_file), true) ?: [];
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

// Build organism → groups map for group chips in the flat organism list
$organism_groups = [];
foreach (getGroupData() as $entry) {
    $org = $entry['organism'];
    if (!isset($organism_groups[$org])) $organism_groups[$org] = [];
    foreach ($entry['groups'] as $g) {
        if (!in_array($g, $organism_groups[$org], true))
            $organism_groups[$org][] = $g;
    }
}

// Chr names are loaded dynamically via API when exactly one assembly is selected.
// No pre-loading here — avoids reading N×M cache files on every page load.

echo render_display_page(
    __DIR__ . '/pages/moopmart.php',
    [
        'ann_type_info'           => $ann_type_info,
        'scope_tree'              => $scope_tree,
        'organism_info'           => $organism_info,
        'assembly_names'          => $assembly_names,
        'organism_groups'         => $organism_groups,
        'annotation_source_types'  => $annotation_source_types,
        'annotation_source_names'  => $annotation_source_names,
        'siteTitle'               => $siteTitle,
        'page_script'             => ["/$site/js/modules/search-utils.js", "/$site/js/modules/moopmart.js"],
        'inline_scripts'          => [
            'const annotationSources = ' . json_encode($annotation_source_names) . ';',
            "const moopSite = '/$site';",
            "const siteTitle = '"        . addslashes($siteTitle)                . "';",
            'const scopeContext = '      . json_encode($scope_context)           . ';',
        ],
    ],
    'MOOPmart — Data Exporter'
);
