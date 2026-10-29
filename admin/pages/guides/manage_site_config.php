<?php
/**
 * Guide for Manage Site Configuration (admin/pages/manage_site_config.php). If the page
 * changes, change this. The list of settings comes from ConfigManager's editable metadata —
 * the same labels and descriptions the page shows — so it cannot drift (the old About named 6
 * of the 22 settings).
 */
$settings = ConfigManager::getInstance()->getEditableConfigMetadata();
echo help_modal('siteconfig-guide', 'How Site Configuration works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'Settings an admin may change', 'text' => 'Saved to config/config_editable.json, over the defaults in site_config.php. Every save is listed under Recent Changes at the foot of the page.'],
        ['label' => 'Not here', 'text' => 'Structural settings — the site\'s paths and directories — are not editable here, so a typo cannot take the site down. The tutorial at the foot explains how to change them.'],
    ]],
    ['heading' => 'The ' . count($settings) . ' settings', 'cards' => array_values(array_map(
        fn($m) => ['label' => (string)($m['label'] ?? ''), 'text' => (string)($m['description'] ?? '')],
        $settings))],
]);
