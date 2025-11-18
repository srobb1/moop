<?php
/**
 * FASTA Sequence Download Tool
 * Allows users to manually search for and download sequences
 * Accessible from organism, assembly, and groups display pages
 * Uses blastdbcmd to extract from FASTA BLAST databases
 */

session_start();

// Start output buffering to prevent any stray whitespace from includes
// affecting file downloads or headers
ob_start();

// Get parameters for processing
$sequence_ids_provided = !empty($_POST['uniquenames']);
$selected_assembly = trim($_POST['selected_assembly'] ?? '');
$download_file_flag = isset($_POST['download_file']) && $_POST['download_file'] == '1';
$sequence_type = trim($_POST['sequence_type'] ?? '');

include_once __DIR__ . '/../../site_config.php';
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/navigation.php';
include_once __DIR__ . '/../moop_functions.php';
include_once __DIR__ . '/../blast_functions.php';

// Discard any output from includes
ob_end_clean();

// Check if user is logged in OR if trying to access public assembly
// Visitors can access public assemblies without login
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$trying_public_access = !empty($_POST['selected_assembly']) && !empty($_POST['organism']);

// If trying to download from an assembly, check if it's public
if ($trying_public_access) {
    if (!is_public_assembly($_POST['organism'], $_POST['selected_assembly']) && !$is_logged_in) {
        header("Location: /$site/login.php");
        exit;
    }
} elseif (!$is_logged_in) {
    // For viewing the form itself, visitors can see public assemblies
    // Only redirect if no accessible assemblies will be shown
}

// Get context parameters for back button
$context_organism = trim($_GET['organism'] ?? $_POST['organism'] ?? '');
$context_assembly = trim($_GET['assembly'] ?? $_POST['assembly'] ?? '');
$context_group = trim($_GET['group'] ?? $_POST['group'] ?? '');
$display_name = trim($_GET['display_name'] ?? $_POST['display_name'] ?? '');

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

// Get uniquenames (may be empty on initial page load)
$uniquenames_string = trim($_POST['uniquenames'] ?? $_GET['uniquenames'] ?? '');

// Get ALL accessible assemblies organized by group and organism
$sources_by_group = getAccessibleAssemblies();

// Flatten for sequential processing
$accessible_sources = [];
foreach ($sources_by_group as $group => $organisms) {
    foreach ($organisms as $org => $assemblies) {
        $accessible_sources = array_merge($accessible_sources, $assemblies);
    }
}

// Initialize selected organism/assembly variables
$selected_organism = trim($_POST['organism'] ?? '');
$selected_assembly = trim($_POST['assembly'] ?? '');
$displayed_content = [];  // Store all sequence types

// If sequence IDs are provided, extract ALL sequence types
if (!empty($sequence_ids_provided)) {
    $extraction_errors = [];
    
    // Validate inputs
    if (empty($uniquenames_string)) {
        $extraction_errors[] = "No feature IDs provided.";
    }
    
    if (empty($selected_organism) || empty($selected_assembly)) {
        $extraction_errors[] = "No assembly selected.";
    }
    
    // Find the selected assembly in accessible sources
    $fasta_source = null;
    if (empty($extraction_errors)) {
        foreach ($accessible_sources as $source) {
            if ($source['assembly'] === $selected_assembly && $source['organism'] === $selected_organism) {
                $fasta_source = $source;
                break;
            }
        }
        
        if (!$fasta_source) {
            $extraction_errors[] = "You do not have access to the selected assembly.";
        }
    }
    
    // Parse feature IDs
    $uniquenames = [];
    if (empty($extraction_errors)) {
        $uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));
        if (empty($uniquenames)) {
            $extraction_errors[] = "No valid feature IDs provided.";
        }
    }
    
    // Extract sequences for ALL available types
    if (empty($extraction_errors)) {
        $assembly_dir = $fasta_source['path'];
        
        foreach ($sequence_types as $seq_type => $config) {
            $files = glob("$assembly_dir/*{$config['pattern']}");
            
            if (!empty($files)) {
                $fasta_file = $files[0];
                $extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames);
                
                if ($extract_result['success']) {
                    // Remove blank lines
                    $lines = explode("\n", $extract_result['content']);
                    $lines = array_filter($lines, function($line) {
                        return trim($line) !== '';
                    });
                    $displayed_content[$seq_type] = implode("\n", $lines);
                }
            }
        }
    }
    
    // Log any errors
    if (!empty($extraction_errors)) {
        foreach ($extraction_errors as $err) {
            logError($err, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
        }
    }
    
    // If download flag is set and we have content, send the specific sequence type
    if ($download_file_flag && !empty($sequence_type) && isset($displayed_content[$sequence_type])) {
        $file_format = $_POST['file_format'] ?? 'fasta';
        $ext = ($file_format === 'txt') ? 'txt' : 'fasta';
        $filename = "sequences_{$sequence_type}_" . date("Y-m-d_His") . ".{$ext}";
        
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename={$filename}");
        header('Content-Length: ' . strlen($displayed_content[$sequence_type]));
        echo $displayed_content[$sequence_type];
        exit;
    }
}

// Organize sources by group -> organism for tree view
// (Already done by getAccessibleAssemblies function)

// Display form - get available sequence types from all accessible sources
$available_types = [];
foreach ($accessible_sources as $source) {
    foreach ($sequence_types as $seq_type => $config) {
        $files = glob($source['path'] . "/*{$config['pattern']}");
        if (!empty($files)) {
            $available_types[$seq_type] = $config['label'];
        }
    }
}

// Now include the HTML headers
include_once __DIR__ . '/../../includes/head.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sequence Search - <?= htmlspecialchars($siteTitle) ?></title>
    <?php include_once __DIR__ . '/../../includes/head.php'; ?>
    <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
    <style>
        .tooltip { z-index: 9999 !important; }
        .tooltip-inner { background-color: #000 !important; }
        /* Ensure tooltip is positioned relative to body, not constrained containers */
        body { position: relative; }
    </style>
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container mt-5">
    <!-- Navigation Buttons -->
    <div class="mb-3">
        <?php
        $nav_context = buildNavContext('tool', [
            'organism' => $context_organism,
            'assembly' => $context_assembly,
            'group' => $context_group,
            'display_name' => $display_name,
            'multi_search' => $filter_organisms
        ]);
        echo render_navigation_buttons($nav_context);
        ?>
    </div>

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
            <input type="hidden" name="organism" value="">
            <input type="hidden" name="assembly" value="">
            <!-- Hidden context fields for back button -->
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" name="display_name" value="<?= htmlspecialchars($display_name) ?>">

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
                            value="<?= htmlspecialchars($context_organism ?: $context_group) ?>"
                            >
                        <button class="btn btn-success" type="button" id="clearFilterBtn">
                            <i class="fa fa-times"></i> Clear Filters
                        </button>
                    </div>
                </div>
                <?php if (!empty($filter_organisms)): ?>
                    <small class="form-text text-muted d-block mt-2"><i class="fa fa-filter"></i> Showing only: <?= htmlspecialchars(implode(', ', $filter_organisms)) ?></small>
                <?php endif; ?>
                
                <!-- Scrollable list of sources -->
                <div class="fasta-source-list">
                    <?php 
                    // Define a color palette for groups only
                    $group_colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
                    $group_color_map = []; // Map group names to colors
                    $first_source = true; // Track first source for auto-checking
                    
                    foreach ($sources_by_group as $group_name => $organisms): 
                        // Assign color to this group
                        if (!isset($group_color_map[$group_name])) {
                            $group_color_map[$group_name] = $group_colors[count($group_color_map) % count($group_colors)];
                        }
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
                                        <?php if ($first_source): ?>checked<?php $first_source = false; endif; ?>
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
        
        // Create mock available_sequences array that sequences_display.php expects
        $available_sequences = [];
        foreach ($displayed_content as $seq_type => $content) {
            $available_sequences[$seq_type] = [
                'label' => $sequence_types[$seq_type]['label'] ?? ucfirst($seq_type),
                'sequences' => [$content]  // Wrap in array since sequences_display expects array
            ];
        }
        
        // Include the reusable sequences display component
        include_once __DIR__ . '/../display/sequences_display.php';
        ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const filterInput = document.getElementById('sourceFilter');
        const clearFilterBtn = document.getElementById('clearFilterBtn');
        const sourceLines = document.querySelectorAll('.fasta-source-line');
        const radios = document.querySelectorAll('input[name="selected_source"]');
        const form = document.getElementById('downloadForm');
        const errorAlert = document.querySelector('.alert-danger');
        
        // Function to apply filter
        function applyFilter() {
            const filterText = (filterInput.value || '').toLowerCase();
            
            sourceLines.forEach(line => {
                const searchText = line.dataset.search || '';
                if (filterText === '' || searchText.includes(filterText)) {
                    line.classList.remove('hidden');
                } else {
                    line.classList.add('hidden');
                }
            });
        }
        
        // Dismiss error alert on form submission
        if (form) {
            form.addEventListener('submit', function() {
                if (errorAlert) {
                    const bsAlert = new bootstrap.Alert(errorAlert);
                    bsAlert.close();
                }
            });
        }
        
        // Handle filter input
        if (filterInput) {
            filterInput.addEventListener('keyup', applyFilter);
        }
        
        // Handle clear filter button
        if (clearFilterBtn) {
            clearFilterBtn.addEventListener('click', function(e) {
                e.preventDefault();
                filterInput.value = '';
                applyFilter();
                filterInput.focus();
            });
        }
        
        // Handle radio button selection
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Update hidden form fields
                if (form) {
                    form.querySelector('input[name="organism"]').value = this.dataset.organism;
                    form.querySelector('input[name="assembly"]').value = this.dataset.assembly;
                }
            });
        });
        
        // Auto-select first sequence type (but NOT the first assembly)
        // (No longer needed - we show all sequence types)
        
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
        
        // Apply initial filter on page load if context_organism was set
        applyFilter();

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

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
