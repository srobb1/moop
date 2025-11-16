<?php
/**
 * BLAST Search Tool
 * Integrated tool for performing BLAST searches against organism databases
 * Respects user permissions for accessing specific assemblies
 * Context-aware: Can be limited to specific organism/assembly/group from referring page
 */

session_start();

include_once __DIR__ . '/../../site_config.php';
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/navigation.php';
include_once __DIR__ . '/../moop_functions.php';
include_once __DIR__ . '/../blast_functions.php';
include_once __DIR__ . '/../blast_results_visualizer.php';

// Check if user is logged in (public users can also access if assemblies are public)
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];

// Get context parameters from referring page (GET first, then POST for form resubmission)
$context_organism = trim($_POST['context_organism'] ?? $_GET['organism'] ?? '');
$context_assembly = trim($_POST['context_assembly'] ?? $_GET['assembly'] ?? '');
$context_group = trim($_POST['context_group'] ?? $_GET['group'] ?? '');
$display_name = trim($_GET['display_name'] ?? '');

// Get comma-separated organisms for filtering (GET or POST)
$filter_organisms_string = trim($_POST['organisms'] ?? $_GET['organisms'] ?? '');

// Parse filter organisms if provided
$filter_organisms = [];
if (!empty($filter_organisms_string)) {
    $filter_organisms = array_map('trim', explode(',', $filter_organisms_string));
    $filter_organisms = array_filter($filter_organisms);
}

// Get form data
$search_query = trim($_POST['query'] ?? '');
$blast_program = trim($_POST['blast_program'] ?? 'blastx');
$selected_source = trim($_POST['selected_source'] ?? '');
$blast_db = trim($_POST['blast_db'] ?? '');
$max_hits = (int)($_POST['max_hits'] ?? 10);
$evalue = trim($_POST['evalue'] ?? '1e-3');
$matrix = trim($_POST['matrix'] ?? 'BLOSUM62');
$filter_seq = isset($_POST['filter_seq']);
$task = trim($_POST['task'] ?? '');

// Get accessible assemblies organized by group -> organism
$sources_by_group = getAccessibleAssemblies();

// Flatten for sequential processing
$accessible_sources = [];
foreach ($sources_by_group as $group => $organisms) {
    foreach ($organisms as $org => $assemblies) {
        $accessible_sources = array_merge($accessible_sources, $assemblies);
    }
}

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
                    'max_hits' => $max_hits,
                    'matrix' => $matrix,
                    'filter' => $filter_seq,
                    'task' => $task
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

// Now include the HTML headers
include_once __DIR__ . '/../../includes/head.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>BLAST Search - <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="container mt-5">
    <!-- Navigation Buttons -->
    <div class="mb-3">
        <?php
        $nav_context = [
            'page' => 'tool',
            'tool_page' => 'blast_search',
            'organism' => $context_organism,
            'assembly' => $context_assembly,
            'group' => $context_group,
            'display_name' => $display_name
        ];
        echo render_navigation_buttons($nav_context);
        ?>
    </div>

    <h2 class="mb-4"><i class="fa fa-dna"></i> BLAST Search</h2>

    <?php if (empty($accessible_sources)): ?>
        <div class="alert alert-warning">
            <strong>No accessible assemblies found.</strong>
            <p class="mb-0">You do not have access to any organism assemblies, or the data directory is misconfigured.</p>
        </div>
    <?php else: ?>
        <div class="fasta-info-box">
            <strong><i class="fa fa-info-circle"></i> How to use:</strong>
            <ol class="mb-0 mt-2">
                <li>Paste a DNA or protein sequence</li>
                <li>Select BLAST program (type determines available databases)</li>
                <li>Select organism and database</li>
                <li>Configure advanced options if needed</li>
                <li>Click Search to run BLAST</li>
            </ol>
        </div>

        <?php if (isset($search_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="fa fa-exclamation-circle"></i> Error:</strong> <?= htmlspecialchars($search_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="blastForm">
            <!-- Hidden context fields for back button -->
            <input type="hidden" name="context_organism" value="<?= htmlspecialchars($context_organism) ?>">
            <input type="hidden" name="context_assembly" value="<?= htmlspecialchars($context_assembly) ?>">
            <input type="hidden" name="context_group" value="<?= htmlspecialchars($context_group) ?>">
            <input type="hidden" name="display_name" value="<?= htmlspecialchars($display_name) ?>">
            <input type="hidden" name="organisms" value="<?= htmlspecialchars($filter_organisms_string) ?>">
            <input type="hidden" name="organism" value="">
            <input type="hidden" name="assembly" value="">

            <!-- Sequence Input -->
            <div class="mb-4">
                <label for="query" class="form-label"><strong>Paste Sequence</strong></label>
                <textarea 
                    id="query" 
                    name="query" 
                    class="form-control fasta-textarea-ids" 
                    rows="8"
                    required
                    placeholder="Enter sequence in FASTA format or plain text&#10;Example:&#10;>seq1&#10;ATGCTAGCTAGC..."
                ><?= htmlspecialchars($search_query) ?></textarea>
                <small class="form-text text-muted">You can paste FASTA format (with >) or just the raw sequence.</small>
            </div>

            <!-- BLAST Program Selection -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="blast_program" class="form-label"><strong>BLAST Program</strong></label>
                    <div id="sequenceTypeInfo" class="sequence-type-info" style="display: none;">
                        <small id="sequenceTypeMessage"></small>
                    </div>
                    <select id="blast_program" name="blast_program" class="form-control" onchange="updateDatabaseList();">
                        <option value="blastn" <?= $blast_program === 'blastn' ? 'selected' : '' ?>>BLASTn (DNA vs DNA)</option>
                        <option value="blastp" <?= $blast_program === 'blastp' ? 'selected' : '' ?>>BLASTp (Protein vs Protein)</option>
                        <option value="blastx" <?= $blast_program === 'blastx' ? 'selected' : '' ?>>BLASTx (DNA to Protein)</option>
                        <option value="tblastn" <?= $blast_program === 'tblastn' ? 'selected' : '' ?>>tBLASTn (Protein vs DNA)</option>
                        <option value="tblastx" <?= $blast_program === 'tblastx' ? 'selected' : '' ?>>tBLASTx (DNA vs DNA)</option>
                    </select>
                    <small class="form-text text-muted">Changing this updates available databases</small>
                </div>
            </div>

            <!-- Source Selection with Compact Badges (matching download_sequences.php) -->
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
                <?php if (!empty($context_organism) || !empty($context_group) || !empty($context_assembly)): ?>
                    <small class="form-text text-muted d-block mt-2"><i class="fa fa-filter"></i> 
                        Filtered to: 
                        <?php if (!empty($context_assembly)): ?>
                            <?= htmlspecialchars($context_organism . ' / ' . $context_assembly) ?>
                        <?php elseif (!empty($context_organism)): ?>
                            <?= htmlspecialchars($context_organism) ?> (all assemblies)
                        <?php elseif (!empty($context_group)): ?>
                            <?= htmlspecialchars($context_group) ?> group
                        <?php endif; ?>
                    </small>
                <?php endif; ?>
                
                <!-- Scrollable list of sources -->
                <div class="fasta-source-list">
                    <?php 
                    // Define a color palette for groups only
                    $group_colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
                    $group_color_map = [];
                    
                    foreach ($sources_by_group as $group_name => $organisms): 
                        // Assign color to this group
                        if (!isset($group_color_map[$group_name])) {
                            $group_color_map[$group_name] = $group_colors[count($group_color_map) % count($group_colors)];
                        }
                        $group_color = $group_color_map[$group_name];
                        
                        foreach ($organisms as $organism => $assemblies): 
                            foreach ($assemblies as $source): 
                                $search_text = strtolower("$group_name $organism $source[assembly]");
                                
                                // Determine if this source should be hidden initially based on context
                                $matches_context = true;
                                
                                if (!empty($context_assembly)) {
                                    // Viewing specific assembly - only show that assembly
                                    $matches_context = ($source['organism'] === $context_organism && $source['assembly'] === $context_assembly);
                                } elseif (!empty($context_organism)) {
                                    // Viewing organism - only show its assemblies
                                    $matches_context = ($source['organism'] === $context_organism);
                                } elseif (!empty($context_group)) {
                                    // Viewing group - only show this group's items
                                    $matches_context = ($group_name === $context_group);
                                }
                                
                                $hidden_class = !$matches_context ? 'hidden' : '';
                                ?>
                                <div class="fasta-source-line <?= $hidden_class ?>" data-search="<?= htmlspecialchars($search_text) ?>" data-matches-context="<?= $matches_context ? '1' : '0' ?>">
                                    <input 
                                        type="radio" 
                                        name="selected_source" 
                                        value="<?= htmlspecialchars($source['organism'] . '|' . $source['assembly']) ?>"
                                        data-organism="<?= htmlspecialchars($source['organism']) ?>"
                                        data-assembly="<?= htmlspecialchars($source['assembly']) ?>"
                                        data-path="<?= htmlspecialchars($source['path']) ?>"
                                        onchange="updateDatabaseList();"
                                        id="src_<?= htmlspecialchars(str_replace([' ', '|'], '_', $source['organism'] . '_' . $source['assembly'])) ?>"
                                        <?= ($selected_source === ($source['organism'] . '|' . $source['assembly'])) ? 'checked' : '' ?>
                                    >
                                    
                                    <!-- Group badge - colorful -->
                                    <span class="badge badge-sm bg-<?= $group_color ?> text-white">
                                        <?= htmlspecialchars($group_name) ?>
                                    </span>
                                    
                                    <!-- Organism badge - gray -->
                                    <span class="badge badge-sm bg-secondary text-white">
                                        <?= htmlspecialchars($organism) ?>
                                    </span>
                                    
                                    <!-- Assembly badge - blue -->
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

            <!-- Database Selection (as badges) -->
            <div class="mt-4" id="databaseSelector">
                <label class="form-label"><strong>Select Database</strong></label>
                <div id="databaseBadges" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
                    <div style="padding: 15px; text-align: center; color: #666; width: 100%;">
                        <small>Select an assembly first</small>
                    </div>
                </div>
            </div>

            <!-- Advanced Options (Collapsible) -->
            <div class="mt-4">
                <div class="collapse-section pointer-cursor" data-toggle="collapse" data-target="#advOptions" style="text-align: center; cursor: pointer; padding: 10px; background-color: #f8f9fa; border-radius: 4px;">
                    <i class="fas fa-sliders-h"></i> <strong>Advanced Options</strong>
                </div>
                
                <div id="advOptions" class="collapse mt-3">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="evalue" class="form-label"><strong>E-value Threshold</strong></label>
                            <select id="evalue" name="evalue" class="form-control">
                                <option value="10" <?= $evalue === '10' ? 'selected' : '' ?>>10</option>
                                <option value="1" <?= $evalue === '1' ? 'selected' : '' ?>>1</option>
                                <option value="0.1" <?= $evalue === '0.1' ? 'selected' : '' ?>>0.1</option>
                                <option value="1e-3" <?= $evalue === '1e-3' ? 'selected' : '' ?> selected>1e-3 (default)</option>
                                <option value="1e-6" <?= $evalue === '1e-6' ? 'selected' : '' ?>>1e-6</option>
                                <option value="1e-9" <?= $evalue === '1e-9' ? 'selected' : '' ?>>1e-9</option>
                                <option value="1e-12" <?= $evalue === '1e-12' ? 'selected' : '' ?>>1e-12</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="max_hits" class="form-label"><strong>Maximum Hits</strong></label>
                            <select id="max_hits" name="max_hits" class="form-control">
                                <option value="10" <?= $max_hits === 10 ? 'selected' : '' ?> selected>10</option>
                                <option value="20" <?= $max_hits === 20 ? 'selected' : '' ?>>20</option>
                                <option value="40" <?= $max_hits === 40 ? 'selected' : '' ?>>40</option>
                                <option value="60" <?= $max_hits === 60 ? 'selected' : '' ?>>60</option>
                                <option value="100" <?= $max_hits === 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="matrix" class="form-label"><strong>Scoring Matrix</strong></label>
                            <select id="matrix" name="matrix" class="form-control">
                                <option value="BLOSUM45" <?= $matrix === 'BLOSUM45' ? 'selected' : '' ?>>BLOSUM45</option>
                                <option value="BLOSUM62" <?= $matrix === 'BLOSUM62' ? 'selected' : '' ?> selected>BLOSUM62 (default)</option>
                                <option value="BLOSUM80" <?= $matrix === 'BLOSUM80' ? 'selected' : '' ?>>BLOSUM80</option>
                                <option value="PAM30" <?= $matrix === 'PAM30' ? 'selected' : '' ?>>PAM30</option>
                                <option value="PAM70" <?= $matrix === 'PAM70' ? 'selected' : '' ?>>PAM70</option>
                                <option value="PAM250" <?= $matrix === 'PAM250' ? 'selected' : '' ?>>PAM250</option>
                            </select>
                            <small class="form-text text-muted">Only used for protein searches</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="filter_seq" id="filter_seq" <?= $filter_seq ? 'checked' : '' ?>>
                                <label class="form-check-label" for="filter_seq">
                                    Filter low complexity regions
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg" id="searchBtn">
                    <i class="fa fa-search"></i> Search
                </button>
            </div>

            <!-- Progress indicator (hidden initially) -->
            <div class="mt-4" id="progressIndicator" style="display: none;">
                <div class="alert alert-info">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span id="progressText">Running BLAST search...</span>
                </div>
            </div>
        </form>

        <!-- Results -->
        <?php if (isset($blast_result) && $blast_result['success'] && !empty($blast_result['output'])): ?>
            <div class="mt-4 card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-chart-bar"></i> BLAST Results</h5>
                    <button type="button" class="btn btn-sm btn-light" onclick="clearResults();" title="Clear results and start new search">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="card-body">
                    <!-- Visualization -->
                    <?= generateCompleteBlastVisualization($blast_result, $search_query, $blast_program) ?>
                    
                    <!-- Store pairwise output in hidden element for download -->
                    <?php if (isset($blast_result['pairwise'])): ?>
                        <div id="pairwiseOutput" style="display: none;">
                            <?= htmlspecialchars($blast_result['pairwise']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Download button -->
                    <div class="mt-4">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadResultsText();">
                            <i class="fa fa-download"></i> Download TXT
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<script>
// Store the previously selected database (for form restoration after errors)
const previouslySelectedDb = '<?= addslashes($blast_db) ?>';

// Store the previously selected source (for form restoration after errors)
const previouslySelectedSource = '<?= addslashes($selected_source) ?>';
console.log('previouslySelectedSource:', previouslySelectedSource);
console.log('Available radio buttons:', document.querySelectorAll('input[name="selected_source"]').length);

// Build databasesByAssembly object for dynamic filtering
const databasesByAssembly = {};
<?php 
foreach ($sources_by_group as $group => $organisms) {
    foreach ($organisms as $organism => $assemblies) {
        foreach ($assemblies as $source) {
            $source_key = $organism . '|' . $source['assembly'];
            $dbs = getBlastDatabases($source['path']);
            echo "databasesByAssembly['" . addslashes($source_key) . "'] = " . json_encode($dbs) . ";\n";
        }
    }
}
?>

// Define other helper functions and event listeners
function downloadResultsHTML() {
    // Find the results card
    const resultsCard = document.querySelector('.card');
    if (!resultsCard) return;
    
    const content = resultsCard.innerHTML;
    const blob = new Blob([content], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'blast_results_' + new Date().toISOString().slice(0, 10) + '.html';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function downloadResultsText() {
    // Get pairwise output from hidden element
    const pairwiseDiv = document.getElementById('pairwiseOutput');
    if (!pairwiseDiv) {
        alert('No BLAST results to download');
        return;
    }
    
    const textContent = pairwiseDiv.textContent || pairwiseDiv.innerText;
    const blob = new Blob([textContent], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'blast_results_' + new Date().toISOString().slice(0, 10) + '.txt';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function clearResults() {
    // Remove the results card
    const resultsCard = document.querySelector('.mt-4.card');
    if (resultsCard) {
        resultsCard.remove();
    }
    
    // Reset the form inputs
    document.getElementById('query').focus();
}

// Apply filter function
function applyFilter() {
    const filterText = (document.getElementById('sourceFilter').value || '').toLowerCase();
    const sourceLines = document.querySelectorAll('input[name="selected_source"]').forEach(radio => {
        const line = radio.closest('.fasta-source-line');
        if (line) {
            const searchText = line.dataset.search || '';
            // Show if: filter is empty OR search text matches OR matches context
            const matchesText = filterText === '' || searchText.includes(filterText);
            if (matchesText) {
                line.classList.remove('hidden');
            } else {
                line.classList.add('hidden');
            }
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const filterInput = document.getElementById('sourceFilter');
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    
    // Handle filter input
    if (filterInput) {
        filterInput.addEventListener('keyup', applyFilter);
    }
    
    // Handle clear filter button - shows all accessible assemblies
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            filterInput.value = '';
            // Show all items (remove context-based hiding)
            document.querySelectorAll('.fasta-source-line').forEach(line => {
                line.classList.remove('hidden');
            });
            filterInput.focus();
        });
    }
    
    // Restore previously selected source (if form was resubmitted)
    if (previouslySelectedSource) {
        // Find the radio button regardless of visibility, then check parent
        const allSourceRadios = document.querySelectorAll(`input[name="selected_source"][value="${previouslySelectedSource}"]`);
        let prevSourceRadio = null;
        
        for (let radio of allSourceRadios) {
            const line = radio.closest('.fasta-source-line');
            if (line && !line.classList.contains('hidden')) {
                prevSourceRadio = radio;
                break;
            }
        }
        
        if (prevSourceRadio) {
            prevSourceRadio.checked = true;
            console.log('Restored previously selected source:', previouslySelectedSource);
        } else {
            console.log('Could not find visible radio button for source:', previouslySelectedSource);
            // Fall back to first visible if restoration failed
            const firstRadio = document.querySelector('input[name="selected_source"]');
            const firstLine = firstRadio ? firstRadio.closest('.fasta-source-line') : null;
            if (firstLine && !firstLine.classList.contains('hidden')) {
                firstRadio.checked = true;
                console.log('Falling back to first visible source');
            }
        }
    }
    
    // Only auto-select first if nothing was restored
    if (!previouslySelectedSource) {
        const allRadios = document.querySelectorAll('input[name="selected_source"]');
        for (let radio of allRadios) {
            const line = radio.closest('.fasta-source-line');
            if (line && !line.classList.contains('hidden') && !radio.checked) {
                radio.click();
                break;
            }
        }
    }
    
    // Update database list based on selected source
    updateDatabaseList();
    
    // Restore previously selected database (after updateDatabaseList renders the radio buttons)
    if (previouslySelectedDb) {
        const prevDbRadio = document.querySelector(`input[name="blast_db"][value="${previouslySelectedDb}"]`);
        if (prevDbRadio) {
            prevDbRadio.checked = true;
            console.log('Restored previously selected database:', previouslySelectedDb);
        }
    }
    
    // Handle form submission
    const form = document.getElementById('blastForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const selectedSource = document.querySelector('input[name="selected_source"]:checked');
            const selectedDb = document.querySelector('input[name="blast_db"]:checked');
            
            if (!selectedSource || !selectedDb) {
                e.preventDefault();
                alert('Please select both an assembly and a database.');
                return false;
            }
            
            // Update hidden organism/assembly fields
            form.querySelector('input[name="organism"]').value = selectedSource.dataset.organism;
            form.querySelector('input[name="assembly"]').value = selectedSource.dataset.assembly;
            
            // Show progress indicator
            const progressIndicator = document.getElementById('progressIndicator');
            if (progressIndicator) {
                progressIndicator.style.display = 'block';
                document.getElementById('searchBtn').disabled = true;
            }
        });
    }
    
    // Apply initial filter (context-based)
    applyFilter();
    
    // Add sequence type detection on textarea change
    const queryTextarea = document.getElementById('query');
    if (queryTextarea) {
        queryTextarea.addEventListener('input', function() {
            const result = detectSequenceType(this.value);
            
            // Update UI
            updateSequenceTypeInfo(result.message, 'sequenceTypeInfo', 'sequenceTypeMessage');
            
            // Filter programs
            if (result.type !== 'unknown') {
                filterBlastPrograms(result.type, 'blast_program');
                updateDatabaseList();
            }
        });
        
        // Run once on page load if there's already a sequence
        if (queryTextarea.value) {
            const result = detectSequenceType(queryTextarea.value);
            updateSequenceTypeInfo(result.message, 'sequenceTypeInfo', 'sequenceTypeMessage');
            if (result.type !== 'unknown') {
                filterBlastPrograms(result.type, 'blast_program');
            }
        }
    }
    
    // Hide progress indicator after page has loaded (if there are results, it means search completed)
    const resultsCard = document.querySelector('.mt-4.card');
    const progressIndicator = document.getElementById('progressIndicator');
    if (resultsCard && progressIndicator) {
        progressIndicator.style.display = 'none';
    }
    
    // Re-enable search button
    const searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        searchBtn.disabled = false;
    }
});
</script>

<script src="/<?= $site ?>/js/tools_utilities.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

</body>
</html>
