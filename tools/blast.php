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
include_once __DIR__ . '/../includes/source-selector-helpers.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();
$siteTitle = $config->getString('siteTitle');

// Get context parameters from referring page using standard parser
$context = parseContextParameters();
$display_name = $context['display_name'];

// Get ALL accessible assemblies early (needed for source selection)
$sources_by_group = getAccessibleAssemblies();
$accessible_sources = flattenSourcesList($sources_by_group);

// Get organisms for filtering - support both array and comma-separated string formats
$organisms_param = $_GET['organisms'] ?? $_POST['organisms'] ?? '';
$organism_result = parseOrganismParameter($organisms_param, '');

// Get form data
$search_query = trim($_POST['query'] ?? '');
$blast_program = trim($_POST['blast_program'] ?? 'blastx');
$blast_db = trim($_POST['blast_db'] ?? '');

// Get the assembly parameter from URL/POST (could be name or accession)
$assembly_param = trim($_POST['assembly'] ?? $_GET['assembly'] ?? '');

// First, try using context parameters (explicit intent to pre-select)
if (!empty($context['organism']) && !empty($context['assembly'])) {
    $assembly_param = $context['assembly'];
}

// Use centralized source selection helper
$selected_organism = trim($_POST['organism'] ?? $_GET['organism'] ?? '');
$source_selection = prepareSourceSelection(
    $context,
    $sources_by_group,
    $accessible_sources,
    $selected_organism,
    $assembly_param,
    $organism_result['organisms']
);

// Extract selected values
$filter_organisms = $source_selection['filter_organisms'];
$selected_source = $source_selection['selected_source'];
$selected_organism = $source_selection['selected_organism'];
$selected_assembly_accession = $source_selection['selected_assembly_accession'];
$selected_assembly_name = $source_selection['selected_assembly_name'];

// Extract context values for backward compatibility
$context_organism = $context['organism'];
$context_assembly = $context['assembly'];
$context_group = $context['group'];

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
        '/' . $site . '/js/modules/collapse-handler.js',
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
    'filter_organisms' => $filter_organisms,
    'filter_organisms_string' => $filter_organisms_string,
    'search_query' => $search_query,
    'blast_program' => $blast_program,
    'selected_source' => $selected_source,
    'selected_organism' => $selected_organism,
    'selected_assembly_name' => $selected_assembly_name,
    'selected_assembly_accession' => $selected_assembly_accession,
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
