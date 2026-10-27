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
include_once __DIR__ . '/../lib/home_examples.php';  // moop_home_examples(): example IDs
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/genomic_regions.php';  // genomic region + flanks, shared with the gene page and Data Exporter
include_once __DIR__ . '/../includes/source-selector-helpers.php';

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

$sequence_notes = [];

// Get uniquenames (may be empty on initial page load)
$uniquenames_string = trim($_POST['uniquenames'] ?? $_GET['uniquenames'] ?? '');

// Get ALL accessible assemblies organized by group and organism
$sources_by_group = getAccessibleGeneSets();
$accessible_sources = flattenSourcesList($sources_by_group);

// Initialize variables
$displayed_content = [];
$should_scroll_to_results = false;
$uniquenames = [];
$ranges = [];
$found_ids = [];
$download_error_msg = '';

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
$filter_organisms            = $source_selection['filter_organisms'];
$selected_source             = $source_selection['selected_source'];
$selected_organism           = $source_selection['selected_organism'];
$selected_assembly_accession = $source_selection['selected_assembly_accession'];
$selected_assembly_name      = $source_selection['selected_assembly_name'];
$should_auto_select          = $source_selection['should_auto_select'];

// Extract gene_set from 3-part selected_source (organism|assembly|gene_set)
$source_parts        = explode('|', $selected_source);
$selected_gene_set   = $source_parts[2] ?? '';

// Which gene sets sit on a genome -- the ones genomic sequence and flanks can be cut from
// (the same two files moop_retrieve_genomic() needs). Two stats per source, ~95 sources.
$source_has_genome = [];
foreach ($accessible_sources as $source) {
    $source_has_genome[$source['organism'] . '|' . $source['assembly'] . '|' . ($source['gene_set'] ?? '')] =
        is_file($source['path'] . '/feature_coords.tsv')
        && is_file(dirname($source['path']) . '/' . genome_fasta_filename() . '.fai');
}

// Flanks for the genomic region, in bases (0 = none). Same limit as the Data Exporter.
// Offered only where there is a genome: js/sequence-retrieval.js hides AND disables the
// boxes otherwise, so they are not submitted; moop_retrieve_genomic() notes the rest.
$upstream_bp   = max(0, min(MOOP_MAX_FLANK_BP, (int)($_POST['upstream_bp'] ?? 0)));
$downstream_bp = max(0, min(MOOP_MAX_FLANK_BP, (int)($_POST['downstream_bp'] ?? 0)));

// If sequence IDs are provided, extract ALL sequence types
if (!empty($sequence_ids_provided)) {
    $extraction_errors = [];
    
    // Find matching source for selected assembly
    // Works with either accession or genome_name
    $fasta_source = null;
    foreach ($accessible_sources as $source) {
        if ($source['organism'] === $selected_organism &&
            ($source['assembly'] === $selected_assembly_accession || $source['genome_name'] === $selected_assembly_name) &&
            ($selected_gene_set === '' || ($source['gene_set'] ?? '') === $selected_gene_set)) {
            $fasta_source = $source;
            break;
        }
    }
    
    if (!$fasta_source) {
        $extraction_errors[] = "Assembly not found or not accessible.";
    }
    
    // Parse the IDs, each with an optional range -- one request per entry (parseFeatureIds()).
    $requests = [];
    if (empty($extraction_errors)) {
        $id_parse = parseFeatureIds($uniquenames_string);
        if (!$id_parse['valid']) {
            $extraction_errors[] = $id_parse['error'];
        } else {
            $requests    = $id_parse['requests'];
            $uniquenames = $id_parse['uniquenames'];
        }
    }

    // Every descendant of each typed ID, so a gene reaches its transcripts, CDS and proteins
    // and a range typed on the gene applies to all of them. Scoped to the selected gene set:
    // a uniquename is unique only within one, and this organism may carry several.
    $parent_to_children = [];
    $genomic_anchors    = [];
    if (empty($extraction_errors) && $uniquenames) {
        $db_path = $organism_data . '/' . $selected_organism . '/organism.sqlite';
        [$gene_set_id] = getGeneSetInfo($fasta_source['assembly'], $fasta_source['gene_set'] ?? '', $db_path);
        $gs_ids = $gene_set_id ? [$gene_set_id] : [];
        try {
            $db = verifyOrganismDatabase($selected_organism, $organism_data);
            foreach ($uniquenames as $uniquename) {
                $feature = getFeatureByUniquename($uniquename, $db, $gs_ids);
                if (empty($feature)) continue;
                $children = array_column(getChildren($feature['feature_id'], $db, $gs_ids), 'feature_uniquename');
                if ($children) $parent_to_children[$uniquename] = $children;
            }
            // Where each typed ID's genomic sequence comes from: a protein or CDS ID uses its
            // transcript's span, so its flanks are its isoform's (lib/genomic_regions.php).
            $genomic_anchors = moop_genomic_anchors($uniquenames, $db, $gs_ids);
        } catch (Exception $e) {
            // Without the database, extract the typed IDs as they are.
            $parent_to_children = [];
        }
        $uniquenames = array_values(array_unique(array_merge($uniquenames, ...array_values($parent_to_children))));
    }

    // Extract sequences for ALL available types
    if (empty($extraction_errors) && !empty($uniquenames)) {
        $typed_ids = buildTypedIds($uniquenames, $db_path, $fasta_source['assembly'], $fasta_source['gene_set'] ?? '');
        $extract_result = extractSequencesForAllTypes($fasta_source['path'], $typed_ids, $sequence_types, $selected_organism, $selected_assembly_accession, $requests, $parent_to_children);
        $displayed_content = $extract_result['content'];
        if (!empty($extract_result['errors'])) {
            $extraction_errors = array_merge($extraction_errors, $extract_result['errors']);
        }

        // Genomic region (and flanks) of each typed gene or transcript -- lib/genomic_regions.php.
        $genomic = moop_retrieve_genomic($requests, $fasta_source['path'], dirname($fasta_source['path']), $upstream_bp, $downstream_bp, $genomic_anchors);
        if ($genomic['fasta'] !== '') $displayed_content['genome'] = rtrim($genomic['fasta']);
        $sequence_notes = $genomic['notes'];
        
        // Parse returned sequences to find which IDs were actually found
        foreach ($displayed_content as $seq_type => $fasta_content) {
            // Extract all header lines (start with >) from FASTA
            preg_match_all('/^>([^\s]+)/m', $fasta_content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $header_id) {
                    $found_ids[] = $header_id;
                    
                    // Also the ID without its range: X:pep:1-30 -> X:pep. (Cutting at the first
                    // colon gave X, which is a different feature.)
                    $found_ids[] = preg_replace('/:\d+-\d+$/', '', $header_id);
                }
            }
        }
        // A gene has no sequence of its own, so its ID never appears in a header -- it was
        // found if anything beneath it was, and showing it red ("not found") said otherwise.
        foreach ($parent_to_children as $parent => $children) {
            if (array_intersect($children, $found_ids)) $found_ids[] = $parent;
        }
        $found_ids = array_values(array_unique($found_ids));
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
    handleSequenceDownload($download_file_flag, $sequence_type, $displayed_content[$sequence_type] ?? null,
        [$selected_organism, $selected_assembly_accession, $selected_gene_set]);
}

// Get available sequence types from all accessible sources
$available_types = getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types);

// Configure display template
$display_config = [
    'title' => 'Sequence Retrieval & Download',
    'content_file' => __DIR__ . '/pages/retrieve_sequences.php',
    'page_script' => [
	'/' . $site . '/js/modules/utilities.js',
        '/' . $site . '/js/modules/source-list-manager.js',
	'/' . $site . '/js/sequence-retrieval.js'
    ]
];

// 'genome' is what the results show for a genomic REGION here, not a whole genome file.
$display_types = array_replace_recursive($sequence_types, ['genome' => ['label' => 'Genomic region']]);

// Prepare data for content file
$data = [
    'site' => $site,
    'siteTitle' => $siteTitle,
    'config' => $config,
    'accessible_sources' => $accessible_sources,
    'selected_organism' => $selected_organism,
    'selected_assembly_accession' => $selected_assembly_accession,
    'selected_assembly_name' => $selected_assembly_name,
    'uniquenames_string' => $uniquenames_string,
    'displayed_content' => $displayed_content,
    'sequence_types' => $display_types,
    'sequence_notes' => $sequence_notes,
    'source_has_genome' => $source_has_genome,
    'upstream_bp'    => $upstream_bp,
    'downstream_bp'  => $downstream_bp,
    'organism_data' => $organism_data,
    'available_sequences' => !empty($displayed_content) ? formatSequenceResults($displayed_content, $display_types) : [],
    'context' => $context,
    'context_organism' => $context['organism'] ?? '',
    'context_assembly' => $context['assembly'] ?? '',
    'context_gene_set' => $context['gene_set'] ?? '',
    'context_group' => $context['group'] ?? '',
    'should_auto_select' => $should_auto_select,
    'filter_organisms' => $filter_organisms,
    'sources_by_group' => $sources_by_group,
    'download_error_msg' => $download_error_msg,
    'uniquenames' => $uniquenames,
    'found_ids' => $found_ids,
    'should_scroll_to_results' => $should_scroll_to_results,
    'selected_source'   => $selected_source,
    'selected_gene_set' => $selected_gene_set,
    // Examples follow the selected gene set (and api/gene_set_examples.php follows a new
    // selection in the browser), so "Load examples" finds something in the source the user
    // is about to extract from -- including one with a range. lib/home_examples.php.
    'examples' => moop_retrieval_examples($selected_organism, $selected_assembly_accession, $selected_gene_set),
    'parent_to_children' => $parent_to_children ?? [],
    'page_styles' => [
        '/' . $site . '/css/display.css',
    ]
];

// Use generic template to render
include_once __DIR__ . '/display-template.php';

?>
