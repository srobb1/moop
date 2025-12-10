<?php
/**
 * Extract/Search Common Helper Functions
 * 
 * Consolidates repeated logic from:
 * - tools/*.php (retrieve_sequences.php, retrieve_selected_sequences.php)
 * - tools/*.php (multi_organism_search.php, annotation_search_ajax.php)
 * 
 * Provides unified handling for:
 * - Multi-organism parameter parsing (multiple input formats)
 * - Context parameter extraction
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
 * Extract context parameters from request
 * 
 * Checks explicit context_* fields first (highest priority), then regular fields as fallback
 * 
 * @return array - ['organism' => '', 'assembly' => '', 'group' => '', 'display_name' => '', 'context_page' => '']
 */
function parseContextParameters() {
    return [
        'organism' => trim($_GET['context_organism'] ?? $_POST['context_organism'] ?? $_GET['organism'] ?? $_POST['organism'] ?? ''),
        'assembly' => trim($_GET['context_assembly'] ?? $_POST['context_assembly'] ?? $_GET['assembly'] ?? $_POST['assembly'] ?? ''),
        'group' => trim($_GET['context_group'] ?? $_POST['context_group'] ?? $_GET['group'] ?? $_POST['group'] ?? ''),
        'display_name' => trim($_GET['display_name'] ?? $_POST['display_name'] ?? ''),
        'context_page' => trim($_GET['context_page'] ?? $_POST['context_page'] ?? '')
    ];
}

/**
 * Parse and validate feature IDs from user input
 * 
 * Handles both comma and newline separated formats
 * Detects range patterns (ID:1..10, ID:1-10, ID 1..10, ID 1-10) and returns them separately
 * 
 * @param string $uniquenames_string - Comma or newline separated IDs with optional ranges
 * @return array - ['valid' => bool, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => bool, 'error' => '']
 */
function parseFeatureIds($uniquenames_string) {
    $uniquenames = [];
    $ranges = [];
    
    if (empty($uniquenames_string)) {
        return ['valid' => false, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => false, 'error' => 'No feature IDs provided'];
    }
    
    // Handle both comma and newline separated formats
    $entries = array_filter(array_map('trim', 
        preg_split('/[\n,]+/', $uniquenames_string)
    ));
    
    if (empty($entries)) {
        return ['valid' => false, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => false, 'error' => 'No valid feature IDs found'];
    }
    
    // Process each entry to detect range patterns
    // Patterns: "ID:1..10", "ID:1-10", "ID 1..10", "ID 1-10"
    foreach ($entries as $entry) {
        // Check for range patterns
        if (preg_match('/^(.+?)[\s:]+(\d+)[.\-]\.?(\d+)$/', $entry, $matches)) {
            $id = trim($matches[1]);
            $start = $matches[2];
            $end = $matches[3];
            
            // Validate range (start should be <= end)
            if ((int)$start <= (int)$end) {
                // Store in format expected by blastdbcmd: "ID:start-end"
                $ranges[] = "$id:$start-$end";
                $uniquenames[] = $id;
            } else {
                // Invalid range, skip or treat as error
                continue;
            }
        } else {
            // Regular ID without range
            $uniquenames[] = $entry;
        }
    }
    
    $uniquenames = array_unique($uniquenames);
    
    if (empty($uniquenames)) {
        return ['valid' => false, 'uniquenames' => [], 'ranges' => [], 'has_ranges' => false, 'error' => 'No valid feature IDs found'];
    }
    
    return [
        'valid' => true, 
        'uniquenames' => array_values($uniquenames), 
        'ranges' => $ranges,
        'has_ranges' => !empty($ranges),
        'error' => ''
    ];
}

/**
 * Extract sequences for all available types from BLAST database
 * 
 * Iterates through all sequence types and extracts for the given feature IDs
 * Supports range notation for subsequence extraction
 * 
 * @param string $assembly_dir - Path to assembly directory
 * @param array $uniquenames - Feature IDs to extract
 * @param array $sequence_types - Available sequence type configurations (from site_config)
 * @param string $organism - Organism name (for parent/child database lookup)
 * @param string $assembly - Assembly name (for parent/child database lookup)
 * @param array $ranges - Optional array of range strings ("ID:start-end") for subsequence extraction
 * @return array - ['success' => bool, 'content' => [...], 'errors' => []]
 */
function extractSequencesForAllTypes($assembly_dir, $uniquenames, $sequence_types, $organism = '', $assembly = '', $ranges = [], $original_input_ids = [], $parent_to_children = []) {
    $displayed_content = [];
    $errors = [];
    
    foreach ($sequence_types as $seq_type => $config) {
        $files = glob("$assembly_dir/*{$config['pattern']}");
        
        if (!empty($files)) {
            $fasta_file = $files[0];
            $extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames, $organism, $assembly, $ranges, $original_input_ids, $parent_to_children);
            
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
    
    // Only report "no sequences found" errors if we got no content at all
    if (empty($displayed_content)) {
        foreach ($sequence_types as $seq_type => $config) {
            $files = glob("$assembly_dir/*{$config['pattern']}");
            if (!empty($files)) {
                $fasta_file = $files[0];
                $extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames, $organism, $assembly, $ranges, $original_input_ids, $parent_to_children);
                if (!empty($extract_result['error'])) {
                    $errors[] = $extract_result['error'];
                    break;
                }
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
        
        // Parse FASTA content into individual sequences by ID
        $sequences = [];
        if (!empty($content)) {
            $current_id = null;
            $current_seq = [];
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (strpos($line, '>') === 0) {
                    // Header line
                    if (!is_null($current_id)) {
                        // Store previous sequence with full FASTA format (including >)
                        $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
                    }
                    // Extract ID from header (remove leading '>')
                    $current_id = substr($line, 1);
                    $current_seq = [];
                } else if (!empty($line)) {
                    // Sequence line (skip empty lines)
                    $current_seq[] = $line;
                }
            }
            
            // Store last sequence with full FASTA format
            if (!is_null($current_id)) {
                $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
            }
        }
        
        foreach ($sequences as $id => $seq_content) {
            $first_line = explode("\n", $seq_content)[0];
        }
        
        $available_sequences[$seq_type] = [
            'label' => $sequence_types[$seq_type]['label'] ?? ucfirst($seq_type),
            'sequences' => $sequences
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

/**
 * Handle sequence download request
 * 
 * Checks for download flag and sends file if conditions are met.
 * Works with both array-based sequences (from extractSequencesFromFasta)
 * and string-based sequences (from extractSequencesForAllTypes).
 * 
 * @param bool $download_flag - Whether download was requested
 * @param string $sequence_type - The sequence type to download
 * @param array|string $sequence_data - Either array of sequences or a string
 * @return bool - True if download was sent and script exited, false otherwise
 */
function handleSequenceDownload($download_flag, $sequence_type, $sequence_data) {
    if (!$download_flag || empty($sequence_type)) {
        return false;
    }
    
    // Handle both formats: array (from extractSequencesFromFasta) and string (from extractSequencesForAllTypes)
    $fasta_content = '';
    if (is_array($sequence_data)) {
        // Array format: feature_id => content
        if (!empty($sequence_data)) {
            $fasta_content = implode("\n", $sequence_data);
        }
    } else if (is_string($sequence_data)) {
        // String format: already combined FASTA content
        $fasta_content = $sequence_data;
    }
    
    if (!empty($fasta_content)) {
        $file_format = $_POST['file_format'] ?? 'fasta';
        sendFileDownload($fasta_content, $sequence_type, $file_format);
        exit;
    }
    
    return false;
}

/**
 * Determine selected source (organism/assembly) based on URL/POST parameters
 * 
 * Selection priority (highest to lowest):
 * 1. Explicit assembly parameter
 * 2. Explicit organism parameter
 * 3. Group parameter (select first organism from group)
 * 4. Organisms filter list (select first organism)
 * 
 * @param array $context - Context parameters [organism, assembly, group, display_name, context_page]
 * @param array $filter_organisms - Pre-filtered list of organisms (from organisms[] or group)
 * @param array $accessible_sources - Flat list of all accessible sources
 * @param string $selected_organism - Optional pre-selected organism (input/output)
 * @param string $selected_assembly_accession - Optional pre-selected assembly (input/output)
 * @return array - ['selected_source' => 'org|assembly', 'selected_organism' => 'org', 'selected_assembly_accession' => 'accession', 'selected_assembly_name' => 'name']
 */
function determineSelectedSource($context, $filter_organisms, $accessible_sources, $selected_organism = '', $selected_assembly_accession = '') {
    $result = [
        'selected_source' => '',
        'selected_organism' => $selected_organism,
        'selected_assembly_accession' => $selected_assembly_accession,
        'selected_assembly_name' => ''
    ];
    
    // Case 1: Both organism and assembly explicitly specified
    if (!empty($selected_organism) && !empty($selected_assembly_accession)) {
        $result['selected_source'] = $selected_organism . '|' . $selected_assembly_accession;
        return $result;
    }
    
    // Case 2: Only organism specified (select its first assembly)
    if (!empty($selected_organism)) {
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $selected_organism) {
                $result['selected_source'] = $selected_organism . '|' . $source['assembly'];
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }
    
    // Case 3: Group specified (select first organism from group, then its first assembly)
    if (!empty($context['group']) && !empty($filter_organisms)) {
        $first_organism = reset($filter_organisms);
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $first_organism && in_array($context['group'], $source['groups'] ?? [])) {
                $result['selected_source'] = $first_organism . '|' . $source['assembly'];
                $result['selected_organism'] = $first_organism;
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }
    
    // Case 4: Organisms filter list specified (select first organism, then its first assembly)
    if (!empty($filter_organisms)) {
        $first_organism = reset($filter_organisms);
        foreach ($accessible_sources as $source) {
            if ($source['organism'] === $first_organism) {
                $result['selected_source'] = $first_organism . '|' . $source['assembly'];
                $result['selected_organism'] = $first_organism;
                $result['selected_assembly_accession'] = $source['assembly'];
                $result['selected_assembly_name'] = $source['genome_name'] ?? $source['assembly'];
                return $result;
            }
        }
    }
    
    // No selection could be determined
    return $result;
}

?>
