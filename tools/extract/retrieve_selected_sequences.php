<?php
/**
 * FASTA Download Tool
 * Allows users to download sequences (protein, CDS, mRNA) for selected features
 * Uses blastdbcmd to extract from FASTA BLAST databases
 */

ob_start();

ob_end_clean();
ob_implicit_flush(true);
session_start();

// Get parameters before including site_config to avoid output before headers
$download_file_flag = isset($_POST['download_file']) && $_POST['download_file'] == '1';
$sequence_type = trim($_POST['sequence_type'] ?? '');
$sequence_ids_provided = !empty($_POST['uniquenames']);

include_once __DIR__ . '/../../site_config.php';
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/navigation.php';
include_once __DIR__ . '/../moop_functions.php';
include_once __DIR__ . '/../blast_functions.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: /$site/login.php");
    exit;
}

// Get all parameters
$organism_name = trim($_POST['organism'] ?? $_GET['organism'] ?? '');
$assembly_name = trim($_POST['assembly'] ?? $_GET['assembly'] ?? '');
$uniquenames_string = trim($_POST['uniquenames'] ?? $_GET['uniquenames'] ?? '');

// Get context parameters for back button
$context_organism = trim($_POST['context_organism'] ?? $_GET['context_organism'] ?? $_GET['organism'] ?? '');
$context_assembly = trim($_POST['context_assembly'] ?? $_GET['context_assembly'] ?? $_GET['assembly'] ?? '');
$context_group = trim($_POST['context_group'] ?? $_GET['context_group'] ?? '');
$display_name = trim($_POST['display_name'] ?? $_GET['display_name'] ?? '');

// Check if user is logged in
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];

// Initialize displayed content
$displayed_content = [];

// If sequence IDs are provided, extract ALL sequence types
if (!empty($sequence_ids_provided)) {
    $extraction_errors = [];
    
    // Validate inputs
    if (empty($organism_name) || empty($uniquenames_string)) {
        $extraction_errors[] = 'Missing organism or feature IDs';
    }
    
    // If assembly not specified, try to find first one for organism
    if (empty($assembly_name)) {
        $groups_file = $metadata_path . '/organism_assembly_groups.json';
        if (file_exists($groups_file)) {
            $groups_data = json_decode(file_get_contents($groups_file), true) ?: [];
            foreach ($groups_data as $entry) {
                if ($entry['organism'] === $organism_name) {
                    $assembly_name = $entry['assembly'];
                    break;
                }
            }
        }
    }
    
    // Check access
    if (empty($extraction_errors)) {
        if (!has_assembly_access($organism_name, $assembly_name)) {
            if (!$is_logged_in) {
                header("Location: /$site/login.php");
            } else {
                header("Location: /$site/access_denied.php");
            }
            exit;
        }
    }
    
    // Parse feature IDs
    $uniquenames = [];
    if (empty($extraction_errors)) {
        $uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));
        if (empty($uniquenames)) {
            $extraction_errors[] = 'No valid feature IDs provided.';
        }
    }
    
    // Find FASTA files and extract for all types
    if (empty($extraction_errors)) {
        $organism_dir = "$organism_data/$organism_name";
        $assembly_dir = null;
        
        if (is_dir($organism_dir)) {
            $dirs = array_diff(scandir($organism_dir), ['.', '..']);
            foreach ($dirs as $item) {
                $full_path = "$organism_dir/$item";
                if (is_dir($full_path) && !in_array(basename($full_path), ['fasta_files'])) {
                    $assembly_dir = $full_path;
                    break;
                }
            }
        }
        
        if ($assembly_dir) {
            foreach ($sequence_types as $seq_type => $config) {
                $files = glob("$assembly_dir/*{$config['pattern']}");
                
                if (!empty($files)) {
                    $fasta_file = $files[0];
                    $extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames);
                    
                    if ($extract_result['success']) {
                        $displayed_content[$seq_type] = $extract_result['content'];
                    }
                }
            }
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

// Display form
$uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));

if (empty($organism_name)) {
    die('Error: Organism not specified.');
}

// Check access for form display
if (!has_assembly_access($organism_name, $assembly_name)) {
    if (!$is_logged_in) {
        header("Location: /$site/login.php");
    } else {
        header("Location: /$site/access_denied.php");
    }
    exit;
}

if (empty($uniquenames)) {
    die('Error: No feature IDs provided.');
}

// Now include the HTML headers
include_once __DIR__ . '/../../includes/head.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>FASTA Download - <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 1200px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin: 0 auto; }
        .sequence-option { padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 10px; cursor: pointer; }
        .sequence-option:hover { background-color: #f8f9fa; border-color: #0d6efd; }
        .sequence-option input[type="radio"] { margin-right: 10px; }
        .selected-ids { background-color: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 150px; overflow-y: auto; }
        .badge-custom { display: inline-block; background-color: #0d6efd; color: white; padding: 5px 10px; margin: 3px; border-radius: 3px; font-size: 0.85em; }
        .tooltip { z-index: 9999 !important; }
        .tooltip-inner { background-color: #000 !important; }
        /* Ensure tooltip is positioned relative to body, not constrained containers */
        body { position: relative; }
    </style>
</head>
<body>
<div class="container">
    <div class="mb-4">
        <?php
        $nav_context = [
            'page' => 'tool',
            'tool_page' => 'retrieve_selected_sequences',
            'organism' => $context_organism,
            'assembly' => $context_assembly,
            'group' => $context_group,
            'display_name' => $display_name
        ];
        echo render_navigation_buttons($nav_context);
        ?>
    </div>

    <h2 class="mb-4"><i class="fa fa-dna"></i> Download Selected Sequences</h2>

    <div class="alert alert-info">
        <strong>Organism:</strong> <em><?= htmlspecialchars($organism_name) ?></em><br>
        <strong>Selected Features:</strong> <span class="badge bg-secondary"><?= count($uniquenames) ?></span>
    </div>

    <div class="mb-4">
        <h5>Selected Feature IDs</h5>
        <div class="selected-ids">
            <?php foreach (array_slice($uniquenames, 0, 10) as $id): ?>
                <span class="badge-custom"><?= htmlspecialchars($id) ?></span>
            <?php endforeach; ?>
            <?php if (count($uniquenames) > 10): ?>
                <span class="badge-custom">+<?= count($uniquenames) - 10 ?> more</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($displayed_content)): ?>
        <!-- If no sequences yet, just show simple submit button -->
        <form method="POST">
            <input type="hidden" name="organism" value="<?= htmlspecialchars($organism_name) ?>">
            <input type="hidden" name="uniquenames" value="<?= htmlspecialchars($uniquenames_string) ?>">
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($assembly_name) ?>">
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa fa-eye"></i> Display All Sequences
                </button>
            </div>
        </form>
    <?php else: ?>
        <!-- Sequences Display Section -->
        <hr class="my-4">
        <?php
        // Set up variables for sequences_display.php
        $gene_name = $uniquenames_string;
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

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
$(document).ready(function() {
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
});
</script>
</body>
</html>
