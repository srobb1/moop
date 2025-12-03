<?php
/**
 * MULTI-ORGANISM SEARCH PAGE - Wrapper
 * 
 * This is the main entry point for multi-organism search pages.
 * It:
 * 1. Loads and validates organisms
 * 2. Checks access control for all organisms
 * 3. Configures the display template
 * 4. Uses generic template system to render the page
 * 
 * The actual HTML content is in: pages/multi_organism.php
 * The generic rendering is in: display-template.php
 * The layout/structure is in: includes/layout.php
 */

include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');

// Get organisms from query parameters
$organisms = $_GET['organisms'] ?? [];
if (is_string($organisms)) {
    $organisms = [$organisms];
}

if (empty($organisms)) {
    header("Location: /$site/index.php");
    exit;
}

// Validate access for all organisms
foreach ($organisms as $organism) {
    $is_public = is_public_organism($organism);
    $has_organism_access = has_access('Collaborator', $organism);
    
    if (!$has_organism_access && !$is_public) {
        header("Location: /$site/access_denied.php");
        exit;
    }
}

// Configure display template
$organism_list = implode(', ', array_map('htmlspecialchars', $organisms));
$display_config = [
    'title' => 'Multi-Organism Search - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/multi_organism.php',
    'page_script' => "/$site/js/multi-organism-search.js",
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const searchOrganisms = " . json_encode($organisms) . ";"
    ]
];

// Data to pass to content file
$data = [
    'organisms' => $organisms,
    'organism_list' => $organism_list,
    'config' => $config,
    'inline_scripts' => $display_config['inline_scripts']
];

// Use generic display template
include_once __DIR__ . '/display-template.php';
?>
