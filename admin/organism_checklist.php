<?php
/**
 * ORGANISM CHECKLIST - Wrapper
 * 
 * Handles admin access verification and renders organism setup checklist
 * using clean architecture layout system.
 */

// Load admin initialization (handles auth, config, includes)
include_once __DIR__ . '/admin_init.php';

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Get config
$site = $config->getString('site');
$organism_data = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();

// Every check the page reports, run once here. The page itself only renders.
include_once __DIR__ . '/../lib/organism_checklist.php';
$checks = organism_checklist_checks($organism_data, $sequence_types, $config->getPath('metadata_path'));

// Configure display
$display_config = [
    'title' => 'New Organism Setup Checklist - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/organism_checklist.php',
];

// Prepare data for content file
$data = [
    'config' => $config,
    'site' => $site,
    'organism_data' => $organism_data,
    'sequence_types' => $sequence_types,
    'page_script' => [
        '/' . $config->getString('site') . '/js/admin-utilities.js',
        '/' . $config->getString('site') . '/js/organism-checklist.js',
    ],
];
$data = array_merge($data, $checks);
// The dashboard's last background permission scan (housekeeping, at most an hour old),
// summarised under the live per-organism check so the deeper levels are not out of sight.
$data['perm_summary'] = $_SESSION['perm_summary'] ?? null;

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
