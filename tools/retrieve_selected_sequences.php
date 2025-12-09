<?php
/**
 * RETRIEVE SELECTED SEQUENCES - Download Tool
 * 
 * Allows users to download sequences (protein, CDS, mRNA) for selected features.
 * Uses blastdbcmd to extract from FASTA BLAST databases.
 * 
 * ========== DATA FLOW ==========
 * 
 * Browser Request → This file (controller)
 *   ↓
 * Validate user access to assembly
 *   ↓
 * Extract sequence IDs and load sequences if requested
 *   ↓
 * IF download flag → sendFile() + exit (never reaches template)
 *   ↓
 * Configure layout (title, scripts, styles)
 *   ↓
 * Call display-template.php with content file + data
 *   ↓
 * layout.php renders complete HTML page
 *   ↓
 * Content file (pages/retrieve_selected_sequences.php) displays data
 */

// Start output buffering to catch any stray output from includes
ob_start();

// Get parameters before including config to avoid output before headers
$download_file_flag = isset($_POST['download_file']) && $_POST['download_file'] == '1';
$sequence_type = trim($_POST['sequence_type'] ?? '');
$sequence_ids_provided = !empty($_POST['uniquenames']) || !empty($_GET['uniquenames']);

include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

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

// Check access to the requested assembly
if (!has_assembly_access($organism_name, $assembly_name)) {
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
    
    // Handle download request if present
    handleSequenceDownload($download_file_flag, $sequence_type, $displayed_content[$sequence_type] ?? null);
}

// Parse form data for display
$uniquenames = array_filter(array_map('trim', explode(',', $uniquenames_string)));

if (empty($organism_name)) {
    die('Error: Organism not specified.');
}

if (empty($uniquenames)) {
    die('Error: No feature IDs provided.');
}

// Configure display template
$display_config = [
    'title' => 'Download Selected Sequences - ' . htmlspecialchars($siteTitle),
    'content_file' => __DIR__ . '/pages/retrieve_selected_sequences.php',
    'page_script' => null,
    'inline_scripts' => []
];

// Prepare data for content file
$data = [
    'site' => $site,
    'siteTitle' => $siteTitle,
    'organism_name' => $organism_name,
    'assembly_name' => $assembly_name,
    'uniquenames' => $uniquenames,
    'uniquenames_string' => $uniquenames_string,
    'displayed_content' => $displayed_content,
    'sequence_types' => $sequence_types,
    'available_sequences' => !empty($displayed_content) ? formatSequenceResults($displayed_content, $sequence_types) : [],
    'page_styles' => [
        '/' . $site . '/css/display.css',
        '/' . $site . '/css/retrieve-selected-sequences.css',
    ]
];

// Use generic template to render
include_once __DIR__ . '/display-template.php';

?>
