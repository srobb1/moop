<?php
/**
 * BLAST Tool Functions
 * Centralized functions for BLAST operations across the application
 * Used by BLAST interface, FASTA extract, and other tools
 */

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
 * 
 * @param string $blast_db Path to BLAST database (without extension)
 * @param array $sequence_ids Array of sequence IDs to extract
 * @return array Result array with 'success', 'content', and 'error' keys
 */
function extractSequencesFromBlastDb($blast_db, $sequence_ids) {
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
    
    // Build list of IDs to search, including variants for parent/child relationships
    $search_ids = [];
    foreach ($sequence_ids as $id) {
        $search_ids[] = $id;
        // Also try with .1 suffix if not already present (for parent->child relationships)
        if (substr($id, -2) !== '.1') {
            $search_ids[] = $id . '.1';
        }
    }
    
    // Use blastdbcmd to extract sequences - it accepts comma-separated IDs
    $ids_string = implode(',', $search_ids);
    $cmd = "blastdbcmd -db " . escapeshellarg($blast_db) . " -entry " . escapeshellarg($ids_string) . " 2>/dev/null";
    $output = [];
    $return_var = 0;
    @exec($cmd, $output, $return_var);
    
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

?>
