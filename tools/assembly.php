<?php
/**
 * ASSEMBLY DISPLAY PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (assembly.php)
 *   ↓
 * Validate user access
 *   ↓
 * Load assembly data from database
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/assembly.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Validate user access (via access_control.php)
 * - Load assembly data from database
 * - Configure title, scripts, styles
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (pages/assembly.php does that)
 */

include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');

// Validate parameters
$organism_name = validateOrganismParam($_GET['organism'] ?? '');
$assembly_param = validateAssemblyParam($_GET['assembly'] ?? '');

// Setup organism context (loads info, checks access)
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
$organism_info = $organism_context['info'];

// Verify database exists
$db_path = verifyOrganismDatabase($organism_name, $organism_data);

// Get assembly stats - searches by both accession and name
$assembly_info = getAssemblyStats($assembly_param, $db_path);

if (empty($assembly_info)) {
    die("Error: Assembly not found.");
}

// Configure display template
$display_config = [
    'title' => htmlspecialchars($assembly_info['genome_name']) . ' - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/assembly.php',
    'page_script' => "/$site/js/assembly-display.js",
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const assemblyName = '" . addslashes($assembly_info['genome_name']) . "';",
        "const assemblyAccession = '" . addslashes($assembly_info['genome_accession'] ?? '') . "';",
        "const organismName = '" . addslashes($organism_name) . "';",
        "const siteTitle = '" . addslashes($config->getString('siteTitle')) . "';"
    ]
];

// Data to pass to content file
$data = [
    'assembly_info' => $assembly_info,
    'organism_name' => $organism_name,
    'organism_info' => $organism_info,
    'assembly_accession' => $assembly_info['genome_accession'] ?? '',
    'site' => $site,
    'images_path' => $config->getString('images_path'),
    'absolute_images_path' => $config->getPath('absolute_images_path'),
    'db_path' => $db_path,
    'config' => $config,
    'inline_scripts' => $display_config['inline_scripts']
];

// Use generic display template
include_once __DIR__ . '/display-template.php';
?>
