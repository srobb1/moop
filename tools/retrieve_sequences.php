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
$organism_result = parseOrganismParameter($organisms_param, $context['organism']);
$filter_organisms = $organism_result['organisms'];

// Get uniquenames (may be empty on initial page load)
$uniquenames_string = trim($_POST['uniquenames'] ?? $_GET['uniquenames'] ?? '');

// Get ALL accessible assemblies organized by group and organism
$sources_by_group = getAccessibleAssemblies();
$accessible_sources = flattenSourcesList($sources_by_group);

// Initialize selected organism/assembly variables
$selected_organism = trim($_POST['organism'] ?? '');
$selected_assembly = trim($_POST['assembly'] ?? '');
$displayed_content = [];

// If sequence IDs are provided, extract ALL sequence types
if (!empty($sequence_ids_provided)) {
    $extraction_errors = [];
    
    // Validate inputs using helper
    $validation = validateExtractInputs($selected_organism, $selected_assembly, $uniquenames_string, $accessible_sources);
    $extraction_errors = $validation['errors'];
    $fasta_source = $validation['fasta_source'];
    
    // Parse and validate feature IDs
    if (empty($extraction_errors)) {
        $id_parse = parseFeatureIds($uniquenames_string);
        if (!$id_parse['valid']) {
            $extraction_errors[] = $id_parse['error'];
        } else {
            $uniquenames = $id_parse['uniquenames'];
        }
    }
    
    // Extract sequences for ALL available types
    if (empty($extraction_errors) && !empty($uniquenames)) {
        $extract_result = extractSequencesForAllTypes($fasta_source['path'], $uniquenames, $sequence_types);
        $displayed_content = $extract_result['content'];
        if (!empty($extract_result['errors'])) {
            $extraction_errors = array_merge($extraction_errors, $extract_result['errors']);
        }
    }
    
    // Log any errors
    if (!empty($extraction_errors)) {
        foreach ($extraction_errors as $err) {
            logError($err, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
        }
    }
    
    // Set download error message if extraction had errors
    $download_error_msg = '';
    if (!empty($extraction_errors)) {
        $download_error_msg = implode(' ', $extraction_errors);
    }
    
    // If download flag is set and we have content, send the specific sequence type
    if ($download_file_flag && !empty($sequence_type) && isset($displayed_content[$sequence_type])) {
        $file_format = $_POST['file_format'] ?? 'fasta';
        sendFileDownload($displayed_content[$sequence_type], $sequence_type, $file_format);
    }
}

// Display form - get available sequence types from all accessible sources
$available_types = getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types);

// Now include the HTML headers
include_once __DIR__ . '/../includes/head.php';
include_once __DIR__ . '/../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sequence Search - <?= htmlspecialchars($siteTitle) ?></title>
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
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
            <input type="hidden" name="organism" value="">
            <input type="hidden" name="assembly" value="">
            <!-- Hidden context fields for back button and navigation (preserve original context) -->
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context['organism']) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context['assembly']) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context['group']) ?>">
            <input type="hidden" name="display_name" value="<?= htmlspecialchars($context['display_name']) ?>">

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
                        <button type="button" class="btn btn-success" onclick="clearSourceFilters('sourceFilter', 'selected_source', 'fasta-source-line', 'filterMessage');">
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
                    $first_visible_source = true;
                    
                    foreach ($sources_by_group as $group_name => $organisms): 
                        $group_color = $group_color_map[$group_name];
                        
                        foreach ($organisms as $organism => $assemblies): 
                            foreach ($assemblies as $source): 
                                $search_text = strtolower("$group_name $organism $source[assembly]");
                                
                                // Skip if organism filter is set and this organism is not in the filter list
                                if (!empty($filter_organisms) && !in_array($organism, $filter_organisms)) {
                                    continue;
                                }
                                ?>
                                <div class="fasta-source-line" data-search="<?= htmlspecialchars($search_text) ?>">
                                    <!-- Radio selector at far left -->
                                    <input 
                                        type="radio" 
                                        name="selected_source" 
                                        value="<?= htmlspecialchars($source['organism'] . '|' . $source['assembly']) ?>"
                                        data-organism="<?= htmlspecialchars($source['organism']) ?>"
                                        data-assembly="<?= htmlspecialchars($source['assembly']) ?>"
                                        <?php if ($first_visible_source): ?>checked<?php $first_visible_source = false; endif; ?>
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

            <!-- Submit button to display all sequences -->
            <div class="d-grid gap-2 d-md-flex gap-md-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-eye"></i> Display All Sequences
                </button>
            </div>
        </form>
        </div>
    <?php endif; ?>

    <!-- Sequences Display Section -->
    <?php if (!empty($displayed_content)): ?>
        <hr class="my-5">
        <?php
        // Set up variables for sequences_display.php
        $gene_name = $uniquenames_string;
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

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('downloadForm');
        const errorAlert = document.querySelector('.alert-danger');
        
        // Initialize source list manager with form-specific callback
        initializeSourceListManager({
            filterId: 'sourceFilter',
            radioName: 'selected_source',
            sourceListClass: 'fasta-source-line',
            onSelectionChange: function(radio) {
                // Update hidden form fields when selection changes
                if (form) {
                    form.querySelector('input[name="organism"]').value = radio.dataset.organism;
                    form.querySelector('input[name="assembly"]').value = radio.dataset.assembly;
                }
            }
        });
        
        // Dismiss error alert on form submission
        if (form) {
            form.addEventListener('submit', function() {
                if (errorAlert) {
                    const bsAlert = new bootstrap.Alert(errorAlert);
                    bsAlert.close();
                }
            });
        }
        
        // Update hidden fields on form submit
        if (form) {
            form.addEventListener('submit', function(e) {
                const checked = document.querySelector('input[name="selected_source"]:checked');
                if (checked) {
                    form.querySelector('input[name="organism"]').value = checked.dataset.organism;
                    form.querySelector('input[name="assembly"]').value = checked.dataset.assembly;
                }
            });
        }

        // Handle copy to clipboard for sequences
        const copyables = document.querySelectorAll(".copyable");
        copyables.forEach(el => {
            let resetColorTimeout;
            el.addEventListener("click", function () {
                const text = el.innerText.trim();
                navigator.clipboard.writeText(text).then(() => {
                    el.classList.add("bg-success", "text-white");
                    if (resetColorTimeout) clearTimeout(resetColorTimeout);
                    resetColorTimeout = setTimeout(() => {
                        el.classList.remove("bg-success", "text-white");
                    }, 1500);
                }).catch(err => console.error("Copy failed:", err));
            });
        });
    });

    // Reinitialize tooltips after a small delay to ensure Bootstrap is fully loaded
    setTimeout(() => {
        const copyables = document.querySelectorAll(".copyable");
        copyables.forEach(el => {
            // Custom simple tooltip that follows cursor
            el.addEventListener("mouseenter", function() {
                // Remove any existing tooltip
                const existing = document.getElementById("custom-copy-tooltip");
                if (existing) existing.remove();
                
                // Create simple tooltip
                const tooltip = document.createElement("div");
                tooltip.id = "custom-copy-tooltip";
                tooltip.textContent = "Click to copy";
                tooltip.style.cssText = `
                    position: fixed;
                    background-color: #000;
                    color: #fff;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    white-space: nowrap;
                    pointer-events: none;
                    z-index: 9999;
                `;
                document.body.appendChild(tooltip);
                
                // Update position on mousemove
                const updatePosition = (e) => {
                    tooltip.style.left = (e.clientX + 10) + "px";
                    tooltip.style.top = (e.clientY - 30) + "px";
                };
                
                el.addEventListener("mousemove", updatePosition);
                
                // Initial position
                updatePosition(event);
                
                el.addEventListener("mouseleave", function() {
                    const existing = document.getElementById("custom-copy-tooltip");
                    if (existing) existing.remove();
                    el.removeEventListener("mousemove", updatePosition);
                }, { once: true });
            });
        });
    }, 500);
</script>

<script src="/<?= $site ?>/js/source_list_manager.js"></script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
