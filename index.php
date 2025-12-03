<?php
/**
 * MOOP Homepage
 * 
 * Main entry point for the application.
 * Displays organism cards and taxonomy tree for browsing/selecting organisms.
 */

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

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/index.php',
    [
        'siteTitle' => $config->getString('siteTitle'),
        'cards_to_display' => $cards_to_display,
        'taxonomy_tree_data' => $taxonomy_tree_data,
        'user_access_json' => $user_access_json,
        'ip' => $ip,
    ],
    $config->getString('siteTitle')
);
?>
