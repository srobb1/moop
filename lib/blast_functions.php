<?php
/**
 * BLAST Tool Functions
 * Centralized functions for BLAST operations across the application
 * Used by BLAST interface, FASTA extract, and other tools
 */

// Include parent_functions for parent->child lookups
require_once __DIR__ . '/parent_functions.php';

/**
 * Get list of available BLAST databases for an assembly
 * Looks for FASTA files matching configured sequence type patterns
 * (protein.aa.fa, cds.nt.fa, transcript.nt.fa)
 * 
 * @param string $assembly_path Path to assembly directory
 * @return array Array of BLAST databases with type and path
 *   Format: [
 *     ['name' => 'protein', 'path' => '/path/to/protein.aa.fa', 'type' => 'protein'],
 *     ['name' => 'cds', 'path' => '/path/to/cds.nt.fa', 'type' => 'nucleotide']
 *   ]
 */
function getBlastDatabases($assembly_path) {
    global $sequence_types;
    $databases = [];
    
    if (!is_dir($assembly_path)) {
        return $databases;
    }
    
    // Map sequence types to database info
    $type_mapping = [
        'protein' => ['name' => 'Protein', 'blast_type' => 'protein'],
        'cds' => ['name' => 'CDS', 'blast_type' => 'nucleotide'],
        'transcript' => ['name' => 'Transcript', 'blast_type' => 'nucleotide'],
    ];
    
    // Check for each configured sequence type
    if (!empty($sequence_types)) {
        foreach ($sequence_types as $seq_type => $config) {
            $pattern = $config['pattern'] ?? '';
            if (empty($pattern)) {
                continue;
            }
            
            $file_path = "$assembly_path/$pattern";
            
            // Check if file exists
            if (file_exists($file_path)) {
                $type_info = $type_mapping[$seq_type] ?? ['name' => ucfirst($seq_type), 'blast_type' => 'nucleotide'];
                
                $databases[] = [
                    'name' => $type_info['name'],
                    'path' => $file_path,
                    'type' => $type_info['blast_type']
                ];
            }
        }
    }
    
    return $databases;
}

/**
 * Filter BLAST databases by program type
 * Returns only databases compatible with the selected BLAST program
 * 
 * @param array $databases Array of databases from getBlastDatabases()
 * @param string $blast_program BLAST program: blastn, blastp, blastx, tblastn, tblastx
 * @return array Filtered array of compatible databases
 */
function filterDatabasesByProgram($databases, $blast_program) {
    $filtered = [];
    
    // Determine which database types are compatible with the program
    $compatible_types = [];
    switch ($blast_program) {
        case 'blastn':
        case 'tblastn':
        case 'tblastx':
            $compatible_types = ['nucleotide'];
            break;
        case 'blastp':
        case 'blastx':
            $compatible_types = ['protein'];
            break;
        default:
            return $databases; // Unknown program, return all
    }
    
    foreach ($databases as $db) {
        if (in_array($db['type'], $compatible_types)) {
            $filtered[] = $db;
        }
    }
    
    return $filtered;
}

/**
 * Execute BLAST search
 * Runs BLAST command with outfmt 11 (ASN.1), then converts using blast_formatter
 * 
 * @param string $query_seq FASTA sequence to search
 * @param string $blast_db Path to BLAST database (without extension)
 * @param string $program BLAST program (blastn, blastp, blastx, etc.)
 * @param array $options Additional BLAST options (evalue, max_hits, matrix, etc.)
 * @return array Result array with 'success', 'output', 'error', and 'stderr' keys
 */
function executeBlastSearch($query_seq, $blast_db, $program, $options = []) {
    $result = [
        'success' => false,
        'output' => '',
        'error' => '',
        'stderr' => ''
    ];
    
    // Validate inputs
    if (empty($query_seq) || empty($blast_db) || empty($program)) {
        $result['error'] = 'Missing required parameters for BLAST search';
        return $result;
    }
    
    // Verify database exists - check for any of the BLAST index files
    $has_index = false;
    if (file_exists("$blast_db.nhr") || file_exists("$blast_db.phr") || 
        file_exists("$blast_db.ndb") || file_exists("$blast_db.pdb")) {
        $has_index = true;
    }
    
    if (!$has_index) {
        $result['error'] = 'BLAST database not found: ' . basename($blast_db);
        return $result;
    }
    
    // Set default options
    $evalue = $options['evalue'] ?? '1e-3';
    $max_hits = (int)($options['max_hits'] ?? 10);
    $matrix = $options['matrix'] ?? 'BLOSUM62';
    $filter = $options['filter'] ? 'yes' : 'no';
    $task = $options['task'] ?? '';
    
    // Create temporary directory for ASN.1 archive output
    $temp_dir = sys_get_temp_dir();
    $archive_file = tempnam($temp_dir, 'blast_');
    
    if ($archive_file === false) {
        $result['error'] = 'Failed to create temporary file for BLAST output';
        return $result;
    }
    
    // Build BLAST command with outfmt 11 (ASN.1)
    $cmd = [];
    $cmd[] = $program;
    // Use absolute path for database
    // For BLAST databases, we pass the base name and BLAST appends the extensions
    $db_dir = dirname($blast_db);
    $db_base = basename($blast_db);
    $db_path = realpath($db_dir);
    if (!$db_path) {
        unlink($archive_file);
        $result['error'] = 'Cannot resolve database path: ' . $blast_db;
        return $result;
    }
    $full_db_path = $db_path . '/' . $db_base;
    $cmd[] = '-db ' . escapeshellarg($full_db_path);
    $cmd[] = '-evalue ' . escapeshellarg($evalue);
    $cmd[] = '-num_descriptions ' . escapeshellarg($max_hits);
    $cmd[] = '-num_alignments ' . escapeshellarg($max_hits);
    $cmd[] = '-outfmt 11';
    $cmd[] = '-out ' . escapeshellarg($archive_file);
    
    // Add program-specific options
    if ($program === 'blastn') {
        $cmd[] = '-dust ' . escapeshellarg($filter);
    } elseif ($program === 'tblastn') {
        $cmd[] = '-seg ' . escapeshellarg($filter);
    } elseif (in_array($program, ['blastp', 'blastx', 'tblastx'])) {
        $cmd[] = '-seg ' . escapeshellarg($filter);
        $cmd[] = '-matrix ' . escapeshellarg($matrix);
    }
    
    // Add task if specified
    if (!empty($task)) {
        $cmd[] = '-task ' . escapeshellarg($task);
    }
    
    $command = 'printf ' . escapeshellarg($query_seq) . ' | ' . implode(' ', $cmd);
    
    // Execute BLAST with proc_open for better control
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];
    
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        unlink($archive_file);
        $result['error'] = 'Failed to execute BLAST command';
        return $result;
    }
    
    fclose($pipes[0]);
    $blast_stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $blast_stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $return_code = proc_close($process);
    
    if ($return_code !== 0) {
        unlink($archive_file);
        $result['error'] = 'BLAST execution failed with code ' . $return_code;
        $result['stderr'] = $blast_stderr;
        return $result;
    }
    
    // Convert ASN.1 archive to outfmt 5 (XML) using blast_formatter
    $xml_file = tempnam($temp_dir, 'blast_xml_');
    $formatter_cmd = 'blast_formatter -archive ' . escapeshellarg($archive_file) . 
                     ' -outfmt 5 -out ' . escapeshellarg($xml_file);
    
    $process = proc_open($formatter_cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        unlink($archive_file);
        $result['error'] = 'Failed to execute blast_formatter for XML';
        return $result;
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $return_code = proc_close($process);
    
    if ($return_code !== 0) {
        unlink($archive_file);
        unlink($xml_file);
        $result['error'] = 'blast_formatter XML conversion failed with code ' . $return_code;
        return $result;
    }
    
    // Read the XML output
    $output = file_get_contents($xml_file);
    
    // Now convert ASN.1 archive to outfmt 0 (Pairwise text) for download
    $pairwise_file = tempnam($temp_dir, 'blast_pairwise_');
    $formatter_cmd = 'blast_formatter -archive ' . escapeshellarg($archive_file) . 
                     ' -outfmt 0 -out ' . escapeshellarg($pairwise_file);
    
    $process = proc_open($formatter_cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        unlink($archive_file);
        unlink($xml_file);
        unlink($pairwise_file);
        $result['error'] = 'Failed to execute blast_formatter for pairwise';
        return $result;
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $return_code = proc_close($process);
    
    if ($return_code !== 0) {
        unlink($archive_file);
        unlink($xml_file);
        unlink($pairwise_file);
        $result['error'] = 'blast_formatter pairwise conversion failed with code ' . $return_code;
        return $result;
    }
    
    // Read the pairwise output
    $pairwise_output = file_get_contents($pairwise_file);
    
    // Clean up temporary files
    unlink($archive_file);
    unlink($xml_file);
    unlink($pairwise_file);
    
    $result['success'] = true;
    $result['output'] = $output;
    $result['pairwise'] = $pairwise_output;
    $result['stderr'] = $blast_stderr;
    
    return $result;
}

/**
 * Extract sequences from BLAST database using blastdbcmd
 * Used by fasta extract and download tools
 * Supports parent->child lookup from database
 * 
 * @param string $blast_db Path to BLAST database (without extension)
 * @param array $sequence_ids Array of sequence IDs to extract
 * @param string $organism Optional organism name for parent/child lookup
 * @param string $assembly Optional assembly name for parent/child lookup
 * @return array Result array with 'success', 'content', and 'error' keys
 */
function extractSequencesFromBlastDb($blast_db, $sequence_ids, $organism = '', $assembly = '', $ranges = [], $original_input_ids = [], $parent_to_children = []) {
    $result = [
        'success' => false,
        'content' => '',
        'error' => ''
    ];
    
    if (empty($sequence_ids) || !is_array($sequence_ids)) {
        $result['error'] = 'No sequence IDs provided';
        return $result;
    }
    
    if (!file_exists($blast_db . '.nhr') && !file_exists($blast_db . '.phr')) {
        $result['error'] = 'BLAST database not found';
        return $result;
    }
    
    // Check if $ranges is provided and not empty
    if (!empty($ranges)) {
        // Use batch mode with range file
        // Convert ranges from "ID:start-end" format to "ID start-end" format for blastdbcmd
        $batch_ranges = array_map(function($range) {
            // Convert "ID:start-end" or "ID:start-end" to "ID start-end"
            return preg_replace('/^([^:]+):(\d+-\d+)$/', '$1 $2', $range);
        }, $ranges);
        
        // Build batch entries: converted ranges + regular IDs as full sequences
        // Ranges apply to parent AND all children
        $ids_with_ranges = array_map(function($range) {
            return explode(':', $range)[0];
        }, $ranges);
        
        // For each ranged ID, find and add all its children with the same range
        $expanded_ranges = [];
        foreach ($batch_ranges as $range_entry) {
            $expanded_ranges[] = $range_entry; // Add the original range
            
            // Extract the ID and range portion from "ID start-end"
            if (preg_match('/^(.+?)\s+(\d+-\d+)$/', $range_entry, $matches)) {
                $parent_id = $matches[1];
                $range_portion = $matches[2];
                
                // Find all children of this parent in sequence_ids and add with same range
                foreach ($sequence_ids as $id) {
                    // Check if this ID is a child of parent_id (e.g., "ID.1", "ID.2")
                    if (preg_match('/^' . preg_quote($parent_id) . '\.\d+$/', $id)) {
                        $expanded_ranges[] = "$id $range_portion";
                    }
                }
            }
        }
        
        // Build batch file following the 4-step plan:
        // 1. Add all no-range input IDs (as full sequences)
        // 2. Add all children of input IDs with no ranges (as full sequences)
        // 3. Add all input IDs with ranges (with range formatting)
        // 4. Add all children of input IDs with ranges (with same ranges as parent)
        
        $batch_entries = [];
        
        // Separate original input IDs into those with and without ranges
        $input_ids_with_ranges = [];
        $input_ids_no_ranges = [];
        
        foreach ($original_input_ids as $id) {
            $has_range = false;
            foreach ($ranges as $range_entry) {
                $range_id = explode(':', $range_entry)[0];
                $range_id = explode(' ', $range_id)[0];
                if ($range_id === $id) {
                    $has_range = true;
                    break;
                }
            }
            if ($has_range) {
                $input_ids_with_ranges[] = $id;
            } else {
                $input_ids_no_ranges[] = $id;
            }
        }
        
        // Step 1: Add all no-range input IDs (full sequences)
        foreach ($input_ids_no_ranges as $id) {
            if (in_array($id, $sequence_ids)) {
                $batch_entries[] = $id;
            }
        }
        
        // Step 2: Add all children of input IDs with no ranges (full sequences)
        foreach ($input_ids_no_ranges as $parent_id) {
            if (isset($parent_to_children[$parent_id])) {
                foreach ($parent_to_children[$parent_id] as $child_id) {
                    if (in_array($child_id, $sequence_ids)) {
                        $batch_entries[] = $child_id;
                    }
                }
            }
        }
        
        // Step 3: Add all input IDs with ranges (formatted as "ID range")
        foreach ($input_ids_with_ranges as $id) {
            // Find the corresponding converted range in $batch_ranges
            foreach (array_keys($ranges) as $idx) {
                $range_id = explode(':', $ranges[$idx])[0];
                $range_id = explode(' ', $range_id)[0];
                if ($range_id === $id) {
                    // Use the converted batch_ranges entry (with space, not colon)
                    $batch_entries[] = $batch_ranges[$idx];
                    break;
                }
            }
        }
        
        // Step 4: Add all children of input IDs with ranges (with same ranges as parent)
        foreach ($input_ids_with_ranges as $parent_id) {
            if (isset($parent_to_children[$parent_id])) {
                // Find the range for this parent
                $parent_range = '';
                foreach ($ranges as $range_entry) {
                    $range_id = explode(':', $range_entry)[0];
                    $range_id = explode(' ', $range_id)[0];
                    if ($range_id === $parent_id) {
                        // Extract range portion
                        $parent_range = strpos($range_entry, ':') !== false ? 
                            explode(':', $range_entry)[1] : 
                            explode(' ', $range_entry)[1];
                        break;
                    }
                }
                
                // Add each child with same range
                if (!empty($parent_range)) {
                    foreach ($parent_to_children[$parent_id] as $child_id) {
                        if (in_array($child_id, $sequence_ids)) {
                            $batch_entries[] = "$child_id $parent_range";
                        }
                    }
                }
            }
        }
        
        $temp_file = tempnam(sys_get_temp_dir(), 'blastdb_');
        if ($temp_file === false) {
            $result['error'] = 'Failed to create temporary file for batch processing';
            return $result;
        }
        
        // Write batch entries to temporary file
        if (file_put_contents($temp_file, implode("\n", $batch_entries)) === false) {
            @unlink($temp_file);
            $result['error'] = 'Failed to write batch file for range extraction';
            return $result;
        }
        
        // Execute: blastdbcmd -db ... -entry_batch temp_file
        $cmd = "blastdbcmd -db " . escapeshellarg($blast_db) . " -entry_batch " . escapeshellarg($temp_file) . " 2>&1";
        
        $output = [];
        $return_var = 0;
        @exec($cmd, $output, $return_var);
        
        // Delete temp file after execution
        @unlink($temp_file);
        
        // Filter out blastdbcmd error messages (lines starting with "Error:")
        // BUT keep track of them for better error reporting
        $error_lines = array_filter($output, function($line) {
            return strpos(trim($line), 'Error:') === 0;
        });
        
        $output = array_filter($output, function($line) {
            return strpos(trim($line), 'Error:') !== 0;
        });
        
        // Check if blastdbcmd executed
        if ($return_var > 1) {
            $result['error'] = "Error extracting sequences (exit code: $return_var). Ensure blastdbcmd is installed and FASTA files are formatted correctly.";
            return $result;
        }
        
        // Check if we got any output
        if (empty($output)) {
            // If there were error messages about entries not found, it's likely an out-of-range coordinate
            if (!empty($error_lines)) {
                $result['error'] = 'One or more requested sequences or ranges were not found. This may be due to: (1) invalid sequence ID, (2) out-of-range coordinates, or (3) incorrectly formatted FASTA database. Please verify your input.';
            } else {
                $result['error'] = 'No sequences found for the requested IDs and ranges';
            }
            return $result;
        }
    } else {
        // No ranges - use current logic: Execute: blastdbcmd -db ... -entry IDs
        $ids_string = implode(',', $sequence_ids);
        $cmd = "blastdbcmd -db " . escapeshellarg($blast_db) . " -entry " . escapeshellarg($ids_string) . " 2>/dev/null";
        $output = [];
        $return_var = 0;
        @exec($cmd, $output, $return_var);
        
        // Filter out blastdbcmd error messages (lines starting with "Error:")
        $output = array_filter($output, function($line) {
            return strpos(trim($line), 'Error:') !== 0;
        });
        
        // Check if blastdbcmd executed
        if ($return_var > 1) {
            // Return code 1 is expected when some IDs don't exist, but >1 is an error
            $result['error'] = "Error extracting sequences (exit code: $return_var). Ensure blastdbcmd is installed and FASTA files are formatted correctly.";
            return $result;
        }
        
        // Check if we got any output
        if (empty($output)) {
            $result['error'] = 'No sequences found for the requested IDs';
            return $result;
        }
    }
    
    $result['success'] = true;
    $result['content'] = implode("\n", $output);
    
    return $result;
}

/**
 * Validate BLAST sequence input
 * Checks if input is valid FASTA format
 * 
 * @param string $sequence Raw sequence input (may or may not have FASTA header)
 * @return array Array with 'valid' bool and 'error' string
 */
function validateBlastSequence($sequence) {
    $sequence = trim($sequence);
    
    if (empty($sequence)) {
        return ['valid' => false, 'error' => 'Sequence is empty'];
    }
    
    // If sequence doesn't start with >, add a header
    if ($sequence[0] !== '>') {
        $sequence = ">query_sequence\n" . $sequence;
    }
    
    // Basic FASTA format validation
    $lines = explode("\n", $sequence);
    $in_header = true;
    $seq_count = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        if ($line[0] === '>') {
            if (!$in_header && $seq_count === 0) {
                return ['valid' => false, 'error' => 'Invalid FASTA format: sequence expected before header'];
            }
            $in_header = true;
            $seq_count = 0;
        } else {
            if ($in_header) {
                $in_header = false;
            }
            $seq_count += strlen($line);
        }
    }
    
    if ($seq_count === 0) {
        return ['valid' => false, 'error' => 'No sequence data found'];
    }
    
    return ['valid' => true, 'error' => ''];
}

/**
 * Validate BLAST index files for FASTA sequences
 * Checks if BLAST databases have their index files (.nhr, .nin, .nsq for nucleotide, .phr, .pin, .psq for protein)
 * @param string $assembly_path Path to assembly directory
 * @param array $sequence_types Configured sequence types
 * @return array Array with 'databases' containing status of each FASTA file
 */
function validateBlastIndexFiles($assembly_path, $sequence_types = []) {
    $result = [
        'databases' => [],
        'missing_count' => 0,
        'total_count' => 0
    ];
    
    if (!is_dir($assembly_path)) {
        return $result;
    }
    
    // Map sequence types to BLAST index extensions
    $type_mapping = [
        'protein' => ['name' => 'Protein', 'extensions' => ['phr', 'pin', 'psq']],
        'cds' => ['name' => 'CDS', 'extensions' => ['nhr', 'nin', 'nsq']],
        'transcript' => ['name' => 'Transcript', 'extensions' => ['nhr', 'nin', 'nsq']],
    ];
    
    // Check for each configured sequence type
    if (!empty($sequence_types)) {
        foreach ($sequence_types as $seq_type => $config) {
            $pattern = $config['pattern'] ?? '';
            if (empty($pattern)) {
                continue;
            }
            
            $file_path = "$assembly_path/$pattern";
            
            // Only check if FASTA file exists
            if (!file_exists($file_path)) {
                continue;
            }
            
            $result['total_count']++;
            $type_info = $type_mapping[$seq_type] ?? null;
            
            // If not in mapping, detect from pattern
            if (!$type_info) {
                $is_protein = strpos($pattern, 'protein') !== false;
                $extensions = $is_protein ? ['phr', 'pin', 'psq'] : ['nhr', 'nin', 'nsq'];
                $type_info = ['name' => ucfirst($seq_type), 'extensions' => $extensions];
            }
            
            $db_entry = [
                'type' => $seq_type,
                'name' => $type_info['name'],
                'fasta' => basename($file_path),
                'fasta_path' => $file_path,
                'has_indexes' => true,
                'missing_indexes' => []
            ];
            
            // Check for all required index files
            foreach ($type_info['extensions'] as $ext) {
                $index_file = "$file_path.$ext";
                if (!file_exists($index_file)) {
                    $db_entry['has_indexes'] = false;
                    $db_entry['missing_indexes'][] = $ext;
                }
            }
            
            if (!$db_entry['has_indexes']) {
                $result['missing_count']++;
            }
            
            $result['databases'][] = $db_entry;
        }
    }
    
    return $result;
}

/**
 * Check if assembly directory and FASTA files are readable/writable by web server
 * This is more comprehensive than checkAssemblyWritableForBlast
 * 
 * @param string $assembly_path Path to assembly directory
 * @param array $fasta_files Array of FASTA filenames to check readability
 * @return array Array with 'writable' boolean and 'message' string
 */
function checkAssemblyCanGenerateBlast($assembly_path, $fasta_files = []) {
    $result = [
        'writable' => false,
        'message' => '',
        'can_execute' => false
    ];
    
    if (!is_dir($assembly_path)) {
        $result['message'] = 'Assembly directory does not exist';
        return $result;
    }
    
    // Check if directory is readable and writable
    if (!is_readable($assembly_path)) {
        $result['message'] = 'Assembly directory is not readable by web server';
        return $result;
    }
    
    if (!is_writable($assembly_path)) {
        $result['message'] = 'Assembly directory is not writable by web server (FASTA index files cannot be created)';
        return $result;
    }
    
    // Check if FASTA files are readable by web server
    foreach ($fasta_files as $fasta_file) {
        $fasta_path = $assembly_path . '/' . $fasta_file;
        if (file_exists($fasta_path) && !is_readable($fasta_path)) {
            $result['message'] = 'FASTA file is not readable by web server: ' . htmlspecialchars($fasta_file);
            return $result;
        }
    }
    
    // Check if we can execute commands (shell_exec or exec available)
    if (!function_exists('shell_exec') && !function_exists('exec')) {
        $result['message'] = 'PHP shell execution functions are disabled on this server';
        return $result;
    }
    
    $result['writable'] = true;
    $result['can_execute'] = true;
    $result['message'] = 'Ready to generate BLAST indexes';
    
    return $result;
}

/**
 * Check if assembly directory is writable by web server for running makeblastdb
 * 
 * @param string $assembly_path Path to assembly directory
 * @return array Array with 'writable' boolean and 'message' string
 */
function checkAssemblyWritableForBlast($assembly_path) {
    $result = [
        'writable' => false,
        'message' => '',
        'can_execute' => false
    ];
    
    if (!is_dir($assembly_path)) {
        $result['message'] = 'Assembly directory does not exist';
        return $result;
    }
    
    // Check if directory is readable and writable
    if (!is_readable($assembly_path)) {
        $result['message'] = 'Assembly directory is not readable by web server';
        return $result;
    }
    
    if (!is_writable($assembly_path)) {
        $result['message'] = 'Assembly directory is not writable by web server (FASTA index files cannot be created)';
        return $result;
    }
    
    // Check if we can execute commands (shell_exec or exec available)
    if (!function_exists('shell_exec') && !function_exists('exec')) {
        $result['message'] = 'PHP shell execution functions are disabled on this server';
        return $result;
    }
    
    $result['writable'] = true;
    $result['can_execute'] = true;
    $result['message'] = 'Ready to generate BLAST indexes';
    
    return $result;
}

/**
 * Generate BLAST indexes for a FASTA file
 * 
 * @param string $organism Organism name
 * @param string $assembly Assembly name
 * @param string $fasta_filename FASTA filename
 * @param string $organism_data_path Path to organism data directory
 * @return array Array with 'success' boolean, 'message', and 'output'
 */
function generateBlastIndexes($organism, $assembly, $fasta_filename, $organism_data_path) {
    $result = [
        'success' => false,
        'message' => '',
        'output' => '',
        'errors' => ''
    ];
    
    // Validate inputs
    if (empty($organism) || empty($assembly) || empty($fasta_filename)) {
        $result['message'] = 'Missing required parameters';
        return $result;
    }
    
    // Prevent directory traversal attacks
    if (strpos($organism, '/') !== false || strpos($assembly, '/') !== false || strpos($fasta_filename, '/') !== false) {
        $result['message'] = 'Invalid characters in parameters';
        return $result;
    }
    
    $assembly_path = $organism_data_path . '/' . $organism . '/' . $assembly;
    $fasta_path = $assembly_path . '/' . $fasta_filename;
    
    // Verify paths exist
    if (!is_dir($assembly_path)) {
        $result['message'] = 'Assembly directory does not exist';
        return $result;
    }
    
    if (!file_exists($fasta_path)) {
        $result['message'] = 'FASTA file does not exist';
        return $result;
    }
    
    // Check permissions
    $perm_check = checkAssemblyWritableForBlast($assembly_path);
    if (!$perm_check['writable']) {
        $result['message'] = $perm_check['message'];
        return $result;
    }
    
    // Determine database type
    $is_protein = strpos($fasta_filename, 'protein') !== false;
    $db_type = $is_protein ? 'prot' : 'nucl';
    
    // Build command with full path to makeblastdb
    $cd_cmd = 'cd ' . escapeshellarg($assembly_path);
    $makeblastdb_path = '/usr/bin/makeblastdb';
    // Don't use escapeshellarg for the filename since we're already in the directory
    $makeblastdb_cmd = $makeblastdb_path . ' -in ' . $fasta_filename . ' -dbtype ' . $db_type . ' -parse_seqids 2>&1';
    $full_cmd = $cd_cmd . ' && ' . $makeblastdb_cmd;
    
    // Execute command
    try {
        if (function_exists('shell_exec')) {
            $output = shell_exec($full_cmd);
            $result['output'] = $output;
            
            // Give filesystem a moment to catch up
            sleep(2);
            
            // Debug: log what we're checking for
            $debug_info = [];
            
            // Check if indexes were created
            $index_ext = $is_protein ? ['phr', 'pin', 'psq'] : ['nhr', 'nin', 'nsq'];
            $all_created = true;
            $missing = [];
            foreach ($index_ext as $ext) {
                $index_file = $fasta_path . '.' . $ext;
                $exists = file_exists($index_file);
                $debug_info[] = $ext . ': ' . ($exists ? 'EXISTS' : 'MISSING') . ' (' . $index_file . ')';
                if (!$exists) {
                    $all_created = false;
                    $missing[] = $ext;
                }
            }
            
            if ($all_created) {
                $result['success'] = true;
                $result['message'] = 'BLAST indexes created successfully';
            } else {
                $result['message'] = 'Missing index files: ' . implode(', ', $missing) . '. Debug: ' . implode(' | ', $debug_info) . ' Command: ' . $full_cmd . ' Output: ' . $output;
                $result['errors'] = $output;
            }
        } else {
            $result['message'] = 'Shell execution not available';
        }
    } catch (Exception $e) {
        $result['message'] = 'Error executing command: ' . $e->getMessage();
    }
    
    return $result;
}

?>

