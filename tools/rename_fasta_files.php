<?php
/**
 * FASTA File Renaming Utility
 * 
 * This script renames FASTA files from the old naming convention to the new pattern-only format.
 * Old format: Organism_name.GCA_xxxxx.x_genome_description.cds.nt.fa
 * New format: cds.nt.fa
 * 
 * Usage: php rename_fasta_files.php [--dry-run] [--organism=name]
 */

include_once __DIR__ . '/../site_config.php';

// Parse command line arguments
$dry_run = in_array('--dry-run', $argv);
$organism_filter = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--organism=') === 0) {
        $organism_filter = substr($arg, strlen('--organism='));
    }
}

// Get patterns from config
$patterns = [];
foreach ($sequence_types as $type => $config) {
    $patterns[] = $config['pattern'];
}

$renamed_count = 0;
$skipped_count = 0;

// Walk through organisms directory
$organisms_dir = $organism_data;
$organisms = array_diff(scandir($organisms_dir), ['.', '..']);

foreach ($organisms as $organism) {
    if ($organism_filter && $organism !== $organism_filter) {
        continue;
    }
    
    $organism_path = "$organisms_dir/$organism";
    if (!is_dir($organism_path)) {
        continue;
    }
    
    // Get assemblies
    $assemblies = array_diff(scandir($organism_path), ['.', '..', 'fasta_files', 'organism.json']);
    
    foreach ($assemblies as $assembly) {
        $assembly_path = "$organism_path/$assembly";
        if (!is_dir($assembly_path)) {
            continue;
        }
        
        // Find files to rename
        $files = array_diff(scandir($assembly_path), ['.', '..']);
        
        foreach ($files as $file) {
            $file_path = "$assembly_path/$file";
            if (is_file($file_path) && (substr($file, -3) === '.fa' || substr($file, -6) === '.fasta')) {
                // Check if it matches any pattern
                foreach ($patterns as $pattern) {
                    if (strpos($file, $pattern) !== false) {
                        // File matches a pattern - check if it needs renaming
                        if ($file !== $pattern) {
                            $new_name = $pattern;
                            $new_path = "$assembly_path/$new_name";
                            
                            // Check for conflicts
                            if (file_exists($new_path) && $new_path !== $file_path) {
                                echo "SKIP: $organism/$assembly/$file -> $new_name (target already exists)\n";
                                $skipped_count++;
                            } else {
                                if (!$dry_run) {
                                    if (rename($file_path, $new_path)) {
                                        echo "RENAME: $organism/$assembly/$file -> $new_name\n";
                                        $renamed_count++;
                                    } else {
                                        echo "ERROR: Failed to rename $organism/$assembly/$file\n";
                                    }
                                } else {
                                    echo "WOULD RENAME: $organism/$assembly/$file -> $new_name\n";
                                    $renamed_count++;
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }
    }
}

echo "\n";
if ($dry_run) {
    echo "DRY RUN: Would have renamed $renamed_count files, skipped $skipped_count\n";
} else {
    echo "Renamed $renamed_count files, skipped $skipped_count\n";
}
echo "Done!\n";
?>
