<?php
/**
 * ORGANISM DISPLAY PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (organism.php)
 *   ↓
 * Validate user access
 *   ↓
 * Load organism data from database
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/organism.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Validate user access (via access_control.php)
 * - Load organism data from database
 * - Configure title, scripts, styles
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (pages/organism.php does that)
 */

// Load initialization
include_once __DIR__ . '/tool_init.php';

// Get config values
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$images_path = $config->getString('images_path');
$site = $config->getString('site');
$metadata_path = $config->getPath('metadata_path');

// Setup organism context (validates param, loads info, checks access)
$organism_context = setupOrganismDisplayContext($_GET['organism'] ?? '', $organism_data);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Load taxonomy tree and user access for breadcrumb counts
$group_data = getGroupData();
$taxonomy_user_access = getTaxonomyTreeUserAccess($group_data);
$taxonomy_tree_data = json_decode(file_get_contents("$metadata_path/taxonomy_tree_config.json"), true);

// Build scientific name from genus and species
$scientific_name = '';
if (!empty($organism_info['genus']) && !empty($organism_info['species'])) {
    $scientific_name = $organism_info['genus'] . ' ' . $organism_info['species'];
}

// If no description in organism info, fetch from Wikipedia
if (!isset($organism_info['html_p']) || empty($organism_info['html_p']) || !is_array($organism_info['html_p']) || count($organism_info['html_p']) === 0) {
    $wiki_data = getWikipediaOrganismData($organism_name, $scientific_name);
    if (!empty($wiki_data['description'])) {
        $organism_info['html_p'] = [
            [
                'text' => htmlspecialchars($wiki_data['description']) . 
                         '<br><br><small class="text-muted">Source: <a href="' . htmlspecialchars($wiki_data['wikipedia_url']) . 
                         '" target="_blank">Wikipedia</a></small>',
                'class' => '',
                'style' => ''
            ]
        ];
    }
}

// If no image, fetch from Wikipedia
if (empty($organism_info['images']) && empty(getOrganismImageWithCaption($organism_info, $images_path, $absolute_images_path)['image_path'])) {
    $wiki_data = getWikipediaOrganismData($organism_name, $scientific_name);
    if (!empty($wiki_data['image_url'])) {
        $organism_info['wikipedia_image'] = $wiki_data['image_url'];
        $organism_info['wikipedia_url'] = $wiki_data['wikipedia_url'];
    }
}

// Configure display template
$display_config = [
    'title' => htmlspecialchars(
        ($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) . ' - ' . $config->getString('siteTitle')
    ),
    'content_file' => __DIR__ . '/pages/organism.php',
    'page_script' => [
        '/' . $site . '/js/modules/search-utils.js',
        '/' . $site . '/js/organism-display.js'
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $site . "';",
        "const organismName = '" . addslashes($organism_name) . "';",
        "const siteTitle = '" . addslashes($config->getString('siteTitle')) . "';"
    ]
];

// Prepare data for content file
$data = [
    'organism_name' => $organism_name,
    'organism_info' => $organism_info,
    'config' => $config,
    'site' => $site,
    'images_path' => $images_path,
    'absolute_images_path' => $absolute_images_path,
    'taxonomy_user_access' => $taxonomy_user_access,
    'taxonomy_tree_data' => $taxonomy_tree_data,
];

// Use generic template to render
include_once __DIR__ . '/display-template.php';

?>
