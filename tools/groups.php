<?php
/**
 * GROUPS DISPLAY PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (groups.php)
 *   ↓
 * Validate user access
 *   ↓
 * Load group data from database
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/groups.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Validate user access (via access_control.php)
 * - Load group data from database
 * - Configure title, scripts, styles
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (pages/groups.php does that)
 */

include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$images_path = $config->getString('images_path');

// Get the group name from query parameter
$group_name = $_GET['group'] ?? '';
$taxonomy_rank = $_GET['taxonomy_rank'] ?? '';
$is_taxonomy_group = !empty($taxonomy_rank);

if (empty($group_name) && empty($taxonomy_rank)) {
    header("Location: /$site/index.php");
    exit;
}

// Load group descriptions using helper
$group_descriptions_file = "$metadata_path/group_descriptions.json";
$group_descriptions = loadJsonFile($group_descriptions_file, []);

// Load organism assembly groups
$group_data = getGroupData();

// Handle both manual groups and taxonomy groups
if ($is_taxonomy_group) {
    // Taxonomy group: Load tree and get organisms at this level
    $taxonomy_tree_data = loadJsonFile("$metadata_path/taxonomy_tree_config.json", []);
    $group_organisms = getOrganismsAtTaxonomyLevel($taxonomy_rank, $taxonomy_tree_data['tree'], $group_data);
    
    // Fetch Wikipedia data for this taxonomic rank
    $wiki_data = getWikipediaTaxonomyData($taxonomy_rank);
    
    // Create synthetic group info for taxonomy groups with Wikipedia content
    $group_info = [
        'name' => $taxonomy_rank,
        'group_name' => $taxonomy_rank,
        'description' => $wiki_data['description'] ?: "All organisms in the taxonomic rank: <em>$taxonomy_rank</em>",
        'type' => 'taxonomy',
        'wikipedia_url' => $wiki_data['wikipedia_url'],
        'wikipedia_image' => $wiki_data['image_url']
    ];
    
    // Add HTML paragraph if we have a description
    if (!empty($wiki_data['description'])) {
        $group_info['html_p'] = [
            [
                'text' => htmlspecialchars($wiki_data['description']) . 
                         '<br><br><small class="text-muted">Source: <a href="' . htmlspecialchars($wiki_data['wikipedia_url']) . 
                         '" target="_blank">Wikipedia</a></small>',
                'class' => '',
                'style' => ''
            ]
        ];
    }
    
    // Use taxonomy_rank as the display name everywhere (replaces $group_name in template)
    $group_name = $taxonomy_rank;
} else {
    // A ?group= name we do not hold. Two things can be going on, and they were
    // previously indistinguishable — both produced an empty organism list and a page
    // that looked broken.
    //
    //   1. It is a taxonomy rank, not a curated group. Someone hand-writing or sharing
    //      a URL has no reason to know the difference (the lineage chips link to
    //      ?taxonomy_rank=, the group chips to ?group=), so send them where they meant
    //      to go rather than showing them nothing.
    //   2. We simply have nothing filed under that name — which does NOT mean the name
    //      is wrong. See taxonomyRankExists(): the tree only contains ranks our own
    //      organisms sit under, so a real taxon like Primates is absent purely because
    //      no organism here belongs to it.
    //
    // Curated groups are checked FIRST and win: names like Cnidaria are both a group
    // and a rank, and the existing group page must keep its URL.
    if (!groupNameExists($group_name, $group_data, $group_descriptions)) {
        $taxonomy_tree_data = loadJsonFile("$metadata_path/taxonomy_tree_config.json", []);
        if (taxonomyRankExists($group_name, $taxonomy_tree_data['tree'] ?? [])) {
            header('Location: /' . $site . '/tools/groups.php?taxonomy_rank=' . urlencode($group_name), true, 302);
            exit;
        }
        // Nothing under that name. Fall through with an empty list; the view says so
        // plainly. No access check below — there is nothing here to protect, and
        // demanding a login for a grouping we do not have would be a lie.
        $group_unknown = true;
    }

    // Manual group: Use existing logic
    // Find the description for this group
    $group_info = null;
    foreach ($group_descriptions as $desc) {
        if ($desc['group_name'] === $group_name && ($desc['in_use'] ?? false)) {
            $group_info = $desc;
            break;
        }
    }

    // Get organisms in this group that user has access to
    $group_organisms = getAccessibleOrganismsInGroup($group_name, $group_data);
}

// Access control: Only check manual groups (taxonomy groups are based on tree data).
// Skipped for a name we hold nothing under — is_public_group() is false for any such
// name, so this used to send a public visitor to access-denied for a grouping that
// does not exist here, telling them they lacked permission for nothing.
if (!$is_taxonomy_group && empty($group_unknown) && !is_public_group($group_name)) {
    require_access('COLLABORATOR', $group_name);
}

// Configure display template
$page_scripts = [
    "/$site/js/modules/organism-utils.js",
    "/$site/js/groups-display.js"
];

$display_config = [
    'title' => htmlspecialchars($group_name) . ' - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/groups.php',
    'page_script' => $page_scripts,
    'inline_scripts' => [
        "const sitePath = '/$site';",
        "const groupName = '" . addslashes($group_name) . "';",
        "const groupOrganisms = " . json_encode(array_keys($group_organisms)) . ";",
        "const siteTitle = '" . addslashes($config->getString('siteTitle')) . "';"
    ]
];

// Data to pass to content file
$data = [
    'group_name' => $group_name,
    'group_info' => $group_info,
    'group_organisms' => $group_organisms,
    'organisms_list' => array_keys($group_organisms),  // For tool context
    'is_taxonomy_group' => $is_taxonomy_group,
    // True when nothing at all is filed under this name — as opposed to a real group
    // that is merely empty or invisible to this user. The view words the two the same
    // way on purpose (see tools/pages/groups.php); this flag exists so it can avoid
    // offering a search box for a grouping with nothing in it.
    'group_unknown' => !empty($group_unknown),
    'config' => $config,
    'site' => $site,
    'images_path' => $images_path,
    'absolute_images_path' => $absolute_images_path,
    'organism_data' => $organism_data,
    'page_styles' => [
        "/$site/css/groups.css",
        "/$site/css/advanced-search-filter.css"
    ],
];

// Use generic display template
include_once __DIR__ . '/display-template.php';
?>
