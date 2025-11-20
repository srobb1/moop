<?php
/**
 * AUTO-GENERATED FUNCTION REGISTRY
 * Generated: 2025-11-20 21:54:18
 * To regenerate, run: php tools/generate_registry.php
 */

$FUNCTION_REGISTRY = array (
  'lib/blast_functions.php' => 
  array (
    0 => 
    array (
      'name' => 'getBlastDatabases',
      'line' => 20,
      'comment' => '/**
* Get list of available BLAST databases for an assembly
* Looks for FASTA files matching configured sequence type patterns
* (protein.aa.fa, cds.nt.fa, transcript.nt.fa)
*
* @param string $assembly_path Path to assembly directory
* @return array Array of BLAST databases with type and path
*   Format: [
*     [\'name\' => \'protein\', \'path\' => \'/path/to/protein.aa.fa\', \'type\' => \'protein\'],
*     [\'name\' => \'cds\', \'path\' => \'/path/to/cds.nt.fa\', \'type\' => \'nucleotide\']
*   ]
*/',
      'code' => 'function getBlastDatabases($assembly_path) {
    global $sequence_types;
    $databases = [];
    
    if (!is_dir($assembly_path)) {
        return $databases;
    }
    
    // Map sequence types to database info
    $type_mapping = [
        \'protein\' => [\'name\' => \'Protein\', \'blast_type\' => \'protein\'],
        \'cds\' => [\'name\' => \'CDS\', \'blast_type\' => \'nucleotide\'],
        \'transcript\' => [\'name\' => \'Transcript\', \'blast_type\' => \'nucleotide\'],
    ];
    
    // Check for each configured sequence type
    if (!empty($sequence_types)) {
        foreach ($sequence_types as $seq_type => $config) {
            $pattern = $config[\'pattern\'] ?? \'\';
            if (empty($pattern)) {
                continue;
            }
            
            $file_path = "$assembly_path/$pattern";
            
            // Check if file exists
            if (file_exists($file_path)) {
                $type_info = $type_mapping[$seq_type] ?? [\'name\' => ucfirst($seq_type), \'blast_type\' => \'nucleotide\'];
                
                $databases[] = [
                    \'name\' => $type_info[\'name\'],
                    \'path\' => $file_path,
                    \'type\' => $type_info[\'blast_type\']
                ];
            }
        }
    }
    
    return $databases;
}',
    ),
    1 => 
    array (
      'name' => 'filterDatabasesByProgram',
      'line' => 69,
      'comment' => '/**
* Filter BLAST databases by program type
* Returns only databases compatible with the selected BLAST program
*
* @param array $databases Array of databases from getBlastDatabases()
* @param string $blast_program BLAST program: blastn, blastp, blastx, tblastn, tblastx
* @return array Filtered array of compatible databases
*/',
      'code' => 'function filterDatabasesByProgram($databases, $blast_program) {
    $filtered = [];
    
    // Determine which database types are compatible with the program
    $compatible_types = [];
    switch ($blast_program) {
        case \'blastn\':
        case \'tblastn\':
        case \'tblastx\':
            $compatible_types = [\'nucleotide\'];
            break;
        case \'blastp\':
        case \'blastx\':
            $compatible_types = [\'protein\'];
            break;
        default:
            return $databases; // Unknown program, return all
    }
    
    foreach ($databases as $db) {
        if (in_array($db[\'type\'], $compatible_types)) {
            $filtered[] = $db;
        }
    }
    
    return $filtered;
}',
    ),
    2 => 
    array (
      'name' => 'executeBlastSearch',
      'line' => 107,
      'comment' => '/**
* Execute BLAST search
* Runs BLAST command with outfmt 11 (ASN.1), then converts using blast_formatter
*
* @param string $query_seq FASTA sequence to search
* @param string $blast_db Path to BLAST database (without extension)
* @param string $program BLAST program (blastn, blastp, blastx, etc.)
* @param array $options Additional BLAST options (evalue, max_hits, matrix, etc.)
* @return array Result array with \'success\', \'output\', \'error\', and \'stderr\' keys
*/',
      'code' => 'function executeBlastSearch($query_seq, $blast_db, $program, $options = []) {
    $result = [
        \'success\' => false,
        \'output\' => \'\',
        \'error\' => \'\',
        \'stderr\' => \'\'
    ];
    
    // Validate inputs
    if (empty($query_seq) || empty($blast_db) || empty($program)) {
        $result[\'error\'] = \'Missing required parameters for BLAST search\';
        return $result;
    }
    
    // Verify database exists - check for any of the BLAST index files
    $has_index = false;
    if (file_exists("$blast_db.nhr") || file_exists("$blast_db.phr") || 
        file_exists("$blast_db.ndb") || file_exists("$blast_db.pdb")) {
        $has_index = true;
    }
    
    if (!$has_index) {
        $result[\'error\'] = \'BLAST database not found: \' . basename($blast_db);
        return $result;
    }
    
    // Set default options
    $evalue = $options[\'evalue\'] ?? \'1e-3\';
    $max_hits = (int)($options[\'max_hits\'] ?? 10);
    $matrix = $options[\'matrix\'] ?? \'BLOSUM62\';
    $filter = $options[\'filter\'] ? \'yes\' : \'no\';
    $task = $options[\'task\'] ?? \'\';
    
    // Create temporary directory for ASN.1 archive output
    $temp_dir = sys_get_temp_dir();
    $archive_file = tempnam($temp_dir, \'blast_\');
    
    if ($archive_file === false) {
        $result[\'error\'] = \'Failed to create temporary file for BLAST output\';
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
        $result[\'error\'] = \'Cannot resolve database path: \' . $blast_db;
        return $result;
    }
    $full_db_path = $db_path . \'/\' . $db_base;
    $cmd[] = \'-db \' . escapeshellarg($full_db_path);
    $cmd[] = \'-evalue \' . escapeshellarg($evalue);
    $cmd[] = \'-num_descriptions \' . escapeshellarg($max_hits);
    $cmd[] = \'-num_alignments \' . escapeshellarg($max_hits);
    $cmd[] = \'-outfmt 11\';
    $cmd[] = \'-out \' . escapeshellarg($archive_file);
    
    // Add program-specific options
    if ($program === \'blastn\') {
        $cmd[] = \'-dust \' . escapeshellarg($filter);
    } elseif ($program === \'tblastn\') {
        $cmd[] = \'-seg \' . escapeshellarg($filter);
    } elseif (in_array($program, [\'blastp\', \'blastx\', \'tblastx\'])) {
        $cmd[] = \'-seg \' . escapeshellarg($filter);
        $cmd[] = \'-matrix \' . escapeshellarg($matrix);
    }
    
    // Add task if specified
    if (!empty($task)) {
        $cmd[] = \'-task \' . escapeshellarg($task);
    }
    
    $command = \'printf \' . escapeshellarg($query_seq) . \' | \' . implode(\' \', $cmd);
    
    // Execute BLAST with proc_open for better control
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];
    
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        unlink($archive_file);
        $result[\'error\'] = \'Failed to execute BLAST command\';
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
        $result[\'error\'] = \'BLAST execution failed with code \' . $return_code;
        $result[\'stderr\'] = $blast_stderr;
        return $result;
    }
    
    // Convert ASN.1 archive to outfmt 5 (XML) using blast_formatter
    $xml_file = tempnam($temp_dir, \'blast_xml_\');
    $formatter_cmd = \'blast_formatter -archive \' . escapeshellarg($archive_file) . 
                     \' -outfmt 5 -out \' . escapeshellarg($xml_file);
    
    $process = proc_open($formatter_cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        unlink($archive_file);
        $result[\'error\'] = \'Failed to execute blast_formatter for XML\';
        return $result;
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $return_code = proc_close($process);
    
    if ($return_code !== 0) {
        unlink($archive_file);
        unlink($xml_file);
        $result[\'error\'] = \'blast_formatter XML conversion failed with code \' . $return_code;
        return $result;
    }
    
    // Read the XML output
    $output = file_get_contents($xml_file);
    
    // Now convert ASN.1 archive to outfmt 0 (Pairwise text) for download
    $pairwise_file = tempnam($temp_dir, \'blast_pairwise_\');
    $formatter_cmd = \'blast_formatter -archive \' . escapeshellarg($archive_file) . 
                     \' -outfmt 0 -out \' . escapeshellarg($pairwise_file);
    
    $process = proc_open($formatter_cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        unlink($archive_file);
        unlink($xml_file);
        unlink($pairwise_file);
        $result[\'error\'] = \'Failed to execute blast_formatter for pairwise\';
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
        $result[\'error\'] = \'blast_formatter pairwise conversion failed with code \' . $return_code;
        return $result;
    }
    
    // Read the pairwise output
    $pairwise_output = file_get_contents($pairwise_file);
    
    // Clean up temporary files
    unlink($archive_file);
    unlink($xml_file);
    unlink($pairwise_file);
    
    $result[\'success\'] = true;
    $result[\'output\'] = $output;
    $result[\'pairwise\'] = $pairwise_output;
    $result[\'stderr\'] = $blast_stderr;
    
    return $result;
}',
    ),
    3 => 
    array (
      'name' => 'extractSequencesFromBlastDb',
      'line' => 293,
      'comment' => '/**
* Extract sequences from BLAST database using blastdbcmd
* Used by fasta extract and download tools
*
* @param string $blast_db Path to BLAST database (without extension)
* @param array $sequence_ids Array of sequence IDs to extract
* @return array Result array with \'success\', \'content\', and \'error\' keys
*/',
      'code' => 'function extractSequencesFromBlastDb($blast_db, $sequence_ids) {
    $result = [
        \'success\' => false,
        \'content\' => \'\',
        \'error\' => \'\'
    ];
    
    if (empty($sequence_ids) || !is_array($sequence_ids)) {
        $result[\'error\'] = \'No sequence IDs provided\';
        return $result;
    }
    
    if (!file_exists($blast_db . \'.nhr\') && !file_exists($blast_db . \'.phr\')) {
        $result[\'error\'] = \'BLAST database not found\';
        return $result;
    }
    
    // Build list of IDs to search, including variants for parent/child relationships
    $search_ids = [];
    foreach ($sequence_ids as $id) {
        $search_ids[] = $id;
        // Also try with .1 suffix if not already present (for parent->child relationships)
        if (substr($id, -2) !== \'.1\') {
            $search_ids[] = $id . \'.1\';
        }
    }
    
    // Use blastdbcmd to extract sequences - it accepts comma-separated IDs
    $ids_string = implode(\',\', $search_ids);
    $cmd = "blastdbcmd -db " . escapeshellarg($blast_db) . " -entry " . escapeshellarg($ids_string) . " 2>/dev/null";
    $output = [];
    $return_var = 0;
    @exec($cmd, $output, $return_var);
    
    // Check if blastdbcmd executed
    if ($return_var > 1) {
        // Return code 1 is expected when some IDs don\'t exist, but >1 is an error
        $result[\'error\'] = "Error extracting sequences (exit code: $return_var). Ensure blastdbcmd is installed and FASTA files are formatted correctly.";
        return $result;
    }
    
    // Check if we got any output
    if (empty($output)) {
        $result[\'error\'] = \'No sequences found for the requested IDs\';
        return $result;
    }
    
    $result[\'success\'] = true;
    $result[\'content\'] = implode("\\n", $output);
    
    return $result;
}',
    ),
    4 => 
    array (
      'name' => 'validateBlastSequence',
      'line' => 353,
      'comment' => '/**
* Validate BLAST sequence input
* Checks if input is valid FASTA format
*
* @param string $sequence Raw sequence input (may or may not have FASTA header)
* @return array Array with \'valid\' bool and \'error\' string
*/',
      'code' => 'function validateBlastSequence($sequence) {
    $sequence = trim($sequence);
    
    if (empty($sequence)) {
        return [\'valid\' => false, \'error\' => \'Sequence is empty\'];
    }
    
    // If sequence doesn\'t start with >, add a header
    if ($sequence[0] !== \'>\') {
        $sequence = ">query_sequence\\n" . $sequence;
    }
    
    // Basic FASTA format validation
    $lines = explode("\\n", $sequence);
    $in_header = true;
    $seq_count = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        if ($line[0] === \'>\') {
            if (!$in_header && $seq_count === 0) {
                return [\'valid\' => false, \'error\' => \'Invalid FASTA format: sequence expected before header\'];
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
        return [\'valid\' => false, \'error\' => \'No sequence data found\'];
    }
    
    return [\'valid\' => true, \'error\' => \'\'];
}',
    ),
  ),
  'lib/blast_results_visualizer.php' => 
  array (
    0 => 
    array (
      'name' => 'parseBlastResults',
      'line' => 19,
      'comment' => '/**
* Parse BLAST results from XML output
* Supports multiple queries, each with Hit/HSP hierarchy
*
* @param string $blast_xml Raw BLAST XML output
* @return array Array of parsed query results, each with hits array
*/',
      'code' => 'function parseBlastResults($blast_xml) {
    $all_queries = [];
    
    // Parse XML
    try {
        $xml = simplexml_load_string($blast_xml);
        
        if ($xml === false) {
            return [\'error\' => \'Failed to parse BLAST XML output\', \'queries\' => []];
        }
        
        // Parse each iteration (each query gets one iteration)
        $iterations = $xml->xpath(\'//Iteration\');
        
        foreach ($iterations as $iteration) {
            $query_result = [
                \'hits\' => [],
                \'query_length\' => 0,
                \'query_name\' => \'\',
                \'query_desc\' => \'\',
                \'total_hits\' => 0,
                \'error\' => \'\'
            ];
            
            // Get query info using XPath to handle hyphens in element names
            $query_len = $iteration->xpath(\'./Iteration_query-len\');
            if (!empty($query_len)) {
                $query_result[\'query_length\'] = (int)$query_len[0];
            }
            
            $query_id = $iteration->xpath(\'./Iteration_query-ID\');
            if (!empty($query_id)) {
                $query_result[\'query_name\'] = (string)$query_id[0];
            }
            
            $query_def = $iteration->xpath(\'./Iteration_query-def\');
            if (!empty($query_def)) {
                $query_result[\'query_desc\'] = (string)$query_def[0];
            }
            
            // Parse each hit using XPath - maintain Hit/HSP hierarchy
            $hits = $iteration->xpath(\'.//Hit\');
            foreach ($hits as $hit_node) {
                // Get hit info
                $hit_id = $hit_node->xpath(\'./Hit_id\');
                $hit_def = $hit_node->xpath(\'./Hit_def\');
                $hit_len = $hit_node->xpath(\'./Hit_len\');
                
                $hit_id_str = !empty($hit_id) ? (string)$hit_id[0] : \'\';
                $hit_def_str = !empty($hit_def) ? (string)$hit_def[0] : \'\';
                $hit_len_int = !empty($hit_len) ? (int)$hit_len[0] : 0;
                
                // Get all HSPs (High-scoring Segment Pairs) for this hit
                $hsps = $hit_node->xpath(\'.//Hsp\');
                if (!empty($hsps)) {
                    $hsps_array = [];
                    $best_evalue = PHP_FLOAT_MAX;
                    $cumulative_coverage = [];
                    
                    // Process each HSP for this hit
                    foreach ($hsps as $hsp) {
                        // Use XPath for HSP elements with hyphens
                        $identities = $hsp->xpath(\'./Hsp_identity\');
                        $align_len = $hsp->xpath(\'./Hsp_align-len\');
                        $evalue = $hsp->xpath(\'./Hsp_evalue\');
                        $bit_score = $hsp->xpath(\'./Hsp_bit-score\');
                        $score = $hsp->xpath(\'./Hsp_score\');
                        $query_from = $hsp->xpath(\'./Hsp_query-from\');
                        $query_to = $hsp->xpath(\'./Hsp_query-to\');
                        $hit_from = $hsp->xpath(\'./Hsp_hit-from\');
                        $hit_to = $hsp->xpath(\'./Hsp_hit-to\');
                        $qseq = $hsp->xpath(\'./Hsp_qseq\');
                        $hseq = $hsp->xpath(\'./Hsp_hseq\');
                        $midline = $hsp->xpath(\'./Hsp_midline\');
                        
                        $identities_int = !empty($identities) ? (int)$identities[0] : 0;
                        $align_len_int = !empty($align_len) ? (int)$align_len[0] : 0;
                        $evalue_float = !empty($evalue) ? (float)$evalue[0] : 0;
                        $hit_from_int = !empty($hit_from) ? (int)$hit_from[0] : 0;
                        $hit_to_int = !empty($hit_to) ? (int)$hit_to[0] : 0;
                        
                        // Track best (smallest) evalue
                        if ($evalue_float < $best_evalue) {
                            $best_evalue = $evalue_float;
                        }
                        
                        // Track coverage regions for cumulative calculation
                        $cumulative_coverage[] = [
                            \'from\' => min($hit_from_int, $hit_to_int),
                            \'to\' => max($hit_from_int, $hit_to_int)
                        ];
                        
                        // Count gaps and similarities with gap lengths
                        $query_seq = !empty($qseq) ? (string)$qseq[0] : \'\';
                        $hit_seq = !empty($hseq) ? (string)$hseq[0] : \'\';
                        $midline_str = !empty($midline) ? (string)$midline[0] : \'\';
                        
                        // Count gaps and track individual gap lengths
                        $gap_count = 0;
                        $gap_lengths = [];
                        
                        // Find gaps in query sequence
                        $in_gap = false;
                        $gap_len = 0;
                        for ($i = 0; $i < strlen($query_seq); $i++) {
                            if ($query_seq[$i] === \'-\') {
                                if (!$in_gap) {
                                    $in_gap = true;
                                    $gap_len = 1;
                                } else {
                                    $gap_len++;
                                }
                            } else {
                                if ($in_gap) {
                                    $gap_lengths[] = $gap_len;
                                    $gap_count += $gap_len;
                                    $in_gap = false;
                                }
                            }
                        }
                        if ($in_gap) {
                            $gap_lengths[] = $gap_len;
                            $gap_count += $gap_len;
                        }
                        
                        // Find gaps in hit sequence
                        $in_gap = false;
                        $gap_len = 0;
                        for ($i = 0; $i < strlen($hit_seq); $i++) {
                            if ($hit_seq[$i] === \'-\') {
                                if (!$in_gap) {
                                    $in_gap = true;
                                    $gap_len = 1;
                                } else {
                                    $gap_len++;
                                }
                            } else {
                                if ($in_gap) {
                                    $gap_lengths[] = $gap_len;
                                    $gap_count += $gap_len;
                                    $in_gap = false;
                                }
                            }
                        }
                        if ($in_gap) {
                            $gap_lengths[] = $gap_len;
                            $gap_count += $gap_len;
                        }
                        
                        $total_gap_length = $gap_count;
                        $gaps = count($gap_lengths);
                        $gap_lengths_str = implode(\', \', $gap_lengths);
                        
                        $similarities = strlen($midline_str) - $identities_int - substr_count($midline_str, \' \');
                        
                        // Calculate HSP subject coverage percentage
                        $subject_coverage_percent = $hit_len_int > 0 ? round((abs($hit_to_int - $hit_from_int) + 1) / $hit_len_int * 100, 2) : 0;
                        
                        $hsp_data = [
                            \'identities\' => $identities_int,
                            \'alignment_length\' => $align_len_int,
                            \'evalue\' => $evalue_float,
                            \'bit_score\' => !empty($bit_score) ? (float)$bit_score[0] : 0,
                            \'score\' => !empty($score) ? (int)$score[0] : 0,
                            \'percent_identity\' => $align_len_int > 0 ? round(($identities_int / $align_len_int) * 100, 2) : 0,
                            \'query_from\' => !empty($query_from) ? (int)$query_from[0] : 0,
                            \'query_to\' => !empty($query_to) ? (int)$query_to[0] : 0,
                            \'hit_from\' => $hit_from_int,
                            \'hit_to\' => $hit_to_int,
                            \'query_seq\' => $query_seq,
                            \'hit_seq\' => $hit_seq,
                            \'midline\' => $midline_str,
                            \'gaps\' => $gaps,
                            \'gap_lengths\' => $gap_lengths,
                            \'gap_lengths_str\' => $gap_lengths_str,
                            \'total_gap_length\' => $total_gap_length,
                            \'similarities\' => $similarities,
                            \'subject_coverage_percent\' => $subject_coverage_percent
                        ];
                        
                        $hsps_array[] = $hsp_data;
                    }
                    
                    // Calculate cumulative coverage for this hit across all HSPs
                    // Track query coverage (not subject coverage)
                    $query_coverage = [];
                    foreach ($hsps_array as $hsp_data) {
                        $query_coverage[] = [
                            \'from\' => $hsp_data[\'query_from\'],
                            \'to\' => $hsp_data[\'query_to\']
                        ];
                    }
                    
                    usort($query_coverage, function($a, $b) { return $a[\'from\'] - $b[\'from\']; });
                    $merged = [];
                    foreach ($query_coverage as $region) {
                        if (empty($merged) || $merged[count($merged)-1][\'to\'] < $region[\'from\']) {
                            $merged[] = $region;
                        } else {
                            $merged[count($merged)-1][\'to\'] = max($merged[count($merged)-1][\'to\'], $region[\'to\']);
                        }
                    }
                    $total_covered = 0;
                    foreach ($merged as $region) {
                        $total_covered += $region[\'to\'] - $region[\'from\'] + 1;
                    }
                    $query_coverage_percent = $query_result[\'query_length\'] > 0 ? round(($total_covered / $query_result[\'query_length\']) * 100, 2) : 0;
                    
                    // Also calculate subject cumulative coverage for display
                    $subject_cumulative_coverage = [];
                    foreach ($query_result[\'hits\'] as $hit_test) {
                        if ($hit_test[\'subject\'] === $hit_def_str) {
                            foreach ($hit_test[\'hsps\'] as $hsp_test) {
                                $subject_cumulative_coverage[] = [
                                    \'from\' => min($hsp_test[\'hit_from\'], $hsp_test[\'hit_to\']),
                                    \'to\' => max($hsp_test[\'hit_from\'], $hsp_test[\'hit_to\'])
                                ];
                            }
                        }
                    }
                    usort($subject_cumulative_coverage, function($a, $b) { return $a[\'from\'] - $b[\'from\']; });
                    $merged_subject = [];
                    foreach ($subject_cumulative_coverage as $region) {
                        if (empty($merged_subject) || $merged_subject[count($merged_subject)-1][\'to\'] < $region[\'from\']) {
                            $merged_subject[] = $region;
                        } else {
                            $merged_subject[count($merged_subject)-1][\'to\'] = max($merged_subject[count($merged_subject)-1][\'to\'], $region[\'to\']);
                        }
                    }
                    $total_subject_covered = 0;
                    foreach ($merged_subject as $region) {
                        $total_subject_covered += $region[\'to\'] - $region[\'from\'] + 1;
                    }
                    $subject_cumulative_coverage_percent = $hit_len_int > 0 ? round(($total_subject_covered / $hit_len_int) * 100, 2) : 0;
                    
                    // Create hit entry with all its HSPs
                    $hit = [
                        \'id\' => $hit_id_str,
                        \'subject\' => $hit_def_str,
                        \'length\' => $hit_len_int,
                        \'hsps\' => $hsps_array,
                        \'best_evalue\' => $best_evalue,
                        \'num_hsps\' => count($hsps_array),
                        \'query_coverage_percent\' => $query_coverage_percent,
                        \'subject_cumulative_coverage_percent\' => $subject_cumulative_coverage_percent
                    ];
                    
                    $query_result[\'hits\'][] = $hit;
                }
            }
            
            $query_result[\'total_hits\'] = count($query_result[\'hits\']);
            $all_queries[] = $query_result;
        }
        
    } catch (Exception $e) {
        return [\'error\' => \'XML parsing error: \' . $e->getMessage(), \'queries\' => []];
    }
    
    return [\'queries\' => $all_queries, \'error\' => \'\'];
}',
    ),
    1 => 
    array (
      'name' => 'generateHitsSummaryTable',
      'line' => 288,
      'comment' => '/**
* Generate HTML for hits summary table
*
* @param array $results Parsed BLAST results
* @param int $query_num Query number for linking to hit sections
* @return string HTML table
*/',
      'code' => 'function generateHitsSummaryTable($results, $query_num = 1) {
    $html = \'<div class="blast-hits-summary mb-4">\';
    $html .= \'<h6><i class="fa fa-table"></i> Hits Summary (\' . $results[\'total_hits\'] . \' hits found)</h6>\';
    $html .= \'<div style="overflow-x: auto;">\';
    $html .= \'<table class="table table-sm table-striped blast-hits-table">\';
    $html .= \'<thead class="table-light">\';
    $html .= \'<tr>\';
    $html .= \'<th style="width: 5%">#</th>\';
    $html .= \'<th style="width: 45%">Subject</th>\';
    $html .= \'<th style="width: 20%">Query Coverage %</th>\';
    $html .= \'<th style="width: 12%">E-value</th>\';
    $html .= \'<th style="width: 18%">HSPs</th>\';
    $html .= \'</tr>\';
    $html .= \'</thead>\';
    $html .= \'<tbody>\';
    
    foreach ($results[\'hits\'] as $idx => $hit) {
        $hit_num = $idx + 1;
        $evalue_display = sprintf(\'%.2e\', $hit[\'best_evalue\']);
        $query_coverage = $hit[\'query_coverage_percent\'];
        $coverage_bar_width = min(100, $query_coverage);
        
        // Color based on coverage percentage
        if ($query_coverage >= 80) {
            $coverage_color = \'#28a745\'; // Green - excellent coverage
        } elseif ($query_coverage >= 50) {
            $coverage_color = \'#ffc107\'; // Yellow - good coverage
        } elseif ($query_coverage >= 30) {
            $coverage_color = \'#fd7e14\'; // Orange - moderate coverage
        } else {
            $coverage_color = \'#dc3545\'; // Red - low coverage
        }
        
        $html .= \'<tr style="cursor: pointer;" onclick="const elem = document.getElementById(\\\'query-\' . $query_num . \'-hit-\' . $hit_num . \'\\\'); elem.scrollIntoView({behavior: \\\'smooth\\\', block: \\\'start\\\'}); highlightHitElement(elem);">\';
        $html .= \'<td><strong>\' . $hit_num . \'</strong></td>\';
        $html .= \'<td><small>\' . htmlspecialchars(substr($hit[\'subject\'], 0, 60)) . \'</small></td>\';
        $html .= \'<td>\';
        $html .= \'<div class="blast-coverage-bar" style="width: 100%; background: #e9ecef; border-radius: 4px; overflow: hidden;">\';
        $html .= \'<div style="width: \' . $coverage_bar_width . \'%; background: \' . $coverage_color . \'; height: 20px; display: flex; align-items: center; justify-content: center;">\';
        $html .= \'<small style="font-weight: bold; color: white;">\' . $query_coverage . \'%</small>\';
        $html .= \'</div>\';
        $html .= \'</div>\';
        $html .= \'</td>\';
        $html .= \'<td><small>\' . $evalue_display . \'</small></td>\';
        $html .= \'<td>\' . $hit[\'num_hsps\'] . \'</td>\';
        $html .= \'</tr>\';
    }
    
    $html .= \'</tbody>\';
    $html .= \'</table>\';
    $html .= \'</div>\';
    $html .= \'</div>\';
    
    return $html;
}',
    ),
    2 => 
    array (
      'name' => 'generateBlastGraphicalView',
      'line' => 352,
      'comment' => '/**
* Generate BLAST graphical results using SVG
* Displays hits/HSPs as colored rectangles with score-based coloring
* Similar to canvas graph but with better styling and E-value display
*
* @param array $results Parsed BLAST results
* @return string SVG HTML
*/',
      'code' => 'function generateBlastGraphicalView($results) {
    if ($results[\'query_length\'] <= 0 || empty($results[\'hits\'])) {
        return \'\';
    }
    
    $query_len = $results[\'query_length\'];
    $canvas_width = 1000;
    $canvas_height_per_row = 25;
    $total_rows = 0;
    
    // Count total rows (one row per HSP) - limit to top 2 HSPs per hit
    foreach ($results[\'hits\'] as $hit) {
        $total_rows += min(2, count($hit[\'hsps\']));
    }
    
    $canvas_height = 120 + ($total_rows * $canvas_height_per_row) + 40;
    $img_width = 850;
    $xscale = $img_width / $query_len;
    $top_margin = 100;
    $left_margin = 200;
    $right_margin = 100;
    
    // Determine tick distance based on query length
    $tick_dist = 100;
    if ($query_len > 2450) $tick_dist = 150;
    if ($query_len > 3900) $tick_dist = 200;
    if ($query_len > 5000) $tick_dist = 300;
    if ($query_len > 7500) $tick_dist = round($query_len / 5);
    
    $html = \'<div style="margin: 20px 0; overflow-x: auto; border: 1px solid #ddd; border-radius: 8px; background: white;">\';
    $html .= \'<svg width="\' . ($canvas_width + $left_margin + $right_margin) . \'" height="\' . $canvas_height . \'" style="font-family: Arial, sans-serif;">\';
    
    // Background
    $html .= \'<rect width="\' . ($canvas_width + $left_margin + $right_margin) . \'" height="\' . $canvas_height . \'" fill="#f9f9f9"/>\';
    
    // Title
    $html .= \'<text x="\' . ($left_margin + ($img_width / 2)) . \'" y="30" font-size="18" font-weight="bold" text-anchor="middle" fill="#333">Query Length (\' . $query_len . \' bp)</text>\';
    
    // Score legend
    $legend_y = 50;
    $legend_items = [
        [\'label\' => \'<40\', \'color\' => \'#000000\', \'range\' => \'<40\'],
        [\'label\' => \'40-50\', \'color\' => \'#0047c8\', \'range\' => \'40-50\'],
        [\'label\' => \'50-80\', \'color\' => \'#77de75\', \'range\' => \'50-80\'],
        [\'label\' => \'80-200\', \'color\' => \'#e967f5\', \'range\' => \'80-200\'],
        [\'label\' => \'≥200\', \'color\' => \'#e83a2d\', \'range\' => \'200+\']
    ];
    
    $legend_x = $left_margin;
    $legend_width = $img_width / count($legend_items);
    foreach ($legend_items as $item) {
        $html .= \'<rect x="\' . $legend_x . \'" y="\' . $legend_y . \'" width="\' . $legend_width . \'" height="20" fill="\' . $item[\'color\'] . \'"/>\';
        $html .= \'<text x="\' . ($legend_x + ($legend_width / 2)) . \'" y="\' . ($legend_y + 15) . \'" font-size="11" font-weight="bold" text-anchor="middle" fill="white">\' . $item[\'label\'] . \'</text>\';
        $legend_x += $legend_width;
    }
    
    // Horizontal line under legend
    $html .= \'<line x1="\' . $left_margin . \'" y1="\' . ($legend_y + 25) . \'" x2="\' . ($left_margin + $img_width) . \'" y2="\' . ($legend_y + 25) . \'" stroke="#333" stroke-width="2"/>\';
    
    // Tick marks and labels
    $vline_tag = $tick_dist;
    for ($l = $tick_dist; $l + ($tick_dist / 2) < $query_len; $l += $tick_dist) {
        $x = $left_margin + ($l * $xscale);
        // Vertical line
        $html .= \'<line x1="\' . $x . \'" y1="\' . ($legend_y + 25) . \'" x2="\' . $x . \'" y2="\' . $canvas_height . \'" stroke="#ccc" stroke-width="1"/>\';
        // Tick label
        $html .= \'<text x="\' . $x . \'" y="\' . ($legend_y + 45) . \'" font-size="12" text-anchor="middle" fill="#333">\' . $vline_tag . \'</text>\';
        $vline_tag += $tick_dist;
    }
    
    // E-value column header
    $html .= \'<text x="\' . ($left_margin + $img_width + 15) . \'" y="\' . ($legend_y + 45) . \'" font-size="12" font-weight="bold" fill="#333">E-value</text>\';
    
    // Draw hits/HSPs
    $current_y = $top_margin;
    $prev_subject = \'\';
    
    foreach ($results[\'hits\'] as $hit_idx => $hit) {
        $subject_name = substr($hit[\'subject\'], 0, 40);
        $is_new_subject = ($prev_subject !== $hit[\'subject\']);
        
        if ($is_new_subject && $prev_subject !== \'\') {
            $current_y += 5; // Add spacing between different subjects
        }
        
        // Subject name (only once per hit)
        if ($is_new_subject) {
            $html .= \'<text x="5" y="\' . ($current_y + 15) . \'" font-size="11" font-weight="bold" fill="#333">\' . htmlspecialchars($subject_name) . \'</text>\';
        }
        
        // HSPs for this hit - limit to top 2
        foreach ($hit[\'hsps\'] as $hsp_idx => $hsp) {
            if ($hsp_idx >= 2) break; // Only show top 2 HSPs
            
            $start_pos = $hsp[\'query_from\'];
            $end_pos = $hsp[\'query_to\'];
            $score = $hsp[\'bit_score\'];
            
            // Determine color based on bit score
            if ($score >= 200) {
                $fill_color = \'rgba(255, 50, 40, 0.8)\';
            } elseif ($score >= 80) {
                $fill_color = \'rgba(235,96,247, 0.8)\';
            } elseif ($score >= 50) {
                $fill_color = \'rgba(119,222,117, 0.8)\';
            } elseif ($score >= 40) {
                $fill_color = \'rgba(0,62,203, 0.8)\';
            } else {
                $fill_color = \'rgba(10,10,10, 0.8)\';
            }
            
            $rect_x = $left_margin + ($start_pos * $xscale);
            $rect_width = (($end_pos - $start_pos + 1) * $xscale);
            
            // Convert rgba to hex for SVG
            if ($score >= 200) {
                $fill_hex = \'#ff3228\';
            } elseif ($score >= 80) {
                $fill_hex = \'#eb60f7\';
            } elseif ($score >= 50) {
                $fill_hex = \'#77de75\';
            } elseif ($score >= 40) {
                $fill_hex = \'#003ecb\';
            } else {
                $fill_hex = \'#0a0a0a\';
            }
            
            // HSP rectangle - clickable
            $html .= \'<g onclick="document.getElementById(\\\'hit-\' . ($hit_idx + 1) . \'\\\').scrollIntoView({behavior: \\\'smooth\\\', block: \\\'start\\\'})" style="cursor: pointer;">\';
            $html .= \'<title>Hit \' . ($hit_idx + 1) . \' HSP \' . ($hsp_idx + 1) . \': \' . round($hsp[\'percent_identity\'], 1) . \'% identity | E-value: \' . sprintf(\'%.2e\', $hsp[\'evalue\']) . \'</title>\';
            $html .= \'<rect x="\' . $rect_x . \'" y="\' . ($current_y) . \'" width="\' . $rect_width . \'" height="16" fill="\' . $fill_hex . \'" stroke="#333" stroke-width="0.5" rx="2"/>\';
            $html .= \'</g>\';
            
            // E-value on the right
            $evalue_display = sprintf(\'%.2e\', $hsp[\'evalue\']);
            $html .= \'<text x="\' . ($left_margin + $img_width + 15) . \'" y="\' . ($current_y + 12) . \'" font-size="10" fill="#333">\' . $evalue_display . \'</text>\';
            
            $current_y += $canvas_height_per_row;
        }
        
        $prev_subject = $hit[\'subject\'];
    }
    
    $html .= \'</svg>\';
    $html .= \'</div>\';
    
    // Add legend explaining the colors
    $html .= \'<div style="margin: 15px 0; background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 15px;">\';
    $html .= \'<strong style="display: block; margin-bottom: 10px;"><i class="fa fa-info-circle"></i> Legend - Bit Score Color Coding:</strong>\';
    $html .= \'<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">\';
    
    $legend_items = [
        [\'color\' => \'#0a0a0a\', \'label\' => \'< 40\', \'desc\' => \'Weak alignment\'],
        [\'color\' => \'#003ecb\', \'label\' => \'40 - 50\', \'desc\' => \'Moderate alignment\'],
        [\'color\' => \'#77de75\', \'label\' => \'50 - 80\', \'desc\' => \'Good alignment\'],
        [\'color\' => \'#eb60f7\', \'label\' => \'80 - 200\', \'desc\' => \'Very good alignment\'],
        [\'color\' => \'#ff3228\', \'label\' => \'≥ 200\', \'desc\' => \'Excellent alignment\']
    ];
    
    foreach ($legend_items as $item) {
        $html .= \'<div style="display: flex; align-items: center;">\';
        $html .= \'<div style="width: 24px; height: 24px; background: \' . $item[\'color\'] . \'; border-radius: 3px; margin-right: 10px;"></div>\';
        $html .= \'<div>\';
        $html .= \'<strong>\' . $item[\'label\'] . \'</strong><br>\';
        $html .= \'<small style="color: #666;">\' . $item[\'desc\'] . \'</small>\';
        $html .= \'</div>\';
        $html .= \'</div>\';
    }
    
    $html .= \'</div>\';
    $html .= \'</div>\';
    
    return $html;
}',
    ),
    3 => 
    array (
      'name' => 'generateAlignmentViewer',
      'line' => 533,
      'comment' => '/**
* Generate alignment viewer section
* Displays alignments organized by Hit, with multiple HSPs per Hit
*
* @param array $results Parsed BLAST results from parseBlastResults()
* @return string HTML with alignment viewer
*/',
      'code' => 'function generateAlignmentViewer($results, $blast_program = \'blastn\', $query_num = 1) {
    $html = \'<div class="blast-alignment-viewer mt-4">\';
    $html .= \'<h6><i class="fa fa-align-justify"></i> Detailed Alignments (HSPs)</h6>\';
    $html .= \'<small class="text-muted d-block mb-3">Each Hit section contains one or more High-Scoring Segment Pairs (HSPs)</small>\';
    
    if (empty($results[\'hits\'])) {
        $html .= \'<div class="alert alert-info"><small>No alignments to display</small></div>\';
        return $html . \'</div>\';
    }
    
    // Determine correct unit based on program
    $unit = \'bp\';
    if (strpos($blast_program, \'blastp\') !== false || strpos($blast_program, \'tblastn\') !== false) {
        $unit = \'aa\';
    }
    
    $html .= \'<div style="background: #f8f9fa; border-radius: 4px; overflow-x: auto;">\';
    
    foreach ($results[\'hits\'] as $hit_idx => $hit) {
        $hit_num = $hit_idx + 1;
        $evalue_display = sprintf(\'%.2e\', $hit[\'best_evalue\']);
        
        // Hit header card
        $html .= \'<div id="query-\' . $query_num . \'-hit-\' . $hit_num . \'" style="padding: 15px;  scroll-margin-top: 20px; background: #f0f7ff; margin-bottom: 15px;">\';
        $html .= \'<h5 style="margin-bottom: 10px; color: #007bff;">\';
        $html .= \'<strong>Hit \' . $hit_num . \': \' . htmlspecialchars($hit[\'subject\']) . \'</strong>\';
        $html .= \'</h5>\';
        $html .= \'<small class="d-block" style="margin-bottom: 10px;">\';
        $html .= \'<strong>Hit ID:</strong> \' . htmlspecialchars($hit[\'id\']) . \' | \';
        $html .= \'<strong>Length:</strong> \' . $hit[\'length\'] . \' \' . $unit . \' | \';
        $html .= \'<strong>Best E-value:</strong> \' . $evalue_display . \' | \';
        $html .= \'<strong>Number of HSPs:</strong> \' . $hit[\'num_hsps\'] . \' | \';
        $html .= \'<strong>Query Coverage:</strong> \' . $hit[\'query_coverage_percent\'] . \'% | \';
        $html .= \'<strong>Subject Coverage:</strong> \' . $hit[\'subject_cumulative_coverage_percent\'] . \'%\';
        $html .= \'</small>\';
        $html .= \'</div>\';
        
        // HSPs for this hit
        foreach ($hit[\'hsps\'] as $hsp_idx => $hsp) {
            $hsp_num = $hsp_idx + 1;
            
            $html .= \'<div style="padding: 15px; border-bottom: 1px solid #dee2e6; margin-left: 15px; background: #ffffff; margin-bottom: 10px; border-left: 4px solid #28a745;">\';
            $html .= \'<h6 style="margin-bottom: 10px;"><strong>HSP \' . $hsp_num . \'</strong></h6>\';
            $html .= \'<small class="text-muted d-block" style="margin-bottom: 10px;">\';
            $html .= \'E-value: \' . sprintf(\'%.2e\', $hsp[\'evalue\']) . \' | \';
            $html .= \'Alignment length: \' . $hsp[\'alignment_length\'] . \' | \';
            $html .= \'Identity: \' . $hsp[\'identities\'] . \'/\' . $hsp[\'alignment_length\'] . \' (\' . $hsp[\'percent_identity\'] . \'%) | \';
            $html .= \'Similarities: \' . $hsp[\'similarities\'] . \' | \';
            $html .= \'Gaps: \' . $hsp[\'gaps\'];
            if ($hsp[\'gaps\'] > 0) {
                $html .= \' (lengths: \' . $hsp[\'gap_lengths_str\'] . \', total: \' . $hsp[\'total_gap_length\'] . \')\';
            }
            $html .= \'</small>\';
            
            // Query coverage information for this HSP
            $query_hsp_coverage = $results[\'query_length\'] > 0 ? round((($hsp[\'query_to\'] - $hsp[\'query_from\'] + 1) / $results[\'query_length\']) * 100, 2) : 0;
            $html .= \'<small class="d-block" style="margin-bottom: 10px; background: #e7f3ff; padding: 8px; border-radius: 3px; border-left: 3px solid #007bff;">\';
            $html .= \'<strong>Query Coverage (This HSP):</strong> \';
            $html .= $query_hsp_coverage . \'% (\' . ($hsp[\'query_to\'] - $hsp[\'query_from\'] + 1) . \'/\' . $results[\'query_length\'] . \') | \';
            $html .= \'<strong>Subject Coverage (This HSP):</strong> \';
            $html .= $hsp[\'subject_coverage_percent\'] . \'% (\' . abs($hsp[\'hit_to\'] - $hsp[\'hit_from\']) + 1 . \'/\' . $results[\'hits\'][$hit_idx][\'length\'] . \')\';
            $html .= \'</small>\';
            
            // Display alignment in monospace with frame-aware formatting
            $html .= \'<pre style="background: white; border: 1px solid #dee2e6; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px; margin: 0; font-family: \\\'Courier New\\\', monospace;">\';
            
            // Use frame-aware alignment formatter if frames are available
            if (isset($hsp[\'query_frame\']) || isset($hsp[\'hit_frame\'])) {
                $query_frame = isset($hsp[\'query_frame\']) ? (int)$hsp[\'query_frame\'] : 0;
                $hit_frame = isset($hsp[\'hit_frame\']) ? (int)$hsp[\'hit_frame\'] : 0;
                $alignment_text = formatBlastAlignment(
                    $hsp[\'alignment_length\'],
                    $hsp[\'query_seq\'],
                    $hsp[\'query_from\'],
                    $hsp[\'query_to\'],
                    $hsp[\'midline\'],
                    $hsp[\'hit_seq\'],
                    $hsp[\'hit_from\'],
                    $hsp[\'hit_to\'],
                    \'Plus\',
                    $query_frame,
                    $hit_frame
                );
                $html .= htmlspecialchars($alignment_text);
            } else {
                // Fallback to simple formatting
                $label_width = 15;
                $query_label = str_pad(\'Query  \' . $hsp[\'query_from\'], $label_width);
                $midline_label = str_pad(\'\', $label_width);
                $sbjct_label = str_pad(\'Sbjct  \' . $hsp[\'hit_from\'], $label_width);
                
                $html .= $query_label . htmlspecialchars($hsp[\'query_seq\']) . \' \' . $hsp[\'query_to\'] . "\\n";
                $html .= $midline_label . htmlspecialchars($hsp[\'midline\']) . "\\n";
                $html .= $sbjct_label . htmlspecialchars($hsp[\'hit_seq\']) . \' \' . $hsp[\'hit_to\'] . "\\n";
            }
            
            $html .= \'</pre>\';
            
            $html .= \'</div>\';
        }
    }
    
    $html .= \'</div>\';
    $html .= \'</div>\';
    
    return $html;
}',
    ),
    4 => 
    array (
      'name' => 'generateBlastStatisticsSummary',
      'line' => 650,
      'comment' => '/**
* Generate BLAST results statistics summary
* Pretty card showing overall results statistics
*
* @param array $results Parsed BLAST results
* @param string $query_seq Query sequence
* @param string $blast_program BLAST program name
* @return string HTML statistics card
*/',
      'code' => 'function generateBlastStatisticsSummary($results, $query_seq, $blast_program) {
    if ($results[\'total_hits\'] === 0) {
        return \'\';
    }
    
    $html = \'<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">\';
    
    // Title
    $html .= \'<h4 style="margin: 0 0 20px 0; font-weight: bold;"><i class="fa fa-chart-bar"></i> BLAST Search Statistics</h4>\';
    
    // Statistics grid
    $html .= \'<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">\';
    
    // Query info
    $html .= \'<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #4fc3f7;">\';
    $html .= \'<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Query</div>\';
    $html .= \'<div style="font-size: 24px; font-weight: bold;">\' . strlen($query_seq) . \' bp</div>\';
    if (!empty($results[\'query_name\'])) {
        $html .= \'<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">\' . htmlspecialchars(substr($results[\'query_name\'], 0, 30)) . \'</div>\';
    }
    $html .= \'</div>\';
    
    // Hits found
    $html .= \'<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #81c784;">\';
    $html .= \'<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Hits Found</div>\';
    $html .= \'<div style="font-size: 24px; font-weight: bold;">\' . $results[\'total_hits\'] . \'</div>\';
    $html .= \'<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Subject sequences</div>\';
    $html .= \'</div>\';
    
    // Best hit info
    $best_hit = $results[\'hits\'][0];
    $best_hsp = $best_hit[\'hsps\'][0];
    $html .= \'<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #ffa726;">\';
    $html .= \'<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Best E-value</div>\';
    $evalue_display = sprintf(\'%.2e\', $best_hit[\'best_evalue\']);
    $html .= \'<div style="font-size: 24px; font-weight: bold;">\' . $evalue_display . \'</div>\';
    $html .= \'<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Top hit</div>\';
    $html .= \'</div>\';
    
    // Best identity
    $html .= \'<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #ef5350;">\';
    $html .= \'<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Best Identity</div>\';
    $html .= \'<div style="font-size: 24px; font-weight: bold;">\' . $best_hsp[\'percent_identity\'] . \'%</div>\';
    $html .= \'<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">\' . $best_hsp[\'identities\'] . \'/\' . $best_hsp[\'alignment_length\'] . \' bp/aa</div>\';
    $html .= \'</div>\';
    
    // Total HSPs
    $total_hsps = 0;
    foreach ($results[\'hits\'] as $hit) {
        $total_hsps += $hit[\'num_hsps\'];
    }
    $html .= \'<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #ab47bc;">\';
    $html .= \'<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Total HSPs</div>\';
    $html .= \'<div style="font-size: 24px; font-weight: bold;">\' . $total_hsps . \'</div>\';
    $html .= \'<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Alignments</div>\';
    $html .= \'</div>\';
    
    // Program
    $html .= \'<div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 6px; border-left: 4px solid #29b6f6;">\';
    $html .= \'<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Program</div>\';
    $html .= \'<div style="font-size: 18px; font-weight: bold;">\' . strtoupper(htmlspecialchars($blast_program)) . \'</div>\';
    $html .= \'<div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">Sequence search</div>\';
    $html .= \'</div>\';
    
    $html .= \'</div>\'; // End grid
    
    $html .= \'</div>\'; // End container
    
    return $html;
}',
    ),
    5 => 
    array (
      'name' => 'generateCompleteBlastVisualization',
      'line' => 730,
      'comment' => '/**
* Generate complete BLAST results visualization
* Combines all visualization components
*
* @param array $blast_result Result from executeBlastSearch()
* @param string $query_seq The query sequence
* @param string $blast_program The BLAST program used
* @return string Complete HTML visualization
*/',
      'code' => 'function generateCompleteBlastVisualization($blast_result, $query_seq, $blast_program, $blast_options = []) {
    if (!$blast_result[\'success\']) {
        return \'<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> No results to visualize</div>\';
    }
    
    $parse_result = parseBlastResults($blast_result[\'output\']);
    
    // Check for parsing errors
    if (!empty($parse_result[\'error\'])) {
        return \'<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> Error parsing results: \' . htmlspecialchars($parse_result[\'error\']) . \'</div>\';
    }
    
    $queries = $parse_result[\'queries\'] ?? [];
    if (empty($queries)) {
        return \'<div class="alert alert-info"><i class="fa fa-info-circle"></i> No queries found in results</div>\';
    }
    
    $html = \'<div class="blast-visualization">\';
    
    // Determine correct unit based on program
    $unit = \'bp\';
    if (strpos($blast_program, \'blastp\') !== false || strpos($blast_program, \'tblastn\') !== false) {
        $unit = \'aa\';
    }
    
    // Search Parameters Section (moved up, collapsible)
    $html .= \'<div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">\';
    $html .= \'<div style="padding: 15px; cursor: pointer; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;" onclick="document.getElementById(\\\'search-params\\\').style.display = document.getElementById(\\\'search-params\\\').style.display === \\\'none\\\' ? \\\'block\\\' : \\\'none\\\'; this.querySelector(\\\'i\\\').style.transform = document.getElementById(\\\'search-params\\\').style.display === \\\'none\\\' ? \\\'rotate(0deg)\\\' : \\\'rotate(180deg)\\\';">\';
    $html .= \'<h6 style="margin: 0; color: #333;"><i class="fa fa-cog"></i> Search Parameters</h6>\';
    $html .= \'<i class="fa fa-chevron-down" style="transition: transform 0.2s; transform: rotate(0deg);"></i>\';
    $html .= \'</div>\';
    
    $html .= \'<div id="search-params" style="display: none; padding: 15px;">\';
    
    // Original search parameters - first grid
    $html .= \'<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 15px;">\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Database</small><br>\';
    $html .= \'<small>protein.aa.fa</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Posted Date</small><br>\';
    $html .= \'<small>Nov 12, 2025 10:40 PM</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Database Size</small><br>\';
    $html .= \'<small>21,106,416 letters | 54,384 sequences</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Program</small><br>\';
    $html .= \'<small>\' . strtoupper(htmlspecialchars($blast_program)) . \'</small>\';
    $html .= \'</div>\';
    
    $html .= \'</div>\';
    
    // Original search parameters - second grid
    $html .= \'<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Matrix</small><br>\';
    $html .= \'<small>BLOSUM62</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Gap Penalties</small><br>\';
    $html .= \'<small>Existence: 11, Extension: 1</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Window for Multiple Hits</small><br>\';
    $html .= \'<small>40</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Threshold</small><br>\';
    $html .= \'<small>11</small>\';
    $html .= \'</div>\';
    
    $html .= \'</div>\';
    
    // New search parameters from form - third grid
    $html .= \'<hr style="margin: 15px 0;">\';
    $html .= \'<h6 style="color: #333; margin-bottom: 15px;"><i class="fa fa-sliders-h"></i> Form Parameters</h6>\';
    $html .= \'<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">\';
    
    // Basic parameters
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">E-value Threshold</small><br>\';
    $html .= \'<small>\' . htmlspecialchars($blast_options[\'evalue\'] ?? \'1e-3\') . \'</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Maximum Hits</small><br>\';
    $html .= \'<small>\' . ($blast_options[\'max_hits\'] ?? 10) . \'</small>\';
    $html .= \'</div>\';
    
    $html .= \'<div>\';
    $html .= \'<small style="color: #666; font-weight: bold;">Scoring Matrix</small><br>\';
    $html .= \'<small>\' . htmlspecialchars($blast_options[\'matrix\'] ?? \'BLOSUM62\') . \'</small>\';
    $html .= \'</div>\';
    
    // Advanced parameters
    if (!empty($blast_options[\'word_size\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Word Size</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'word_size\']) . \'</small>\';
        $html .= \'</div>\';
    }
    
    if (!empty($blast_options[\'gapopen\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Gap Open Penalty</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'gapopen\']) . \'</small>\';
        $html .= \'</div>\';
    }
    
    if (!empty($blast_options[\'gapextend\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Gap Extend Penalty</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'gapextend\']) . \'</small>\';
        $html .= \'</div>\';
    }
    
    if (!empty($blast_options[\'max_hsps\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Max HSPs</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'max_hsps\']) . \'</small>\';
        $html .= \'</div>\';
    }
    
    if (!empty($blast_options[\'perc_identity\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Percent Identity</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'perc_identity\']) . \'%</small>\';
        $html .= \'</div>\';
    }
    
    if (!empty($blast_options[\'culling_limit\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Culling Limit</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'culling_limit\']) . \'</small>\';
        $html .= \'</div>\';
    }
    
    if (!empty($blast_options[\'threshold\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Threshold</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'threshold\']) . \'</small>\';
        $html .= \'</div>\';
    }
    
    if (!empty($blast_options[\'strand\'])) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Strand</small><br>\';
        $html .= \'<small>\' . htmlspecialchars($blast_options[\'strand\']) . \'</small>\';
        $html .= \'</div>\';
    }
    
    if ($blast_options[\'soft_masking\'] ?? false) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Soft Masking</small><br>\';
        $html .= \'<small><i class="fa fa-check" style="color: green;"></i> Enabled</small>\';
        $html .= \'</div>\';
    }
    
    if ($blast_options[\'filter\'] ?? false) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Filter Low Complexity</small><br>\';
        $html .= \'<small><i class="fa fa-check" style="color: green;"></i> Enabled</small>\';
        $html .= \'</div>\';
    }
    
    if ($blast_options[\'ungapped\'] ?? false) {
        $html .= \'<div>\';
        $html .= \'<small style="color: #666; font-weight: bold;">Ungapped</small><br>\';
        $html .= \'<small><i class="fa fa-check" style="color: green;"></i> Enabled</small>\';
        $html .= \'</div>\';
    }
    
    $html .= \'</div>\';
    $html .= \'</div>\';
    $html .= \'</div>\';
    
    // Query Summary Table - all queries with links to their sections
    $html .= \'<div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">\';
    $html .= \'<h6 style="margin-bottom: 15px;"><i class="fa fa-list"></i> Query Summary</h6>\';
    $html .= \'<div style="overflow-x: auto;">\';
    $html .= \'<table class="table table-sm table-striped">\';
    $html .= \'<thead class="table-light">\';
    $html .= \'<tr>\';
    $html .= \'<th style="width: 5%">#</th>\';
    $html .= \'<th style="width: 35%">Query Name</th>\';
    $html .= \'<th style="width: 15%">Length</th>\';
    $html .= \'<th style="width: 15%">Hits</th>\';
    $html .= \'<th style="width: 30%">Best E-value</th>\';
    $html .= \'</tr>\';
    $html .= \'</thead>\';
    $html .= \'<tbody>\';
    
    foreach ($queries as $query_idx => $query) {
        $query_num = $query_idx + 1;
        $query_name = !empty($query[\'query_desc\']) ? htmlspecialchars($query[\'query_desc\']) : \'Query \' . $query_num;
        $best_evalue = $query[\'total_hits\'] > 0 ? $query[\'hits\'][0][\'best_evalue\'] : PHP_FLOAT_MAX;
        $best_evalue_display = $best_evalue < PHP_FLOAT_MAX ? sprintf(\'%.2e\', $best_evalue) : \'N/A\';
        
        $html .= \'<tr style="cursor: pointer;" onclick="document.getElementById(\\\'query-\' . $query_num . \'\\\').scrollIntoView({behavior: \\\'smooth\\\', block: \\\'start\\\'});">\';
        $html .= \'<td><strong>\' . $query_num . \'</strong></td>\';
        $html .= \'<td><small>\' . $query_name . \'</small></td>\';
        $html .= \'<td>\' . $query[\'query_length\'] . \' \' . $unit . \'</td>\';
        $html .= \'<td>\' . $query[\'total_hits\'] . \'</td>\';
        $html .= \'<td><small>\' . $best_evalue_display . \'</small></td>\';
        $html .= \'</tr>\';
    }
    
    $html .= \'</tbody>\';
    $html .= \'</table>\';
    $html .= \'</div>\';
    $html .= \'</div>\';
    
    // Individual Query Sections - each query with all its results
    foreach ($queries as $query_idx => $query) {
        $query_num = $query_idx + 1;
        $query_name = !empty($query[\'query_desc\']) ? htmlspecialchars($query[\'query_desc\']) : \'Query \' . $query_num;
        
        // Collapsible query section
        $html .= \'<div id="query-\' . $query_num . \'" style="background: white; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px; scroll-margin-top: 20px;">\';
        
        // Query header (collapsible)
        $html .= \'<div id="query-\' . $query_num . \'-header" style="padding: 15px; cursor: pointer; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;" onclick="toggleQuerySection(\\\'query-\' . $query_num . \'-content\\\', this);">\';
        $html .= \'<h5 style="margin: 0;"><i class="fa fa-dna"></i> Query \' . $query_num . \': \' . $query_name . \'</h5>\';
        $html .= \'<i class="fa fa-chevron-down" style="transition: transform 0.2s; transform: rotate(0deg);"></i>\';
        $html .= \'</div>\';
        
        // Query content (collapsible)
        $html .= \'<div id="query-\' . $query_num . \'-content" style="padding: 15px;">\';
        
        // Query info
        $html .= \'<div style="background: #f8f9fa; border-left: 4px solid #667eea; padding: 12px; margin-bottom: 15px; border-radius: 4px;">\';
        $html .= \'<small>\';
        if (!empty($query[\'query_desc\'])) {
            $html .= \'<strong>Description:</strong> \' . htmlspecialchars($query[\'query_desc\']) . \'<br>\';
        }
        $html .= \'<strong>Length:</strong> \' . $query[\'query_length\'] . \' \' . $unit . \' | \';
        $html .= \'<strong>Total Hits:</strong> \' . $query[\'total_hits\'];
        $html .= \'</small>\';
        $html .= \'</div>\';
        
        if ($query[\'total_hits\'] === 0) {
            $html .= \'<div class="alert alert-info"><small>No significant matches found for this query</small></div>\';
        } else {
            // HSP visualization for this query
            $html .= generateHspVisualizationWithLines($query, $blast_program, $query_num);
            
            // Hits summary table for this query
            $html .= generateHitsSummaryTable($query, $query_num);
            
            // Alignment viewer for this query
            $html .= generateAlignmentViewer($query, $blast_program, $query_num);
        }
        
        $html .= \'</div>\'; // End query content
        $html .= \'</div>\'; // End query section
    }
    
    $html .= \'</div>\';
    
    return $html;
}',
    ),
    6 => 
    array (
      'name' => 'generateHspVisualizationWithLines',
      'line' => 985,
      'comment' => '/**
* Generate HSP visualization with connecting lines (ported from locBLAST)
* Displays HSPs as colored segments with lines connecting adjacent HSPs
* Adapted from: https://github.com/cobilab/locBLAST (GPL-3.0)
*
* @param array $results Parsed BLAST results
* @param string $blast_program BLAST program name (blastn, blastp, etc.)
* @return string HTML with HSP visualization
*/',
      'code' => 'function generateHspVisualizationWithLines($results, $blast_program = \'blastn\', $query_num = 1) {
    if (empty($results[\'hits\']) || $results[\'query_length\'] <= 0) {
        return \'\';
    }
    
    // Determine unit based on program
    $unit = \'bp\';
    if (strpos($blast_program, \'blastp\') !== false || strpos($blast_program, \'tblastn\') !== false) {
        $unit = \'aa\';
    }
    
    $html = \'<div class="blast-hsp-visualization" style="margin: 20px 0; background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">\';
    $html .= \'<h6 style="text-align: center;"><i class="fa fa-ruler-horizontal"></i> Query Length (\' . $results[\'query_length\'] . \' \' . $unit . \')</h6>\';
    
    // Add CSS for HSP visualization
    $html .= \'<style>\';
    $html .= \'.hsp-row { display: flex; align-items: center; margin-bottom: 12px; }\';
    $html .= \'.hsp-label { min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; word-break: break-all; }\';
    $html .= \'.hsp-segments { display: flex; align-items: center; width: 800px; position: relative; z-index: 5; }\';
    $html .= \'.hsp-segment { height: 16px; display: inline-block; margin-right: 0; cursor: pointer; border: 1px solid #333; transition: opacity 0.2s; }\';
    $html .= \'.hsp-segment:hover { opacity: 0.8; }\';
    $html .= \'.hsp-gap { height: 4px; background: #e0e0e0; display: inline-block; margin-top: 6px; }\';
    $html .= \'.hsp-connector { width: 1px; height: 6px; background: #000; display: inline-block; margin: 5px 0; }\';
    $html .= \'.color-black { background-color: #000000; }\';
    $html .= \'.color-blue { background-color: #0047c8; }\';
    $html .= \'.color-green { background-color: #77de75; }\';
    $html .= \'.color-purple { background-color: #e967f5; }\';
    $html .= \'.color-red { background-color: #e83a2d; }\';
    $html .= \'.hit-highlighted { background-color: #ffffcc !important; transition: background-color 0.3s ease-out; }\';
    $html .= \'.hsp-row:hover { background-color: rgba(100, 150, 255, 0.1); }\';
    $html .= \'</style>\';
    
     // Add JavaScript for hit navigation and highlighting
     $html .= \'<script>\';
     $html .= \'function jumpToHit(hitIndex, queryNum) {\';
     $html .= \'  if (queryNum === undefined) queryNum = 1;\';
     $html .= \'  const hitId = "query-" + queryNum + "-hit-" + (hitIndex + 1);\';
     $html .= \'  const element = document.getElementById(hitId);\';
     $html .= \'  if (element) {\';
     $html .= \'    element.scrollIntoView({ behavior: "smooth", block: "start" });\';
     $html .= \'    highlightHitElement(element);\';
     $html .= \'  }\';
     $html .= \'}\';
     $html .= \'function highlightHitElement(element) {\';
     $html .= \'  if (!element) return;\';
     $html .= \'  element.classList.remove("hit-highlighted");\';
     $html .= \'  void element.offsetWidth;\';
     $html .= \'  element.classList.add("hit-highlighted");\';
     $html .= \'  setTimeout(() => {\';
     $html .= \'    element.classList.remove("hit-highlighted");\';
     $html .= \'  }, 3500);\';
     $html .= \'}\';
     $html .= \'function highlightHit(hitIndex, queryNum) {\';
     $html .= \'  if (queryNum === undefined) queryNum = 1;\';
     $html .= \'  jumpToHit(hitIndex, queryNum);\';
     $html .= \'}\';
     $html .= \'</script>\';
    
    $html .= \'<div style="margin-top: 15px; margin: 0 auto; width: 1000px; text-align: center;">\';

    // Generate just the score legend (outside overflow:hidden)
    $html .= generateQueryScoreLegend($results[\'query_length\'], $results[\'query_name\']);

    // Pixel unit calculation based on query length (800px width)
    $px_unit = 800 / $results[\'query_length\'];

    // Calculate total HSP rows
    $total_hsp_rows = 0;
    foreach ($results[\'hits\'] as $hit) {
        $total_hsp_rows += count($hit[\'hsps\']) + 1; // +1 for spacing between hits
    }
    
    // Calculate height needed for HSP rows (each row ~25px + spacing)
    $hsp_content_height = ($total_hsp_rows * 25) + 50;
    // Set max height before scrolling (e.g., 400px)
    $max_hsp_height = 400;
    $use_scroll = $hsp_content_height > $max_hsp_height;
    
    // Total container height: ticks (80px) + HSP area (either content height or max height)
    $hsp_display_height = min($hsp_content_height, $max_hsp_height);
    $total_container_height = 80 + $hsp_display_height;

    // Create a container for scale ticks and HSPs with relative positioning
    // This must be tall enough for all tick lines to extend down
    $html .= \'<div style="position: relative; overflow: hidden; height: \' . $total_container_height . \'px;">\';
    
    // Generate the query scale ruler with ticks (inside overflow:hidden to be clipped properly)
    $html .= generateQueryScaleTicks($results[\'query_length\']);
    
    // Create a scrollable wrapper for HSP rows positioned below the ticks
    $scroll_style = $use_scroll ? \'overflow-y: scroll;\' : \'overflow: hidden;\';
    $html .= \'<div style="position: absolute; top: 80px; left: 0; right: 0; height: \' . $hsp_display_height . \'px; \' . $scroll_style . \'">\';
    
    // Wrapper for HSP rows
    $html .= \'<div style="padding-top: 0;">\';
    
    foreach ($results[\'hits\'] as $hit_idx => $hit) {
        $hit_num = $hit_idx + 1;
        
        // Organize HSPs by their query coordinates
        $hsp_positions = [];
        $hsp_scores = [];
        $hsp_details = [];
        
        foreach ($hit[\'hsps\'] as $hsp_idx => $hsp) {
            $q_start = min($hsp[\'query_from\'], $hsp[\'query_to\']);
            $q_end = max($hsp[\'query_from\'], $hsp[\'query_to\']);
            
            $hsp_positions[] = [
                \'start\' => $q_start,
                \'end\' => $q_end,
                \'index\' => $hsp_idx
            ];
            
            $hsp_scores[$hsp_idx] = $hsp[\'bit_score\'];
            $hsp_details[$hsp_idx] = $hsp;
        }
        
        // Sort by start position
        usort($hsp_positions, function($a, $b) {
            return $a[\'start\'] - $b[\'start\'];
        });
        
        // Build HTML row
        $html .= \'<div class="hsp-row" style="cursor: pointer;" onclick="jumpToHit(\' . $hit_idx . \', \' . $query_num . \'); highlightHit(\' . $hit_idx . \', \' . $query_num . \');">\';
        $html .= \'<div class="hsp-label"></div>\';
        $html .= \'<div class="hsp-segments">\';
        
        // First HSP
        if (!empty($hsp_positions)) {
            $first_hsp = $hsp_positions[0];
            $first_idx = $first_hsp[\'index\'];
            $color = getHspColorClass($hsp_scores[$first_idx]);
            $segment_width = ($first_hsp[\'end\'] - $first_hsp[\'start\']) * $px_unit;
            
            // Add leading gap if needed
            if ($first_hsp[\'start\'] > 1) {
                $gap_width = ($first_hsp[\'start\'] - 1) * $px_unit;
                $html .= \'<div class="hsp-gap" style="width: \' . $gap_width . \'px;"></div>\';
            }
            
            $hsp = $hsp_details[$first_idx];
            // Extract just the subject name (first word/identifier before space or bracket)
            $hit_name = \'Hit \' . $hit_num;
            if (!empty($hit[\'subject\'])) {
                $desc = htmlspecialchars($hit[\'subject\']);
                // Extract first word or identifier (up to first space, bracket, or pipe)
                preg_match(\'/^([^\\s\\[\\|\\-]+)/\', $desc, $matches);
                if (!empty($matches[1])) {
                    $hit_name = $matches[1];
                }
            }
            $title = $hit_name . \' - HSP \' . ($first_idx + 1) . \': \' . $hsp[\'percent_identity\'] . \'% identity | E-value: \' . sprintf(\'%.2e\', $hsp[\'evalue\']);
            $html .= \'<div class="hsp-segment \' . $color . \'" style="width: \' . $segment_width . \'px;" title="\' . htmlspecialchars($title) . \'"></div>\';
            
            // Additional HSPs with connecting logic
            for ($k = 1; $k < count($hsp_positions); $k++) {
                $current = $hsp_positions[$k];
                $previous = $hsp_positions[$k - 1];
                $current_idx = $current[\'index\'];
                
                $gap = $current[\'start\'] - $previous[\'end\'];
                
                if ($gap > 0) {
                    // Add connector lines for gaps
                    $html .= \'<div class="hsp-connector"></div>\';
                    
                    // Add gap
                    $gap_width = $gap * $px_unit;
                    $html .= \'<div class="hsp-gap" style="width: \' . $gap_width . \'px;"></div>\';
                    
                    // Add connector on other side
                    $html .= \'<div class="hsp-connector"></div>\';
                } else {
                    // Overlapping or adjacent HSPs - just connector line
                    $html .= \'<div class="hsp-connector"></div>\';
                }
                
                // Add current segment
                $color = getHspColorClass($hsp_scores[$current_idx]);
                $segment_width = ($current[\'end\'] - $current[\'start\']) * $px_unit;
                $hsp = $hsp_details[$current_idx];
                // Extract just the subject name (first word/identifier before space or bracket)
                $hit_name = \'Hit \' . $hit_num;
                if (!empty($hit[\'subject\'])) {
                    $desc = htmlspecialchars($hit[\'subject\']);
                    // Extract first word or identifier (up to first space, bracket, or pipe)
                    preg_match(\'/^([^\\s\\[\\|\\-]+)/\', $desc, $matches);
                    if (!empty($matches[1])) {
                        $hit_name = $matches[1];
                    }
                }
                $title = $hit_name . \' - HSP \' . ($current_idx + 1) . \': \' . $hsp[\'percent_identity\'] . \'% identity | E-value: \' . sprintf(\'%.2e\', $hsp[\'evalue\']);
                $html .= \'<div class="hsp-segment \' . $color . \'" style="width: \' . $segment_width . \'px;" title="\' . htmlspecialchars($title) . \'"></div>\';
            }
            
            // Trailing gap
            $last_end = $hsp_positions[count($hsp_positions) - 1][\'end\'];
            if ($last_end < $results[\'query_length\']) {
                $trailing_gap = ($results[\'query_length\'] - $last_end) * $px_unit;
                $html .= \'<div class="hsp-gap" style="width: \' . $trailing_gap . \'px;"></div>\';
            }
        }
        
        $html .= \'</div>\';
        $html .= \'</div>\';
    }
    
    // Close the HSP rows wrapper
    $html .= \'</div>\';
    
    // Close the scrollable HSP container
    $html .= \'</div>\';
    
    // Close the main overflow:hidden container and outer div
    $html .= \'</div>\';
    $html .= \'</div>\';
    
    // Add description below the HSP visualization (outside the relative container so ticks don\'t overlap)
    $html .= \'<small class="text-muted" style="display: block; margin-top: 15px; margin-bottom: 30px; text-align: center;">Each color represents a different bit score range. Lines connect adjacent HSPs on the query sequence.</small>\';
    
    return $html;
}',
    ),
    7 => 
    array (
      'name' => 'getHspColorClass',
      'line' => 1144,
      'comment' => '/**
* Get HSP color class based on bit score
* Mirrors locBLAST color_key function
*
* @param float $score Bit score
* @return string CSS class name for color
*/',
      'code' => 'function getHspColorClass($score) {
    if ($score <= 40) {
        return \'color-black\';
    } elseif ($score <= 50) {
        return \'color-blue\';
    } elseif ($score <= 80) {
        return \'color-green\';
    } elseif ($score <= 200) {
        return \'color-purple\';
    } else {
        return \'color-red\';
    }
}',
    ),
    8 => 
    array (
      'name' => 'getColorStyle',
      'line' => 1263,
      'comment' => '/**
* Get inline CSS style for color class
*
* @param string $colorClass CSS class name
* @return string Inline style
*/',
      'code' => 'function getColorStyle($colorClass) {
    $styles = [
        \'color-black\' => \'background-color: #000000;\',
        \'color-blue\' => \'background-color: #0047c8;\',
        \'color-green\' => \'background-color: #77de75;\',
        \'color-purple\' => \'background-color: #e967f5;\',
        \'color-red\' => \'background-color: #e83a2d;\'
    ];
    
    return isset($styles[$colorClass]) ? $styles[$colorClass] : \'\';
}',
    ),
    9 => 
    array (
      'name' => 'formatBlastAlignment',
      'line' => 603,
      'comment' => '/**
* Format BLAST alignment output with frame-aware coordinate tracking
* Ported from locBLAST fmtprint() - handles frame shifts for BLASTx/tBLASTx
*
* @param int $length Alignment length
* @param string $query_seq Query sequence with gaps
* @param int $query_seq_from Query start coordinate
* @param int $query_seq_to Query end coordinate
* @param string $align_seq Midline (match indicators)
* @param string $sbjct_seq Subject sequence with gaps
* @param int $sbjct_seq_from Subject start coordinate
* @param int $sbjct_seq_to Subject end coordinate
* @param string $p_m Plus/Minus strand
* @param int $query_frame Query reading frame (0=none, ±1,2,3 for proteins)
* @param int $hit_frame Subject reading frame
* @return string Formatted alignment text
*/',
      'code' => 'function formatBlastAlignment($length, $query_seq, $query_seq_from, $query_seq_to, $align_seq, $sbjct_seq, $sbjct_seq_from, $sbjct_seq_to, $p_m = \'Plus\', $query_frame = 0, $hit_frame = 0) {
    $output = \'\';
    $large = max(array((int)$query_seq_from, (int)$query_seq_to, (int)$sbjct_seq_from, (int)$sbjct_seq_to));
    $large_len = strlen($large);
    $n = (int)($length / 60);
    $r = $length % 60;
    if ($r > 0) $t = $n + 1;
    else $t = $n;
    
    if ($query_frame != 0 && $hit_frame != 0) {
        // Both query and subject are in frames (protein vs protein or translated)
        for ($i = 0; $i < $t; $i++) {
            if ($query_frame > 0) {
                $xn4 = $query_seq_from;
                $xs4 = substr($query_seq, 60*$i, 60);
                $xs4 = preg_replace("/-/", "", $xs4);
                $yn4 = $xn4 + (strlen($xs4) * 3) - 1;
                $output .= "\\nQuery  " . str_pad($xn4, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn4;
                $xn4 = $yn4 + 1;
                $output .= "\\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
            } else {
                $xn = $query_seq_to;
                $xs = substr($query_seq, 60*$i, 60);
                $xs = preg_replace("/-/", "", $xs);
                $yn = $xn - (strlen($xs) * 3) + 1;
                $output .= "\\nQuery  " . str_pad($xn, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn;
                $xn = $yn - 1;
                $output .= "\\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
            }
            if ($hit_frame > 0) {
                $an4 = $sbjct_seq_from;
                $ys4 = substr($sbjct_seq, 60*$i, 60);
                $ys4 = preg_replace("/-/", "", $ys4);
                $bn4 = $an4 + (strlen($ys4) *3) - 1;
                $output .= "\\nSbjct  " . str_pad($an4, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn4 . "\\n";
                $an4 = $bn4 + 1;
            } else {
                $an = $sbjct_seq_to;
                $ys = substr($sbjct_seq, 60*$i, 60);
                $ys = preg_replace("/-/", "", $ys);
                $bn = $an - (strlen($ys) *3) + 1;
                $output .= "\\nSbjct  " . str_pad($an, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn . "\\n";
                $an = $bn - 1;
            }
        }
    } elseif ($query_frame != 0 && $hit_frame == 0) {
        // Query is framed (tBLASTx, BLASTx), subject is not
        if ($query_frame > 0) { $xn1 = $query_seq_from; } else { $xn1 = $query_seq_to; }
        $an1 = $sbjct_seq_from;
        for ($i = 0; $i < $t; $i++) {
            if ($query_frame > 0) {
                $xs1 = substr($query_seq, 60*$i, 60);
                $xs1 = preg_replace("/-/", "", $xs1);
                $yn1 = $xn1 + (strlen($xs1) * 3) - 1;
                $output .= "\\nQuery  " . str_pad($xn1, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn1;
                $xn1 = $yn1 + 1;
                $output .= "\\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys1 = substr($sbjct_seq, 60*$i, 60);
                $ys1 = preg_replace("/-/", "", $ys1);
                $bn1 = $an1 + strlen($ys1) - 1;
                $output .= "\\nSbjct  " . str_pad($an1, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn1 . "\\n";
                $an1 = $bn1 + 1;
            } else {
                $xs1 = substr($query_seq, 60*$i, 60);
                $xs1 = preg_replace("/-/", "", $xs1);
                $yn1 = $xn1 - (strlen($xs1) * 3) + 1;
                $output .= "\\nQuery  " . str_pad($xn1, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn1;
                $xn1 = $yn1 - 1;
                $output .= "\\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys1 = substr($sbjct_seq, 60*$i, 60);
                $ys1 = preg_replace("/-/", "", $ys1);
                $bn1 = $an1 + strlen($ys1) - 1;
                $output .= "\\nSbjct  " . str_pad($an1, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn1 . "\\n";
                $an1 = $bn1 + 1;
            }
        }
    } elseif ($query_frame == 0 && $hit_frame != 0) {
        // Subject is framed, query is not
        if ($hit_frame > 0) { $an3 = $sbjct_seq_from; } else { $an3 = $sbjct_seq_to; }
        $xn3 = $query_seq_from;
        for ($i = 0; $i < $t; $i++) {
            if ($hit_frame > 0) {
                $xs3 = substr($query_seq, 60*$i, 60);
                $xs3 = preg_replace("/-/", "", $xs3);
                $yn3 = $xn3 + strlen($xs3) - 1;
                $output .= "\\nQuery  " . str_pad($xn3, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn3;
                $xn3 = $yn3 + 1;
                $output .= "\\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys3 = substr($sbjct_seq, 60*$i, 60);
                $ys3 = preg_replace("/-/", "", $ys3);
                $bn3 = $an3 + (strlen($ys3) * 3) - 1;
                $output .= "\\nSbjct  " . str_pad($an3, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn3 . "\\n";
                $an3 = $bn3 + 1;
            } else {
                $xs3 = substr($query_seq, 60*$i, 60);
                $xs3 = preg_replace("/-/", "", $xs3);
                $yn3 = $xn3 + strlen($xs3) - 1;
                $output .= "\\nQuery  " . str_pad($xn3, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn3;
                $xn3 = $yn3 + 1;
                $output .= "\\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
                $ys3 = substr($sbjct_seq, 60*$i, 60);
                $ys3 = preg_replace("/-/", "", $ys3);
                $bn3 = $an3 - (strlen($ys3) * 3) + 1;
                $output .= "\\nSbjct  " . str_pad($an3, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn3 . "\\n";
                $an3 = $bn3 - 1;
            }
        }
    } else {
        // No frames - standard nucleotide vs nucleotide
        $xn2 = $query_seq_from;
        $an2 = $sbjct_seq_from;
        for ($i = 0; $i < $t; $i++) {
            $xs2 = substr($query_seq, 60*$i, 60);
            $xs2 = preg_replace("/-/", "", $xs2);
            $yn2 = $xn2 + strlen($xs2) - 1;
            $output .= "\\nQuery  " . str_pad($xn2, $large_len) . "  " . substr($query_seq, 60*$i, 60) . "  " . $yn2;
            $xn2 = $yn2 + 1;
            $output .= "\\n       ". str_pad(" ", $large_len) . "  " . substr($align_seq, 60*$i, 60);
            $ys2 = substr($sbjct_seq, 60*$i, 60);
            $ys2 = preg_replace("/-/", "", $ys2);
            if ($p_m == "Plus") {
                $bn2 = $an2 + strlen($ys2) - 1;
                $output .= "\\nSbjct  " . str_pad($an2, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn2 . "\\n";
                $an2 = $bn2 + 1;
            } else {
                $bn2 = $an2 - strlen($ys2) + 1;
                $output .= "\\nSbjct  " . str_pad($an2, $large_len) . "  " . substr($sbjct_seq, 60*$i, 60) . "  " . $bn2 . "\\n";
                $an2 = $bn2 - 1;
            }
        }
    }
    
    return $output;
}',
    ),
    10 => 
    array (
      'name' => 'generateQueryScoreLegend',
      'line' => 1073,
      'comment' => '/**
* Generate query score legend (outside overflow container)
* Shows score color ranges and query bar info
*
* @param int $query_length Total query length
* @param string $query_name Optional query name/ID
* @return string HTML for legend and query bar
*/',
      'code' => 'function generateQueryScoreLegend($query_length, $query_name = \'\') {
    $output = \'<div style="margin: 0 auto 0px auto; width: 1000px;">\';
    
    // Score legend bar - discrete colored boxes (800px total width to match query)
    $output .= \'<div style="display: flex; align-items: center; margin-bottom: 0;">\';
    $output .= \'<div style="min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; text-align: right;">Score:</div>\';
    $output .= \'<div style="display: flex; gap: 0; width: 800px;">\';
    
    $score_ranges = [
        [\'color\' => \'#000000\', \'label\' => \'≤40<br><small>(Weak)</small>\'],
        [\'color\' => \'#0047c8\', \'label\' => \'40-50\'],
        [\'color\' => \'#77de75\', \'label\' => \'50-80\'],
        [\'color\' => \'#e967f5\', \'label\' => \'80-200\'],
        [\'color\' => \'#e83a2d\', \'label\' => \'≥200<br><small>(Excellent)</small>\']
    ];
    
    $box_width = (800 / 5); // Divide 800px by 5 color ranges evenly
    foreach ($score_ranges as $range) {
        $output .= \'<div style="width: \' . $box_width . \'px; height: 25px; background-color: \' . $range[\'color\'] . \'; border-right: 1px solid #333; display: flex; align-items: center; justify-content: center;">\';
        $output .= \'<span style="color: white; font-size: 10px; font-weight: bold; text-align: center; line-height: 1.2;">\' . $range[\'label\'] . \'</span>\';
        $output .= \'</div>\';
    }
    
    $output .= \'</div>\';
    $output .= \'</div>\';
    
    // Query bar - 800px width (no margins or padding, directly touches everything)
    $output .= \'<div style="display: flex; align-items: center; margin: 0; padding: 0;">\';
    $output .= \'<div style="min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; text-align: right;">\';
    $output .= \'Query:\';
    if (!empty($query_name)) {
        $output .= \'<br><small style="font-weight: normal; color: #666;">\' . htmlspecialchars(substr($query_name, 0, 20)) . \'</small>\';
    }
    $output .= \'</div>\';
    $output .= \'<div style="width: 800px; height: 20px; position: relative; background: #f0f0f0; border-left: 1px solid #999; border-right: 1px solid #999; margin: 0; padding: 0;">\';
    $output .= \'<div style="position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(to right, #4CAF50 0%, #45a049 100%); margin: 0; padding: 0;"></div>\';
    $output .= \'<div style="position: absolute; left: 5px; top: 2px; color: white; font-size: 11px; font-weight: bold;">1 - \' . $query_length . \' bp</div>\';
    $output .= \'</div>\';
    $output .= \'</div>\';
    
    $output .= \'</div>\';
    
    return $output;
}',
    ),
    11 => 
    array (
      'name' => 'generateQueryScaleTicks',
      'line' => 1099,
      'comment' => '/**
* Generate query scale ticks (inside overflow container to be clipped)
* Shows tick marks and vertical reference lines
* Must be positioned at top of the HSP rows
*
* @param int $query_length Total query length
* @return string HTML for scale ticks and vertical lines
*/',
      'code' => 'function generateQueryScaleTicks($query_length) {
    $pxls = 800 / $query_length;
    $output = \'\';
    
    // Generate exactly 10 evenly-spaced ticks across the query length
    $tick_numbers = [];
    
    // Calculate the spacing to get 10 ticks
    $tick_interval = $query_length / 10;
    
    // Generate 10 ticks
    for ($i = 1; $i <= 10; $i++) {
        $tick_numbers[] = (int)($tick_interval * $i);
    }
    
    // Ensure the last tick is exactly the query length
    if (!empty($tick_numbers)) {
        $tick_numbers[count($tick_numbers) - 1] = $query_length;
    }
    
    // Scale ruler container with absolute positioning at the top
    // Use lower z-index so HSP bars appear on top of the reference lines
    $output .= \'<div style="position: absolute; top: 0; left: 0; width: 100%; height: 80px; z-index: 1; margin: 0; padding: 0;">\';
    
    // Flex container for alignment
    $output .= \'<div style="display: flex; align-items: flex-start; width: 100%; margin: 0; padding: 0;">\';
    $output .= \'<div style="min-width: 100px; padding-right: 15px; margin: 0;"></div>\';
    
    // Container for ruler - 800px width
    $output .= \'<div style="position: relative; width: 800px; height: 80px; margin: 0; padding: 0;">\';
    
    // Draw "1" marker at the start (aligned with other tick numbers at top: 10px)
    $output .= \'<div style="position: absolute; left: -15px; top: 10px; width: 30px; text-align: center; font-size: 11px; font-weight: bold;">1</div>\';
    
    // Draw tick marks with labels and vertical reference lines
    foreach ($tick_numbers as $tick_num) {
        // Calculate pixel position for this tick number (accounting for 1-based indexing)
        $pixel_pos = (int)($pxls * ($tick_num - 1));
        
        // Vertical reference line - extends from top through ticks to HSP boxes below
        $output .= \'<div style="position: absolute; left: \' . $pixel_pos . \'px; top: 0px; width: 1px; height: 2000px; background: #cccccc; pointer-events: none;"></div>\';
        
        // Tick mark at the top (dark gray)
        $output .= \'<div style="position: absolute; left: \' . $pixel_pos . \'px; top: 0px; width: 1px; height: 8px; background: #999;"></div>\';
        
        // Tick label number (aligned with "1" marker)
        $output .= \'<div style="position: absolute; left: \' . ($pixel_pos - 15) . \'px; top: 10px; width: 30px; text-align: center; font-size: 11px; font-weight: bold;">\' . $tick_num . \'</div>\';
    }
    
    $output .= \'</div>\';
    $output .= \'</div>\';
    $output .= \'</div>\';
    
    return $output;
}',
    ),
    12 => 
    array (
      'name' => 'generateQueryScale',
      'line' => 1099,
      'comment' => '/**
* Generate query scale ruler with intelligent tick spacing
* Ported from locBLAST unit() function - displays as positioned overlay
* Includes horizontal query bar representation aligned with HSP boxes
* Tick lines are positioned absolutely and will be clipped by parent container
*
* @param int $query_length Total query length
* @param string $query_name Optional query name/ID
* @return string HTML for scale labels, ticks, and query bar
*/',
      'code' => 'function generateQueryScale($query_length, $query_name = \'\') {
    $pxls = 800 / $query_length;  // Wider 800px instead of 500px
    $output = \'<div style="margin: 0 auto 20px auto; width: 1000px;">\';
    
    // Score legend bar - discrete colored boxes (800px total width to match query)
    $output .= \'<div style="display: flex; align-items: center; margin-bottom: 10px;">\';
    $output .= \'<div style="min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; text-align: right;">Score:</div>\';
    $output .= \'<div style="display: flex; gap: 0; width: 800px;">\';
    
    $score_ranges = [
        [\'color\' => \'#000000\', \'label\' => \'≤40<br><small>(Weak)</small>\'],
        [\'color\' => \'#0047c8\', \'label\' => \'40-50\'],
        [\'color\' => \'#77de75\', \'label\' => \'50-80\'],
        [\'color\' => \'#e967f5\', \'label\' => \'80-200\'],
        [\'color\' => \'#e83a2d\', \'label\' => \'≥200<br><small>(Excellent)</small>\']
    ];
    
    $box_width = (800 / 5); // Divide 800px by 5 color ranges evenly
    foreach ($score_ranges as $range) {
        $output .= \'<div style="width: \' . $box_width . \'px; height: 25px; background-color: \' . $range[\'color\'] . \'; border-right: 1px solid #333; display: flex; align-items: center; justify-content: center;">\';
        $output .= \'<span style="color: white; font-size: 10px; font-weight: bold; text-align: center; line-height: 1.2;">\' . $range[\'label\'] . \'</span>\';
        $output .= \'</div>\';
    }
    
    $output .= \'</div>\';
    $output .= \'</div>\';
    
    // Query bar - 800px width
    $output .= \'<div style="display: flex; align-items: center; margin-bottom: 0;">\';
    $output .= \'<div style="min-width: 100px; padding-right: 15px; font-size: 11px; font-weight: bold; text-align: right;">\';
    $output .= \'Query:\';
    if (!empty($query_name)) {
        $output .= \'<br><small style="font-weight: normal; color: #666;">\' . htmlspecialchars(substr($query_name, 0, 20)) . \'</small>\';
    }
    $output .= \'</div>\';
    $output .= \'<div style="width: 800px; height: 20px; position: relative; background: #f0f0f0; border-left: 1px solid #999; border-right: 1px solid #999; border-bottom: 1px solid #999; border-radius: 0 0 3px 3px; margin-right: 10px;">\';
    $output .= \'<div style="position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: linear-gradient(to right, #4CAF50 0%, #45a049 100%); border-radius: 0 0 2px 2px;"></div>\';
    $output .= \'<div style="position: absolute; left: 5px; top: 2px; color: white; font-size: 11px; font-weight: bold;">1 - \' . $query_length . \' bp</div>\';
    $output .= \'</div>\';
    $output .= \'</div>\';
    
    // Generate scale tick numbers: Calculate evenly spaced rounded intervals
    $tick_numbers = [];
    $tick_interval = $query_length / 10;  // Base interval for 10 ticks
    
    // Determine rounding interval based on query length for clean numbers
    if ($query_length <= 100) {
        $round_to = 10;
    } elseif ($query_length <= 500) {
        $round_to = 50;
    } elseif ($query_length <= 1000) {
        $round_to = 100;
    } elseif ($query_length <= 5000) {
        $round_to = 500;
    } else {
        $round_to = 1000;
    }
    
    // Round the base interval to get consistent tick spacing
    $rounded_interval = round($tick_interval / $round_to) * $round_to;
    
    // Generate 10 tick numbers using the rounded interval
    for ($i = 1; $i <= 10; $i++) {
        $tick_num = $i * $rounded_interval;
        if ($tick_num <= $query_length) {
            $tick_numbers[] = $tick_num;
        }
    }
    
    // Scale ruler with ticks and vertical lines down to HSPs
    $output .= \'<div style="display: flex; align-items: flex-start;">\';
    $output .= \'<div style="min-width: 100px; padding-right: 15px;"></div>\';
    
    // Container for ruler - 800px width, extended height for vertical lines
    $output .= \'<div style="position: relative; height: 40px; width: 800px; margin-right: 10px;">\';
    
    // Draw "1" marker at the start
    $output .= \'<div style="position: absolute; left: -15px; top: 10px; width: 30px; text-align: center; font-size: 11px; font-weight: bold;">1</div>\';
    
    // Draw tick marks with labels and vertical reference lines
    foreach ($tick_numbers as $tick_num) {
        // Calculate pixel position for this tick number (accounting for 1-based indexing)
        $pixel_pos = (int)($pxls * ($tick_num - 1));
        
        // Vertical reference line (light gray) extending down - will be clipped by parent container\'s overflow:hidden
        $output .= \'<div style="position: absolute; left: \' . $pixel_pos . \'px; top: 0; width: 1px; height: 2000px; background: #cccccc; pointer-events: none;"></div>\';
        
        // Tick mark at the top (dark gray)
        $output .= \'<div style="position: absolute; left: \' . $pixel_pos . \'px; top: 0; width: 1px; height: 8px; background: #999;"></div>\';
        
        // Tick label number (centered below tick mark)
        $output .= \'<div style="position: absolute; left: \' . ($pixel_pos - 15) . \'px; top: 10px; width: 30px; text-align: center; font-size: 11px; font-weight: bold;">\' . $tick_num . \'</div>\';
    }
    
    $output .= \'</div>\';
    $output .= \'</div>\';
    $output .= \'</div>\';
    
    return $output;
}',
    ),
    13 => 
    array (
      'name' => 'getToggleQuerySectionScript',
      'line' => 1659,
      'comment' => '/**
* JavaScript function for toggling query sections (embedded in PHP output)
* Called onclick from query section headers
*/',
      'code' => 'function getToggleQuerySectionScript() {
    return <<<\'JS\'
<script>
function toggleQuerySection(contentId, headerElement) {
    const content = document.getElementById(contentId);
    const chevron = headerElement.querySelector(\'i\');
    
    if (content.style.display === \'none\') {
        content.style.display = \'block\';
        chevron.style.transform = \'rotate(180deg)\';
    } else {
        content.style.display = \'none\';
        chevron.style.transform = \'rotate(0deg)\';
    }
}
</script>
JS;
}',
    ),
    14 => 
    array (
      'name' => 'toggleQuerySection',
      'line' => 962,
      'comment' => '',
      'code' => 'function toggleQuerySection(contentId, headerElement) {
    const content = document.getElementById(contentId);
    const chevron = headerElement.querySelector(\'i\');
    
    if (content.style.display === \'none\') {
        content.style.display = \'block\';
        chevron.style.transform = \'rotate(180deg)\';
    } else {
        content.style.display = \'none\';
        chevron.style.transform = \'rotate(0deg)\';
    }
}',
    ),
  ),
  'lib/database_queries.php' => 
  array (
    0 => 
    array (
      'name' => 'getFeatureById',
      'line' => 28,
      'comment' => '/**
* Get feature data by feature_id
* Returns complete feature information including organism and genome data
*
* @param int $feature_id - Feature ID to retrieve
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Feature row with organism and genome info, or empty array
*/',
      'code' => 'function getFeatureById($feature_id, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(\',\', array_fill(0, count($genome_ids), \'?\'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
                  WHERE f.feature_id = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$feature_id], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
                  WHERE f.feature_id = ?";
        $params = [$feature_id];
    }
    
    $results = fetchData($query, $dbFile, $params);
    return !empty($results) ? $results[0] : [];
}',
    ),
    1 => 
    array (
      'name' => 'getFeatureByUniquename',
      'line' => 65,
      'comment' => '/**
* Get feature data by feature_uniquename
* Returns complete feature information including organism and genome data
*
* @param string $feature_uniquename - Feature uniquename to retrieve
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Feature row with organism and genome info, or empty array
*/',
      'code' => 'function getFeatureByUniquename($feature_uniquename, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(\',\', array_fill(0, count($genome_ids), \'?\'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
                  WHERE f.feature_uniquename = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$feature_uniquename], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id, f.genome_id, f.organism_id,
                         o.genus, o.species, o.subtype, o.common_name, o.taxon_id,
                         g.genome_accession, g.genome_name
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  JOIN genome g ON f.genome_id = g.genome_id
                  WHERE f.feature_uniquename = ?";
        $params = [$feature_uniquename];
    }
    
    $results = fetchData($query, $dbFile, $params);
    return !empty($results) ? $results[0] : [];
}',
    ),
    2 => 
    array (
      'name' => 'getChildrenByFeatureId',
      'line' => 102,
      'comment' => '/**
* Get immediate children of a feature (not recursive)
* Returns direct children only
*
* @param int $parent_feature_id - Parent feature ID
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Array of child feature rows
*/',
      'code' => 'function getChildrenByFeatureId($parent_feature_id, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(\',\', array_fill(0, count($genome_ids), \'?\'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.parent_feature_id = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$parent_feature_id], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.parent_feature_id = ?";
        $params = [$parent_feature_id];
    }
    
    return fetchData($query, $dbFile, $params);
}',
    ),
    3 => 
    array (
      'name' => 'getParentFeature',
      'line' => 130,
      'comment' => '/**
* Get immediate parent of a feature by ID
* Returns minimal parent info for hierarchy traversal
*
* @param int $feature_id - Feature ID to get parent of
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Parent feature row (minimal fields), or empty array
*/',
      'code' => 'function getParentFeature($feature_id, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(\',\', array_fill(0, count($genome_ids), \'?\'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.feature_id = ? AND f.genome_id IN ($placeholders)";
        $params = array_merge([$feature_id], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, f.parent_feature_id
                  FROM feature f
                  WHERE f.feature_id = ?";
        $params = [$feature_id];
    }
    
    $results = fetchData($query, $dbFile, $params);
    return !empty($results) ? $results[0] : [];
}',
    ),
    4 => 
    array (
      'name' => 'getFeaturesByType',
      'line' => 157,
      'comment' => '/**
* Get all features of specific types in a genome
* Useful for getting genes, mRNAs, or other feature types
*
* @param string $feature_type - Feature type to retrieve (e.g., \'gene\', \'mRNA\')
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Array of features with specified type
*/',
      'code' => 'function getFeaturesByType($feature_type, $dbFile, $genome_ids = []) {
    if (!empty($genome_ids)) {
        $placeholders = implode(\',\', array_fill(0, count($genome_ids), \'?\'));
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.genome_id
                  FROM feature f
                  WHERE f.feature_type = ? AND f.genome_id IN ($placeholders)
                  ORDER BY f.feature_uniquename";
        $params = array_merge([$feature_type], $genome_ids);
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.genome_id
                  FROM feature f
                  WHERE f.feature_type = ?
                  ORDER BY f.feature_uniquename";
        $params = [$feature_type];
    }
    
    return fetchData($query, $dbFile, $params);
}',
    ),
    5 => 
    array (
      'name' => 'searchFeaturesByUniquename',
      'line' => 187,
      'comment' => '/**
* Search features by uniquename with optional organism filter
* Used for quick feature lookup and search suggestions
*
* @param string $search_term - Search term for feature uniquename (supports wildcards)
* @param string $dbFile - Path to SQLite database
* @param string $organism_name - Optional: Filter by organism name
* @return array - Array of matching features
*/',
      'code' => 'function searchFeaturesByUniquename($search_term, $dbFile, $organism_name = \'\') {
    if ($organism_name) {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.organism_id, o.genus, o.species
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  WHERE f.feature_uniquename LIKE ? AND o.genus || \' \' || o.species LIKE ?
                  ORDER BY f.feature_uniquename
                  LIMIT 50";
        $params = ["%$search_term%", "%$organism_name%"];
    } else {
        $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_name, f.feature_description, 
                         f.feature_type, f.organism_id, o.genus, o.species
                  FROM feature f
                  JOIN organism o ON f.organism_id = o.organism_id
                  WHERE f.feature_uniquename LIKE ?
                  ORDER BY f.feature_uniquename
                  LIMIT 50";
        $params = ["%$search_term%"];
    }
    
    return fetchData($query, $dbFile, $params);
}',
    ),
    6 => 
    array (
      'name' => 'getAnnotationsByFeature',
      'line' => 219,
      'comment' => '/**
* Get all annotations for a feature
* Returns annotations with their sources and metadata
*
* @param int $feature_id - Feature ID to get annotations for
* @param string $dbFile - Path to SQLite database
* @return array - Array of annotation records
*/',
      'code' => 'function getAnnotationsByFeature($feature_id, $dbFile) {
    $query = "SELECT a.annotation_id, a.annotation_accession, a.annotation_description, 
                     ans.annotation_source_name, ans.annotation_source_id,
                     fa.score, fa.date, fa.additional_info
              FROM annotation a
              JOIN feature_annotation fa ON a.annotation_id = fa.annotation_id
              JOIN annotation_source ans ON a.annotation_source_id = ans.annotation_source_id
              WHERE fa.feature_id = ?
              ORDER BY fa.date DESC";
    
    return fetchData($query, $dbFile, [$feature_id]);
}',
    ),
    7 => 
    array (
      'name' => 'getOrganismInfo',
      'line' => 240,
      'comment' => '/**
* Get organism information
* Returns complete organism record with taxonomic data
*
* @param string $organism_name - Organism name (genus + species)
* @param string $dbFile - Path to SQLite database
* @return array - Organism record, or empty array if not found
*/',
      'code' => 'function getOrganismInfo($organism_name, $dbFile) {
    $query = "SELECT organism_id, genus, species, common_name, subtype, taxon_id
              FROM organism
              WHERE (genus || \' \' || species = ? OR common_name = ?)
              LIMIT 1";
    
    $results = fetchData($query, [$organism_name, $organism_name], $dbFile);
    return !empty($results) ? $results[0] : [];
}',
    ),
    8 => 
    array (
      'name' => 'getAssemblyStats',
      'line' => 258,
      'comment' => '/**
* Get assembly/genome statistics
* Returns feature counts and metadata for an assembly
*
* @param string $genome_accession - Genome/assembly accession
* @param string $dbFile - Path to SQLite database
* @return array - Genome record with feature counts, or empty array
*/',
      'code' => 'function getAssemblyStats($genome_accession, $dbFile) {
    $query = "SELECT g.genome_id, g.genome_accession, g.genome_name,
                     COUNT(DISTINCT CASE WHEN f.feature_type = \'gene\' THEN f.feature_id END) as gene_count,
                     COUNT(DISTINCT CASE WHEN f.feature_type = \'mRNA\' THEN f.feature_id END) as mrna_count,
                     COUNT(DISTINCT f.feature_id) as total_features
              FROM genome g
              LEFT JOIN feature f ON g.genome_id = f.genome_id
              WHERE g.genome_accession = ?
              GROUP BY g.genome_id";
    
    $results = fetchData($query, $dbFile, [$genome_accession]);
    return !empty($results) ? $results[0] : [];
}',
    ),
    9 => 
    array (
      'name' => 'searchFeaturesAndAnnotations',
      'line' => 282,
      'comment' => '/**
* Search features and annotations by keyword
* Supports both keyword and quoted phrase searches
* Used by annotation_search_ajax.php
*
* @param string $search_term - Search term or phrase
* @param bool $is_quoted_search - Whether this is a quoted phrase search
* @param string $dbFile - Path to SQLite database
* @return array - Array of matching features with annotations
*/',
      'code' => 'function searchFeaturesAndAnnotations($search_term, $is_quoted_search, $dbFile) {
    // Build the WHERE clause for annotations
    if ($is_quoted_search) {
        // Exact phrase match - use CASE for relevance scoring
        $like_pattern = "%$search_term%";
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         a.annotation_accession, a.annotation_description, 
                         fa.score, fa.date, ans.annotation_source_name, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o, genome g
                  WHERE ans.annotation_source_id = a.annotation_source_id 
                    AND f.feature_id = fa.feature_id 
                    AND fa.annotation_id = a.annotation_id 
                    AND f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND (a.annotation_description LIKE ? 
                       OR f.feature_name LIKE ? 
                       OR f.feature_description LIKE ?
                       OR a.annotation_accession LIKE ?)
                  ORDER BY 
                    CASE 
                      WHEN f.feature_name LIKE ? THEN 1
                      WHEN f.feature_description LIKE ? THEN 2
                      WHEN a.annotation_description LIKE ? THEN 3
                      ELSE 4
                    END,
                    f.feature_uniquename
                  LIMIT 100";
        $params = [$like_pattern, $like_pattern, $like_pattern, $like_pattern, $like_pattern, $like_pattern, $like_pattern];
    } else {
        // Multi-term keyword search (all terms must appear somewhere)
        $terms = array_filter(array_map(\'trim\', preg_split(\'/\\s+/\', $search_term)));
        if (empty($terms)) {
            return [];
        }
        
        // Extract primary term for relevance scoring (first word of search)
        $primary_term = $terms[0];
        $primary_pattern = "%$primary_term%";
        
        // Build conditions: (col1 LIKE term1 OR col2 LIKE term1 OR ...) AND (col1 LIKE term2 OR ...)
        $conditions = [];
        $params = [];
        $columns = [\'a.annotation_description\', \'f.feature_name\', \'f.feature_description\', \'a.annotation_accession\'];
        
        foreach ($terms as $term) {
            $term_conditions = implode(\' OR \', array_map(function($col) { return "$col LIKE ?"; }, $columns));
            $conditions[] = "($term_conditions)";
            for ($i = 0; $i < count($columns); $i++) {
                $params[] = "%$term%";
            }
        }
        
        $where_clause = implode(\' AND \', $conditions);
        
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         a.annotation_accession, a.annotation_description, 
                         fa.score, fa.date, ans.annotation_source_name, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM annotation a, feature f, feature_annotation fa, annotation_source ans, organism o, genome g
                  WHERE ans.annotation_source_id = a.annotation_source_id 
                    AND f.feature_id = fa.feature_id 
                    AND fa.annotation_id = a.annotation_id 
                    AND f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND $where_clause
                  ORDER BY 
                    CASE 
                      WHEN f.feature_name LIKE ? THEN 1
                      WHEN f.feature_description LIKE ? THEN 2
                      WHEN a.annotation_description LIKE ? THEN 3
                      ELSE 4
                    END,
                    f.feature_uniquename
                  LIMIT 100";
        
        // Add primary term patterns for CASE statement to params
        $params[] = $primary_pattern;
        $params[] = $primary_pattern;
        $params[] = $primary_pattern;
    }
    
    return fetchData($query, $dbFile, $params);
}',
    ),
    10 => 
    array (
      'name' => 'searchFeaturesByUniquenameForSearch',
      'line' => 379,
      'comment' => '/**
* Search features by uniquename (primary search)
* Returns only features, not annotations
* Used as fast path before annotation search
*
* @param string $search_term - Search term for uniquename
* @param string $dbFile - Path to SQLite database
* @param string $organism_name - Optional: Filter by organism
* @return array - Array of matching features
*/',
      'code' => 'function searchFeaturesByUniquenameForSearch($search_term, $dbFile, $organism_name = \'\') {
    if ($organism_name) {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM feature f, organism o, genome g
                  WHERE f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND f.feature_uniquename LIKE ? 
                    AND (o.genus || \' \' || o.species = ?)
                  ORDER BY f.feature_uniquename
                  LIMIT 100";
        $params = ["%$search_term%", $organism_name];
    } else {
        $query = "SELECT f.feature_uniquename, f.feature_name, f.feature_description, 
                         o.genus, o.species, o.common_name, o.subtype, f.feature_type, f.organism_id,
                         g.genome_accession
                  FROM feature f, organism o, genome g
                  WHERE f.organism_id = o.organism_id
                    AND f.genome_id = g.genome_id
                    AND f.feature_uniquename LIKE ?
                  ORDER BY f.feature_uniquename
                  LIMIT 100";
        $params = ["%$search_term%"];
    }
    
    return fetchData($query, $dbFile, $params);
}',
    ),
  ),
  'lib/extract_search_helpers.php' => 
  array (
    0 => 
    array (
      'name' => 'parseOrganismParameter',
      'line' => 29,
      'comment' => '/**
* Parse organism parameter from various sources and formats
*
* Handles multiple input formats:
* - Array from multi-search context (organisms[])
* - Single organism from context parameters
* - Comma-separated string
*
* @param string|array $organisms_param - Raw parameter value
* @param string $context_organism - Optional fallback organism
* @return array - [\'organisms\' => [], \'string\' => \'comma,separated,list\']
*/',
      'code' => 'function parseOrganismParameter($organisms_param, $context_organism = \'\') {
    $filter_organisms = [];
    $filter_organisms_string = \'\';
    
    // First check for array (highest priority - from multi-search)
    if (is_array($organisms_param)) {
        $filter_organisms = array_filter($organisms_param);
        $filter_organisms_string = implode(\',\', $filter_organisms);
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
            $filter_organisms = array_map(\'trim\', explode(\',\', $filter_organisms_string));
            $filter_organisms = array_filter($filter_organisms);
        }
    }
    
    return [
        \'organisms\' => $filter_organisms,
        \'string\' => $filter_organisms_string
    ];
}',
    ),
    1 => 
    array (
      'name' => 'parseContextParameters',
      'line' => 65,
      'comment' => '/**
* Extract context parameters from request
*
* Checks explicit context_* fields first (highest priority), then regular fields as fallback
*
* @return array - [\'organism\' => \'\', \'assembly\' => \'\', \'group\' => \'\', \'display_name\' => \'\', \'context_page\' => \'\']
*/',
      'code' => 'function parseContextParameters() {
    return [
        \'organism\' => trim($_GET[\'context_organism\'] ?? $_POST[\'context_organism\'] ?? $_GET[\'organism\'] ?? $_POST[\'organism\'] ?? \'\'),
        \'assembly\' => trim($_GET[\'context_assembly\'] ?? $_POST[\'context_assembly\'] ?? $_GET[\'assembly\'] ?? $_POST[\'assembly\'] ?? \'\'),
        \'group\' => trim($_GET[\'context_group\'] ?? $_POST[\'context_group\'] ?? $_GET[\'group\'] ?? $_POST[\'group\'] ?? \'\'),
        \'display_name\' => trim($_GET[\'display_name\'] ?? $_POST[\'display_name\'] ?? \'\'),
        \'context_page\' => trim($_GET[\'context_page\'] ?? $_POST[\'context_page\'] ?? \'\')
    ];
}',
    ),
    2 => 
    array (
      'name' => 'validateExtractInputs',
      'line' => 86,
      'comment' => '/**
* Validate extract/search inputs (organism, assembly, feature IDs)
*
* Comprehensive validation for extract operations
*
* @param string $organism - Organism name
* @param string $assembly - Assembly name
* @param string $uniquenames_string - Comma-separated feature IDs
* @param array $accessible_sources - Available assemblies from getAccessibleAssemblies()
* @return array - [\'valid\' => bool, \'errors\' => [], \'fasta_source\' => null]
*/',
      'code' => 'function validateExtractInputs($organism, $assembly, $uniquenames_string, $accessible_sources) {
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
            if ($source[\'assembly\'] === $assembly && $source[\'organism\'] === $organism) {
                $fasta_source = $source;
                break;
            }
        }
        
        if (!$fasta_source) {
            $errors[] = "You do not have access to the selected assembly.";
        }
    }
    
    return [
        \'valid\' => empty($errors),
        \'errors\' => $errors,
        \'fasta_source\' => $fasta_source
    ];
}',
    ),
    3 => 
    array (
      'name' => 'parseFeatureIds',
      'line' => 128,
      'comment' => '/**
* Parse and validate feature IDs from user input
*
* Handles both comma and newline separated formats
*
* @param string $uniquenames_string - Comma or newline separated IDs
* @return array - [\'valid\' => bool, \'uniquenames\' => [], \'error\' => \'\']
*/',
      'code' => 'function parseFeatureIds($uniquenames_string) {
    $uniquenames = [];
    
    if (empty($uniquenames_string)) {
        return [\'valid\' => false, \'uniquenames\' => [], \'error\' => \'No feature IDs provided\'];
    }
    
    // Handle both comma and newline separated formats
    $uniquenames = array_filter(array_map(\'trim\', 
        preg_split(\'/[\\n,]+/\', $uniquenames_string)
    ));
    
    if (empty($uniquenames)) {
        return [\'valid\' => false, \'uniquenames\' => [], \'error\' => \'No valid feature IDs found\'];
    }
    
    return [\'valid\' => true, \'uniquenames\' => $uniquenames, \'error\' => \'\'];
}',
    ),
    4 => 
    array (
      'name' => 'extractSequencesForAllTypes',
      'line' => 157,
      'comment' => '/**
* Extract sequences for all available types from BLAST database
*
* Iterates through all sequence types and extracts for the given feature IDs
*
* @param string $assembly_dir - Path to assembly directory
* @param array $uniquenames - Feature IDs to extract
* @param array $sequence_types - Available sequence type configurations (from site_config)
* @return array - [\'success\' => bool, \'content\' => [...], \'errors\' => []]
*/',
      'code' => 'function extractSequencesForAllTypes($assembly_dir, $uniquenames, $sequence_types) {
    $displayed_content = [];
    $errors = [];
    
    foreach ($sequence_types as $seq_type => $config) {
        $files = glob("$assembly_dir/*{$config[\'pattern\']}");
        
        if (!empty($files)) {
            $fasta_file = $files[0];
            $extract_result = extractSequencesFromBlastDb($fasta_file, $uniquenames);
            
            if ($extract_result[\'success\']) {
                // Remove blank lines
                $lines = explode("\\n", $extract_result[\'content\']);
                $lines = array_filter($lines, function($line) {
                    return trim($line) !== \'\';
                });
                $displayed_content[$seq_type] = implode("\\n", $lines);
            } else {
                $errors[] = "Failed to extract $seq_type sequences";
            }
        }
    }
    
    return [
        \'success\' => !empty($displayed_content),
        \'content\' => $displayed_content,
        \'errors\' => $errors
    ];
}',
    ),
    5 => 
    array (
      'name' => 'formatSequenceResults',
      'line' => 197,
      'comment' => '/**
* Format extracted sequences for display component
*
* Converts extracted content into format expected by sequences_display.php
*
* @param array $displayed_content - Extracted sequences by type
* @param array $sequence_types - Type configurations (from site_config)
* @return array - Formatted for sequences_display.php inclusion
*/',
      'code' => 'function formatSequenceResults($displayed_content, $sequence_types) {
    $available_sequences = [];
    
    foreach ($displayed_content as $seq_type => $content) {
        $available_sequences[$seq_type] = [
            \'label\' => $sequence_types[$seq_type][\'label\'] ?? ucfirst($seq_type),
            \'sequences\' => [$content]  // Wrap in array as sequences_display expects
        ];
    }
    
    return $available_sequences;
}',
    ),
    6 => 
    array (
      'name' => 'sendFileDownload',
      'line' => 220,
      'comment' => '/**
* Send file download response and exit
*
* Sets appropriate headers and outputs file content
* Should be called before any HTML output
*
* @param string $content - File content to download
* @param string $sequence_type - Type of sequence (for filename)
* @param string $file_format - Format (fasta or txt)
*/',
      'code' => 'function sendFileDownload($content, $sequence_type, $file_format = \'fasta\') {
    $ext = ($file_format === \'txt\') ? \'txt\' : \'fasta\';
    $filename = "sequences_{$sequence_type}_" . date("Y-m-d_His") . ".{$ext}";
    
    header(\'Content-Type: application/octet-stream\');
    header("Content-Disposition: attachment; filename={$filename}");
    header(\'Content-Length: \' . strlen($content));
    echo $content;
    exit;
}',
    ),
    7 => 
    array (
      'name' => 'buildFilteredSourcesList',
      'line' => 240,
      'comment' => '/**
* Build organism-filtered list of accessible assembly sources
*
* Filters nested sources array by organism list
*
* @param array $sources_by_group - Nested array from getAccessibleAssemblies()
* @param array $filter_organisms - Optional organism filter list
* @return array - Nested array [group][organism][...assemblies]
*/',
      'code' => 'function buildFilteredSourcesList($sources_by_group, $filter_organisms = []) {
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
}',
    ),
    8 => 
    array (
      'name' => 'flattenSourcesList',
      'line' => 269,
      'comment' => '/**
* Flatten nested sources array for sequential processing
*
* Converts nested [group][organism][...sources] structure to flat list
* Useful for iterating all sources without nested loops
*
* @param array $sources_by_group - Nested array from getAccessibleAssemblies()
* @return array - Flat list of all sources
*/',
      'code' => 'function flattenSourcesList($sources_by_group) {
    $accessible_sources = [];
    
    foreach ($sources_by_group as $group => $organisms) {
        foreach ($organisms as $org => $assemblies) {
            $accessible_sources = array_merge($accessible_sources, $assemblies);
        }
    }
    
    return $accessible_sources;
}',
    ),
    9 => 
    array (
      'name' => 'assignGroupColors',
      'line' => 290,
      'comment' => '/**
* Assign Bootstrap colors to groups for consistent UI display
*
* Uses Bootstrap color palette cyclically across groups
* Same group always gets same color (idempotent)
*
* @param array $sources_by_group - Groups to assign colors to
* @return array - [group_name => bootstrap_color]
*/',
      'code' => 'function assignGroupColors($sources_by_group) {
    $group_colors = [\'primary\', \'success\', \'info\', \'warning\', \'danger\', \'secondary\', \'dark\'];
    $group_color_map = [];
    
    foreach ($sources_by_group as $group_name => $organisms) {
        if (!isset($group_color_map[$group_name])) {
            $group_color_map[$group_name] = $group_colors[count($group_color_map) % count($group_colors)];
        }
    }
    
    return $group_color_map;
}',
    ),
    10 => 
    array (
      'name' => 'getAvailableSequenceTypesForDisplay',
      'line' => 313,
      'comment' => '/**
* Get available sequence types from all accessible sources
*
* Scans assembly directories to determine which sequence types are available
* Useful for populating UI dropdowns/display options
*
* @param array $accessible_sources - Flattened list of sources
* @param array $sequence_types - Type configurations (from site_config)
* @return array - [type => label] for types that have available files
*/',
      'code' => 'function getAvailableSequenceTypesForDisplay($accessible_sources, $sequence_types) {
    $available_types = [];
    
    foreach ($accessible_sources as $source) {
        foreach ($sequence_types as $seq_type => $config) {
            $files = glob($source[\'path\'] . "/*{$config[\'pattern\']}");
            if (!empty($files)) {
                $available_types[$seq_type] = $config[\'label\'];
            }
        }
    }
    
    return $available_types;
}',
    ),
  ),
  'lib/functions_access.php' => 
  array (
    0 => 
    array (
      'name' => 'getAccessibleAssemblies',
      'line' => 15,
      'comment' => '/**
* Get assemblies accessible to current user
* Filters assemblies based on user access level and group membership
*
* @param string $specific_organism Optional organism to filter by
* @param string $specific_assembly Optional assembly to filter by
* @return array Organized by group -> organism, or assemblies for specific organism/assembly
*/',
      'code' => 'function getAccessibleAssemblies($specific_organism = null, $specific_assembly = null) {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath(\'organism_data\');
    $metadata_path = $config->getPath(\'metadata_path\');
    
    // Load groups data
    $groups_data = [];
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true) ?: [];
    }
    
    $accessible_sources = [];
    
    // Filter entries based on referrer (specific org/assembly or all)
    $entries_to_process = $groups_data;
    
    if (!empty($specific_organism)) {
        $entries_to_process = array_filter($groups_data, function($entry) use ($specific_organism) {
            return $entry[\'organism\'] === $specific_organism;
        });
    }
    
    if (!empty($specific_assembly)) {
        $entries_to_process = array_filter($entries_to_process, function($entry) use ($specific_assembly) {
            return $entry[\'assembly\'] === $specific_assembly;
        });
    }
    
    // Build list of accessible sources using assembly-based permissions
    foreach ($entries_to_process as $entry) {
        $org = $entry[\'organism\'];
        $assembly = $entry[\'assembly\'];
        $entry_groups = $entry[\'groups\'] ?? [];
        
        // Check if user has access to this specific assembly
        // 1. ALL/Admin users have access to everything
        // 2. Public assemblies are accessible to everyone
        // 3. Collaborators can access assemblies in their $_SESSION[\'access\'] list
        $access_granted = false;
        
        if (has_access(\'ALL\')) {
            $access_granted = true;
        } elseif (is_public_assembly($org, $assembly)) {
            $access_granted = true;
        } elseif (has_access(\'Collaborator\')) {
            // Check if user has access to this specific assembly
            $user_access = get_user_access();
            if (isset($user_access[$org]) && is_array($user_access[$org]) && in_array($assembly, $user_access[$org])) {
                $access_granted = true;
            }
        }
        
        if ($access_granted) {
            $assembly_path = "$organism_data/$org/$assembly";
            
            // Only include assembly if directory exists AND has FASTA files
            if (is_dir($assembly_path)) {
                // Check if assembly has any FASTA files (protein, transcript, cds, or genome)
                $has_fasta = false;
                foreach ([\'.fa\', \'.fasta\', \'.faa\', \'.nt.fa\', \'.aa.fa\'] as $ext) {
                    if (glob("$assembly_path/*$ext")) {
                        $has_fasta = true;
                        break;
                    }
                }
                
                if ($has_fasta) {
                    $accessible_sources[] = [
                        \'organism\' => $org,
                        \'assembly\' => $assembly,
                        \'path\' => $assembly_path,
                        \'groups\' => $entry_groups
                    ];
                }
            }
        }
    }
    
    // Organize by group -> organism
    $organized = [];
    foreach ($accessible_sources as $source) {
        foreach ($source[\'groups\'] as $group) {
            if (!isset($organized[$group])) {
                $organized[$group] = [];
            }
            $org = $source[\'organism\'];
            if (!isset($organized[$group][$org])) {
                $organized[$group][$org] = [];
            }
            $organized[$group][$org][] = $source;
        }
    }
    
    // Sort groups (Public first, then alphabetically)
    uksort($organized, function($a, $b) {
        if ($a === \'Public\') return -1;
        if ($b === \'Public\') return 1;
        return strcasecmp($a, $b);
    });
    
    // Sort organisms within each group alphabetically
    foreach ($organized as &$group_data) {
        ksort($group_data);
    }
    
    return $organized;
}',
    ),
    1 => 
    array (
      'name' => 'getPhyloTreeUserAccess',
      'line' => 131,
      'comment' => '/**
* Get phylogenetic tree user access for display
* Returns organisms accessible to current user for phylo tree display
*
* @param array $group_data Array of organism/assembly/groups data
* @return array Array of accessible organisms with true value
*/',
      'code' => 'function getPhyloTreeUserAccess($group_data) {
    $phylo_user_access = [];
    
    if (get_access_level() === \'ALL\' || get_access_level() === \'Admin\') {
        // Admin gets access to all organisms
        foreach ($group_data as $data) {
            $organism = $data[\'organism\'];
            if (!isset($phylo_user_access[$organism])) {
                $phylo_user_access[$organism] = true;
            }
        }
    } elseif (is_logged_in()) {
        // Logged-in users get their specific access
        $phylo_user_access = get_user_access();
    } else {
        // Public users: get organisms in Public group
        foreach ($group_data as $data) {
            if (in_array(\'Public\', $data[\'groups\'])) {
                $organism = $data[\'organism\'];
                if (!isset($phylo_user_access[$organism])) {
                    $phylo_user_access[$organism] = true;
                }
            }
        }
    }
    
    return $phylo_user_access;
}',
    ),
    2 => 
    array (
      'name' => 'requireAccess',
      'line' => 170,
      'comment' => '/**
* Require user to have specific access level or redirect to access denied
*
* @param string $level Required access level (e.g., \'Collaborator\', \'Admin\')
* @param string $resource Resource name (e.g., group name or organism name)
* @param array $options Options array with keys:
*   - redirect_on_deny (bool, default: true) - Redirect to deny page if no access
*   - deny_page (string, default: /$site/access_denied.php) - URL to redirect to
* @return bool True if user has access, false otherwise
*/',
      'code' => 'function requireAccess($level, $resource, $options = []) {
    global $site;
    
    $redirect_on_deny = $options[\'redirect_on_deny\'] ?? true;
    $deny_page = $options[\'deny_page\'] ?? "/$site/access_denied.php";
    
    $has_access = has_access($level, $resource);
    
    if (!$has_access && $redirect_on_deny) {
        header("Location: $deny_page");
        exit;
    }
    
    return $has_access;
}',
    ),
  ),
  'lib/functions_data.php' => 
  array (
    0 => 
    array (
      'name' => 'getGroupData',
      'line' => 12,
      'comment' => '/**
* Get group metadata from organism_assembly_groups.json
*
* @return array Array of organism/assembly/groups data
*/',
      'code' => 'function getGroupData() {
    $config = ConfigManager::getInstance();
    $metadata_path = $config->getPath(\'metadata_path\');
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    $groups_data = [];
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true);
    }
    return $groups_data;
}',
    ),
    1 => 
    array (
      'name' => 'getAllGroupCards',
      'line' => 30,
      'comment' => '/**
* Get all group cards from metadata
* Returns card objects for every group in the system
*
* @param array $group_data Array of organism/assembly/groups data
* @return array Associative array of group_name => card_info
*/',
      'code' => 'function getAllGroupCards($group_data) {
    $cards = [];
    foreach ($group_data as $data) {
        foreach ($data[\'groups\'] as $group) {
            if (!isset($cards[$group])) {
                $cards[$group] = [
                    \'title\' => $group,
                    \'text\' => "Explore $group Data",
                    \'link\' => \'tools/groups_display.php?group=\' . urlencode($group)
                ];
            }
        }
    }
    return $cards;
}',
    ),
    2 => 
    array (
      'name' => 'getPublicGroupCards',
      'line' => 53,
      'comment' => '/**
* Get group cards that have at least one public assembly
* Returns card objects only for groups containing assemblies in the "Public" group
*
* @param array $group_data Array of organism/assembly/groups data
* @return array Associative array of group_name => card_info for public groups only
*/',
      'code' => 'function getPublicGroupCards($group_data) {
    $public_groups = [];
    
    // Find all groups that contain at least one public assembly
    foreach ($group_data as $data) {
        if (in_array(\'Public\', $data[\'groups\'])) {
            foreach ($data[\'groups\'] as $group) {
                if (!isset($public_groups[$group])) {
                    $public_groups[$group] = [
                        \'title\' => $group,
                        \'text\' => "Explore $group Data",
                        \'link\' => \'tools/groups_display.php?group=\' . urlencode($group)
                    ];
                }
            }
        }
    }
    return $public_groups;
}',
    ),
    3 => 
    array (
      'name' => 'getAccessibleOrganismsInGroup',
      'line' => 81,
      'comment' => '/**
* Filter organisms in a group to only those with at least one accessible assembly
* Respects user permissions for assembly access
*
* @param string $group_name The group name to filter
* @param array $group_data Array of organism/assembly/groups data
* @return array Filtered array of organism => [accessible_assemblies]
*/',
      'code' => 'function getAccessibleOrganismsInGroup($group_name, $group_data) {
    $group_organisms = [];
    
    // Find all organisms/assemblies in this group
    foreach ($group_data as $data) {
        if (in_array($group_name, $data[\'groups\'])) {
            $organism = $data[\'organism\'];
            $assembly = $data[\'assembly\'];
            
            if (!isset($group_organisms[$organism])) {
                $group_organisms[$organism] = [];
            }
            $group_organisms[$organism][] = $assembly;
        }
    }
    
    // Filter: only keep organisms with at least one accessible assembly
    $accessible_organisms = [];
    foreach ($group_organisms as $organism => $assemblies) {
        $has_accessible_assembly = false;
        
        foreach ($assemblies as $assembly) {
            // Check if user has access to this specific assembly
            if (has_assembly_access($organism, $assembly)) {
                $has_accessible_assembly = true;
                break;
            }
        }
        
        if ($has_accessible_assembly) {
            $accessible_organisms[$organism] = $assemblies;
        }
    }
    
    // Sort organisms alphabetically
    ksort($accessible_organisms);
    
    return $accessible_organisms;
}',
    ),
    4 => 
    array (
      'name' => 'getAssemblyFastaFiles',
      'line' => 131,
      'comment' => '/**
* Get FASTA files for an assembly
*
* Scans the assembly directory for FASTA files matching configured sequence types.
* Uses patterns from $sequence_types global to identify file types (genome, protein, transcript, cds).
*
* @param string $organism_name The organism name
* @param string $assembly_name The assembly name (accession)
* @return array Associative array of type => [\'path\' => relative_path, \'label\' => label]
*/',
      'code' => 'function getAssemblyFastaFiles($organism_name, $assembly_name) {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath(\'organism_data\');
    $sequence_types = $config->getSequenceTypes();
    $fasta_files = [];
    $assembly_dir = "$organism_data/$organism_name/$assembly_name";
    
    if (is_dir($assembly_dir)) {
        $fasta_files_found = glob($assembly_dir . \'/*.fa\');
        foreach ($fasta_files_found as $fasta_file) {
            $filename = basename($fasta_file);
            $relative_path = "$organism_name/$assembly_name/$filename";
            
            foreach ($sequence_types as $type => $config) {
                if (strpos($filename, $config[\'pattern\']) !== false) {
                    $fasta_files[$type] = [
                        \'path\' => $relative_path,
                        \'label\' => $config[\'label\']
                    ];
                    break;
                }
            }
        }
    }
    return $fasta_files;
}',
    ),
    5 => 
    array (
      'name' => 'getIndexDisplayCards',
      'line' => 164,
      'comment' => '/**
* Get cards to display on index page based on user access level
*
* @param array $group_data Array of group data from getGroupData()
* @return array Cards to display with title, text, and link
*/',
      'code' => 'function getIndexDisplayCards($group_data) {
    $cards_to_display = [];
    $all_cards = getAllGroupCards($group_data);
    
    if (get_access_level() === \'ALL\' || get_access_level() === \'Admin\') {
        $cards_to_display = $all_cards;
    } elseif (is_logged_in()) {
        // Logged-in users see: public groups + their permitted organisms
        $cards_to_display = getPublicGroupCards($group_data);
        
        foreach (get_user_access() as $organism => $assemblies) {
            if (!isset($cards_to_display[$organism])) {
                $formatted_name = formatIndexOrganismName($organism);
                $cards_to_display[$organism] = [
                    \'title\' => $formatted_name,
                    \'text\'  => "Explore " . strip_tags($formatted_name) . " Data",
                    \'link\'  => \'tools/organism_display.php?organism=\' . urlencode($organism)
                ];
            }
        }
    } else {
        // Visitors see only groups with public assemblies
        $cards_to_display = getPublicGroupCards($group_data);
    }
    
    return $cards_to_display;
}',
    ),
    6 => 
    array (
      'name' => 'formatIndexOrganismName',
      'line' => 176,
      'comment' => '/**
* Format organism name for index page display with italics
*
* @param string $organism Organism name with underscores
* @return string Formatted name with proper capitalization and italics
*/',
      'code' => 'function formatIndexOrganismName($organism) {
    $parts = explode(\'_\', $organism);
    $formatted_name = ucfirst(strtolower($parts[0]));
    for ($i = 1; $i < count($parts); $i++) {
        $formatted_name .= \' \' . strtolower($parts[$i]);
    }
    return \'<i>\' . $formatted_name . \'</i>\';
}',
    ),
  ),
  'lib/functions_database.php' => 
  array (
    0 => 
    array (
      'name' => 'validateDatabaseFile',
      'line' => 13,
      'comment' => '/**
* Validates database file is readable and accessible
*
* @param string $dbFile - Path to SQLite database file
* @return array - Validation results with \'valid\' and \'error\' keys
*/',
      'code' => 'function validateDatabaseFile($dbFile) {
    if (!file_exists($dbFile)) {
        return [\'valid\' => false, \'error\' => \'Database file not found\'];
    }
    
    if (!is_readable($dbFile)) {
        return [\'valid\' => false, \'error\' => \'Database file not readable (permission denied)\'];
    }
    
    try {
        $db = new PDO(\'sqlite:\' . $dbFile);
        $db = null;
        return [\'valid\' => true, \'error\' => \'\'];
    } catch (Exception $e) {
        return [\'valid\' => false, \'error\' => $e->getMessage()];
    }
}',
    ),
    1 => 
    array (
      'name' => 'validateDatabaseIntegrity',
      'line' => 44,
      'comment' => '/**
* Validate database integrity and data quality
*
* Checks:
* - File is readable
* - Valid SQLite database
* - All required tables exist
* - Tables have data
* - Data completeness (no orphaned records)
*
* @param string $dbFile - Path to SQLite database file
* @return array - Validation results with status and details
*/',
      'code' => 'function validateDatabaseIntegrity($dbFile) {
    $result = [
        \'valid\' => false,
        \'readable\' => false,
        \'database_valid\' => false,
        \'tables_present\' => [],
        \'tables_missing\' => [],
        \'row_counts\' => [],
        \'data_issues\' => [],
        \'errors\' => []
    ];
    
    // Check if file exists and is readable
    if (!file_exists($dbFile)) {
        $result[\'errors\'][] = \'Database file not found\';
        return $result;
    }
    
    if (!is_readable($dbFile)) {
        $result[\'errors\'][] = \'Database file not readable (permission denied)\';
        return $result;
    }
    
    $result[\'readable\'] = true;
    
    // Try to connect to database
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $result[\'errors\'][] = \'Invalid SQLite database: \' . $e->getMessage();
        return $result;
    }
    
    $result[\'database_valid\'] = true;
    
    // Required tables based on schema
    $required_tables = [
        \'organism\',
        \'genome\',
        \'feature\',
        \'annotation_source\',
        \'annotation\',
        \'feature_annotation\'
    ];
    
    // Check which tables exist
    try {
        $stmt = $dbh->query("SELECT name FROM sqlite_master WHERE type=\'table\'");
        $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($required_tables as $table) {
            if (in_array($table, $existing_tables)) {
                $result[\'tables_present\'][] = $table;
            } else {
                $result[\'tables_missing\'][] = $table;
            }
        }
    } catch (PDOException $e) {
        $result[\'errors\'][] = \'Cannot query tables: \' . $e->getMessage();
        $dbh = null;
        return $result;
    }
    
    // Check row counts and data quality
    try {
        foreach ($result[\'tables_present\'] as $table) {
            $stmt = $dbh->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            $result[\'row_counts\'][$table] = $count;
        }
        
        // Check for data quality issues
        
        // 1. Check for annotations without sources (orphaned)
        if (in_array(\'annotation\', $result[\'tables_present\']) && in_array(\'annotation_source\', $result[\'tables_present\'])) {
            $stmt = $dbh->query("
                SELECT COUNT(*) FROM annotation a 
                LEFT JOIN annotation_source ans ON a.annotation_source_id = ans.annotation_source_id 
                WHERE ans.annotation_source_id IS NULL
            ");
            $orphaned_count = $stmt->fetchColumn();
            if ($orphaned_count > 0) {
                $result[\'data_issues\'][] = "Orphaned annotations (no source): $orphaned_count";
            }
        }
        
        // 2. Check for incomplete annotations (missing accession or description)
        if (in_array(\'annotation\', $result[\'tables_present\'])) {
            $stmt = $dbh->query("
                SELECT COUNT(*) FROM annotation 
                WHERE annotation_accession IS NULL OR annotation_accession = \'\'
            ");
            $missing_accession = $stmt->fetchColumn();
            if ($missing_accession > 0) {
                $result[\'data_issues\'][] = "Annotations with missing accession: $missing_accession";
            }
        }
        
        // 3. Check for features without organisms
        if (in_array(\'feature\', $result[\'tables_present\']) && in_array(\'organism\', $result[\'tables_present\'])) {
            $stmt = $dbh->query("
                SELECT COUNT(*) FROM feature f 
                LEFT JOIN organism o ON f.organism_id = o.organism_id 
                WHERE o.organism_id IS NULL
            ");
            $orphaned_features = $stmt->fetchColumn();
            if ($orphaned_features > 0) {
                $result[\'data_issues\'][] = "Features without organism: $orphaned_features";
            }
        }
        
    } catch (PDOException $e) {
        $result[\'errors\'][] = \'Data quality check failed: \' . $e->getMessage();
    }
    
    $dbh = null;
    
    // Determine overall validity
    $result[\'valid\'] = (
        $result[\'readable\'] && 
        $result[\'database_valid\'] && 
        empty($result[\'tables_missing\']) && 
        empty($result[\'data_issues\']) &&
        empty($result[\'errors\'])
    );
    
    return $result;
}',
    ),
    2 => 
    array (
      'name' => 'getDbConnection',
      'line' => 181,
      'comment' => '/**
* Get database connection
*
* @param string $dbFile - Path to SQLite database file
* @return PDO - Database connection
* @throws PDOException if connection fails
*/',
      'code' => 'function getDbConnection($dbFile) {
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $dbh;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}',
    ),
    3 => 
    array (
      'name' => 'fetchData',
      'line' => 200,
      'comment' => '/**
* Execute SQL query with prepared statement
*
* @param string $sql - SQL query with ? placeholders
* @param string $dbFile - Path to SQLite database file
* @param array $params - Parameters to bind to query (optional)
* @return array - Array of associative arrays (results)
* @throws PDOException if query fails
*/',
      'code' => 'function fetchData($sql, $dbFile, $params = []) {
    try {
        $dbh = getDbConnection($dbFile);
        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbh = null;
        return $result;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}',
    ),
    4 => 
    array (
      'name' => 'buildLikeConditions',
      'line' => 235,
      'comment' => '/**
* Build SQL LIKE conditions for multi-column search
* Supports both quoted (phrase) and unquoted (word-by-word) searches
*
* Creates SQL WHERE clause fragments for searching multiple columns.
* Supports both keyword search (AND logic) and quoted phrase search.
*
* Keyword search: "ABC transporter"
*   - Splits into terms: ["ABC", "transporter"]
*   - Logic: (col1 LIKE \'%ABC%\' OR col2 LIKE \'%ABC%\') AND (col1 LIKE \'%transporter%\' OR col2 LIKE \'%transporter%\')
*   - Result: Both terms must match somewhere
*
* Quoted search: \'"ABC transporter"\'
*   - Keeps as single phrase: "ABC transporter"
*   - Logic: (col1 LIKE \'%ABC transporter%\' OR col2 LIKE \'%ABC transporter%\')
*   - Result: Exact phrase must match
*
* @param array $columns - Column names to search
* @param string $search - Search string (unquoted: words separated by space, quoted: single phrase)
* @param bool $quoted - If true, treat entire $search as single phrase; if false, split on whitespace
* @return array - [$sqlFragment, $params] for use with fetchData()
*/',
      'code' => 'function buildLikeConditions($columns, $search, $quoted = false) {
    $conditions = [];
    $params = [];

    if ($quoted) {
        $searchConditions = [];
        foreach ($columns as $col) {
            $searchConditions[] = "$col LIKE ?";
            $params[] = "%" . $search . "%";
        }
        $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
    } else {
        $terms = preg_split(\'/\\s+/\', trim($search));
        foreach ($terms as $term) {
            if (empty($term)) continue;
            $termConditions = [];
            foreach ($columns as $col) {
                $termConditions[] = "$col LIKE ?";
                $params[] = "%" . $term . "%";
            }
            $conditions[] = "(" . implode(" OR ", $termConditions) . ")";
        }
    }

    $sqlFragment = implode(" AND ", $conditions);
    return [$sqlFragment, $params];
}',
    ),
    5 => 
    array (
      'name' => 'getAccessibleGenomeIds',
      'line' => 271,
      'comment' => '/**
* Get accessible genome IDs from database for organism
*
* @param string $organism_name - Organism name
* @param array $accessible_assemblies - List of accessible assembly names
* @param string $db_path - Path to SQLite database file
* @return array - Array of genome IDs
*/',
      'code' => 'function getAccessibleGenomeIds($organism_name, $accessible_assemblies, $db_path) {
    if (empty($accessible_assemblies)) {
        return [];
    }
    
    $placeholders = implode(\',\', array_fill(0, count($accessible_assemblies), \'?\'));
    $query = "SELECT DISTINCT genome_id FROM genome 
              WHERE genome_name IN ($placeholders) 
              OR genome_accession IN ($placeholders)";
    
    // Pass accessible_assemblies twice - once for names, once for accessions
    $params = array_merge($accessible_assemblies, $accessible_assemblies);
    $results = fetchData($query, $db_path, $params);
    
    return array_column($results, \'genome_id\');
}',
    ),
    6 => 
    array (
      'name' => 'loadOrganismInfo',
      'line' => 295,
      'comment' => '/**
* Load organism info from organism.json file
*
* @param string $organism_name - Organism name
* @param string $organism_data_dir - Path to organism data directory
* @return array|null - Organism info array or null if not found
*/',
      'code' => 'function loadOrganismInfo($organism_name, $organism_data_dir) {
    $organism_json_path = "$organism_data_dir/$organism_name/organism.json";
    $organism_info = loadJsonFile($organism_json_path);
    
    if (!$organism_info) {
        return null;
    }
    
    // Handle improperly wrapped JSON (extra outer braces)
    if (!isset($organism_info[\'genus\']) && !isset($organism_info[\'common_name\'])) {
        $keys = array_keys($organism_info);
        if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]][\'genus\'])) {
            $organism_info = $organism_info[$keys[0]];
        }
    }
    
    // Validate required fields
    if (!isset($organism_info[\'common_name\']) && !isset($organism_info[\'genus\'])) {
        return null;
    }
    
    return $organism_info;
}',
    ),
    7 => 
    array (
      'name' => 'verifyOrganismDatabase',
      'line' => 326,
      'comment' => '/**
* Verify organism database file exists
*
* @param string $organism_name - Organism name
* @param string $organism_data_dir - Path to organism data directory
* @return string - Database path if exists, exits with error if not
*/',
      'code' => 'function verifyOrganismDatabase($organism_name, $organism_data_dir) {
    $db_path = "$organism_data_dir/$organism_name/organism.sqlite";
    
    if (!file_exists($db_path)) {
        header("HTTP/1.1 500 Internal Server Error");
        die("Error: Database not found for organism \'$organism_name\'. Please ensure the organism is properly configured.");
    }
    
    return $db_path;
}',
    ),
  ),
  'lib/functions_display.php' => 
  array (
    0 => 
    array (
      'name' => 'loadOrganismAndGetImagePath',
      'line' => 18,
      'comment' => '/**
* Load organism info and get image path
*
* Loads organism.json file and returns the image path using getOrganismImagePath()
* Encapsulates all the loading logic in one place.
*
* @param string $organism_name The organism name
* @param string $images_path URL path to images directory (e.g., \'moop/images\')
* @param string $absolute_images_path Absolute file system path to images directory
* @return array [\'organism_info\' => array, \'image_path\' => string]
*/',
      'code' => 'function loadOrganismAndGetImagePath($organism_name, $images_path = \'moop/images\', $absolute_images_path = \'\') {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath(\'organism_data\');
    
    $result = [
        \'organism_info\' => [],
        \'image_path\' => \'\'
    ];
    
    $organism_json_path = "$organism_data/$organism_name/organism.json";
    if (file_exists($organism_json_path)) {
        $organism_info = json_decode(file_get_contents($organism_json_path), true);
        if ($organism_info) {
            $result[\'organism_info\'] = $organism_info;
            $result[\'image_path\'] = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
        }
    }
    
    return $result;
}',
    ),
    1 => 
    array (
      'name' => 'getOrganismImagePath',
      'line' => 10,
      'comment' => '/**
* Get organism image file path
*
* Returns the URL path to an organism\'s image with fallback logic:
* 1. Custom image from organism.json if defined
* 2. NCBI taxonomy image if taxon_id exists and image file found
* 3. Empty string if no image available
*
* @param array $organism_info Array from organism.json with keys: images, taxon_id
* @param string $images_path URL path to images directory (e.g., \'moop/images\')
* @param string $absolute_images_path Absolute file system path to images directory
* @return string URL path to image file or empty string if no image
*/',
      'code' => 'function getOrganismImagePath($organism_info, $images_path = \'moop/images\', $absolute_images_path = \'\') {
    // Validate input
    if (empty($organism_info) || !is_array($organism_info)) {
        logError(\'getOrganismImagePath received invalid organism_info\', \'organism_image\', [
            \'organism_info_type\' => gettype($organism_info),
            \'organism_info_empty\' => empty($organism_info)
        ]);
        return \'\';
    }
    
    // Check for custom image first
    if (!empty($organism_info[\'images\']) && is_array($organism_info[\'images\'])) {
        return "/$images_path/" . htmlspecialchars($organism_info[\'images\'][0][\'file\']);
    }
    
    // Fall back to NCBI taxonomy image if taxon_id exists
    if (!empty($organism_info[\'taxon_id\'])) {
        // Construct path - use absolute_images_path if provided, otherwise fall back
        if (!empty($absolute_images_path)) {
            $ncbi_image_file = "$absolute_images_path/ncbi_taxonomy/" . $organism_info[\'taxon_id\'] . \'.jpg\';
        } else {
            $ncbi_image_file = __DIR__ . \'/../../images/ncbi_taxonomy/\' . $organism_info[\'taxon_id\'] . \'.jpg\';
        }
        
        if (file_exists($ncbi_image_file)) {
            return "/moop/images/ncbi_taxonomy/" . $organism_info[\'taxon_id\'] . ".jpg";
        } else {
            logError(\'NCBI taxonomy image not found\', \'organism_image\', [
                \'taxon_id\' => $organism_info[\'taxon_id\'],
                \'expected_path\' => $ncbi_image_file
            ]);
        }
    }
    
    return \'\';
}',
    ),
    2 => 
    array (
      'name' => 'getOrganismImageCaption',
      'line' => 100,
      'comment' => '/**
* Get organism image caption with optional link
*
* Returns display caption for organism image:
* - Custom images: caption from organism.json or empty string
* - NCBI taxonomy fallback: "Image from NCBI Taxonomy" with link to NCBI
*
* @param array $organism_info Array from organism.json with keys: images, taxon_id
* @param string $absolute_images_path Absolute file system path to images directory
* @return array [\'caption\' => caption text, \'link\' => URL or empty string]
*/',
      'code' => 'function getOrganismImageCaption($organism_info, $absolute_images_path = \'\') {
    $result = [
        \'caption\' => \'\',
        \'link\' => \'\'
    ];
    
    // Validate input
    if (empty($organism_info) || !is_array($organism_info)) {
        logError(\'getOrganismImageCaption received invalid organism_info\', \'organism_image\', [
            \'organism_info_type\' => gettype($organism_info),
            \'organism_info_empty\' => empty($organism_info)
        ]);
        return $result;
    }
    
    // Custom image caption
    if (!empty($organism_info[\'images\']) && is_array($organism_info[\'images\'])) {
        if (!empty($organism_info[\'images\'][0][\'caption\'])) {
            $result[\'caption\'] = $organism_info[\'images\'][0][\'caption\'];
        }
        return $result;
    }
    
    // NCBI taxonomy caption with link
    if (!empty($organism_info[\'taxon_id\'])) {
        // Construct path - use absolute_images_path if provided, otherwise fall back
        if (!empty($absolute_images_path)) {
            $ncbi_image_file = "$absolute_images_path/ncbi_taxonomy/" . $organism_info[\'taxon_id\'] . \'.jpg\';
        } else {
            $ncbi_image_file = __DIR__ . \'/../../images/ncbi_taxonomy/\' . $organism_info[\'taxon_id\'] . \'.jpg\';
        }
        
        if (file_exists($ncbi_image_file)) {
            $result[\'caption\'] = \'Image from NCBI Taxonomy\';
            $result[\'link\'] = \'https://www.ncbi.nlm.nih.gov/datasets/taxonomy/\' . htmlspecialchars($organism_info[\'taxon_id\']);
        }
    }
    
    return $result;
}',
    ),
    3 => 
    array (
      'name' => 'validateOrganismJson',
      'line' => 153,
      'comment' => '/**
* Validate organism.json file
*
* Checks:
* - File exists
* - File is readable
* - Valid JSON format
* - Contains required fields (genus, species, common_name, taxon_id)
*
* @param string $json_path - Path to organism.json file
* @return array - Validation results with status and details
*/',
      'code' => 'function validateOrganismJson($json_path) {
    $validation = [
        \'exists\' => false,
        \'readable\' => false,
        \'writable\' => false,
        \'valid_json\' => false,
        \'has_required_fields\' => false,
        \'required_fields\' => [\'genus\', \'species\', \'common_name\', \'taxon_id\'],
        \'missing_fields\' => [],
        \'errors\' => []
    ];
    
    if (!file_exists($json_path)) {
        $validation[\'errors\'][] = \'organism.json file does not exist\';
        return $validation;
    }
    
    $validation[\'exists\'] = true;
    
    if (!is_readable($json_path)) {
        $validation[\'errors\'][] = \'organism.json file is not readable\';
        return $validation;
    }
    
    $validation[\'readable\'] = true;
    $validation[\'writable\'] = is_writable($json_path);
    
    $content = file_get_contents($json_path);
    $json_data = json_decode($content, true);
    
    if ($json_data === null) {
        $validation[\'errors\'][] = \'organism.json contains invalid JSON: \' . json_last_error_msg();
        return $validation;
    }
    
    $validation[\'valid_json\'] = true;
    
    // Handle wrapped JSON (single-level wrapping)
    if (!isset($json_data[\'genus\']) && !isset($json_data[\'common_name\'])) {
        $keys = array_keys($json_data);
        if (count($keys) > 0 && is_array($json_data[$keys[0]])) {
            $json_data = $json_data[$keys[0]];
        }
    }
    
    // Check for required fields
    foreach ($validation[\'required_fields\'] as $field) {
        if (!isset($json_data[$field]) || empty($json_data[$field])) {
            $validation[\'missing_fields\'][] = $field;
        }
    }
    
    $validation[\'has_required_fields\'] = empty($validation[\'missing_fields\']);
    
    if (!$validation[\'has_required_fields\']) {
        $validation[\'errors\'][] = \'Missing required fields: \' . implode(\', \', $validation[\'missing_fields\']);
    }
    
    return $validation;
}',
    ),
    4 => 
    array (
      'name' => 'setupOrganismDisplayContext',
      'line' => 225,
      'comment' => '/**
* Complete setup for organism display pages
* Validates parameter, loads organism info, checks access, returns context
* Use this to replace boilerplate in organism_display, assembly_display, parent_display
*
* @param string $organism_name Organism from GET/POST
* @param string $organism_data_dir Path to organism data directory
* @param bool $check_access Whether to check access control (default: true)
* @param string $redirect_home Home URL for redirects (default: /moop/index.php)
* @return array Array with \'name\' and \'info\' keys, or exits on error
*/',
      'code' => 'function setupOrganismDisplayContext($organism_name, $organism_data_dir, $check_access = true, $redirect_home = \'/moop/index.php\') {
    // Validate organism parameter
    $organism_name = validateOrganismParam($organism_name, $redirect_home);
    
    // Load and validate organism info
    $organism_info = loadOrganismInfo($organism_name, $organism_data_dir);
    
    if (!$organism_info) {
        header("Location: $redirect_home");
        exit;
    }
    
    // Check access (unless it\'s public)
    if ($check_access) {
        $is_public = is_public_organism($organism_name);
        if (!$is_public) {
            require_access(\'Collaborator\', $organism_name);
        }
    }
    
    return [
        \'name\' => $organism_name,
        \'info\' => $organism_info
    ];
}',
    ),
  ),
  'lib/functions_errorlog.php' => 
  array (
    0 => 
    array (
      'name' => 'logError',
      'line' => 15,
      'comment' => '/**
* Log an error to the error log file
*
* @param string $error_message The error message to log
* @param string $context Optional context (e.g., organism name, page name)
* @param array $additional_info Additional details to log
* @return void
*/',
      'code' => 'function logError($error_message, $context = \'\', $additional_info = []) {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath(\'error_log_file\');
    
    $error_entry = [
        \'timestamp\' => date(\'Y-m-d H:i:s\'),
        \'error\' => $error_message,
        \'context\' => $context,
        \'user\' => $_SESSION[\'username\'] ?? \'anonymous\',
        \'page\' => $_SERVER[\'REQUEST_URI\'] ?? \'\',
        \'ip\' => $_SERVER[\'REMOTE_ADDR\'] ?? \'\',
        \'details\' => $additional_info
    ];
    
    $log_line = json_encode($error_entry) . "\\n";
    
    if ($log_file) {
        error_log($log_line, 3, $log_file);
    }
}',
    ),
    1 => 
    array (
      'name' => 'getErrorLog',
      'line' => 42,
      'comment' => '/**
* Get error log entries
*
* @param int $limit Maximum number of entries to retrieve (0 = all)
* @return array Array of error entries
*/',
      'code' => 'function getErrorLog($limit = 0) {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath(\'error_log_file\');
    
    if (!$log_file || !file_exists($log_file)) {
        return [];
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }
    
    $errors = [];
    foreach (array_reverse($lines) as $line) {
        $error = json_decode($line, true);
        if ($error) {
            $errors[] = $error;
        }
    }
    
    if ($limit > 0) {
        $errors = array_slice($errors, 0, $limit);
    }
    
    return $errors;
}',
    ),
    2 => 
    array (
      'name' => 'clearErrorLog',
      'line' => 75,
      'comment' => '/**
* Clear the error log file
*
* @return bool True if successful
*/',
      'code' => 'function clearErrorLog() {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath(\'error_log_file\');
    
    if (!$log_file) {
        return false;
    }
    
    return file_put_contents($log_file, \'\') !== false;
}',
    ),
  ),
  'lib/functions_filesystem.php' => 
  array (
    0 => 
    array (
      'name' => 'validateAssemblyDirectories',
      'line' => 17,
      'comment' => '/**
* Validate assembly directories match database records
*
* Checks that for each genome in the database, there is a corresponding directory
* named either genome_name or genome_accession
*
* @param string $dbFile - Path to SQLite database file
* @param string $organism_data_dir - Path to organism data directory
* @return array - Validation results with genomes list and mismatches
*/',
      'code' => 'function validateAssemblyDirectories($dbFile, $organism_data_dir) {
    $result = [
        \'valid\' => true,
        \'genomes\' => [],
        \'mismatches\' => [],
        \'errors\' => []
    ];
    
    if (!file_exists($dbFile) || !is_readable($dbFile)) {
        $result[\'valid\'] = false;
        $result[\'errors\'][] = \'Database not readable\';
        return $result;
    }
    
    if (!is_dir($organism_data_dir)) {
        $result[\'valid\'] = false;
        $result[\'errors\'][] = \'Organism directory not found\';
        return $result;
    }
    
    try {
        $dbh = new PDO("sqlite:" . $dbFile);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get all genome records
        $stmt = $dbh->query("SELECT genome_id, genome_name, genome_accession FROM genome ORDER BY genome_name");
        $genomes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get list of directories in organism folder
        $dirs = array_diff(scandir($organism_data_dir), [\'.\', \'..\']);
        $dir_names = [];
        foreach ($dirs as $dir) {
            $full_path = "$organism_data_dir/$dir";
            if (is_dir($full_path)) {
                $dir_names[] = $dir;
            }
        }
        
        // Check each genome record
        foreach ($genomes as $genome) {
            $name = $genome[\'genome_name\'];
            $accession = $genome[\'genome_accession\'];
            $genome_id = $genome[\'genome_id\'];
            
            // Check if either name or accession matches a directory
            $found_dir = null;
            if (in_array($name, $dir_names)) {
                $found_dir = $name;
            } elseif (in_array($accession, $dir_names)) {
                $found_dir = $accession;
            }
            
            $result[\'genomes\'][] = [
                \'genome_id\' => $genome_id,
                \'genome_name\' => $name,
                \'genome_accession\' => $accession,
                \'directory_found\' => $found_dir,
                \'exists\' => $found_dir !== null
            ];
            
            if ($found_dir === null) {
                $result[\'valid\'] = false;
                $result[\'mismatches\'][] = [
                    \'type\' => \'missing_directory\',
                    \'genome_name\' => $name,
                    \'genome_accession\' => $accession,
                    \'message\' => "No directory found matching genome_name \'$name\' or genome_accession \'$accession\'"
                ];
            } elseif ($found_dir !== $name && $found_dir !== $accession) {
                // Directory exists but doesn\'t match expected names
                $result[\'mismatches\'][] = [
                    \'type\' => \'name_mismatch\',
                    \'genome_name\' => $name,
                    \'genome_accession\' => $accession,
                    \'found_directory\' => $found_dir,
                    \'message\' => "Directory \'$found_dir\' found, but doesn\'t match genome_name \'$name\' or genome_accession \'$accession\'"
                ];
            }
        }
        
        $dbh = null;
    } catch (PDOException $e) {
        $result[\'valid\'] = false;
        $result[\'errors\'][] = \'Database query failed: \' . $e->getMessage();
    }
    
    return $result;
}',
    ),
    1 => 
    array (
      'name' => 'validateAssemblyFastaFiles',
      'line' => 116,
      'comment' => '/**
* Validate assembly FASTA files exist
*
* Checks if each assembly directory contains the required FASTA files
* based on sequence_types patterns from site config
*
* @param string $organism_dir - Path to organism directory
* @param array $sequence_types - Sequence type patterns from site_config
* @return array - Validation results for each assembly
*/',
      'code' => 'function validateAssemblyFastaFiles($organism_dir, $sequence_types) {
    $result = [
        \'assemblies\' => [],
        \'missing_files\' => []
    ];
    
    if (!is_dir($organism_dir)) {
        return $result;
    }
    
    // Get all directories in organism folder
    $dirs = array_diff(scandir($organism_dir), [\'.\', \'..\']);
    
    foreach ($dirs as $dir) {
        $full_path = "$organism_dir/$dir";
        if (!is_dir($full_path)) {
            continue;
        }
        
        $assembly_info = [
            \'name\' => $dir,
            \'fasta_files\' => [],
            \'missing_patterns\' => []
        ];
        
        // Check for each sequence type pattern
        foreach ($sequence_types as $type => $config) {
            $pattern = $config[\'pattern\'];
            $files = glob("$full_path/*$pattern");
            
            if (!empty($files)) {
                $assembly_info[\'fasta_files\'][$type] = [
                    \'found\' => true,
                    \'pattern\' => $pattern,
                    \'file\' => basename($files[0])
                ];
            } else {
                $assembly_info[\'fasta_files\'][$type] = [
                    \'found\' => false,
                    \'pattern\' => $pattern
                ];
                $assembly_info[\'missing_patterns\'][] = $pattern;
            }
        }
        
        $result[\'assemblies\'][$dir] = $assembly_info;
        
        if (!empty($assembly_info[\'missing_patterns\'])) {
            $result[\'missing_files\'][$dir] = $assembly_info[\'missing_patterns\'];
        }
    }
    
    return $result;
}',
    ),
    2 => 
    array (
      'name' => 'renameAssemblyDirectory',
      'line' => 183,
      'comment' => '/**
* Rename an assembly directory
*
* Renames a directory within an organism folder from old_name to new_name
* Used to align directory names with genome_name or genome_accession
* Returns manual command if automatic rename fails
*
* @param string $organism_dir - Path to organism directory
* @param string $old_name - Current directory name
* @param string $new_name - New directory name
* @return array - [\'success\' => bool, \'message\' => string, \'command\' => string (if manual fix needed)]
*/',
      'code' => 'function renameAssemblyDirectory($organism_dir, $old_name, $new_name) {
    $result = [
        \'success\' => false,
        \'message\' => \'\',
        \'command\' => \'\'
    ];
    
    if (!is_dir($organism_dir)) {
        $result[\'message\'] = \'Organism directory not found\';
        return $result;
    }
    
    $old_path = "$organism_dir/$old_name";
    $new_path = "$organism_dir/$new_name";
    
    // Validate old directory exists
    if (!is_dir($old_path)) {
        $result[\'message\'] = "Directory \'$old_name\' not found";
        return $result;
    }
    
    // Check new name doesn\'t already exist
    if (is_dir($new_path) || file_exists($new_path)) {
        $result[\'message\'] = "Directory \'$new_name\' already exists";
        return $result;
    }
    
    // Sanitize names to prevent path traversal
    if (strpos($old_name, \'/\') !== false || strpos($new_name, \'/\') !== false ||
        strpos($old_name, \'..\') !== false || strpos($new_name, \'..\') !== false) {
        $result[\'message\'] = \'Invalid directory name (contains path separators)\';
        return $result;
    }
    
    // Build command for admin to run if automatic rename fails
    $result[\'command\'] = "cd " . escapeshellarg($organism_dir) . " && mv " . escapeshellarg($old_name) . " " . escapeshellarg($new_name);
    
    // Try to rename
    if (@rename($old_path, $new_path)) {
        $result[\'success\'] = true;
        $result[\'message\'] = "Successfully renamed \'$old_name\' to \'$new_name\'";
    } else {
        $result[\'message\'] = \'Web server lacks permission to rename directory.\';
    }
    
    return $result;
}',
    ),
    3 => 
    array (
      'name' => 'deleteAssemblyDirectory',
      'line' => 242,
      'comment' => '/**
* Delete an assembly directory
*
* Recursively deletes a directory within an organism folder
* Used to remove incorrectly named or unused assembly directories
* Returns manual command if automatic delete fails
*
* @param string $organism_dir - Path to organism directory
* @param string $dir_name - Directory name to delete
* @return array - [\'success\' => bool, \'message\' => string, \'command\' => string (if manual fix needed)]
*/',
      'code' => 'function deleteAssemblyDirectory($organism_dir, $dir_name) {
    $result = [
        \'success\' => false,
        \'message\' => \'\',
        \'command\' => \'\'
    ];
    
    if (!is_dir($organism_dir)) {
        $result[\'message\'] = \'Organism directory not found\';
        return $result;
    }
    
    $dir_path = "$organism_dir/$dir_name";
    
    // Validate directory exists
    if (!is_dir($dir_path)) {
        $result[\'message\'] = "Directory \'$dir_name\' not found";
        return $result;
    }
    
    // Prevent deletion of non-assembly directories
    if ($dir_name === \'.\' || $dir_name === \'..\' || strpos($dir_name, \'/\') !== false || 
        strpos($dir_name, \'..\') !== false || $dir_name === \'organism.json\') {
        $result[\'message\'] = \'Invalid directory name (security check failed)\';
        return $result;
    }
    
    // Build command for admin to run if automatic delete fails
    $result[\'command\'] = "rm -rf " . escapeshellarg($dir_path);
    
    // Try to delete recursively
    if (rrmdir($dir_path)) {
        $result[\'success\'] = true;
        $result[\'message\'] = "Successfully deleted directory \'$dir_name\'";
    } else {
        $result[\'message\'] = \'Web server lacks permission to delete directory.\';
    }
    
    return $result;
}',
    ),
    4 => 
    array (
      'name' => 'rrmdir',
      'line' => 273,
      'comment' => '/**
* Recursively remove directory
*
* Helper function to delete a directory and all its contents
*
* @param string $dir - Directory path
* @return bool - True if successful
*/',
      'code' => 'function rrmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === \'.\' || $item === \'..\') {
            continue;
        }
        $path = $dir . \'/\' . $item;
        if (is_dir($path)) {
            if (!rrmdir($path)) {
                return false;
            }
        } else {
            if (!@unlink($path)) {
                return false;
            }
        }
    }
    
    return @rmdir($dir);
}',
    ),
    5 => 
    array (
      'name' => 'getFileWriteError',
      'line' => 323,
      'comment' => '/**
* Check file writeability and return error info if file is not writable
* Uses web server group and keeps original owner
*
* @param string $filepath - Path to file to check
* @return array|null - Array with error details if not writable, null if ok
*/',
      'code' => 'function getFileWriteError($filepath) {
    if (!file_exists($filepath) || is_writable($filepath)) {
        return null;
    }
    
    $webserver = getWebServerUser();
    $web_user = $webserver[\'user\'];
    $web_group = $webserver[\'group\'];
    
    $current_owner = function_exists(\'posix_getpwuid\') ? posix_getpwuid(fileowner($filepath))[\'name\'] ?? \'unknown\' : \'unknown\';
    $current_perms = substr(sprintf(\'%o\', fileperms($filepath)), -4);
    
    // Command keeps original owner but changes group to webserver and sets perms to 664
    $fix_command = "sudo chgrp " . escapeshellarg($web_group) . " " . escapeshellarg($filepath) . " && sudo chmod 664 " . escapeshellarg($filepath);
    
    return [
        \'owner\' => $current_owner,
        \'perms\' => $current_perms,
        \'web_user\' => $web_user,
        \'web_group\' => $web_group,
        \'command\' => $fix_command,
        \'file\' => $filepath
    ];
}',
    ),
    6 => 
    array (
      'name' => 'getDirectoryError',
      'line' => 354,
      'comment' => '/**
* Check directory existence and writeability, return error info if issues found
* Uses owner of /moop directory and web server group
* Automatically detects if sudo is needed for the commands
*
* Usage:
*   $dir_error = getDirectoryError(\'/path/to/directory\');
*   if ($dir_error) {
*       // Display error alert with fix instructions
*   }
*
* Can be used in any admin page that needs to ensure a directory exists and is writable.
* Common use cases:
*   - Image cache directories (ncbi_taxonomy, organisms, etc)
*   - Log directories
*   - Upload/temp directories
*   - Any other required filesystem paths
*
* @param string $dirpath - Path to directory to check
* @return array|null - Array with error details if directory missing/not writable, null if ok
*/',
      'code' => 'function getDirectoryError($dirpath) {
    if (is_dir($dirpath) && is_writable($dirpath)) {
        return null;
    }
    
    $webserver = getWebServerUser();
    $web_group = $webserver[\'group\'];
    
    // Get owner from /moop directory
    $moop_owner = \'ubuntu\';  // Default fallback
    if (function_exists(\'posix_getpwuid\')) {
        $moop_info = @stat(__DIR__ . \'/..\');  // Get stat of /moop parent directory
        if ($moop_info) {
            $moop_pwd = posix_getpwuid($moop_info[\'uid\']);
            if ($moop_pwd) {
                $moop_owner = $moop_pwd[\'name\'];
            }
        }
    }
    
    // Detect if sudo is needed
    $current_uid = function_exists(\'posix_getuid\') ? posix_getuid() : null;
    $need_sudo = false;
    
    if ($current_uid !== null && $current_uid !== 0) {
        // Not running as root, check if we\'re the moop owner
        $current_user = function_exists(\'posix_getpwuid\') ? posix_getpwuid($current_uid)[\'name\'] ?? null : null;
        if ($current_user !== $moop_owner) {
            $need_sudo = true;
        }
    }
    
    // Helper function to add sudo if needed
    $cmd_prefix = $need_sudo ? \'sudo \' : \'\';
    
    if (!is_dir($dirpath)) {
        // Directory doesn\'t exist
        return [
            \'type\' => \'missing\',
            \'dir\' => $dirpath,
            \'owner\' => $moop_owner,
            \'group\' => $web_group,
            \'need_sudo\' => $need_sudo,
            \'commands\' => [
                "{$cmd_prefix}mkdir -p " . escapeshellarg($dirpath),
                "{$cmd_prefix}chown {$moop_owner}:{$web_group} " . escapeshellarg($dirpath),
                "{$cmd_prefix}chmod 775 " . escapeshellarg($dirpath)
            ]
        ];
    }
    
    // Directory exists but not writable
    $current_owner = function_exists(\'posix_getpwuid\') ? posix_getpwuid(fileowner($dirpath))[\'name\'] ?? \'unknown\' : \'unknown\';
    $current_perms = substr(sprintf(\'%o\', fileperms($dirpath)), -4);
    
    return [
        \'type\' => \'not_writable\',
        \'dir\' => $dirpath,
        \'owner\' => $current_owner,
        \'perms\' => $current_perms,
        \'target_owner\' => $moop_owner,
        \'target_group\' => $web_group,
        \'need_sudo\' => $need_sudo,
        \'commands\' => [
            "{$cmd_prefix}chown {$moop_owner}:{$web_group} " . escapeshellarg($dirpath),
            "{$cmd_prefix}chmod 775 " . escapeshellarg($dirpath)
        ]
    ];
}',
    ),
  ),
  'lib/functions_json.php' => 
  array (
    0 => 
    array (
      'name' => 'loadJsonFile',
      'line' => 14,
      'comment' => '/**
* Load JSON file safely with error handling
*
* @param string $path Path to JSON file
* @param mixed $default Default value if file doesn\'t exist (default: [])
* @return mixed Decoded JSON data or default value
*/',
      'code' => 'function loadJsonFile($path, $default = []) {
    if (!file_exists($path)) {
        return $default;
    }
    
    $json_content = file_get_contents($path);
    if ($json_content === false) {
        return $default;
    }
    
    $data = json_decode($json_content, true);
    return $data !== null ? $data : $default;
}',
    ),
    1 => 
    array (
      'name' => 'loadJsonFileRequired',
      'line' => 36,
      'comment' => '/**
* Load JSON file and require it to exist
*
* @param string $path Path to JSON file
* @param string $errorMsg Error message to log if file missing
* @param bool $exitOnError Whether to exit if file not found (default: true)
* @return mixed Decoded JSON data or empty array if error
*/',
      'code' => 'function loadJsonFileRequired($path, $errorMsg = \'\', $exitOnError = false) {
    if (!file_exists($path)) {
        if ($errorMsg) {
            error_log($errorMsg);
        }
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Required data file not found");
        }
        return [];
    }
    
    $json_content = file_get_contents($path);
    if ($json_content === false) {
        $msg = $errorMsg ?: "Failed to read file: $path";
        error_log($msg);
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Failed to read required data");
        }
        return [];
    }
    
    $data = json_decode($json_content, true);
    if ($data === null) {
        $msg = $errorMsg ?: "Invalid JSON in file: $path";
        error_log($msg);
        if ($exitOnError) {
            header("HTTP/1.1 500 Internal Server Error");
            exit("Invalid data format");
        }
        return [];
    }
    
    return $data;
}',
    ),
    2 => 
    array (
      'name' => 'loadAndMergeJson',
      'line' => 81,
      'comment' => '/**
* Load existing JSON file and merge with new data
* Handles wrapped JSON automatically, preserves existing fields not in merge data
*
* @param string $file_path Path to JSON file to load
* @param array $new_data New data to merge in (overwrites matching keys)
* @return array Merged data (or just new_data if file doesn\'t exist)
*/',
      'code' => 'function loadAndMergeJson($file_path, $new_data = []) {
    // If file doesn\'t exist, just return new data
    if (!file_exists($file_path)) {
        return $new_data;
    }
    
    // Load existing data
    $existing = loadJsonFile($file_path);
    
    if (!is_array($existing)) {
        return $new_data;
    }
    
    // Handle wrapped JSON (extra outer braces)
    if (!isset($existing[\'genus\']) && !isset($existing[\'common_name\'])) {
        $keys = array_keys($existing);
        if (count($keys) > 0 && is_array($existing[$keys[0]])) {
            $existing = $existing[$keys[0]];
        }
    }
    
    // Merge: existing fields are preserved, new data overwrites matching keys
    return array_merge($existing, $new_data);
}',
    ),
    3 => 
    array (
      'name' => 'decodeJsonString',
      'line' => 113,
      'comment' => '/**
* Decode JSON string safely with type checking
*
* @param string $json_string JSON string to decode
* @param bool $as_array Return as array (default: true)
* @return array|null Decoded data or null if invalid
*/',
      'code' => 'function decodeJsonString($json_string, $as_array = true) {
    if (empty($json_string)) {
        return $as_array ? [] : null;
    }
    
    $decoded = json_decode($json_string, $as_array);
    
    // Validate it decoded properly
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return $as_array ? [] : null;
    }
    
    return $decoded;
}',
    ),
  ),
  'lib/functions_system.php' => 
  array (
    0 => 
    array (
      'name' => 'getWebServerUser',
      'line' => 14,
      'comment' => '/**
* Get the web server user and group
*
* Detects the user running the current PHP process (web server)
*
* @return array - [\'user\' => string, \'group\' => string]
*/',
      'code' => 'function getWebServerUser() {
    $user = \'www-data\';
    $group = \'www-data\';
    
    // Try to get the actual user running this process
    if (function_exists(\'posix_getuid\')) {
        $uid = posix_getuid();
        $pwinfo = posix_getpwuid($uid);
        if ($pwinfo !== false) {
            $user = $pwinfo[\'name\'];
        }
    }
    
    // Try to get the actual group
    if (function_exists(\'posix_getgid\')) {
        $gid = posix_getgid();
        $grinfo = posix_getgrgid($gid);
        if ($grinfo !== false) {
            $group = $grinfo[\'name\'];
        }
    }
    
    return [\'user\' => $user, \'group\' => $group];
}',
    ),
    1 => 
    array (
      'name' => 'fixDatabasePermissions',
      'line' => 48,
      'comment' => '/**
* Attempt to fix database file permissions
*
* Tries to make database readable by web server user.
* Returns instructions if automatic fix fails.
*
* @param string $dbFile - Path to database file
* @return array - [\'success\' => bool, \'message\' => string, \'command\' => string (if manual fix needed)]
*/',
      'code' => 'function fixDatabasePermissions($dbFile) {
    $result = [
        \'success\' => false,
        \'message\' => \'\',
        \'command\' => \'\'
    ];
    
    if (!file_exists($dbFile)) {
        $result[\'message\'] = \'Database file not found\';
        return $result;
    }
    
    if (!is_file($dbFile)) {
        $result[\'message\'] = \'Path is not a file\';
        return $result;
    }
    
    // Get web server user
    $webserver = getWebServerUser();
    $web_user = $webserver[\'user\'];
    $web_group = $webserver[\'group\'];
    
    // Get file info for reporting
    $file_owner = function_exists(\'posix_getpwuid\') ? posix_getpwuid(fileowner($dbFile))[\'name\'] : \'unknown\';
    $file_group = function_exists(\'posix_getgrgid\') ? posix_getgrgid(filegroup($dbFile))[\'name\'] : \'unknown\';
    $current_perms = substr(sprintf(\'%o\', fileperms($dbFile)), -4);
    
    // Build command for admin to run if automatic fix fails
    $result[\'command\'] = "sudo chmod 644 " . escapeshellarg($dbFile) . " && sudo chown " . escapeshellarg("$web_user:$web_group") . " " . escapeshellarg($dbFile);
    
    // Try to fix permissions
    try {
        // Try chmod to make readable (644 = rw-r--r--)
        $chmod_result = @chmod($dbFile, 0644);
        
        if (!$chmod_result) {
            $result[\'message\'] = \'Web server lacks permission to change file permissions.\';
            return $result;
        }
        
        // Try to change ownership to web server user (may fail if not root)
        @chown($dbFile, $web_user);
        @chgrp($dbFile, $web_group);
        
        // Verify it worked
        if (is_readable($dbFile)) {
            $result[\'success\'] = true;
            $result[\'message\'] = \'Permissions fixed successfully! Database is now readable.\';
        } else {
            $result[\'message\'] = \'Permissions were modified but file still not readable.\';
        }
        
    } catch (Exception $e) {
        $result[\'message\'] = \'Error: \' . $e->getMessage();
    }
    
    return $result;
}',
    ),
  ),
  'lib/functions_tools.php' => 
  array (
    0 => 
    array (
      'name' => 'getAvailableTools',
      'line' => 14,
      'comment' => '/**
* Get available tools filtered by context
* Returns only tools that have the required context parameters available
*
* @param array $context - Context array with optional keys: organism, assembly, group, display_name
* @return array - Array of available tools with built URLs
*/',
      'code' => 'function getAvailableTools($context = []) {
    global $site, $available_tools;
    
    // Include tool configuration
    include_once __DIR__ . \'/tool_config.php\';
    
    // If $available_tools not set by include, return empty array
    if (!isset($available_tools) || !is_array($available_tools)) {
        return [];
    }
    
    // Get current page from context (optional)
    $current_page = $context[\'page\'] ?? null;
    
    $tools = [];
    foreach ($available_tools as $tool_id => $tool) {
        // Check page visibility - skip if tool doesn\'t show on this page
        if ($current_page && !isToolVisibleOnPage($tool, $current_page)) {
            continue;
        }
        
        $url = buildToolUrl($tool_id, $context, $site);
        if ($url) {
            $tools[$tool_id] = array_merge($tool, [\'url\' => $url]);
        }
    }
    
    return $tools;
}',
    ),
    1 => 
    array (
      'name' => 'createIndexToolContext',
      'line' => 50,
      'comment' => '/**
* Create a tool context for index/home page
*
* @param bool $use_onclick_handler Whether to use onclick handler for tools
* @return array Context array for tool_section.php
*/',
      'code' => 'function createIndexToolContext($use_onclick_handler = true) {
    return [
        \'display_name\' => \'Multi-Organism Search\',
        \'page\' => \'index\',
        \'use_onclick_handler\' => $use_onclick_handler
    ];
}',
    ),
    2 => 
    array (
      'name' => 'createOrganismToolContext',
      'line' => 65,
      'comment' => '/**
* Create a tool context for an organism display page
*
* @param string $organism_name The organism name
* @param string $display_name Optional display name (defaults to organism_name)
* @return array Context array for tool_section.php
*/',
      'code' => 'function createOrganismToolContext($organism_name, $display_name = null) {
    return [
        \'organism\' => $organism_name,
        \'display_name\' => $display_name ?? $organism_name,
        \'page\' => \'organism\'
    ];
}',
    ),
    3 => 
    array (
      'name' => 'createAssemblyToolContext',
      'line' => 81,
      'comment' => '/**
* Create a tool context for an assembly display page
*
* @param string $organism_name The organism name
* @param string $assembly_accession The assembly/genome accession
* @param string $display_name Optional display name (defaults to assembly_accession)
* @return array Context array for tool_section.php
*/',
      'code' => 'function createAssemblyToolContext($organism_name, $assembly_accession, $display_name = null) {
    return [
        \'organism\' => $organism_name,
        \'assembly\' => $assembly_accession,
        \'display_name\' => $display_name ?? $assembly_accession,
        \'page\' => \'assembly\'
    ];
}',
    ),
    4 => 
    array (
      'name' => 'createGroupToolContext',
      'line' => 96,
      'comment' => '/**
* Create a tool context for a group display page
*
* @param string $group_name The group name
* @return array Context array for tool_section.php
*/',
      'code' => 'function createGroupToolContext($group_name) {
    return [
        \'group\' => $group_name,
        \'display_name\' => $group_name,
        \'page\' => \'group\'
    ];
}',
    ),
    5 => 
    array (
      'name' => 'createFeatureToolContext',
      'line' => 112,
      'comment' => '/**
* Create a tool context for a feature/parent display page
*
* @param string $organism_name The organism name
* @param string $assembly_accession The assembly/genome accession
* @param string $feature_name The feature name
* @return array Context array for tool_section.php
*/',
      'code' => 'function createFeatureToolContext($organism_name, $assembly_accession, $feature_name) {
    return [
        \'organism\' => $organism_name,
        \'assembly\' => $assembly_accession,
        \'display_name\' => $feature_name,
        \'page\' => \'parent\'
    ];
}',
    ),
    6 => 
    array (
      'name' => 'createMultiOrganismToolContext',
      'line' => 128,
      'comment' => '/**
* Create a tool context for multi-organism search page
*
* @param array $organisms Array of organism names
* @param string $display_name Optional display name
* @return array Context array for tool_section.php
*/',
      'code' => 'function createMultiOrganismToolContext($organisms, $display_name = \'Multi-Organism Search\') {
    return [
        \'organisms\' => $organisms,
        \'display_name\' => $display_name,
        \'page\' => \'multi_organism_search\'
    ];
}',
    ),
  ),
  'lib/functions_validation.php' => 
  array (
    0 => 
    array (
      'name' => 'test_input',
      'line' => 23,
      'comment' => '/**
* Sanitize user input - remove dangerous characters
*
* DEPRECATED: Use context-specific sanitization instead:
* - For database queries: Use prepared statements with parameter binding
* - For HTML output: Use htmlspecialchars() at the point of output
* - For URL parameters: Use urlencode()/urldecode() as needed
*
* This function is kept for backwards compatibility but combines multiple
* concerns and is typically misused. It applies both raw character removal
* and HTML escaping, which should be handled separately based on context.
*
* @param string $data - Raw user input
* @return string - Sanitized string with < > removed and HTML entities escaped
* @deprecated Use prepared statements and context-specific escaping
*/',
      'code' => 'function test_input($data) {
    $data = stripslashes($data);
    $data = preg_replace(\'/[\\<\\>]+/\', \'\', $data);
    $data = htmlspecialchars($data);
    return $data;
}',
    ),
    1 => 
    array (
      'name' => 'sanitize_search_input',
      'line' => 40,
      'comment' => '/**
* Sanitize search input specifically for use in database search queries
*
* This function handles search-specific sanitization that removes or escapes
* characters that could interfere with search functionality while preserving
* useful search characters like spaces, quotes, and basic punctuation.
*
* @param string $input - Raw search input from user
* @return string - Sanitized search string safe for database queries
*/',
      'code' => 'function sanitize_search_input($input) {
    // Remove null bytes and control characters
    $input = preg_replace(\'/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/\', \'\', $input);
    
    // Trim whitespace
    $input = trim($input);
    
    // Remove excessive whitespace (multiple spaces become single space)
    $input = preg_replace(\'/\\s+/\', \' \', $input);
    
    return $input;
}',
    ),
    2 => 
    array (
      'name' => 'validate_search_term',
      'line' => 63,
      'comment' => '/**
* Validate a search term for safety and usability
*
* Checks that a search term meets minimum requirements and doesn\'t contain
* problematic patterns that could cause issues with database queries or
* return meaningless results.
*
* @param string $term - Search term to validate
* @return array - Validation result with \'valid\' boolean and \'error\' message
*/',
      'code' => 'function validate_search_term($term) {
    $term = sanitize_search_input($term);
    
    if (empty($term)) {
        return [\'valid\' => false, \'error\' => \'Search term cannot be empty\'];
    }
    
    if (strlen($term) < 2) {
        return [\'valid\' => false, \'error\' => \'Search term must be at least 2 characters long\'];
    }
    
    if (strlen($term) > 100) {
        return [\'valid\' => false, \'error\' => \'Search term too long (maximum 100 characters)\'];
    }
    
    // Check for patterns that might cause performance issues
    if (preg_match(\'/^[%_*]+$/\', $term)) {
        return [\'valid\' => false, \'error\' => \'Search term cannot consist only of wildcards\'];
    }
    
    return [\'valid\' => true, \'term\' => $term];
}',
    ),
    3 => 
    array (
      'name' => 'is_quoted_search',
      'line' => 92,
      'comment' => '/**
* Check if a search term is quoted (surrounded by quotes)
*
* @param string $term - Search term to check
* @return bool - True if term is quoted, false otherwise
*/',
      'code' => 'function is_quoted_search($term) {
    $term = trim($term);
    return (strlen($term) >= 2 && 
            (($term[0] === \'"\' && $term[-1] === \'"\') ||
             ($term[0] === "\'" && $term[-1] === "\'")));
}',
    ),
    4 => 
    array (
      'name' => 'validateOrganismParam',
      'line' => 107,
      'comment' => '/**
* Validate and extract organism parameter from GET/POST
* Redirects to home if missing/empty
*
* @param string $organism_name Organism name to validate
* @param string $redirect_on_empty URL to redirect to if empty (default: /moop/index.php)
* @return string Validated organism name
*/',
      'code' => 'function validateOrganismParam($organism_name, $redirect_on_empty = \'/moop/index.php\') {
    if (empty($organism_name)) {
        header("Location: $redirect_on_empty");
        exit;
    }
    return $organism_name;
}',
    ),
    5 => 
    array (
      'name' => 'validateAssemblyParam',
      'line' => 123,
      'comment' => '/**
* Validate and extract assembly parameter from GET/POST
* Redirects to home if missing/empty
*
* @param string $assembly Assembly accession to validate
* @param string $redirect_on_empty URL to redirect to if empty
* @return string Validated assembly name
*/',
      'code' => 'function validateAssemblyParam($assembly, $redirect_on_empty = \'/moop/index.php\') {
    if (empty($assembly)) {
        header("Location: $redirect_on_empty");
        exit;
    }
    return $assembly;
}',
    ),
  ),
  'lib/parent_functions.php' => 
  array (
    0 => 
    array (
      'name' => 'getAncestors',
      'line' => 18,
      'comment' => '/**
* Get hierarchy of features (ancestors)
* Traverses up the feature hierarchy from a given feature to its parents/grandparents
* Optionally filters by genome_ids for permission-based access
*
* @param string $feature_uniquename - The feature uniquename to start from
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
* @return array - Array of features: [self, parent, grandparent, ...]
*/',
      'code' => 'function getAncestors($feature_uniquename, $dbFile, $genome_ids = []) {
    $feature = getFeatureByUniquename($feature_uniquename, $dbFile, $genome_ids);
    
    if (empty($feature)) {
        return [];
    }
    
    $ancestors = [$feature];
    
    if ($feature[\'parent_feature_id\']) {
        $parent_ancestors = getAncestorsByFeatureId($feature[\'parent_feature_id\'], $dbFile, $genome_ids);
        $ancestors = array_merge($ancestors, $parent_ancestors);
    }
    
    return $ancestors;
}',
    ),
    1 => 
    array (
      'name' => 'getAncestorsByFeatureId',
      'line' => 28,
      'comment' => '/**
* Helper function for recursive ancestor traversal
* Fetches ancestors by feature_id (used internally by getAncestors)
* Optionally filters by genome_ids for permission-based access
*
* @param int $feature_id - The feature ID to start from
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results
* @return array - Array of ancestor features
*/',
      'code' => 'function getAncestorsByFeatureId($feature_id, $dbFile, $genome_ids = []) {
    $feature = getParentFeature($feature_id, $dbFile, $genome_ids);
    
    if (empty($feature)) {
        return [];
    }
    
    $ancestors = [$feature];
    
    if ($feature[\'parent_feature_id\']) {
        $parent_ancestors = getAncestorsByFeatureId($feature[\'parent_feature_id\'], $dbFile, $genome_ids);
        $ancestors = array_merge($ancestors, $parent_ancestors);
    }
    
    return $ancestors;
}',
    ),
    2 => 
    array (
      'name' => 'getChildren',
      'line' => 72,
      'comment' => '/**
* Get all children and descendants of a feature
* Recursively fetches all child features at any depth
* Optionally filters by genome_ids for permission-based access
*
* @param int $feature_id - The parent feature ID
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
* @return array - Flat array of all children and descendants
*/',
      'code' => 'function getChildren($feature_id, $dbFile, $genome_ids = []) {
    $children = [];
    
    $results = getChildrenByFeatureId($feature_id, $dbFile, $genome_ids);
    
    foreach ($results as $row) {
        $children[] = $row;
        $child_descendants = getChildren($row[\'feature_id\'], $dbFile, $genome_ids);
        $children = array_merge($children, $child_descendants);
    }
    return $children;
}',
    ),
    3 => 
    array (
      'name' => 'generateAnnotationTableHTML',
      'line' => 99,
      'comment' => '/**
* Generate annotation table with export buttons
* Creates a responsive HTML table displaying annotations with sorting/filtering
*
* @param array $results - Annotation results from database
* @param string $uniquename - Feature uniquename (for export)
* @param string $type - Feature type (for export)
* @param int $count - Table counter (ensures unique IDs)
* @param string $annotation_type - Type of annotation (e.g., "InterPro")
* @param string $desc - Description/definition of annotation type
* @param string $color - Bootstrap color class for badge
* @param string $organism - Organism name (for export)
* @return string - HTML for the annotation table section
*/',
      'code' => 'function generateAnnotationTableHTML($results, $uniquename, $type, $count, $annotation_type, $desc, $color = \'warning\', $organism = \'\') {
    if (empty($results)) {
        return \'\';
    }
    
    $table_id = "annotTable_$count";
    $result_count = count($results);
    $desc_id = "annotDesc_$count";
    
    // Determine text color based on background color
    $text_color = in_array($color, [\'warning\', \'info\', \'secondary\']) ? \'text-dark\' : \'text-white\';
    
    // Border color matches badge color
    $border_class = "border-$color";
    
    // Create unique ID for this annotation section
    $section_id = "annot_section_" . preg_replace(\'/[^a-zA-Z0-9_]/\', \'_\', $uniquename . \'_\' . $annotation_type);
    
    $html = \'<div class="annotation-section mb-3 \' . htmlspecialchars($border_class) . \'" id="\' . htmlspecialchars($section_id) . \'">\';
    $html .= \'<div class="d-flex justify-content-between align-items-center mb-2">\';
    $html .= "<h5 class=\\"mb-0\\"><span class=\\"badge bg-" . htmlspecialchars($color) . " $text_color badge-lg\\">" . htmlspecialchars($annotation_type) . "</span>";
    $html .= " <span class=\\"badge bg-secondary badge-lg\\">" . htmlspecialchars($result_count) . " result" . ($result_count > 1 ? \'s\' : \'\') . "</span>";
    
    if ($desc) {
        $html .= "&nbsp;<button class=\\"btn btn-sm btn-link p-0 annotation-info-btn\\" type=\\"button\\" data-bs-toggle=\\"collapse\\" data-bs-target=\\"#" . htmlspecialchars($desc_id) . "\\" aria-expanded=\\"false\\">";
        $html .= "<i class=\\"fas fa-info-circle\\"></i>";
        $html .= "</button>";
    }
    
    $html .= "</h5>";
    $html .= \'<div class="d-flex gap-2 align-items-center">\';
    $html .= \'<div id="\' . htmlspecialchars($table_id) . \'_filter" class="dataTables_filter"></div>\';
    $html .= \'<a href="#sequencesSection" class="btn btn-sm btn-info" title="Jump to sequences section"><i class="fas fa-dna"></i> Jump to Sequences</a>\';
    $html .= \'</div>\';
    $html .= \'</div>\';
    
    if ($desc) {
        $html .= \'<div class="collapse mb-3" id="\' . htmlspecialchars($desc_id) . \'">\';
        $html .= \'<div class="alert alert-info mb-0 font-size-xsmall">\';
        $html .= $desc;
        $html .= \'</div>\';
        $html .= \'</div>\';
    }
    
    // Table with DataTables
    $html .= "<div class=\\"table-responsive\\">";
    $html .= "<table id=\\"" . htmlspecialchars($table_id) . "\\" class=\\"table table-sm table-striped table-hover\\" style=\\"width:100%;\\">";
    $html .= "<thead><tr>";
    $html .= "<th class=\\"export-only\\">Organism</th>";
    $html .= "<th class=\\"export-only\\">Feature ID</th>";
    $html .= "<th class=\\"export-only\\">Feature Type</th>";
    $html .= "<th class=\\"export-only\\">Annotation Type</th>";
    $html .= "<th>Annotation ID</th>";
    $html .= "<th>Description</th>";
    $html .= "<th>Score</th>";
    $html .= "<th>Source</th>";
    $html .= "</tr></thead>";
    $html .= "<tbody>";
    
    foreach ($results as $row) {
        $hit_id = htmlspecialchars($row[\'annotation_accession\']);
        $hit_description = htmlspecialchars($row[\'annotation_description\']);
        $hit_score = htmlspecialchars($row[\'score\']);
        $annotation_source = htmlspecialchars($row[\'annotation_source_name\']);
        $annotation_accession_url = htmlspecialchars($row[\'annotation_accession_url\']);
        $hit_id_link = $annotation_accession_url . urlencode($row[\'annotation_accession\']);
        
        $html .= "<tr>";
        $html .= "<td class=\\"export-only\\">" . htmlspecialchars($organism) . "</td>";
        $html .= "<td class=\\"export-only\\">" . htmlspecialchars($uniquename) . "</td>";
        $html .= "<td class=\\"export-only\\">" . htmlspecialchars($type) . "</td>";
        $html .= "<td class=\\"export-only\\">" . htmlspecialchars($annotation_type) . "</td>";
        $html .= "<td><a href=\\"" . htmlspecialchars($hit_id_link) . "\\" target=\\"_blank\\">" . $hit_id . "</a></td>";
        $html .= "<td>" . $hit_description . "</td>";
        $html .= "<td>" . $hit_score . "</td>";
        $html .= "<td>" . $annotation_source . "</td>";
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table>";
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}',
    ),
    4 => 
    array (
      'name' => 'getAllAnnotationsForFeatures',
      'line' => 195,
      'comment' => '/**
* Get all annotations for multiple features at once (optimized)
* Fetches annotations for multiple features in a single query
* Optionally filters by genome_ids for permission-based access
*
* @param array $feature_ids - Array of feature IDs to fetch annotations for
* @param string $dbFile - Path to SQLite database
* @param array $genome_ids - Optional: Array of genome IDs to filter results (empty = no filtering)
* @return array - Organized as [$feature_id => [$annotation_type => [results]]]
*/',
      'code' => 'function getAllAnnotationsForFeatures($feature_ids, $dbFile, $genome_ids = []) {
    if (empty($feature_ids)) {
        return [];
    }
    
    $placeholders = implode(\',\', array_fill(0, count($feature_ids), \'?\'));
    
    // Build WHERE clause with optional genome filtering
    $where_clause = "f.feature_id IN ($placeholders)";
    $params = $feature_ids;
    
    if (!empty($genome_ids)) {
        $genome_placeholders = implode(\',\', array_fill(0, count($genome_ids), \'?\'));
        $where_clause .= " AND f.genome_id IN ($genome_placeholders)";
        $params = array_merge($params, $genome_ids);
    }
    
    $query = "SELECT f.feature_id, f.feature_uniquename, f.feature_type, 
              a.annotation_accession, a.annotation_description, 
              fa.score, fa.date, 
              ans.annotation_source_name, ans.annotation_accession_url, ans.annotation_type
        FROM annotation a, feature f, feature_annotation fa, annotation_source ans, genome g, organism o
        WHERE f.organism_id = o.organism_id
          AND f.genome_id = g.genome_id
          AND ans.annotation_source_id = a.annotation_source_id
          AND f.feature_id = fa.feature_id
          AND fa.annotation_id = a.annotation_id
          AND $where_clause
        ORDER BY f.feature_id, ans.annotation_type";
    
    $results = fetchData($query, $dbFile, $params);
    
    // Organize by feature_id and annotation_type
    $organized = [];
    foreach ($results as $row) {
        $feature_id = $row[\'feature_id\'];
        $annotation_type = $row[\'annotation_type\'];
        
        if (!isset($organized[$feature_id])) {
            $organized[$feature_id] = [];
        }
        if (!isset($organized[$feature_id][$annotation_type])) {
            $organized[$feature_id][$annotation_type] = [];
        }
        
        $organized[$feature_id][$annotation_type][] = $row;
    }
    
    return $organized;
}',
    ),
    5 => 
    array (
      'name' => 'generateTreeHTML',
      'line' => 256,
      'comment' => '/**
* Generate tree-style HTML for feature hierarchy
* Creates a hierarchical list with box-drawing characters (like Unix \'tree\' command)
*
* @param int $feature_id - The parent feature ID
* @param string $dbFile - Path to SQLite database
* @param string $prefix - Internal use for recursion
* @param bool $is_last - Internal use for recursion
* @return string - HTML string with nested ul/li tree structure
*/',
      'code' => 'function generateTreeHTML($feature_id, $dbFile, $prefix = \'\', $is_last = true, $genome_ids = []) {
    $results = getChildrenByFeatureId($feature_id, $dbFile, $genome_ids);

    if (empty($results)) {
        return \'\';
    }
    
    $html = "<ul>";
    $total = count($results);
    
    foreach ($results as $index => $row) {
        $is_last_child = ($index === $total - 1);
        
        $feature_type = htmlspecialchars($row[\'feature_type\']);
        $feature_name = htmlspecialchars($row[\'feature_uniquename\']);
        
        // Color code badges by feature type
        $badge_class = \'bg-secondary\';
        $text_color = \'text-white\';
        
        if ($feature_type == \'mRNA\') {
            $badge_class = \'bg-feature-mrna\';
            $text_color = \'text-white\';
        } elseif ($feature_type == \'CDS\') {
            $badge_class = \'bg-info\';
            $text_color = \'text-white\';
        } elseif ($feature_type == \'exon\') {
            $badge_class = \'bg-warning\';
            $text_color = \'text-dark\';
        } elseif ($feature_type == \'gene\') {
            $badge_class = \'bg-feature-gene\';
            $text_color = \'text-white\';
        }
        
        // Tree character - └── for last child, ├── for others
        $tree_char = $is_last_child ? \'└── \' : \'├── \';
        
        $html .= "<li>";
        $html .= "<span class=\\"tree-char\\">$tree_char</span>";
        $html .= "<span class=\\"text-dark\\">$feature_name</span> ";
        $html .= "<span class=\\"badge $badge_class $text_color\\">$feature_type</span>";
        
        // Recursive call for nested children
        $html .= generateTreeHTML($row[\'feature_id\'], $dbFile, $prefix, $is_last_child, $genome_ids);
        $html .= "</li>";
    }
    $html .= "</ul>";

    return $html;
}',
    ),
  ),
  'lib/tool_config.php' => 
  array (
    0 => 
    array (
      'name' => 'getTool',
      'line' => 52,
      'comment' => '/**
* Get a specific tool configuration
*
* @param string $tool_id - The tool identifier
* @return array|null - Tool configuration or null if not found
*/',
      'code' => 'function getTool($tool_id) {
    global $available_tools;
    return $available_tools[$tool_id] ?? null;
}',
    ),
    1 => 
    array (
      'name' => 'getAllTools',
      'line' => 62,
      'comment' => '/**
* Get all available tools
*
* @return array - Array of all tool configurations
*/',
      'code' => 'function getAllTools() {
    global $available_tools;
    return $available_tools;
}',
    ),
    2 => 
    array (
      'name' => 'buildToolUrl',
      'line' => 75,
      'comment' => '/**
* Build tool URL with context parameters
*
* @param string $tool_id - The tool identifier
* @param array $context - Context array with organism, assembly, group, display_name
* @param string $site - Site variable (from site_config.php)
* @return string|null - Built URL or null if tool not found
*/',
      'code' => 'function buildToolUrl($tool_id, $context, $site) {
    $tool = getTool($tool_id);
    if (!$tool) {
        return null;
    }
    
    $url = "/$site" . $tool[\'url_path\'];
    $params = [];
    
    // Build query parameters from context
    foreach ($tool[\'context_params\'] as $param) {
        if (!empty($context[$param])) {
            $params[$param] = $context[$param];
        }
    }
    
    if (!empty($params)) {
        $url .= \'?\' . http_build_query($params);
    }
    
    return $url;
}',
    ),
    3 => 
    array (
      'name' => 'isToolVisibleOnPage',
      'line' => 105,
      'comment' => '/**
* Check if a tool should be visible on a specific page
*
* @param array $tool - Tool configuration
* @param string $page - Page identifier (index, organism, group, assembly, parent, multi_organism_search)
* @return bool - True if tool should be visible on this page
*/',
      'code' => 'function isToolVisibleOnPage($tool, $page) {
    // If \'pages\' key not defined, default to \'all\'
    $pages = $tool[\'pages\'] ?? \'all\';
    
    // \'all\' means show on all pages
    if ($pages === \'all\') {
        return true;
    }
    
    // If pages is an array, check if current page is in it
    if (is_array($pages)) {
        return in_array($page, $pages);
    }
    
    // If pages is a string (and not \'all\'), treat as single page name
    return $page === $pages;
}',
    ),
  ),
  'tools/sequences_display.php' => 
  array (
    0 => 
    array (
      'name' => 'extractSequencesFromFasta',
      'line' => 113,
      'comment' => '/**
* Extract sequences from a FASTA file for specific feature IDs
*
* @param string $fasta_file Path to FASTA file
* @param array $feature_ids Array of feature IDs to extract
* @return array Associative array with feature_id => sequence content
*/',
      'code' => 'function extractSequencesFromFasta($fasta_file, $feature_ids, $seq_type, &$errors) {
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
        if (substr($id, -2) !== \'.1\') {
            $search_ids[] = $id . \'.1\';
        }
    }
    
    // Use blastdbcmd to extract sequences - it accepts comma-separated IDs
    $ids_string = implode(\',\', $search_ids);
    $cmd = "blastdbcmd -db " . escapeshellarg($fasta_file) . " -entry " . escapeshellarg($ids_string) . " 2>/dev/null";
    $output = [];
    $return_var = 0;
    @exec($cmd, $output, $return_var);
    
    // Check if blastdbcmd executed
    if ($return_var > 1) {
        // Return code 1 is expected when some IDs don\'t exist, but >1 is an error
        $errors[] = "Error extracting $seq_type sequences (exit code: $return_var). Ensure blastdbcmd is installed and FASTA files are formatted correctly.";
        return $sequences;
    }
    
    // Check if we got any output
    // If empty, it just means these IDs don\'t exist in this file type (e.g., gene IDs won\'t be in genome.fa)
    // Return empty sequences gracefully - not an error
    if (empty($output)) {
        return $sequences;
    }
    
    // Parse FASTA output into individual sequences by feature ID
    $current_id = null;
    $current_seq = [];
    
    foreach ($output as $line) {
        if (strpos($line, \'>\') === 0) {
            // Header line
            if (!is_null($current_id)) {
                // Store previous sequence with full FASTA format (including >)
                $sequences[$current_id] = implode("\\n", array_merge([">" . $current_id], $current_seq));
            }
            // Extract ID from header (remove leading \'>\')
            $current_id = substr($line, 1);
            $current_seq = [];
        } else {
            // Sequence line
            $current_seq[] = $line;
        }
    }
    
    // Store last sequence with full FASTA format
    if (!is_null($current_id)) {
        $sequences[$current_id] = implode("\\n", array_merge([">" . $current_id], $current_seq));
    }
    
    return $sequences;
}',
    ),
  ),
);

function findFunction($funcName) {
    global $FUNCTION_REGISTRY;
    foreach ($FUNCTION_REGISTRY as $file => $functions) {
        foreach ($functions as $func) {
            if ($func['name'] === $funcName) {
                return ['file' => $file, 'line' => $func['line']];
            }
        }
    }
    return null;
}

function getAllFunctions() {
    global $FUNCTION_REGISTRY;
    $all = [];
    foreach ($FUNCTION_REGISTRY as $file => $functions) {
        foreach ($functions as $func) {
            $all[$func['name']] = ['file' => $file, 'line' => $func['line']];
        }
    }
    return $all;
}

function checkDuplicates() {
    global $FUNCTION_REGISTRY;
    $funcMap = [];
    $duplicates = [];
    foreach ($FUNCTION_REGISTRY as $file => $functions) {
        foreach ($functions as $func) {
            $name = $func['name'];
            if (isset($funcMap[$name])) {
                if (!isset($duplicates[$name])) {
                    $duplicates[$name] = [$funcMap[$name]];
                }
                $duplicates[$name][] = ['file' => $file, 'line' => $func['line']];
            } else {
                $funcMap[$name] = ['file' => $file, 'line' => $func['line']];
            }
        }
    }
    return $duplicates;
}
?>
