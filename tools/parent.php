<?php
/**
 * PARENT DISPLAY PAGE
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (parent.php)
 *   ↓
 * Validate user access
 *   ↓
 * Load parent feature data from database
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call render_display_page() with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/parent.php) displays data
 * 
 * ========== RESPONSIBILITIES ==========
 * 
 * This file does:
 * - Validate user access (via access_control.php)
 * - Load parent feature data from database
 * - Configure title, scripts, styles
 * - Pass data to render_display_page()
 * 
 * This file does NOT:
 * - Output HTML directly (layout.php does that)
 * - Include <html>, <head>, <body> tags (layout.php does that)
 * - Load CSS/JS libraries (layout.php does that)
 * - Display content (pages/parent.php does that)
 * 
 * URL Parameters:
 * - organism: Organism name (required)
 * - uniquename: Feature uniquename (required)
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/parent_functions.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sequence_types = $config->getSequenceTypes();

// Validate required parameters
if (empty($_GET['organism']) || empty($_GET['uniquename'])) {
    die("Error: Missing required parameters. Please provide both 'organism' and 'uniquename' parameters.");
}

$uniquename = $_GET['uniquename']; // uniquename is a feature identifier, not an assembly

// Setup organism context (validates param, loads info, checks access)
$organism_context = setupOrganismDisplayContext($_GET['organism'], $organism_data, true);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Verify and get database path
$db = verifyOrganismDatabase($organism_name, $organism_data);

// Get accessible assemblies and convert to genome IDs for permission-based filtering
$group_data = getGroupData();
$accessible_assemblies = [];
foreach ($group_data as $data) {
    if ($data['organism'] === $organism_name && has_assembly_access($organism_name, $data['assembly'])) {
        $accessible_assemblies[] = $data['assembly'];
    }
}
$accessible_genome_ids = getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db);

// Security: Verify user has access to at least one assembly
if (empty($accessible_genome_ids)) {
    die("Error: No accessible assemblies found for this organism.");
}

// Load annotation configuration using helper
$annotation_config_file = "$metadata_path/annotation_config.json";
$annotation_config = loadJsonFileRequired($annotation_config_file, "Missing annotation_config.json");

$analysis_order = [];
$analysis_desc = [];
$annotation_colors = [];
$annotation_labels = [];

// Require new format with annotation_types
if (isset($annotation_config['annotation_types'])) {
    $types = $annotation_config['annotation_types'];
    // Sort by order
    uasort($types, function($a, $b) {
        return ($a['order'] ?? 999) - ($b['order'] ?? 999);
    });
    
    foreach ($types as $key => $config) {
        if ($config['enabled'] ?? true) {
            $analysis_order[] = $key;
            $analysis_desc[$key] = $config['description'] ?? '';
            $annotation_colors[$key] = $config['color'] ?? 'secondary';
            $annotation_labels[$key] = $config['display_label'] ?? $key;
        }
    }
} else {
    die("Error: annotation_config.json must use the new 'annotation_types' format. Legacy format is no longer supported.");
}

// Define parent types from organism.json feature_types, fallback to defaults
$parents = ['gene', 'pseudogene'];
if (!empty($organism_info['feature_types']['parents'])) {
    $parents = $organism_info['feature_types']['parents'];
}

// Get ancestors for the feature
$ancestors = getAncestors($uniquename, $db, $accessible_genome_ids);

// Save the highest ancestor with type in $parents in these variables
[$ancestor_feature_id, $ancestor_feature_uniquename, $ancestor_feature_type] = ['', '', ''];

if (count($ancestors) == 1) {
    // self only, no parents
    $ancestor = $ancestors[0];
    $ancestor_feature_id = $ancestor['feature_id'];
    $ancestor_feature_type = $ancestor['feature_type'];
    $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
    $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
} elseif (count($ancestors) > 1) {
    // self, plus at least one ancestor
    foreach ($ancestors as $ancestor) {
        $ancestor_feature_id = $ancestor['feature_id'];
        $ancestor_feature_type = $ancestor['feature_type'];
        $ancestor_feature_uniquename = $ancestor['feature_uniquename'];
        $ancestor_parent_feature_id = $ancestor['parent_feature_id'];
        if (in_array($ancestor_feature_type, $parents)) {
            // Stop: we reached our valid parent type for a page
            break;
        }
    }
}

// Performing SQL query to get info associated with found Parent ID
$row = getFeatureById($ancestor_feature_id, $db, $accessible_genome_ids);

// Get all info about Highest Parent
if (empty($row)) { 
    die("The gene $uniquename was not found in the database. Please, check the spelling carefully or try to find it in the search tool.");
}

$feature_id = $row['feature_id'];
$feature_uniquename = $row['feature_uniquename'];
$parent_id = $row['parent_feature_id'];
$name = $row['feature_name'];
$description = $row['feature_description'];      
$genus = $row['genus'];
$species = $row['species'];
$species_subtype = $row['subtype'];
$type = $row['feature_type'];
$common_name = $row['common_name'];
$genome_accession = $row['genome_accession'];
$genome_name = $row['genome_name'];

$family_feature_ids = [$feature_id];
$retrieve_these_seqs = [$feature_uniquename];

// Get children with hierarchical structure (for proper nesting)
$children_hierarchical = getChildrenHierarchical($feature_id, $db, $accessible_genome_ids);

// Get all children flat for sequence retrieval (keeping getChildren for backwards compatibility)
$children = getChildren($feature_id, $db, $accessible_genome_ids);

// Optimize: Get ALL annotations for parent and all children in ONE query
$all_feature_ids = [$feature_id];
foreach ($children as $child) {
    $all_feature_ids[] = $child['feature_id'];
}
$all_annotations = getAllAnnotationsForFeatures($all_feature_ids, $db);

// Render page using layout system
echo render_display_page(
    __DIR__ . '/pages/parent.php',
    [
        'organism_name' => $organism_name,
        'feature_id' => $feature_id,
        'feature_uniquename' => $feature_uniquename,
        'description' => $description,
        'type' => $type,
        'genus' => $genus,
        'species' => $species,
        'species_subtype' => $species_subtype,
        'common_name' => $common_name,
        'genome_accession' => $genome_accession,
        'genome_name' => $genome_name,
        'children' => $children,
        'children_hierarchical' => $children_hierarchical,
        'db' => $db,
        'all_annotations' => $all_annotations,
        'analysis_order' => $analysis_order,
        'annotation_colors' => $annotation_colors,
        'annotation_labels' => $annotation_labels,
        'analysis_desc' => $analysis_desc,
        'retrieve_these_seqs' => $retrieve_these_seqs,
        'enable_downloads' => true,
        'assembly_name' => $genome_accession,
        'page_styles' => ["/moop/css/parent.css"],
        'page_script' => "/moop/js/modules/parent-tools.js"
    ],
    htmlspecialchars($feature_uniquename)
);
?>
