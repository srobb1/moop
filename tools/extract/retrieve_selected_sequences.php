<?php
/**
 * FASTA Download Tool
 * Allows users to download sequences (protein, CDS, mRNA) for selected features
 * Uses blastdbcmd to extract from FASTA BLAST databases
 */

// Start output buffering to catch any stray output from includes
ob_start();

session_start();

// Get parameters before including config to avoid output before headers
$download_file_flag = isset($_POST['download_file']) && $_POST['download_file'] == '1';
$sequence_type = trim($_POST['sequence_type'] ?? '');
// Check if sequence IDs provided (from form submission OR from GET link)
$sequence_ids_provided = !empty($_POST['uniquenames']) || !empty($_GET['uniquenames']);

include_once __DIR__ . '/../../includes/config_init.php';
include_once __DIR__ . '/../../includes/access_control.php';
include_once __DIR__ . '/../../includes/navigation.php';
include_once __DIR__ . '/../moop_functions.php';
include_once __DIR__ . '/../blast_functions.php';
include_once __DIR__ . '/../extract_search_helpers.php';

// Clean output buffer - discard any stray output from includes before headers
ob_end_clean();

// Get config
$config = ConfigManager::getInstance();
$site = $config->getString('site');

// Get all parameters first for access check
$organism_name = trim($_POST['organism'] ?? $_GET['organism'] ?? '');
$assembly_name = trim($_POST['assembly'] ?? $_GET['assembly'] ?? '');
$uniquenames_string = trim($_POST['uniquenames'] ?? $_GET['uniquenames'] ?? '');

// Assembly MUST be specified - it's a security requirement
if (empty($assembly_name)) {
    header("Location: /$site/access_denied.php?error=assembly_required");
    exit;
}

// Check access to the requested assembly (allows public assemblies, logged-in users with access, or admins)
if (!has_assembly_access($organism_name, $assembly_name)) {
    // Redirect to login if not logged in, or access_denied if logged in but no access
    if (!is_logged_in()) {
        header("Location: /$site/login.php");
    } else {
        header("Location: /$site/access_denied.php");
    }
    exit;
}

// Get remaining config values
$organism_data = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();
$siteTitle = $config->getString('siteTitle');
$header_img = $config->getString('header_img');
$images_path = $config->getString('images_path');

// Parse context parameters
$context = parseContextParameters();

// Check if user is logged in
$is_logged_in = is_logged_in();

// Initialize displayed content
$displayed_content = [];

// If sequence IDs are provided, extract ALL sequence types
if (!empty($sequence_ids_provided)) {
    $extraction_errors = [];
    
    // Validate inputs
    if (empty($organism_name) || empty($uniquenames_string)) {
        $extraction_errors[] = 'Missing organism or feature IDs';
    }
    
    // Parse feature IDs
    if (empty($extraction_errors)) {
        $id_parse = parseFeatureIds($uniquenames_string);
        if (!$id_parse['valid']) {
            $extraction_errors[] = $id_parse['error'];
        } else {
            $uniquenames = $id_parse['uniquenames'];
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
        
        if ($assembly_dir && !empty($uniquenames)) {
            $extract_result = extractSequencesForAllTypes($assembly_dir, $uniquenames, $sequence_types);
            $displayed_content = $extract_result['content'];
            if (!empty($extract_result['errors'])) {
                $extraction_errors = array_merge($extraction_errors, $extract_result['errors']);
            }
        }
    }
    
    // If download flag is set and we have content, send the specific sequence type
    if ($download_file_flag && !empty($sequence_type) && isset($displayed_content[$sequence_type])) {
        $file_format = $_POST['file_format'] ?? 'fasta';
        sendFileDownload($displayed_content[$sequence_type], $sequence_type, $file_format);
    }
}

// Display form
$uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));

if (empty($organism_name)) {
    die('Error: Organism not specified.');
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
        $nav_context = buildNavContext('tool', [
            'organism' => $context['organism'],
            'assembly' => $context['assembly'],
            'group' => $context['group'],
            'display_name' => $context['display_name']
        ]);
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
        // Keep the values already set from extraction (which used GET/POST from line 45)
        // organism_name and assembly_name were populated during extraction
        $gene_name = $uniquenames_string;
        $enable_downloads = true;
        
        // Format results for sequences_display.php component
        $available_sequences = formatSequenceResults($displayed_content, $sequence_types);
        
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
