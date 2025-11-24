<?php
/**
 * Sequences Display Component for MOOP
 * Displays sequences for parent feature and all child features grouped by sequence type
 * 
 * Expected variables from parent_display.php:
 * - $organism_name: Name of the organism
 * - $organism_data: Path to organism data directory
 * - $gene_name: Comma-separated list of feature uniquenames (parent and children)
 * - $sequence_types: Array of sequence type configurations (from site_config.php)
 * - $admin_email: Administrator email (from site_config.php)
 * 
 * Optional variables for download functionality:
 * - $enable_downloads: (bool) Set to true to show download buttons
 * - $assembly_name: Name of assembly (required if enable_downloads is true)
 * - $download_script_url: URL to download script (default: for parent_display context)
 */

// Ensure config is initialized (should be from parent_display.php, but double-check)
if (!class_exists('ConfigManager')) {
    include_once __DIR__ . '/../includes/config_init.php';
}

// Get config if not already set by parent
if (!isset($sequence_types)) {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    $sequence_types = $config->getSequenceTypes();
    $admin_email = $config->getString('admin_email');
}

// Include error logging (logError function)
include_once __DIR__ . '/../lib/moop_functions.php';

// Initialize download settings
$enable_downloads = $enable_downloads ?? false;
$assembly_name = $assembly_name ?? '';
$download_script_url = $download_script_url ?? '';

// Initialize error tracking
$sequence_errors = [];
$organism_dir = null;
$assembly_dir = null;
// Only initialize available_sequences if not already provided
if (!isset($available_sequences)) {
    $available_sequences = [];
}

// Validate required parameters
if (empty($organism_name)) {
    $sequence_errors[] = 'Organism name not specified';
} elseif (empty($gene_name)) {
    // No sequences requested - this is not an error, just no display needed
    $gene_name = '';
} else {
    // Get the organism directory path
    $organism_dir = "$organism_data/$organism_name";
    
    // Validate organism directory exists
    if (!is_dir($organism_dir)) {
        $sequence_errors[] = "Organism directory not found: $organism_name";
    } else {
        // Find the assembly directory
        $dirs = array_diff(scandir($organism_dir), ['.', '..']);
        $assembly_found = false;
        
        foreach ($dirs as $item) {
            $full_path = "$organism_dir/$item";
            if (is_dir($full_path) && !in_array(basename($full_path), ['fasta_files'])) {
                $assembly_dir = $full_path;
                $assembly_found = true;
                break;
            }
        }
        
        if (!$assembly_found) {
            $sequence_errors[] = "Assembly directory not found in: $organism_name";
        } elseif (!is_dir($assembly_dir)) {
            $sequence_errors[] = "Assembly directory is not readable: " . basename($assembly_dir);
        } else {
            // Look for FASTA files
            $fasta_files_found = false;
            foreach ($sequence_types as $seq_type => $config) {
                $files = glob("$assembly_dir/*{$config['pattern']}");
                
                if (!empty($files)) {
                    $fasta_files_found = true;
                    $fasta_file = $files[0];
                    $available_sequences[$seq_type] = [
                        'file' => $fasta_file,
                        'label' => $config['label'],
                        'sequences' => []
                    ];
                }
            }
            
            if (!$fasta_files_found) {
                $sequence_errors[] = "No FASTA sequence files found for: $organism_name";
            }
        }
    }
}

// If we have sequence files available, try to extract sequences
if (empty($sequence_errors) && !empty($available_sequences) && !empty($gene_name)) {
    $feature_ids = array_map('trim', explode(',', $gene_name));
    $extraction_errors = [];
    
    foreach ($available_sequences as $seq_type => $seq_data) {
        $fasta_file = $available_sequences[$seq_type]['file'];
        
        // Extract sequences and track errors
        $sequences = extractSequencesFromFasta($fasta_file, $feature_ids, $seq_type, $extraction_errors);
        $available_sequences[$seq_type]['sequences'] = $sequences;
    }
    
    // Collect extraction errors
    if (!empty($extraction_errors)) {
        $sequence_errors = array_merge($sequence_errors, $extraction_errors);
    }
}

// Display error messages if any occurred
if (!empty($sequence_errors)) {
    // Log errors for admin review
    logError(
        implode('; ', $sequence_errors),
        "sequences_display",
        [
            'organism' => $organism_name,
            'features' => $gene_name
        ]
    );
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning">
            <strong class="text-dark">⚠️ Sequence Display Error</strong>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">We encountered issues loading the sequence data:</p>
            <ul class="mb-3">
                <?php foreach ($sequence_errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="alert alert-info mb-0">
                <strong>What to do:</strong>
                <p>Please contact the database administrator with the following information:</p>
                <ul class="mb-0">
                    <li><strong>Organism:</strong> <?= htmlspecialchars($organism_name) ?></li>
                    <li><strong>Feature:</strong> <?= htmlspecialchars($gene_name) ?></li>
                    <li><strong>Error details:</strong> <?= htmlspecialchars(implode('; ', $sequence_errors)) ?></li>
                    <li><strong>Page:</strong> <code><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'unknown') ?></code></li>
                </ul>
                <p class="mt-2 mb-0"><a href="mailto:<?= htmlspecialchars($admin_email ?? 'admin@example.com') ?>">Email Administrator</a></p>
            </div>
        </div>
    </div>
    <?php
} elseif (!empty($available_sequences) && array_reduce($available_sequences, function($carry, $item) {
    return $carry || !empty($item['sequences']);
}, false)) {
    // Display Sequences Section - only if no errors and sequences exist
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="collapse-section d-flex align-items-center" data-toggle="collapse" data-target="#sequencesSection" aria-expanded="true">
                <i class="fas fa-minus toggle-icon text-primary"></i>
                <strong class="ms-2">Sequences</strong>
            </div>
            <a href="#" class="btn btn-sm btn-outline-secondary" title="Back to top">
                <i class="fas fa-arrow-up"></i> Back to Top
            </a>
        </div>
        <div id="sequencesSection" class="collapse show">
            <div class="card-body">
                <?php
                $seq_count = 0;
                foreach ($sequence_types as $seq_type => $config) {
                    $seq_count++;
                    $seq_data = $available_sequences[$seq_type] ?? [];
                    $sequences = $seq_data['sequences'] ?? [];
                    
                    if (!empty($sequences)) {
                        echo '<div class="card annotation-card border-info mb-3">';
                        echo '  <div class="card-header card-header-light-info">';
                        echo '    <div class="collapse-section" data-toggle="collapse" data-target="#seq_' . $seq_type . '" aria-expanded="true">';
                        echo '      <i class="fas fa-minus toggle-icon text-info"></i>';
                        echo '      <strong class="ms-2 text-dark">';
                        // Map sequence types to badge colors
                        $badge_class = match($seq_type) {
                            'transcript' => 'bg-feature-mrna',
                            'protein' => 'bg-info',
                            'cds' => 'bg-success',
                            'genome' => 'bg-warning text-dark',
                            default => 'bg-secondary'
                        };
                        echo '        <span class="text-white px-2 py-1 rounded ' . $badge_class . '">' . htmlspecialchars($config['label']) . '</span>';
                        echo '        (' . count($sequences) . ' sequence' . (count($sequences) > 1 ? 's' : '') . ')';
                        echo '      </strong>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '  <div id="seq_' . $seq_type . '" class="collapse show">';
                        echo '    <div class="card-body">';
                        
                        // Concatenate all sequences for this type
                        $concatenated_sequences = implode("\n\n", $sequences);
                        
                        echo '      <div class="card bg-light">';
                        echo '        <div class="card-body copyable font-monospace-small cursor-pointer preserve-whitespace">';
                        echo htmlspecialchars($concatenated_sequences);
                        echo '        </div>';
                        echo '      </div>';
                        
                        // Add download button if enabled
                        if ($enable_downloads && !empty($assembly_name) && !empty($organism_name) && !empty($gene_name)) {
                            echo '      <div class="margin-top">';
                            echo '        <form method="POST" class="display-inline">';
                            echo '          <input type="hidden" name="organism" value="' . htmlspecialchars($organism_name) . '">';
                            echo '          <input type="hidden" name="assembly" value="' . htmlspecialchars($assembly_name) . '">';
                            echo '          <input type="hidden" name="sequence_type" value="' . htmlspecialchars($seq_type) . '">';
                            echo '          <input type="hidden" name="uniquenames" value="' . htmlspecialchars($gene_name) . '">';
                            echo '          <input type="hidden" name="download_file" value="1">';
                            echo '          <button type="submit" class="btn btn-sm btn-success">';
                            echo '            <i class="fa fa-download"></i> Download ' . htmlspecialchars($config['label']);
                            echo '          </button>';
                            echo '        </form>';
                            echo '      </div>';
                        }
                        
                        echo '    </div>';
                        echo '  </div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php
/**
 * Extract sequences from a FASTA file for specific feature IDs
 * 
 * @param string $fasta_file Path to FASTA file
 * @param array $feature_ids Array of feature IDs to extract
 * @return array Associative array with feature_id => sequence content
 */
function extractSequencesFromFasta($fasta_file, $feature_ids, $seq_type, &$errors) {
    $sequences = [];
    
    // Validate inputs
    if (empty($fasta_file)) {
        $errors[] = "FASTA file path is empty for $seq_type sequences";
        return $sequences;
    }
    
    if (empty($feature_ids)) {
        $errors[] = "No feature IDs provided to extract";
        return $sequences;
    }
    
    // Check if file exists
    if (!file_exists($fasta_file)) {
        $errors[] = "FASTA file not found for $seq_type: " . basename($fasta_file);
        return $sequences;
    }
    
    // Build list of IDs to search, including variants for parent/child relationships
    $search_ids = [];
    foreach ($feature_ids as $id) {
        $search_ids[] = $id;
        // Also try with .1 suffix if not already present (for parent->child relationships)
        if (substr($id, -2) !== '.1') {
            $search_ids[] = $id . '.1';
        }
    }
    
    // Use blastdbcmd to extract sequences - it accepts comma-separated IDs
    $ids_string = implode(',', $search_ids);
    $cmd = "blastdbcmd -db " . escapeshellarg($fasta_file) . " -entry " . escapeshellarg($ids_string) . " 2>/dev/null";
    $output = [];
    $return_var = 0;
    @exec($cmd, $output, $return_var);
    
    // Check if blastdbcmd executed
    if ($return_var > 1) {
        // Return code 1 is expected when some IDs don't exist, but >1 is an error
        $errors[] = "Error extracting $seq_type sequences (exit code: $return_var). Ensure blastdbcmd is installed and FASTA files are formatted correctly.";
        return $sequences;
    }
    
    // Check if we got any output
    // If empty, it just means these IDs don't exist in this file type (e.g., gene IDs won't be in genome.fa)
    // Return empty sequences gracefully - not an error
    if (empty($output)) {
        return $sequences;
    }
    
    // Parse FASTA output into individual sequences by feature ID
    $current_id = null;
    $current_seq = [];
    
    foreach ($output as $line) {
        if (strpos($line, '>') === 0) {
            // Header line
            if (!is_null($current_id)) {
                // Store previous sequence with full FASTA format (including >)
                $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
            }
            // Extract ID from header (remove leading '>')
            $current_id = substr($line, 1);
            $current_seq = [];
        } else {
            // Sequence line
            $current_seq[] = $line;
        }
    }
    
    // Store last sequence with full FASTA format
    if (!is_null($current_id)) {
        $sequences[$current_id] = implode("\n", array_merge([">" . $current_id], $current_seq));
    }
    
    return $sequences;
}
?>


<script src="/<?= $site ?>/js/core/copy-to-clipboard.js"></script>
