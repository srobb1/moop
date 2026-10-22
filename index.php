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
include_once __DIR__ . '/lib/home_examples.php';

// Get configuration
$config = ConfigManager::getInstance();

// Everything on this page is built from the entries THIS viewer may see, decided per
// gene set (moop_visible_group_entries()). Building it from the raw group data and
// filtering by organism -- three separate ways -- would show a private assembly of an
// organism that also has a public one.
$group_data       = getGroupData();
$visible_entries  = moop_visible_group_entries($group_data);
$cards_to_display = getIndexDisplayCards($group_data);

// Load taxonomy tree data
$metadata_path = $config->getPath('metadata_path');
$taxonomy_tree_data = loadJsonFile("$metadata_path/taxonomy_tree_config.json", []);

// Get user access for taxonomy tree
$taxonomy_user_access = getTaxonomyTreeUserAccess($group_data);

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

$organism_groups_map = moop_organism_groups_map($visible_entries);   // sorted, one order site-wide

$organism_list = [];
foreach (indexTraverseTree($taxonomy_tree_data['tree']) as $leaf) {
    $org = $leaf['organism'];
    if (!isset($taxonomy_user_access[$org])) continue;
    $leaf['groups'] = $organism_groups_map[$org] ?? [];
    $organism_list[] = $leaf;
}
usort($organism_list, fn($a, $b) => strcmp($a['display_name'], $b['display_name']));

$site = $config->getString('site');

// Quick-search dataset (organisms + groups + assemblies + gene sets).
$quick_search = buildHomeQuickSearch($visible_entries, $organism_list, $site);

// Featured groups for above-the-fold chips — only show groups the current user can access
$featured_groups = [];
$desc_file = "$metadata_path/group_descriptions.json";
if (file_exists($desc_file)) {
    $all_descs = loadJsonFile($desc_file, []);
    foreach ($all_descs as $d) {
        if (!empty($d['featured']) && isset($cards_to_display[$d['group_name']])) {
            $featured_groups[] = [
                'name' => $d['group_name'],
                'url'  => "/$site/tools/groups.php?group=" . urlencode($d['group_name']),
            ];
        }
    }
}

// Fallback: if this viewer has nothing manually featured to show (e.g. a public
// visitor whose only groups are the ones holding public gene sets, none of which
// an admin has marked featured), promote the groups that DO contain public gene
// sets — so the prominent showcase is never empty when there is public content.
// Manual `featured` always wins; this only fills an otherwise-blank section.
if (empty($featured_groups)) {
    $groups_with_public = [];
    foreach ($visible_entries as $entry) {
        if (entry_is_public($entry)) {
            foreach (($entry['groups'] ?? []) as $g) {
                if (strtoupper(trim((string)$g)) === 'PUBLIC') continue; // legacy keyword, not a real group
                $groups_with_public[$g] = true;
            }
        }
    }
    foreach ($cards_to_display as $group_name => $card) {
        if (isset($groups_with_public[$group_name])) {
            $featured_groups[] = [
                'name' => $group_name,
                'url'  => "/$site/tools/groups.php?group=" . urlencode($group_name),
            ];
        }
    }
}

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/index.php',
    [
        'siteTitle'          => $config->getString('siteTitle'),
        // Needed by the content file to build in-app links (the search-help modal points
        // at the annotation search). Every other page already has it.
        'site'               => $site,
        'organism_count'     => count($organism_list),
        'assembly_count'     => $quick_search['assembly_count'],
        'featured_groups'    => $featured_groups,
        // Example chips under both search boxes, chosen from data this viewer can open
        // (lib/home_examples.php) -- they were hard-coded IDs a public visitor could not.
        'examples'           => moop_home_examples($group_data),
        'cards_to_display'   => $cards_to_display,
        'page_script'        => [
            "/$site/js/modules/feature-search.js",
            "/$site/js/modules/taxonomy-tree.js",
            "/$site/js/modules/tool-launch.js",
            "/$site/js/index.js",
        ],
        'inline_scripts'     => [
            "const sitePath = '/$site';",
            "const quickSearchData = " . json_encode($quick_search['items']) . ";",
            "const userAccess = " . json_encode($taxonomy_user_access) . ";",
            // Only the organisms this viewer may see -- see pruneTaxonomyTreeForAccess().
            "const treeData = " . json_encode(['tree' => pruneTaxonomyTreeForAccess($taxonomy_tree_data['tree'] ?? [], $taxonomy_user_access)
                                                         ?? ['name' => 'Life', 'children' => []]]) . ";",
            "const organismData = " . json_encode($organism_list) . ";",
            // Colours come from moop_group_color(), shared with every other page; index.js
            // does not compute its own.
            "const groupColors = " . json_encode(moop_group_colors(array_merge(
                array_keys($cards_to_display),
                ...array_map(fn($o) => $o['groups'], $organism_list ?: [['groups' => []]])
            ))) . ";",
        ],
    ],
    $config->getString('siteTitle')
);
?>
