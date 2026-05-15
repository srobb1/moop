<?php
/**
 * GENERIC SEARCH PAGE
 * Full-site annotation search with inline organism/assembly/gene_set scope selector
 * and inline annotation source filter.
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

$organism_data = $config->getPath('organism_data');

// Build scope tree: organism → assembly → [gene_sets]
// Also collect organism display info (genus/species/common_name)
$raw_sources    = flattenSourcesList(getAccessibleAssemblies());
$scope_tree     = [];   // [organism][assembly] = [gene_set, ...]
$organism_info  = [];   // [organism] = ['genus'=>..., 'species'=>..., 'common_name'=>...]

foreach ($raw_sources as $src) {
    $org = $src['organism'];
    $asm = $src['assembly'];
    $gs  = $src['gene_set'] ?? '';

    if (!isset($scope_tree[$org]))          $scope_tree[$org]       = [];
    if (!isset($scope_tree[$org][$asm]))    $scope_tree[$org][$asm] = [];
    if ($gs !== '' && !in_array($gs, $scope_tree[$org][$asm])) {
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

// Sort tree: organisms alphabetically, assemblies alphabetically, gene_sets alphabetically
ksort($scope_tree);
foreach ($scope_tree as $org => &$asms) {
    ksort($asms);
    foreach ($asms as $asm => &$gene_sets) {
        sort($gene_sets);
    }
}
unset($asms, $gene_sets);

$all_organisms = array_keys($scope_tree);

$display_config = [
    'title'        => 'Search — ' . htmlspecialchars($siteTitle),
    'content_file' => __DIR__ . '/pages/search.php',
    'page_script'  => [
        "/$site/js/modules/search-utils.js",
        "/$site/js/search-display.js",
    ],
    'page_styles'  => [
        "/$site/css/display.css",
        "/$site/css/parent.css",
        "/$site/css/advanced-search-filter.css",
        "/$site/css/search-controls.css",
    ],
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const allOrganisms = " . json_encode($all_organisms) . ";",
        "const scopeTree = "    . json_encode($scope_tree)    . ";",
    ],
];

$data = [
    'site'          => $site,
    'siteTitle'     => $siteTitle,
    'scope_tree'    => $scope_tree,
    'organism_info' => $organism_info,
    'all_organisms' => $all_organisms,
    'inline_scripts' => $display_config['inline_scripts'],
    'page_styles'    => $display_config['page_styles'],
];

include_once __DIR__ . '/display-template.php';
