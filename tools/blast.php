<?php
/**
 * BLAST SEARCH - BLAST Search Tool
 * 
 * Integrated tool for performing BLAST searches against organism databases.
 * Respects user permissions for accessing specific assemblies.
 * Context-aware: Can be limited to specific organism/assembly/group from referring page.
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (controller)
 *   ↓
 * Load configuration and accessibility settings
 *   ↓
 * Process form submission if present
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call display-template.php with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/blast.php) displays form and results
 * 
 * TODO: Implement cleanup mechanism for old BLAST result files
 * - Results are stored in temporary files on the filesystem
 * - Need to implement periodic cleanup (cron job or on-demand) to remove old results
 * - Should delete files older than X days (suggest 7-30 days)
 * - Consider storing results in database instead of filesystem for better management
 * - See: blast_functions.php executeBlastSearch() function for result file handling
 */

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/blast_results_visualizer.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();
$siteTitle = $config->getString('siteTitle');

// Get context parameters from referring page using standard parser
$context = parseContextParameters();
$context_organism = $context['organism'];
$context_assembly = $context['assembly'];
$context_group = $context['group'];
$display_name = $context['display_name'];

// Get ALL accessible assemblies early (needed for pre-selection logic below)
$sources_by_group = getAccessibleAssemblies();
$accessible_sources = [];
foreach ($sources_by_group as $group => $organisms) {
    foreach ($organisms as $org => $assemblies) {
        $accessible_sources = array_merge($accessible_sources, $assemblies);
    }
}

// Get organisms for filtering - support both array and comma-separated string formats
// Array format: organisms[] from multi-search context (via tool_config.php)
// String format: comma-separated organisms from form resubmission
$organisms_param = $_GET['organisms'] ?? $_POST['organisms'] ?? '';
$filter_organisms = [];
$filter_organisms_string = '';

if (is_array($organisms_param)) {
    // Array format (from multi-search via tool links)
    $filter_organisms = array_filter($organisms_param);
    $filter_organisms_string = implode(',', $filter_organisms);
} else {
    // String format (comma-separated or from form resubmission)
    $filter_organisms_string = trim($organisms_param);
    if (!empty($filter_organisms_string)) {
        $filter_organisms = array_map('trim', explode(',', $filter_organisms_string));
        $filter_organisms = array_filter($filter_organisms);
    }
}

// Get form data
$search_query = trim($_POST['query'] ?? '');
$blast_program = trim($_POST['blast_program'] ?? 'blastx');
$selected_source = trim($_POST['selected_source'] ?? '');
$blast_db = trim($_POST['blast_db'] ?? '');

// Initialize selected_source from context if not set from form
// When arriving with context_organism and context_assembly, pre-select that source
if (empty($selected_source) && !empty($context_organism) && !empty($context_assembly)) {
    $selected_source = $context_organism . '|' . $context_assembly;
    
    // Try to find matching source - assembly param might be accession or genome_name
    $source_found = false;
    foreach ($accessible_sources as $source) {
        if ($source['organism'] === $context_organism && $source['assembly'] === $context_assembly) {
            // Direct match found
            $source_found = true;
            break;
        }
    }
    
    // If not found by direct match, try via genome_id lookup to find actual directory name
    if (!$source_found) {
        try {
            $db_path = "$organism_data/$context_organism/organism.sqlite";
            [$genome_id_param, $genome_name_param, $genome_accession_param] = getAssemblyInfo($context_assembly, $db_path);
            
            // Now find source matching this genome_id
            if (!empty($genome_id_param)) {
                foreach ($accessible_sources as $source) {
                    if ($source['organism'] === $context_organism && $source['genome_id'] == $genome_id_param) {
                        $selected_source = $context_organism . '|' . $source['assembly'];
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // If lookup fails, keep original selected_source
        }
    }
}

// Handle evalue with custom option
$evalue = trim($_POST['evalue'] ?? '1e-3');
$evalue_custom = '';
if ($evalue === 'custom' && !empty($_POST['evalue_custom'])) {
    $evalue_custom = trim($_POST['evalue_custom']);
    $evalue = $evalue_custom;
}

// Handle max_hits/results as number input
$max_results = trim($_POST['max_results'] ?? '50');

$matrix = trim($_POST['matrix'] ?? 'BLOSUM62');
$filter_seq = isset($_POST['filter_seq']);
$task = trim($_POST['task'] ?? '');
$word_size = (int)($_POST['word_size'] ?? 0);
$gapopen = (int)($_POST['gapopen'] ?? 0);
$gapextend = (int)($_POST['gapextend'] ?? 0);
$max_hsps = (int)($_POST['max_hsps'] ?? 0);
$perc_identity = trim($_POST['perc_identity'] ?? '');
$culling_limit = (int)($_POST['culling_limit'] ?? 0);
$threshold = trim($_POST['threshold'] ?? '');
$soft_masking = isset($_POST['soft_masking']);
$ungapped = isset($_POST['ungapped']);
$strand = trim($_POST['strand'] ?? 'plus');

// Initialize result variables
$search_error = null;
$blast_result = null;
$blast_options = [];

// If search is submitted
if (!empty($search_query) && !empty($blast_db) && !empty($selected_source)) {
    // Parse selected_source (format: organism|assembly)
    $source_parts = explode('|', $selected_source);
    if (count($source_parts) === 2) {
        $selected_organism = $source_parts[0];
        $selected_assembly = $source_parts[1];
    } else {
        $selected_organism = '';
        $selected_assembly = '';
    }
    
    // Find the selected source to verify access
    $selected_source_obj = null;
    foreach ($accessible_sources as $source) {
        if ($source['assembly'] === $selected_assembly && $source['organism'] === $selected_organism) {
            $selected_source_obj = $source;
            break;
        }
    }
    
    if (!$selected_source_obj) {
        $search_error = "You do not have access to the selected assembly.";
    } else {
        // Get BLAST databases for this assembly
        $all_dbs = getBlastDatabases($selected_source_obj['path']);
        
        // Find the selected database
        $selected_db_obj = null;
        foreach ($all_dbs as $db) {
            if ($db['path'] === $blast_db) {
                $selected_db_obj = $db;
                break;
            }
        }
        
        if (!$selected_db_obj) {
            $search_error = "Selected BLAST database not found.";
        } else {
            // Validate sequence
            $validation = validateBlastSequence($search_query);
            if (!$validation['valid']) {
                $search_error = "Invalid sequence: " . $validation['error'];
            } else {
                // If validation passed, ensure sequence has header
                $query_with_header = $search_query;
                if ($search_query[0] !== '>') {
                    $query_with_header = ">query_sequence\n" . $search_query;
                }
                
                // Execute BLAST search
                $blast_options = [
                    'evalue' => $evalue,
                    'max_hits' => $max_results,
                    'matrix' => $matrix,
                    'filter' => $filter_seq,
                    'task' => $task,
                    'word_size' => $word_size,
                    'gapopen' => $gapopen,
                    'gapextend' => $gapextend,
                    'max_hsps' => $max_hsps,
                    'perc_identity' => $perc_identity,
                    'culling_limit' => $culling_limit,
                    'threshold' => $threshold,
                    'soft_masking' => $soft_masking,
                    'ungapped' => $ungapped,
                    'strand' => $strand
                ];
                
                $blast_result = executeBlastSearch($query_with_header, $blast_db, $blast_program, $blast_options);
                
                if (!$blast_result['success']) {
                    $search_error = $blast_result['error'];
                    if (!empty($blast_result['stderr'])) {
                        $search_error .= "\n\nDetails: " . $blast_result['stderr'];
                    }
                }
            }
        }
    }
}

// Build databasesByAssembly array
$databasesByAssembly = [];
foreach ($sources_by_group as $group => $organisms) {
    foreach ($organisms as $organism => $assemblies) {
        foreach ($assemblies as $source) {
            $key = $source['organism'] . '|' . $source['assembly'];
            $databasesByAssembly[$key] = getBlastDatabases($source['path']);
        }
    }
}

// Configure display template
$display_config = [
    'title' => 'BLAST Search - ' . htmlspecialchars($siteTitle),
    'content_file' => __DIR__ . '/pages/blast.php',
    'page_script' => [
        '/' . $site . '/js/modules/source-list-manager.js',
        '/' . $site . '/js/blast-manager.js'
    ],
    'inline_scripts' => [
        "const previouslySelectedDb = '" . addslashes($blast_db) . "';",
        "const previouslySelectedSource = '" . addslashes($selected_source) . "';",
        "const sampleSequences = " . json_encode($config->getArray('blast_sample_sequences', [])) . ";",
        "const databasesByAssembly = " . json_encode($databasesByAssembly) . ";"
    ]
];

// Prepare data for content file
$data = [
    'site' => $site,
    'siteTitle' => $siteTitle,
    'config' => $config,
    'accessible_sources' => $accessible_sources,
    'sources_by_group' => $sources_by_group,
    'context_organism' => $context_organism,
    'context_assembly' => $context_assembly,
    'context_group' => $context_group,
    'display_name' => $display_name,
    'filter_organisms_string' => $filter_organisms_string,
    'search_query' => $search_query,
    'blast_program' => $blast_program,
    'selected_source' => $selected_source,
    'blast_db' => $blast_db,
    'search_error' => $search_error,
    'blast_result' => $blast_result,
    'evalue' => $evalue,
    'evalue_custom' => $evalue_custom,
    'max_results' => $max_results,
    'blast_options' => $blast_options,
    'page_styles' => [
        '/' . $site . '/css/display.css',
    ]
];

// Use generic template to render
include_once __DIR__ . '/display-template.php';

?>
