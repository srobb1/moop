<?php
/**
 * FASTA Sequence Download Tool
 * Allows users to manually search for and download sequences
 * Accessible from organism, assembly, and groups display pages
 * Uses blastdbcmd to extract from FASTA BLAST databases
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
            $organism_data = $config->getPath('organism_data');
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
            
            // Get children for each parent ID (like parent_display.php does)
            try {
                $db = verifyOrganismDatabase($selected_organism, $organism_data);
                
                $expanded_uniquenames = [];
                foreach ($uniquenames as $uniquename) {
                    $expanded_uniquenames[] = $uniquename;
                    
                    // Lookup feature to get feature_id
                    $feature_result = getFeatureByUniquename($uniquename, $db);
                    if (!empty($feature_result)) {
                        $feature_id = $feature_result['feature_id'];
                        // Get all children
                        $children = getChildren($feature_id, $db);
                        foreach ($children as $child) {
                            $expanded_uniquenames[] = $child['feature_uniquename'];
                        }
                    }
                }
                $uniquenames = array_unique($expanded_uniquenames);
            } catch (Exception $e) {
                // If database lookup fails, just use original IDs
            }
        }
    }
    
    // Extract sequences for ALL available types
    if (empty($extraction_errors) && !empty($uniquenames)) {
        $extract_result = extractSequencesForAllTypes($fasta_source['path'], $uniquenames, $sequence_types, $selected_organism, $selected_assembly);
        $displayed_content = $extract_result['content'];
        if (!empty($extract_result['errors'])) {
            $extraction_errors = array_merge($extraction_errors, $extract_result['errors']);
        }
        
        // Parse returned sequences to find which IDs were actually found
        $found_ids = [];
        foreach ($displayed_content as $seq_type => $fasta_content) {
            // Extract all header lines (start with >) from FASTA
            preg_match_all('/^>([^\s]+)/m', $fasta_content, $matches);
            if (!empty($matches[1])) {
                $found_ids = array_merge($found_ids, $matches[1]);
            }
        }
        $found_ids = array_unique($found_ids);
    } else {
        $found_ids = [];
    }
    
    // Log any errors
    if (!empty($extraction_errors)) {
        foreach ($extraction_errors as $err) {
            logError($err, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
        }
    }
    
    // Set download error message only if no content was retrieved
    $download_error_msg = '';
    if (empty($displayed_content) && !empty($extraction_errors)) {
        $download_error_msg = implode(' ', $extraction_errors);
    }
    
    // Flag to scroll to results section if sequences were displayed
    $should_scroll_to_results = !empty($displayed_content);
    
    // If download flag is set and we have content, send the specific sequence type
    if ($download_file_flag && !empty($sequence_type) && isset($displayed_content[$sequence_type])) {
        $file_format = $_POST['file_format'] ?? 'fasta';
        sendFileDownload($displayed_content[$sequence_type], $sequence_type, $file_format);
    }
}

// Display form - get available sequence types from all accessible sources
$available_types = getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types);

// Now include the HTML headers
include_once __DIR__ . '/../includes/head-resources.php';
include_once __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sequence Search - <?= htmlspecialchars($siteTitle) ?></title>
    <?php include_once __DIR__ . '/../includes/head-resources.php'; ?>
    <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
    <style>
        .tooltip { z-index: 9999 !important; }
        .tooltip-inner { background-color: #000 !important; }
        /* Ensure tooltip is positioned relative to body, not constrained containers */
        body { position: relative; }
    </style>
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-5">
    <!-- Navigation Buttons -->
    <div class="mb-3"></div>

    <h2 class="mb-4"><i class="fa fa-dna"></i> Sequence Retrieval & Download</h2>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong>No accessible assemblies found.</strong>
            <p class="mb-0">You do not have access to any organism assemblies, or the data directory is misconfigured.</p>
        </div>
    <?php else: ?>
        <div class="fasta-info-box">
            <strong><i class="fa fa-info-circle"></i> How to use:</strong>
            <ol class="mb-0 mt-2">
                <li>Select which organism and assembly to extract from</li>
                <li>Enter gene/feature IDs (one per line or comma-separated)</li>
                <li>Click "Display Sequences" to see all available sequence types</li>
                <li>Copy or download as needed</li>
            </ol>
        </div>

        <?php if (!empty($download_error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($download_error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <form method="POST" id="downloadForm">
            <!-- Hidden fields for selected source (populated by JavaScript on submit) -->
            <input type="hidden" name="organism" value="<?= htmlspecialchars($selected_organism) ?>">
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($selected_assembly) ?>">
            <!-- Hidden context fields for back button and navigation (preserve original context) -->
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context['organism']) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context['assembly']) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context['group']) ?>">
            <input type="hidden" name="display_name" value="<?= htmlspecialchars($context['display_name']) ?>">
            <!-- Hidden field with expanded uniquenames for display purposes -->
            <input type="hidden" id="expandedUniqueames" value="<?= htmlspecialchars(json_encode($uniquenames ?? [])) ?>">
            <!-- Hidden field with found IDs (IDs that were actually returned by blastdbcmd) -->
            <input type="hidden" id="foundIds" value="<?= htmlspecialchars(json_encode($found_ids ?? [])) ?>">

            <!-- Source Selection with Compact Badges -->
            <div class="fasta-source-selector">
                <label class="form-label"><strong>Select Source</strong></label>
                
                <!-- Filter input with clear button -->
                <div class="fasta-source-filter">
                    <div class="input-group input-group-sm">
                        <input 
                            type="text" 
                            class="form-control" 
                            id="sourceFilter" 
                            placeholder="Filter by group, organism, or assembly..."
                            value="<?= htmlspecialchars($context['organism'] ?: $context['group']) ?>"
                            >
                        <button type="button" class="btn btn-success" onclick="clearSourceFilter();">
                            <i class="fa fa-times"></i> Clear Filters
                        </button>
                    </div>
                </div>
                <?php if (!empty($filter_organisms)): ?>
                    <small class="form-text text-muted d-block mt-2" id="filterMessage"><i class="fa fa-filter"></i> Showing only: <?= htmlspecialchars(implode(', ', $filter_organisms)) ?></small>
                <?php endif; ?>
                
                <!-- Scrollable list of sources -->
                <div class="fasta-source-list">
                    <?php 
                    $group_color_map = assignGroupColors($sources_by_group);
                    
                    foreach ($sources_by_group as $group_name => $organisms): 
                        $group_color = $group_color_map[$group_name];
                        
                        foreach ($organisms as $organism => $assemblies): 
                            foreach ($assemblies as $source): 
                                $search_text = strtolower("$group_name $organism $source[assembly]");
                                
                                // Determine if this source should be hidden due to organism filter on initial load
                                $is_filtered_out = !empty($filter_organisms) && !in_array($organism, $filter_organisms);
                                $display_style = $is_filtered_out ? ' style="display: none;"' : '';
                                ?>
                                <div class="fasta-source-line" data-search="<?= htmlspecialchars($search_text) ?>"<?= $display_style ?>>
                                    <!-- Radio selector at far left -->
                                    <input 
                                        type="radio" 
                                        name="selected_source" 
                                        value="<?= htmlspecialchars($source['organism'] . '|' . $source['assembly']) ?>"
                                        data-organism="<?= htmlspecialchars($source['organism']) ?>"
                                        data-assembly="<?= htmlspecialchars($source['assembly']) ?>"
                                        >
                                    
                                    <!-- Group badge - colorful -->
                                    <span class="badge badge-sm bg-<?= $group_color ?> text-white">
                                        <?= htmlspecialchars($group_name) ?>
                                    </span>
                                    
                                    <!-- Organism badge - all gray -->
                                    <span class="badge badge-sm bg-secondary text-white">
                                        <?= htmlspecialchars($organism) ?>
                                    </span>
                                    
                                    <!-- Assembly badge - all dark blue -->
                                    <span class="badge badge-sm bg-info text-white">
                                        <?= htmlspecialchars($source['assembly']) ?>
                                    </span>
                                </div>
                            <?php endforeach; 
                        endforeach; 
                    endforeach; ?>
                </div>
                <small class="form-text text-muted d-block mt-2">Select an assembly from the list above.</small>
            </div>

            <!-- Current Selection Display -->
            <div class="mb-4 p-3 bg-light border rounded">
                <strong>Currently Selected:</strong>
                <div id="currentSelection" style="margin-top: 8px; font-size: 14px;">
                    <span style="color: #999;">None selected</span>
                </div>
            </div>

            <!-- Feature ID Input -->
            <div class="mb-4">
                <label for="featureIds" class="form-label"><strong>Feature/Gene IDs</strong></label>
                <textarea 
                    class="form-control textarea-ids" 
                    id="featureIds"
                    name="uniquenames" 
                    rows="6" 
                    placeholder="Enter feature IDs (one per line or comma-separated)&#10;Example:&#10;AT1G01010&#10;AT1G01020&#10;AT1G01030"
                    required><?= htmlspecialchars($uniquenames_string) ?></textarea>
                <small class="form-text text-muted">Enter one ID per line, or use commas to separate multiple IDs on one line.</small>
            </div>

            <!-- Search IDs Display (shows expanded IDs with children) -->
            <div class="mb-4 p-3 bg-light border rounded">
                <strong>IDs to Search:</strong>
                <div id="searchIdsDisplay" style="margin-top: 8px; font-size: 14px; max-height: 150px; overflow-y: auto;">
                    <?php if (!empty($uniquenames) && !empty($sequence_ids_provided)): ?>
                        <?php foreach ($uniquenames as $id): ?>
                            <?php $is_child = strpos($id, '.t') !== false || strpos($id, '.1') !== false; ?>
                            <div style="padding: 4px 0;">
                                <span style="background: <?= $is_child ? '#d4edda' : '#e8f4f8' ?>; padding: 2px 6px; border-radius: 3px;">
                                    <?= htmlspecialchars($id) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color: #999;">Enter IDs above to see expanded list (including children)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Collapsed info about Parent and Child IDs -->
            <div class="mt-3">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#idInfoCollapse" aria-expanded="false" aria-controls="idInfoCollapse">
                    <i class="fa fa-info-circle"></i> About Parent and Child IDs
                </button>
                <div class="collapse mt-2" id="idInfoCollapse">
                    <div class="p-3 bg-light border rounded">
                        <p class="mb-0">
                            When you enter a parent gene ID (e.g., <code>g24397</code>), the system looks it up in the organism's database 
                            and automatically retrieves all associated child transcript IDs (e.g., <code>g24397.t1</code>, <code>g24397.t2</code>). 
                            All IDs (parents and children) are then used to search the FASTA sequence database using <code>blastdbcmd</code>. 
                            The "IDs to Search" box shows which ones were found 
                            (<span style="background: #d4edda; padding: 2px 4px; border-radius: 2px;">green</span>) 
                            and which were not found 
                            (<span style="background: #f8d7da; padding: 2px 4px; border-radius: 2px;">red</span>).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Submit button to display all sequences -->
            <div class="d-grid gap-2 d-md-flex gap-md-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-eye"></i> Display All Sequences
                </button>
            </div>
            </form>
            
            <!-- Debug: Show the blastdbcmd command if one was generated -->
            <?php if (!empty($debug_cmd)): ?>
                <div class="mt-4 p-3 bg-info bg-opacity-10 border border-info rounded">
                    <strong>Debug Command:</strong>
                    <div style="margin-top: 8px; font-family: monospace; word-break: break-all; font-size: 12px;">
                        <?= htmlspecialchars($debug_cmd) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <!-- Sequences Display Section -->
    <?php if (!empty($displayed_content)): ?>
        <hr class="my-5" id="sequences-section">
        <?php
        // Set up variables for sequences_display.php
        $gene_name = implode(', ', $uniquenames);  // Use expanded uniquenames
        $organism_name = $selected_organism;
        $assembly_name = $selected_assembly;
        $enable_downloads = true;
        
        // Format results for sequences_display.php component
        $available_sequences = formatSequenceResults($displayed_content, $sequence_types);
        
        // Include the reusable sequences display component
        include_once __DIR__ . '/sequences_display.php';
        ?>
    <?php endif; ?>
</div>

<?php endif; ?>

<script>
// Pass scroll preference from PHP to JavaScript
const scrollToResults = <?= $should_scroll_to_results ? 'true' : 'false' ?>;

// Store the previously selected source (for form restoration)
const previouslySelectedSource = '<?= addslashes($selected_source) ?>';
</script>

<script src="/<?= $site ?>/js/modules/source-list-manager.js"></script>
<script src="/<?= $site ?>/js/sequence-retrieval.js"></script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
