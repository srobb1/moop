<?php
/**
 * MOOP Homepage
 *
 * Main entry point for the application.
 * Displays organism cards and taxonomy tree for browsing/selecting organisms.
 */

// Redirect to setup wizard if not yet configured
if (!file_exists(__DIR__ . '/config/config_editable.json')) {
    header('Location: setup.php');
    exit;
}

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';
include_once __DIR__ . '/lib/moop_functions.php';

// Get configuration and user data
$config = ConfigManager::getInstance();
$usersFile = $config->getPath('users_file');
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
}

// Get visitor IP (set in access_control.php)
global $visitor_ip;
$ip = $visitor_ip;

// Get user group data and display permissions
$group_data = getGroupData();
$cards_to_display = getIndexDisplayCards($group_data);

// Load taxonomy tree data
$metadata_path = $config->getPath('metadata_path');
$taxonomy_tree_data = json_decode(file_get_contents("$metadata_path/taxonomy_tree_config.json"), true);

// Get user access for taxonomy tree
$taxonomy_user_access = getTaxonomyTreeUserAccess($group_data);
$user_access_json = json_encode($taxonomy_user_access);

// Build flat organism list for Organism Select / Taxon Select tabs
function indexTraverseTree(array $node, array $chain = []): array {
    $path = array_merge($chain, [$node['name']]);
    $out  = [];
    if (isset($node['organism'])) {
        $out[] = [
            'organism'    => $node['organism'],
            'display_name'=> $node['name'],
            'common_name' => $node['common_name'] ?? '',
            'taxon_chain' => array_slice($path, 0, -1), // ancestors only, not the leaf
        ];
    }
    foreach ($node['children'] ?? [] as $child) {
        $out = array_merge($out, indexTraverseTree($child, $path));
    }
    return $out;
}

$organism_groups_map = [];
foreach ($group_data as $entry) {
    $org = $entry['organism'];
    if (!isset($organism_groups_map[$org])) $organism_groups_map[$org] = [];
    foreach ($entry['groups'] as $g) {
        if (!in_array($g, $organism_groups_map[$org], true))
            $organism_groups_map[$org][] = $g;
    }
}

$organism_list = [];
foreach (indexTraverseTree($taxonomy_tree_data['tree']) as $leaf) {
    $org = $leaf['organism'];
    if (!isset($taxonomy_user_access[$org])) continue;
    $leaf['groups'] = $organism_groups_map[$org] ?? [];
    $organism_list[] = $leaf;
}
usort($organism_list, fn($a, $b) => strcmp($a['display_name'], $b['display_name']));

// Build quick-search dataset (organisms + groups + assemblies + gene sets)
$site = $config->getString('site');

$qs_items = [];

// Organisms
$org_display_map = [];
foreach ($organism_list as $org_entry) {
    $org_display_map[$org_entry['organism']] = $org_entry['display_name'];
    $search_parts = array_filter(array_merge(
        [$org_entry['display_name'], $org_entry['common_name']],
        $org_entry['groups'],
        $org_entry['taxon_chain']
    ));
    $qs_items[] = [
        'type'      => 'organism',
        'label'     => $org_entry['display_name'],
        'secondary' => $org_entry['common_name'],
        'url'       => "/$site/tools/organism.php?organism=" . urlencode($org_entry['organism']),
        'search'    => strtolower(implode(' ', $search_parts)),
    ];
}

// Groups
foreach ($cards_to_display as $card) {
    $count = !empty($card['organism_count']) ? $card['organism_count'] . ' organisms' : '';
    $qs_items[] = [
        'type'      => 'group',
        'label'     => $card['title'],
        'secondary' => $count,
        'url'       => "/$site/tools/groups.php?group=" . urlencode($card['title']),
        'search'    => strtolower($card['title']),
    ];
}

// Assemblies and gene sets
$seen_assemblies = [];
$seen_genesets   = [];
foreach (getAccessibleAssemblies() as $a) {
    $org         = $a['organism'];
    $org_display = $org_display_map[$org] ?? str_replace('_', ' ', $org);
    $asm_id      = $a['genome_accession'] ?? $a['assembly'];
    $asm_name    = $a['genome_name'] ?? '';

    $asm_key = $org . '|' . $asm_id;
    if (!isset($seen_assemblies[$asm_key])) {
        $seen_assemblies[$asm_key] = true;
        $secondary = trim(($asm_name ? $asm_name . ' · ' : '') . $org_display, ' · ');
        $qs_items[] = [
            'type'      => 'assembly',
            'label'     => $asm_id,
            'secondary' => $secondary,
            'url'       => "/$site/tools/assembly.php?organism=" . urlencode($org) . "&assembly=" . urlencode($asm_id),
            'search'    => strtolower(implode(' ', array_filter([$asm_id, $asm_name, $org_display, $org]))),
        ];
    }

    $gs_key = $asm_key . '|' . $a['gene_set'];
    if (!isset($seen_genesets[$gs_key])) {
        $seen_genesets[$gs_key] = true;
        $qs_items[] = [
            'type'      => 'geneset',
            'label'     => $a['gene_set'],
            'secondary' => $org_display . ' › ' . $asm_id,
            'url'       => "/$site/tools/gene_set.php?organism=" . urlencode($org) . "&assembly=" . urlencode($asm_id) . "&gene_set=" . urlencode($a['gene_set']),
            'search'    => strtolower(implode(' ', array_filter([$a['gene_set'], $org_display, $org, $asm_id, $asm_name]))),
        ];
    }
}

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/index.php',
    [
        'siteTitle'          => $config->getString('siteTitle'),
        'cards_to_display'   => $cards_to_display,
        'taxonomy_tree_data' => $taxonomy_tree_data,
        'user_access_json'   => $user_access_json,
        'organism_list_json' => json_encode($organism_list),
        'ip'                 => $ip,
        'inline_scripts'     => [
            "const sitePath = '/$site';",
            "const quickSearchData = " . json_encode($qs_items) . ";",
        ],
    ],
    $config->getString('siteTitle')
);
?>
