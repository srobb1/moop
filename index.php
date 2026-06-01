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
    ],
    $config->getString('siteTitle')
);
?>
