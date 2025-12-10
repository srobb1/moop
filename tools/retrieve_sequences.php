<?php
/**
 * RETRIEVE SEQUENCES - Sequence Download Tool
 * 
 * Allows users to manually search for and download sequences.
 * Accessible from organism, assembly, and groups display pages.
 * Uses blastdbcmd to extract from FASTA BLAST databases.
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (controller)
 *   ↓
 * Validate user access to assembly
 *   ↓
 * Extract sequences if IDs provided
 *   ↓
 * IF download flag → sendFile() + exit (never reaches template)
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call display-template.php with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/retrieve_sequences.php) displays data
 */

// Start output buffering to prevent any stray whitespace from includes
// affecting file downloads or headers
ob_start();

// Get parameters for processing
$sequence_ids_provided = !empty($_POST['uniquenames']);
$download_file_flag = isset($_POST['download_file']) && $_POST['download_file'] == '1';
$sequence_type = trim($_POST['sequence_type'] ?? '');

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();
$admin_email = $config->getString('admin_email');
$siteTitle = $config->getString('siteTitle');

// Discard any output from includes
ob_end_clean();

// Check access to assembly if specified (visitors can access public assemblies without login)
$trying_public_access = !empty($_POST['assembly']) && !empty($_POST['organism']);
if ($trying_public_access) {
    if (!has_assembly_access($_POST['organism'], $_POST['assembly'])) {
        header("Location: /$site/login.php");
        exit;
    }
}

// Parse context parameters and organism filters
$context = parseContextParameters();

$organisms_param = $_GET['organisms'] ?? $_POST['organisms'] ?? '';
$organism_result = parseOrganismParameter($organisms_param, '');
$filter_organisms = $organism_result['organisms'];

// Get uniquenames (may be empty on initial page load)
$uniquenames_string = trim($_POST['uniquenames'] ?? $_GET['uniquenames'] ?? '');

// Get ALL accessible assemblies organized by group and organism
$sources_by_group = getAccessibleAssemblies();
$accessible_sources = flattenSourcesList($sources_by_group);

// Initialize selected organism/assembly variables
// Check both GET (from URL parameters like ?organism=X&assembly=Y) and POST (from form submission)
$selected_organism = trim($_POST['organism'] ?? $_GET['organism'] ?? '');
$selected_assembly = trim($_POST['assembly'] ?? $_GET['assembly'] ?? '');
$displayed_content = [];
$should_scroll_to_results = false;
$uniquenames = [];
$ranges = [];
$found_ids = [];
$download_error_msg = '';

// Initialize selected_source based on organism and assembly
// This ensures the correct radio button is pre-selected when the page loads with URL parameters
$selected_source = '';

// First, try using context parameters (explicit intent to pre-select)
if (!empty($context['organism']) && !empty($context['assembly'])) {
    $selected_organism = $context['organism'];
    $selected_assembly = $context['assembly'];
}

if (!empty($selected_organism) && !empty($selected_assembly)) {
    // First try direct match (assembly as-is)
    $selected_source = $selected_organism . '|' . $selected_assembly;
    
    // If no direct match found by checking accessible_sources, try matching by genome_id
    $source_found = false;
    foreach ($accessible_sources as $source) {
        if ($source['organism'] === $selected_organism && $source['assembly'] === $selected_assembly) {
            $source_found = true;
            break;
        }
    }
    
    // If not found by direct accession match, try via genome_id lookup
    if (!$source_found) {
        try {
            $db_path = "$organism_data/$selected_organism/organism.sqlite";
            [$genome_id_param, $genome_name_param, $genome_accession_param] = getAssemblyInfo($selected_assembly, $db_path);
            
            // Now find source matching this genome_id
            foreach ($accessible_sources as $source) {
                if ($source['organism'] === $selected_organism && $source['genome_id'] == $genome_id_param) {
                    $selected_source = $selected_organism . '|' . $source['assembly'];
                    break;
                }
            }
        } catch (Exception $e) {
            // If lookup fails, stick with original assembly value
        }
    }
} elseif (!empty($selected_organism)) {
    // If only organism specified (no assembly), select first assembly for that organism
    foreach ($accessible_sources as $source) {
        if ($source['organism'] === $selected_organism) {
            $selected_source = $selected_organism . '|' . $source['assembly'];
            break;
        }
    }
}

// If sequence IDs are provided, extract ALL sequence types
if (!empty($sequence_ids_provided)) {
    $extraction_errors = [];
    $original_uniquenames = []; // Initialize early in case of errors
    
    // Find matching source for $selected_assembly
    // Works whether $selected_assembly is accession or genome_name
    $fasta_source = null;
    foreach ($accessible_sources as $source) {
        if ($source['organism'] === $selected_organism && 
            ($source['assembly'] === $selected_assembly || $source['genome_name'] === $selected_assembly)) {
            $fasta_source = $source;
            break;
        }
    }
    
    if (!$fasta_source) {
        $extraction_errors[] = "Assembly not found or not accessible.";
    }
    
    // Parse and validate feature IDs
    if (empty($extraction_errors)) {
        $id_parse = parseFeatureIds($uniquenames_string);
        if (!$id_parse['valid']) {
            $extraction_errors[] = $id_parse['error'];
        } else {
            $uniquenames = $id_parse['uniquenames'];
            $ranges = $id_parse['ranges'] ?? [];  // Extract ranges from parsing result
            
            // Save original uniquenames to check which children were explicitly listed
            $original_uniquenames = $uniquenames;
            
            // Get children for each parent ID (like parent_display.php does)
            try {
                $db = verifyOrganismDatabase($selected_organism, $organism_data);
                
                $expanded_uniquenames = [];
                $parent_to_children = []; // Track parent->children mapping
                foreach ($uniquenames as $uniquename) {
                    $expanded_uniquenames[] = $uniquename;
                    
                    // Check if any children of this ID are already in the input with ranges
                    // If so, skip auto-expansion (children are explicitly requested)
                    $has_ranged_children = false;
                    foreach ($ranges as $range_entry) {
                        // Get the ID part from the range
                        $range_id = explode(':', $range_entry)[0];
                        $range_id = explode(' ', $range_id)[0];
                        
                        // Check if this is a child of current uniquename
                        if (preg_match('/^' . preg_quote($uniquename) . '\.\d+$/', $range_id)) {
                            $has_ranged_children = true;
                            break;
                        }
                    }
                    
                    // Lookup feature to get feature_id
                    $feature_result = getFeatureByUniquename($uniquename, $db);
                    if (!empty($feature_result)) {
                        $feature_id = $feature_result['feature_id'];
                        // Get all children
                        $children = getChildren($feature_id, $db);
                        if (!empty($children)) {
                            $child_names = [];
                            foreach ($children as $child) {
                                $child_name = $child['feature_uniquename'];
                                
                                // If children are already explicitly provided (with ranges), don't auto-add others
                                if ($has_ranged_children) {
                                    // Only add child if it's already in the expanded list
                                    $already_present = in_array($child_name, $expanded_uniquenames);
                                    if ($already_present) {
                                        $child_names[] = $child_name;
                                    }
                                } else {
                                    // No ranged children found - add all children
                                    if (!in_array($child_name, $expanded_uniquenames)) {
                                        $expanded_uniquenames[] = $child_name;
                                    }
                                    $child_names[] = $child_name;
                                }
                            }
                            if (!empty($child_names)) {
                                $parent_to_children[$uniquename] = $child_names;
                            }
                        }
                    }
                }
                $uniquenames = array_unique($expanded_uniquenames);
                
                // Track which children were explicitly listed in the input without ranges
                $explicitly_listed_children = [];
                foreach ($original_uniquenames as $id) {
                    foreach ($parent_to_children as $parent => $children) {
                        if (in_array($id, $children)) {
                            // Check if this child has a range
                            $has_range = false;
                            foreach ($ranges as $range_entry) {
                                $range_id = explode(':', $range_entry)[0];
                                $range_id = explode(' ', $range_id)[0];
                                if ($range_id === $id) {
                                    $has_range = true;
                                    break;
                                }
                            }
                            if (!$has_range) {
                                $explicitly_listed_children[] = $id;
                            }
                        }
                    }
                }
                
                // Don't expand ranges here - blast_functions will handle building the batch file
                // with all 4 categories (no-range inputs, their children, ranged inputs, their children)
                
            } catch (Exception $e) {
                // If database lookup fails, just use original IDs
                $parent_to_children = [];
            }
        }
    }
    
    // Extract sequences for ALL available types
    if (empty($extraction_errors) && !empty($uniquenames)) {
        $extract_result = extractSequencesForAllTypes($fasta_source['path'], $uniquenames, $sequence_types, $selected_organism, $selected_assembly, $ranges, $original_uniquenames ?? [], $parent_to_children ?? []);
        $displayed_content = $extract_result['content'];
        if (!empty($extract_result['errors'])) {
            $extraction_errors = array_merge($extraction_errors, $extract_result['errors']);
        }
        
        // Parse returned sequences to find which IDs were actually found
        foreach ($displayed_content as $seq_type => $fasta_content) {
            // Extract all header lines (start with >) from FASTA
            preg_match_all('/^>([^\s]+)/m', $fasta_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $header_id) {
                    $found_ids[] = $header_id;
                    
                    // Also add the plain ID without range (e.g., XP_023382306.1 from XP_023382306.1:1-10)
                    if (strpos($header_id, ':') !== false) {
                        $plain_id = explode(':', $header_id)[0];
                        $found_ids[] = $plain_id;
                    }
                }
            }
        }
        $found_ids = array_unique($found_ids);
    }
    
    // Log any errors
    if (!empty($extraction_errors)) {
        foreach ($extraction_errors as $err) {
            logError($err, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
        }
    }
    
    // Set download error message only if no content was retrieved
    if (empty($displayed_content) && !empty($extraction_errors)) {
        $download_error_msg = implode(' ', $extraction_errors);
    }
    
    // Flag to scroll to results section if sequences were displayed
    $should_scroll_to_results = !empty($displayed_content);
    
    // Handle download request if present
    handleSequenceDownload($download_file_flag, $sequence_type, $displayed_content[$sequence_type] ?? null);
}

// Get available sequence types from all accessible sources
$available_types = getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types);

// Configure display template
$display_config = [
    'title' => 'Sequence Retrieval & Download - ' . htmlspecialchars($siteTitle),
    'content_file' => __DIR__ . '/pages/retrieve_sequences.php',
    'page_script' => [
	'/' . $site . '/js/modules/utilities.js',
        '/' . $site . '/js/modules/source-list-manager.js',
	'/' . $site . '/js/sequence-retrieval.js'
    ]
];

// Prepare data for content file
$data = [
    'site' => $site,
    'siteTitle' => $siteTitle,
    'config' => $config,
    'accessible_sources' => $accessible_sources,
    'selected_organism' => $selected_organism,
    'selected_assembly' => $selected_assembly,
    'uniquenames_string' => $uniquenames_string,
    'displayed_content' => $displayed_content,
    'sequence_types' => $sequence_types,
    'available_sequences' => !empty($displayed_content) ? formatSequenceResults($displayed_content, $sequence_types) : [],
    'context' => $context,
    'filter_organisms' => $filter_organisms,
    'sources_by_group' => $sources_by_group,
    'download_error_msg' => $download_error_msg,
    'uniquenames' => $uniquenames,
    'found_ids' => $found_ids,
    'should_scroll_to_results' => $should_scroll_to_results,
    'selected_source' => $selected_source,
    'sample_feature_ids' => $config->getArray('sample_feature_ids', []),
    'parent_to_children' => $parent_to_children ?? [],
    'page_styles' => [
        '/' . $site . '/css/display.css',
    ]
];

// Use generic template to render
include_once __DIR__ . '/display-template.php';

?>
