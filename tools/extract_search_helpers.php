<?php
/**
 * Extract/Search Common Helper Functions
 * 
 * Consolidates repeated logic from:
 * - tools/extract/*.php (retrieve_sequences.php, retrieve_selected_sequences.php)
 * - tools/search/*.php (multi_organism_search.php, annotation_search_ajax.php)
 * 
 * Provides unified handling for:
 * - Multi-organism parameter parsing (multiple input formats)
 * - Context extraction for navigation
 * - Sequence extraction and formatting
 * - File download orchestration
 * - Source list organization
 */

/**
 * Parse organism parameter from various sources and formats
 * 
 * Handles multiple input formats:
 * - Array from multi-search context (organisms[])
 * - Single organism from context parameters
 * - Comma-separated string
 * 
 * @param string|array $organisms_param - Raw parameter value
 * @param string $context_organism - Optional fallback organism
 * @return array - ['organisms' => [], 'string' => 'comma,separated,list']
 */
function parseOrganismParameter($organisms_param, $context_organism = '') {
    $filter_organisms = [];
    $filter_organisms_string = '';
    
    // First check for array (highest priority - from multi-search)
    if (is_array($organisms_param)) {
        $filter_organisms = array_filter($organisms_param);
        $filter_organisms_string = implode(',', $filter_organisms);
    } 
    // Then check for single organism context
    elseif (!empty($context_organism)) {
        $filter_organisms = [$context_organism];
        $filter_organisms_string = $context_organism;
    }
    // Finally try comma-separated string format
    else {
        $filter_organisms_string = trim($organisms_param);
        if (!empty($filter_organisms_string)) {
            $filter_organisms = array_map('trim', explode(',', $filter_organisms_string));
            $filter_organisms = array_filter($filter_organisms);
        }
    }
    
    return [
        'organisms' => $filter_organisms,
        'string' => $filter_organisms_string
    ];
}

/**
 * Extract context parameters needed for navigation (back button, etc.)
 * 
 * Checks GET then POST for standard context fields
 * 
 * @return array - ['organism' => '', 'assembly' => '', 'group' => '', 'display_name' => '']
 */
function parseContextParameters() {
    return [
        'organism' => trim($_GET['organism'] ?? $_POST['organism'] ?? ''),
        'assembly' => trim($_GET['assembly'] ?? $_POST['assembly'] ?? ''),
        'group' => trim($_GET['group'] ?? $_POST['group'] ?? ''),
        'display_name' => trim($_GET['display_name'] ?? $_POST['display_name'] ?? '')
    ];
}

/**
 * Validate extract/search inputs (organism, assembly, feature IDs)
 * 
 * Comprehensive validation for extract operations
 * 
 * @param string $organism - Organism name
 * @param string $assembly - Assembly name
 * @param string $uniquenames_string - Comma-separated feature IDs
 * @param array $accessible_sources - Available assemblies from getAccessibleAssemblies()
 * @return array - ['valid' => bool, 'errors' => [], 'fasta_source' => null]
 */
function validateExtractInputs($organism, $assembly, $uniquenames_string, $accessible_sources) {
    $errors = [];
    $fasta_source = null;
    
    // Check required fields
    if (empty($uniquenames_string)) {
        $errors[] = "No feature IDs provided.";
    }
    
    if (empty($organism) || empty($assembly)) {
        $errors[] = "No assembly selected.";
    }
    
    // Find the assembly in accessible sources
    if (empty($errors)) {
        foreach ($accessible_sources as $source) {
            if ($source['assembly'] === $assembly && $source['organism'] === $organism) {
                $fasta_source = $source;
                break;
            }
        }
        
        if (!$fasta_source) {
            $errors[] = "You do not have access to the selected assembly.";
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'fasta_source' => $fasta_source
    ];
}

/**
 * Parse and validate feature IDs from user input
 * 
 * Handles both comma and newline separated formats
 * 
 * @param string $uniquenames_string - Comma or newline separated IDs
 * @return array - ['valid' => bool, 'uniquenames' => [], 'error' => '']
 */
function parseFeatureIds($uniquenames_string) {
    $uniquenames = [];
    
    if (empty($uniquenames_string)) {
        return ['valid' => false, 'uniquenames' => [], 'error' => 'No feature IDs provided'];
    }
    
    // Handle both comma and newline separated formats
    $uniquenames = array_filter(array_map('trim', 
        preg_split('/[\n,]+/', $uniquenames_string)
    ));
    
    if (empty($uniquenames)) {
        return ['valid' => false, 'uniquenames' => [], 'error' => 'No valid feature IDs found'];
    }
    
    return ['valid' => true, 'uniquenames' => $uniquenames, 'error' => ''];
}

/**
 * Extract sequences for all available types from BLAST database
 * 
 * Iterates through all sequence types and extracts for the given feature IDs
 * 
 * @param string $assembly_dir - Path to assembly directory
 * @param array $uniquenames - Feature IDs to extract
 * @param array $sequence_types - Available sequence type configurations (from site_config)
 * @return array - ['success' => bool, 'content' => [...], 'errors' => []]
 */
function extractSequencesForAllTypes($assembly_dir, $uniquenames, $sequence_types) {
    $displayed_content = [];
    $errors = [];
    
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
            } else {
                $errors[] = "Failed to extract $seq_type sequences";
            }
        }
    }
    
    return [
        'success' => !empty($displayed_content),
        'content' => $displayed_content,
        'errors' => $errors
    ];
}

/**
 * Format extracted sequences for display component
 * 
 * Converts extracted content into format expected by sequences_display.php
 * 
 * @param array $displayed_content - Extracted sequences by type
 * @param array $sequence_types - Type configurations (from site_config)
 * @return array - Formatted for sequences_display.php inclusion
 */
function formatSequenceResults($displayed_content, $sequence_types) {
    $available_sequences = [];
    
    foreach ($displayed_content as $seq_type => $content) {
        $available_sequences[$seq_type] = [
            'label' => $sequence_types[$seq_type]['label'] ?? ucfirst($seq_type),
            'sequences' => [$content]  // Wrap in array as sequences_display expects
        ];
    }
    
    return $available_sequences;
}

/**
 * Send file download response and exit
 * 
 * Sets appropriate headers and outputs file content
 * Should be called before any HTML output
 * 
 * @param string $content - File content to download
 * @param string $sequence_type - Type of sequence (for filename)
 * @param string $file_format - Format (fasta or txt)
 */
function sendFileDownload($content, $sequence_type, $file_format = 'fasta') {
    $ext = ($file_format === 'txt') ? 'txt' : 'fasta';
    $filename = "sequences_{$sequence_type}_" . date("Y-m-d_His") . ".{$ext}";
    
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename={$filename}");
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

/**
 * Build organism-filtered list of accessible assembly sources
 * 
 * Filters nested sources array by organism list
 * 
 * @param array $sources_by_group - Nested array from getAccessibleAssemblies()
 * @param array $filter_organisms - Optional organism filter list
 * @return array - Nested array [group][organism][...assemblies]
 */
function buildFilteredSourcesList($sources_by_group, $filter_organisms = []) {
    $filtered = [];
    
    foreach ($sources_by_group as $group_name => $organisms) {
        foreach ($organisms as $organism => $assemblies) {
            // Skip if organism filter is set and this organism is not in it
            if (!empty($filter_organisms) && !in_array($organism, $filter_organisms)) {
                continue;
            }
            
            if (!isset($filtered[$group_name])) {
                $filtered[$group_name] = [];
            }
            $filtered[$group_name][$organism] = $assemblies;
        }
    }
    
    return $filtered;
}

/**
 * Flatten nested sources array for sequential processing
 * 
 * Converts nested [group][organism][...sources] structure to flat list
 * Useful for iterating all sources without nested loops
 * 
 * @param array $sources_by_group - Nested array from getAccessibleAssemblies()
 * @return array - Flat list of all sources
 */
function flattenSourcesList($sources_by_group) {
    $accessible_sources = [];
    
    foreach ($sources_by_group as $group => $organisms) {
        foreach ($organisms as $org => $assemblies) {
            $accessible_sources = array_merge($accessible_sources, $assemblies);
        }
    }
    
    return $accessible_sources;
}

/**
 * Assign Bootstrap colors to groups for consistent UI display
 * 
 * Uses Bootstrap color palette cyclically across groups
 * Same group always gets same color (idempotent)
 * 
 * @param array $sources_by_group - Groups to assign colors to
 * @return array - [group_name => bootstrap_color]
 */
function assignGroupColors($sources_by_group) {
    $group_colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
    $group_color_map = [];
    
    foreach ($sources_by_group as $group_name => $organisms) {
        if (!isset($group_color_map[$group_name])) {
            $group_color_map[$group_name] = $group_colors[count($group_color_map) % count($group_colors)];
        }
    }
    
    return $group_color_map;
}

/**
 * Get available sequence types from all accessible sources
 * 
 * Scans assembly directories to determine which sequence types are available
 * Useful for populating UI dropdowns/display options
 * 
 * @param array $accessible_sources - Flattened list of sources
 * @param array $sequence_types - Type configurations (from site_config)
 * @return array - [type => label] for types that have available files
 */
function getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types) {
    $available_types = [];
    
    foreach ($accessible_sources as $source) {
        foreach ($sequence_types as $seq_type => $config) {
            $files = glob($source['path'] . "/*{$config['pattern']}");
            if (!empty($files)) {
                $available_types[$seq_type] = $config['label'];
            }
        }
    }
    
    return $available_types;
}

?>
