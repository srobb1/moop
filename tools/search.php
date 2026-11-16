<?php
/**
 * GENERIC SEARCH PAGE
 * Full-site annotation search with inline organism/assembly/gene_set scope selector
 * and inline annotation source filter.
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

$organism_data = $config->getPath('organism_data');

// Load annotation type descriptions for the (i) modal
$ann_config_file = $config->getPath('metadata_path') . '/annotation_config.json';
$ann_type_info   = [];
if (file_exists($ann_config_file)) {
    $ann_cfg = loadJsonFile($ann_config_file, []);
    foreach ($ann_cfg['annotation_types'] ?? [] as $type => $data) {
        $ann_type_info[$type] = [
            'color'       => $data['color']       ?? 'secondary',
            'description' => $data['description'] ?? '',
        ];
    }
}

// The organism selector: tree, display info and what to pre-select from where the visitor came
// (organism / assembly / gene set / group / organisms[]). Shared with the Data Exporter.
require_once __DIR__ . '/../lib/scope_selector.php';
$scope         = moop_scope_selector_data();
$all_organisms = array_keys($scope['tree']);
$scope_context = moop_scope_context_from_request($scope);
// ?source=<annotation source>: search ONLY that source (e.g. "KEGG Pathway (eggNOG)" from a gene page's KEGG
// pathway), and run the search at once when the keyword is there too. The name is only matched against the
// sources the panel lists for this scope, so an unknown one does nothing.
$ctx_source = (string)($_GET['source'] ?? '');
if ($scope_context && $ctx_source !== '' && strlen($ctx_source) <= 200 && !preg_match('/[\x00-\x1f]/', $ctx_source)) {
    $scope_context['source'] = $ctx_source;
}

// Was read below but never set: the tab said "Search —" and siteTitle reached the JS empty.
$siteTitle = ConfigManager::getInstance()->getString('siteTitle');

$display_config = [
    'title'        => 'Search',
    'content_file' => __DIR__ . '/pages/search.php',
    'page_script'  => [
        // Before search-display.js, which calls initScopeSelector().
        moop_url_base() . "/js/modules/scope-selector.js",
        moop_url_base() . "/js/search-display.js",
    ],
    'page_styles'  => [
        moop_url_base() . "/css/display.css",
        moop_url_base() . "/css/parent.css",
        moop_url_base() . "/css/advanced-search-filter.css",
        moop_url_base() . "/css/search-controls.css",
    ],
    'inline_scripts' => [
        "const sitePath = '" . moop_url_base() . "';",
        "const siteTitle = '" . addslashes($siteTitle) . "';",
        "const allOrganisms = "  . json_encode($all_organisms)  . ";",
        "const scopeContext = "  . json_encode($scope_context, JSON_HEX_TAG | JSON_HEX_AMP) . ";",
        "const annTypeOrder = "  . json_encode(array_keys($ann_type_info)) . ";",
    ],
];

$data = [
    'site'          => $site,
    'siteTitle'     => $siteTitle,
    'scope'           => $scope,
    'all_organisms'   => $all_organisms,
    'ann_type_info'   => $ann_type_info,
    'inline_scripts' => $display_config['inline_scripts'],
    'page_styles'    => $display_config['page_styles'],
];

include_once __DIR__ . '/display-template.php';
