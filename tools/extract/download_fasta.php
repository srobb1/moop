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
$sequence_type = trim($_POST['sequence_type'] ?? '');
$selected_assembly = trim($_POST['selected_assembly'] ?? '');

include_once __DIR__ . '/../../site_config.php';
include_once __DIR__ . '/../../includes/access_control.php';
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

// Get comma-separated organisms for filtering (from URL or POST)
$filter_organisms_string = trim($_GET['organisms'] ?? $_POST['organisms'] ?? '');

// Parse filter organisms if provided
$filter_organisms = [];
if (!empty($filter_organisms_string)) {
    $filter_organisms = array_map('trim', explode(',', $filter_organisms_string));
    $filter_organisms = array_filter($filter_organisms);
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

// If sequence_type is set, this is the download request
if (!empty($sequence_type)) {
    $download_error = null;
    
    // Validate inputs
    if (empty($uniquenames_string)) {
        $download_error = "No feature IDs provided.";
        logError($download_error, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
    }
    
    // Get the selected source from the form
    $selected_organism = trim($_POST['organism'] ?? '');
    $selected_assembly = trim($_POST['assembly'] ?? '');
    
    if (!$download_error && (empty($selected_organism) || empty($selected_assembly))) {
        $download_error = "No assembly selected.";
        logError($download_error, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
    }
    
    // Find the selected assembly in accessible sources
    $fasta_source = null;
    if (!$download_error) {
        foreach ($accessible_sources as $source) {
            if ($source['assembly'] === $selected_assembly && $source['organism'] === $selected_organism) {
                $fasta_source = $source;
                break;
            }
        }
        
        if (!$fasta_source) {
            $download_error = "You do not have access to the selected assembly.";
            logError($download_error, "download_fasta", [
                'user' => $_SESSION['username'] ?? 'unknown',
                'organism' => $selected_organism,
                'assembly' => $selected_assembly
            ]);
        }
    }
    
    // Parse feature IDs
    $uniquenames = [];
    if (!$download_error) {
        $uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));
        if (empty($uniquenames)) {
            $download_error = "No valid feature IDs provided.";
            logError($download_error, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
        }
    }
    
    // Find FASTA file for selected sequence type
    $fasta_file = null;
    if (!$download_error) {
        $assembly_dir = $fasta_source['path'];
        
        if (isset($sequence_types[$sequence_type])) {
            $files = glob("$assembly_dir/*{$sequence_types[$sequence_type]['pattern']}");
            if (!empty($files)) {
                $fasta_file = $files[0];
            }
        }
        
        if (!$fasta_file || !file_exists($fasta_file)) {
            $download_error = "FASTA file not found for $sequence_type sequences.";
            logError($download_error, "download_fasta", [
                'user' => $_SESSION['username'] ?? 'unknown',
                'sequence_type' => $sequence_type,
                'organism' => $selected_organism
            ]);
        }
    }
    
    // Extract sequences using blast function
    if (!$download_error) {
        $extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames);
        
        if (!$extract_result['success']) {
            $download_error = $extract_result['error'];
            logError($download_error, "download_fasta", ['user' => $_SESSION['username'] ?? 'unknown']);
        } else {
            $content = $extract_result['content'];
        }
    }
    
    // If there was an error, set error message and show form later
    $download_error_msg = $download_error;
    
    // If no error, proceed with download
    if (!$download_error_msg) {
        // Remove blank lines from output
        $lines = explode("\n", $content);
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        $content = implode("\n", $lines);
        
        // Send download
        $file_format = $_POST['file_format'] ?? 'fasta';
        $ext = ($file_format === 'txt') ? 'txt' : 'fasta';
        $filename = "sequences_{$sequence_type}_" . date("Y-m-d_His") . ".{$ext}";
        
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename={$filename}");
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}

// Initialize error message if not set
if (!isset($download_error_msg)) {
    $download_error_msg = null;
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
    <title>FASTA Sequence Search - <?= htmlspecialchars($siteTitle) ?></title>
    <?php include_once __DIR__ . '/../../includes/head.php'; ?>
    <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container mt-5">
    <!-- Navigation Buttons -->
    <div class="mb-3">
        <?php if (!empty($context_assembly) && !empty($context_organism)): ?>
            <a href="/<?= $site ?>/tools/display/assembly_display.php?organism=<?= urlencode($context_organism) ?>&assembly=<?= urlencode($context_assembly) ?>" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to <?= htmlspecialchars($display_name ?: $context_assembly) ?>
            </a>
        <?php elseif (!empty($context_organism)): ?>
            <a href="/<?= $site ?>/tools/display/organism_display.php?organism=<?= urlencode($context_organism) ?>" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to <?= htmlspecialchars($display_name ?: 'Organism') ?>
            </a>
        <?php elseif (!empty($context_group)): ?>
            <a href="/<?= $site ?>/tools/display/groups_display.php?group=<?= urlencode($context_group) ?>" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to <?= htmlspecialchars($display_name ?: $context_group) ?>
            </a>
        <?php else: ?>
            <a href="/<?= $site ?>/index.php" class="btn btn-secondary">
                <i class="fa fa-home"></i> Home
            </a>
        <?php endif; ?>
    </div>

    <h2 class="mb-4"><i class="fa fa-dna"></i> FASTA Sequence Search & Download</h2>

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
                <li>Select sequence type (genome, protein, CDS, or transcript)</li>
                <li>Download your sequences</li>
            </ol>
        </div>

        <?php if (!empty($download_error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($download_error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

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

            <!-- Sequence Type Selection -->
            <?php if (!empty($available_types)): ?>
                <div class="mb-4">
                    <label class="form-label"><strong>Select Sequence Type</strong></label>
                    <?php foreach ($available_types as $seq_type => $label): ?>
                        <div class="fasta-sequence-option">
                            <label>
                                <input type="radio" name="sequence_type" value="<?= htmlspecialchars($seq_type) ?>" required>
                                <strong><?= htmlspecialchars($label) ?></strong>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-download"></i> Download Sequences
                    </button>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <strong>No FASTA files available</strong>
                    <p class="mb-0">No sequence files were found for the accessible assemblies.</p>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
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
        const firstRadio = document.querySelector('input[name="sequence_type"]');
        if (firstRadio) {
            firstRadio.checked = true;
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
        
        // Apply initial filter on page load if context_organism was set
        applyFilter();
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
