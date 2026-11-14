<?php
/**
 * BLAST Tool Functions
 * Centralized functions for BLAST operations across the application
 * Used by BLAST interface, FASTA extract, and other tools
 */

/**
 * Residues per FASTA line in everything MOOP writes -- what blastdbcmd prints, so sequences
 * read through the .fai reader look the same as ones it returns. Genomic regions and the Data
 * Exporter's exons used 60, so one results page showed two line widths side by side.
 */
const MOOP_FASTA_LINE_WIDTH = 80;

// Include parent_functions for parent->child lookups
require_once __DIR__ . '/parent_functions.php';
require_once __DIR__ . '/functions_system.php';
require_once __DIR__ . '/extract_search_helpers.php';   // buildSequenceBatch()
require_once __DIR__ . '/blast_queue.php';             // executeBlastSearch() waits its turn

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
    $databases = [];

    if (!is_dir($assembly_path)) {
        return $databases;
    }

    $sequence_types = ConfigManager::getInstance()->getSequenceTypes();

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
            $type_info = $type_mapping[$seq_type] ?? ['name' => ucfirst($seq_type), 'blast_type' => 'nucleotide'];

            // Accept if FASTA exists OR if a BLAST index exists (FASTA may have been removed after indexing)
            $idx_ext = $type_info['blast_type'] === 'protein' ? '.pin' : '.nin';
            if (!file_exists($file_path) && !file_exists($file_path . $idx_ext)) {
                // Gene sets live one level below the assembly dir — check parent for assembly-level files (e.g. genome.fa)
                $parent_path = dirname($assembly_path) . "/$pattern";
                if (file_exists($parent_path) || file_exists($parent_path . $idx_ext)) {
                    $file_path = $parent_path;
                }
            }
            if (file_exists($file_path) || file_exists($file_path . $idx_ext)) {
                $databases[] = [
                    'name'     => $type_info['name'],
                    'path'     => $file_path,
                    'type'     => $type_info['blast_type'],
                    'seq_type' => $seq_type,
                ];
            }
        }
    }

    // A transcriptome's "genome" (genome_kind = transcripts: genome.fa is one assembled transcript per
    // gene -- 21 assemblies, e.g. bkew.kc1.ref) holds the same sequences as the gene set's own transcript
    // database, under ".ref" ids whose hits link to coordinates instead of gene pages. Offering both
    // listed every sequence twice, the worse copy labelled "Genome". Dropped only when the gene set
    // has its own nucleotide database, so nothing becomes unsearchable.
    $nucleotide_own = array_filter($databases, fn($d) => $d['seq_type'] !== 'genome' && $d['type'] === 'nucleotide');
    if ($nucleotide_own) {
        require_once __DIR__ . '/genomic_regions.php';   // moop_genome_kind()
        $databases = array_values(array_filter($databases, fn($d) =>
            $d['seq_type'] !== 'genome' || moop_genome_kind(dirname($d['path'])) !== 'transcripts'));
    }

    return $databases;
}

/**
 * What a failed BLAST run may tell the visitor, and whether the failure belongs in the error log.
 *
 * BLAST's stderr mixes two kinds of failure. Some are the visitor's own input -- BLAST labels
 * those "BLAST query/options error:" (e.g. gap costs the chosen matrix does not support) --
 * and the visitor needs the message to fix it. Everything else is the server's fault (a
 * missing database, a missing binary, a crash), and that text can carry full server paths
 * ("No alias or index file found for protein database [/var/www/html/moop/organisms/...]"),
 * which a visitor can do nothing with. A reviewer flagged it shown to everyone (2026-11-09).
 *
 * How much that leak was worth to an attacker, so nobody over- or under-rates it later:
 * very little. The path layout (/var/www/html/moop/organisms/{organism}/{assembly}/...) is
 * the documented default in the public repository, so it tells an attacker nothing they
 * could not read on GitHub. The path also only names the database the visitor picked, and
 * tools/blast.php checks assembly access BEFORE running BLAST, so a restricted organism or
 * assembly can never surface this way. And knowing a path grants nothing: organism data
 * is served through access-checked endpoints, the web server refuses to execute PHP in the
 * writable trees (CLAUDE.md §11), and users.json lives outside the document root. What
 * the leak could reveal is host detail that is NOT in the repo -- a custom data path, a
 * BLAST install location, a temp directory (BLAST names its search path), the BLAST
 * version's wording. Low severity, but that, plus "a visitor cannot act on it", is why it
 * is hidden rather than left.
 *
 * So: input errors are shown as BLAST wrote them; server errors get a generic message, are
 * logged in full, and only an admin sees the detail on the page. An input-labelled message
 * that nonetheless contains an absolute path is treated as a server error -- the label is
 * BLAST's, the guarantee about paths is ours.
 *
 * @param array $result   executeBlastSearch()'s result (uses 'error' and 'stderr')
 * @param bool  $is_admin whether the viewer may see server detail
 * @return array ['message' => string to show, 'log' => bool (server-side fault)]
 */
function blast_failure_for_display(array $result, bool $is_admin): array {
    $error  = trim((string)($result['error'] ?? ''));
    $stderr = trim((string)($result['stderr'] ?? ''));

    // No stderr: the message is one MOOP wrote itself (queue full, timed out, output too
    // large, missing parameters), none of which names a path. Shown as is, as before.
    if ($stderr === '') {
        return ['message' => $error, 'log' => false];
    }

    $is_input_error = preg_match('/^BLAST (query\/options|query|options) error:/', $stderr)
                   && !preg_match('#(^|[\s\[(\'"=])/[A-Za-z0-9_.-]+/#', $stderr);
    if ($is_input_error) {
        return ['message' => $stderr, 'log' => false];
    }

    $message = t_plain('BLAST could not run against this database. This is a problem on our side, '
                     . 'not with your sequence, and it has been logged.');
    if ($is_admin) {
        $message .= "\n\nDetails (shown to admins only): " . $error . "\n" . $stderr;
    }
    return ['message' => $message, 'log' => true];
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
    // Every search waits its turn in the BLAST queue (lib/blast_queue.php), sized by the
    // memory it is estimated to need. Without it, a handful of genome tblastns at once ran
    // the server out of memory (capacity test, 2026-10-23).
    $queued = blast_queue_run(
        fn() => runBlastSearch($query_seq, $blast_db, $program, $options),
        blast_queue_cost_units((string)$program, (string)$blast_db),
        blast_queue_ticket($options['queue_ticket'] ?? null)
    );
    if (!$queued['ran']) {
        return ['success' => false, 'output' => '', 'error' => $queued['error'], 'stderr' => ''];
    }
    $queued['result']['queue_waited'] = $queued['waited'];
    return $queued['result'];
}

/**
 * The search itself, run once executeBlastSearch() has a place for it. Call that, not this.
 */
function runBlastSearch($query_seq, $blast_db, $program, $options = []) {
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
    
    // Temp files on the big disk, not the 4 GB /tmp: the raw archive can run to hundreds of MB
    // before the size check sees it (lib/temp_dir.php)
    require_once __DIR__ . '/temp_dir.php';
    $temp_dir = moop_temp_dir();
    $archive_file = tempnam($temp_dir, 'blast_');
    
    if ($archive_file === false) {
        $result['error'] = 'Failed to create temporary file for BLAST output';
        return $result;
    }
    
    // Build BLAST command with outfmt 11 (ASN.1). Tool paths come from moop_tool():
    // the configured blast_tools value, resolved to an absolute path.
    $config = ConfigManager::getInstance();

    // ONLY these binaries, and the path shell-quoted. $program arrives from a form field
    // (tools/blast.php posts blast_program), and this used to fall back to `?? $program`
    // and put it into the command UNQUOTED: any posted value that was not a configured
    // tool name became shell text -- remote command execution as the web server user,
    // reachable anonymously on any public assembly. Found and closed 2026-10-13.
    if (!in_array($program, ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'], true)) {
        unlink($archive_file);
        $result['error'] = 'Unknown BLAST program.';
        return $result;
    }
    $program_bin = moop_tool($program, 'blast_tools');
    if ($program_bin === '') {
        unlink($archive_file);
        $result['error'] = "$program is not installed or not on the web server's PATH.";
        return $result;
    }
    $program_path = escapeshellarg($program_bin);
    $num_threads = $config->getInt('blast_num_threads', 2);
    if ($num_threads < 1) $num_threads = 1;

    $cmd = [];
    $cmd[] = $program_path;
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
    // -num_alignments is what limits an archive (-outfmt 11); -num_descriptions only
    // produced "ignored for output formats > 4" on every run.
    $cmd[] = '-num_alignments ' . escapeshellarg($max_hits);
    $cmd[] = '-num_threads ' . $num_threads;
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
    
    // Advanced options.
    //
    // These were collected by the form and passed in $options from the very first
    // commit that added the Advanced panel, but nothing here ever read them -- the
    // panel set word size, strand, percent identity and the rest, and BLAST never saw
    // any of it. Checked with `git log -S`: word_size has never appeared in this file.
    //
    // Each flag is emitted ONLY for the programs that accept it. This is not caution:
    // -perc_identity to blastp, or -strand to tblastn, is a hard usage error that makes
    // BLAST exit non-zero and return nothing. The matrix below was read off
    // `<program> -help` for all five programs rather than assumed.
    $supported_by = [
        'word_size'     => ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'],
        'gapopen'       => ['blastn', 'blastp', 'blastx', 'tblastn'],
        'gapextend'     => ['blastn', 'blastp', 'blastx', 'tblastn'],
        'max_hsps'      => ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'],
        'perc_identity' => ['blastn'],
        'culling_limit' => ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'],
        'threshold'     => ['blastp', 'blastx', 'tblastn', 'tblastx'],
        'soft_masking'  => ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'],
        'strand'        => ['blastn', 'blastx', 'tblastx'],
        'ungapped'      => ['blastn', 'blastp', 'blastx', 'tblastn'],
    ];
    $accepts = function ($opt) use ($supported_by, $program) {
        return in_array($program, $supported_by[$opt], true);
    };

    // Numeric options: 0 and '' both mean "not set", since the form uses empty inputs
    // for "use the program default" and casts to int on the way in.
    foreach (['word_size', 'gapopen', 'gapextend', 'max_hsps', 'culling_limit'] as $opt) {
        $val = $options[$opt] ?? 0;
        if ($accepts($opt) && $val !== '' && (int)$val > 0) {
            $cmd[] = '-' . $opt . ' ' . escapeshellarg((int)$val);
        }
    }

    // These two are floats and 0 is a legitimate value the user may want.
    foreach (['perc_identity', 'threshold'] as $opt) {
        $val = $options[$opt] ?? '';
        if ($accepts($opt) && $val !== '' && is_numeric($val)) {
            $cmd[] = '-' . $opt . ' ' . escapeshellarg($val);
        }
    }

    if ($accepts('strand') && !empty($options['strand'])
        && in_array($options['strand'], ['plus', 'minus', 'both'], true)) {
        $cmd[] = '-strand ' . escapeshellarg($options['strand']);
    }

    if ($accepts('soft_masking') && isset($options['soft_masking'])) {
        $cmd[] = '-soft_masking ' . ($options['soft_masking'] ? 'true' : 'false');
    }

    // -ungapped is a bare flag, not a valued option.
    if ($accepts('ungapped') && !empty($options['ungapped'])) {
        $cmd[] = '-ungapped';
    }

    // Add task if specified.
    //
    // -task must come LAST so it cannot clobber an explicit flag above -- though note
    // BLAST resolves task defaults first and explicit flags win regardless of order,
    // which is exactly how -evalue 10 used to defeat blastn-short's own default of 1000.
    if (!empty($task)) {
        $cmd[] = '-task ' . escapeshellarg($task);
    }

    // The query goes to BLAST's stdin (its default -query). It used to be piped in with
    // `printf <query>`, and printf reads its first argument as a FORMAT: a header like
    // ">clone 100% identical" became "clone 100 0dentical", and "\n" in a header became a
    // real newline that pushed the rest of the header into the sequence.
    $command = blast_queue_wrap_command(implode(' ', $cmd));
    
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
    
    fwrite($pipes[0], $query_seq);
    fclose($pipes[0]);
    $blast_stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $blast_stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $return_code = proc_close($process);
    
    if (blast_queue_timed_out($return_code)) {
        @unlink($archive_file);
        $result['error'] = blast_queue_timeout_message();
        return $result;
    }
    if ($return_code !== 0) {
        unlink($archive_file);
        $result['error'] = 'BLAST execution failed with code ' . $return_code;
        $result['stderr'] = $blast_stderr;
        return $result;
    }
    
    $too_big = blastOutputTooLarge($archive_file, true);
    if ($too_big !== '') {
        unlink($archive_file);
        $result['error'] = $too_big;
        return $result;
    }

    // Convert ASN.1 archive to outfmt 5 (XML) using blast_formatter
    $xml_file = tempnam($temp_dir, 'blast_xml_');
    $blast_formatter_path = moop_tool('blast_formatter', 'blast_tools') ?: 'blast_formatter';
    $formatter_cmd = escapeshellarg($blast_formatter_path) . ' -archive ' . escapeshellarg($archive_file) . 
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
    
    // Too much to show? Checked BEFORE reading: a transcript searched against a genome with
    // the low-complexity filter off gave 344,582 alignments -- 224 MB of XML -- and reading it
    // blew PHP's memory limit, so the user waited a minute for an error page. Here they get a
    // reason and what to change instead. (The archive check above catches most of these
    // sooner; this one is the backstop.)
    $too_big = blastOutputTooLarge($xml_file);
    if ($too_big !== '') {
        unlink($archive_file);
        unlink($xml_file);
        $result['error'] = $too_big;
        return $result;
    }

    // Read the XML output
    $output = file_get_contents($xml_file);
    
    // Now convert ASN.1 archive to outfmt 0 (Pairwise text) for download
    $pairwise_file = tempnam($temp_dir, 'blast_pairwise_');
    $formatter_cmd = escapeshellarg($blast_formatter_path) . ' -archive ' . escapeshellarg($archive_file) . 
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
    unlink($pairwise_file);

    // Convert ASN.1 archive to outfmt 6 (tabular) with stitle for TSV download
    $tabular_output = '';
    $tabular_file = tempnam($temp_dir, 'blast_tabular_');
    $tabular_fmt = '6 qseqid sseqid pident length mismatch gapopen qstart qend sstart send evalue bitscore stitle';
    $formatter_cmd = escapeshellarg($blast_formatter_path) . ' -archive ' . escapeshellarg($archive_file) .
                     ' -outfmt ' . escapeshellarg($tabular_fmt) . ' -out ' . escapeshellarg($tabular_file);
    $process = proc_open($formatter_cmd, $descriptors, $pipes);
    if (is_resource($process)) {
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $return_code = proc_close($process);
        if ($return_code === 0) {
            $header = "# qseqid\tsseqid\tpident\tlength\tmismatch\tgapopen\tqstart\tqend\tsstart\tsend\tevalue\tbitscore\tstitle\n";
            $tabular_output = $header . file_get_contents($tabular_file);
        }
    }
    unlink($tabular_file);

    // Clean up temporary files
    unlink($archive_file);
    unlink($xml_file);

    $result['success'] = true;
    $result['output']   = $output;
    $result['pairwise'] = $pairwise_output;
    $result['tabular']  = $tabular_output;
    $result['stderr']   = $blast_stderr;

    return $result;
}

/**
 * '' if a BLAST result is small enough to display, else the message saying why not.
 *
 * The limit (blast_max_output_mb, default 5 MB of XML) is what fits PHP's memory_limit:
 * the page holds the XML, the drawn alignments and hidden copies of the pairwise, tabular and
 * XML text -- about 4x the XML, measured 2026-10-27 (12.7 MB of XML was a 502; the old default
 * of 20 let that through). The pairwise and tabular downloads are made from the same archive, so the XML size
 * stands for all three.
 *
 * Checked twice. First on the ASN.1 archive, the moment BLAST finishes: it runs ~1.4x the
 * size of the XML made from it (measured: 319 MB archive -> 224 MB XML), so an archive over
 * 1.4x the limit is refused without spending ~11 s converting it -- that conversion is what
 * pushed the refusal to 57 s, against nginx's 60. Then on the XML itself, as the backstop.
 *
 * @param string $file        the archive or the XML
 * @param bool   $is_archive  true for the ASN.1 archive
 */
function blastOutputTooLarge(string $file, bool $is_archive = false): string {
    $limit_mb = max(1, ConfigManager::getInstance()->getInt('blast_max_output_mb', 5));
    $size = (int)@filesize($file);
    $xml_size = $is_archive ? $size / 1.4 : $size;
    if ($xml_size <= $limit_mb * 1048576) return '';

    return 'This search found too many alignments to show -- about ' . round($xml_size / 1048576)
        . " MB of results, over the {$limit_mb} MB limit. "
        . 'This usually means the query contains repeats or simple sequence. '
        . 'Turn "Filter low complexity regions" back on, or set Max HSPs (for example 20) '
        . 'or a stricter E-value under Advanced options. '
        . 'For a search this large, run BLAST+ on your own computer: the FASTA files are on the Downloads page.';
}

/**
 * Extract sequences from a BLAST database using blastdbcmd.
 * Used by Sequence Retrieval, the gene page, search-results FASTA and the Data Exporter.
 *
 * One path for whole sequences and ranges alike: the requests become a blastdbcmd
 * -entry_batch file (buildSequenceBatch()), one line per "ID" or "ID start-end". This
 * replaced two code paths -- a plain -entry list, and a four-step range builder that kept
 * only the first range per ID and only matched children named "ID.<digits>".
 *
 * @param string $blast_db           Path to the FASTA / BLAST database (without extension)
 * @param array  $sequence_ids       IDs routed to this FASTA
 * @param string $organism           Unused; kept for the caller's argument order
 * @param string $assembly           Unused; kept for the caller's argument order
 * @param array  $requests           parseFeatureIds()['requests']; empty = each of
 *                                   $sequence_ids, whole
 * @param array  $parent_to_children ID => its descendants, so a request reaches them
 * @return array ['success' => bool, 'content' => string, 'error' => string]
 */
function extractSequencesFromBlastDb($blast_db, $sequence_ids, $organism = '', $assembly = '', array $requests = [], array $parent_to_children = []) {
    $result = ['success' => false, 'content' => '', 'error' => ''];

    if (empty($sequence_ids) || !is_array($sequence_ids)) {
        $result['error'] = 'No sequence IDs provided';
        return $result;
    }
    if (!file_exists($blast_db . '.nhr') && !file_exists($blast_db . '.phr')) {
        $result['error'] = 'BLAST database not found';
        $result['system_error'] = true;   // a server fault, not the visitor's input -- logged
        return $result;
    }

    if (!$requests) {
        $requests = array_map(fn($id) => ['id' => $id, 'start' => null, 'end' => null], array_values($sequence_ids));
    }
    $batch = buildSequenceBatch($requests, array_values($sequence_ids), $parent_to_children);
    if (!$batch) {
        $result['error'] = 'No sequences found for the requested IDs';
        return $result;
    }

    // Fast path: whole sequences from a current .fai index, read in-process -- no blastdbcmd
    // process (~225 ms each). Output is what blastdbcmd prints: the full header line, the
    // residues uppercased, 80 per line. This is what lets the gene page DISPLAY through this
    // same function as its downloads, instead of a second reader of its own.
    $ranged = array_filter($batch, fn($line) => strpos($line, ' ') !== false);
    if (!$ranged) {
        require_once __DIR__ . '/fasta_index.php';
        if (moop_fasta_index_available($blast_db)) {
            $records = [];
            foreach (moop_fasta_fetch_with_headers($blast_db, $batch) as $rec) {
                $records[] = '>' . $rec['header'] . "\n" . rtrim(chunk_split($rec['seq'], MOOP_FASTA_LINE_WIDTH, "\n"));
            }
            if (!$records) {
                $result['error'] = 'No sequences found for the requested IDs';
                return $result;
            }
            $result['success'] = true;
            $result['content'] = implode("\n", $records);
            return $result;
        }
    }

    require_once __DIR__ . '/temp_dir.php';
    $temp_file = tempnam(moop_temp_dir(), 'blastdb_');
    if ($temp_file === false || file_put_contents($temp_file, implode("\n", $batch) . "\n") === false) {
        if ($temp_file !== false) @unlink($temp_file);
        $result['error'] = 'Failed to write the batch file for sequence extraction';
        $result['system_error'] = true;   // a server fault, not the visitor's input -- logged
        return $result;
    }

    $blastdbcmd_path = moop_tool('blastdbcmd', 'blast_tools') ?: 'blastdbcmd';
    $cmd = escapeshellarg($blastdbcmd_path) . ' -db ' . escapeshellarg($blast_db) . ' -entry_batch ' . escapeshellarg($temp_file) . ' 2>&1';
    $output     = [];
    $return_var = 0;
    @exec($cmd, $output, $return_var);
    @unlink($temp_file);

    // blastdbcmd reports an ID it does not hold as an "Error:" line and exits 1 -- expected
    // when IDs are tried against several FASTAs. Anything above 1 is a real failure.
    $output = array_filter($output, fn($line) => strpos(trim($line), 'Error:') !== 0);
    if ($return_var > 1) {
        $result['error'] = "Error extracting sequences (exit code: $return_var). Ensure blastdbcmd is installed and FASTA files are formatted correctly.";
        $result['system_error'] = true;   // a server fault, not the visitor's input -- logged
        return $result;
    }
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
            
            // Not in the mapping: decide by the type key, not by the filename
            if (!$type_info) {
                $is_protein = sequence_type_blast_dbtype($seq_type) === 'prot';
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
 * BLAST index status for one ASSEMBLY: validateBlastIndexFiles() over each gene-set directory,
 * aggregated -- plus, for a BLAST-only genome (genome.json "blast_only": true, no gene set), the
 * genome's own index in the assembly directory, which is the only database it has. Shared by the
 * organism cache and its uncached fallback in getOrganismOverallStatus(), which used to each walk
 * gene-set dirs only, so a BLAST-only genome's missing index was never reported (item 8).
 *
 * @return array{databases: list<array>, missing_count: int, total_count: int}
 */
function moop_assembly_blast_validation(string $assembly_path, array $sequence_types): array {
    require_once __DIR__ . '/genome_meta.php';
    $out = ['databases' => [], 'missing_count' => 0, 'total_count' => 0];
    $gs_dirs = glob($assembly_path . '/*', GLOB_ONLYDIR) ?: [];
    $dirs = $gs_dirs;
    $types = $sequence_types;
    if (!$gs_dirs && isset($sequence_types['genome']) && moop_genome_declares_blast_only($assembly_path)) {
        $dirs  = [$assembly_path];
        $types = ['genome' => $sequence_types['genome']];
    }
    foreach ($dirs as $dir) {
        $bv = validateBlastIndexFiles($dir, $types);
        $out['databases']      = array_merge($out['databases'], $bv['databases']);
        $out['missing_count'] += $bv['missing_count'];
        $out['total_count']   += $bv['total_count'];
    }
    return $out;
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
function generateBlastIndexes($organism, $assembly, $fasta_filename, $organism_data_path, $gene_set) {
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

    // Prevent directory traversal attacks. '.' and '..' contain no '/' but still climb: gene set
    // '..' used to index a FASTA sitting in the organism directory.
    foreach ([$organism, $assembly, $fasta_filename, $gene_set] as $part) {
        if (!moop_plain_name($part)) {
            $result['message'] = 'Invalid characters in parameters';
            return $result;
        }
    }

    // A BLAST-only genome (genome.json "blast_only": true) has no gene-set directory: its one
    // database, the genome, sits in the assembly directory (ASSEMBLY_WITHOUT_GENE_SET_PLAN item 8).
    if ($gene_set === (defined('MOOP_BLAST_ONLY') ? MOOP_BLAST_ONLY : 'blast_only')) {
        require_once __DIR__ . '/genome_meta.php';
        $assembly_path = $organism_data_path . '/' . $organism . '/' . $assembly;
        if (!moop_genome_declares_blast_only($assembly_path)) {
            $result['message'] = 'This assembly is not declared BLAST-only (genome.json "blast_only": true)';
            return $result;
        }
    } else {
        $assembly_path = $organism_data_path . '/' . $organism . '/' . $assembly . '/' . $gene_set;
    }
    $fasta_path = $assembly_path . '/' . $fasta_filename;

    // Verify paths exist
    if (!is_dir($assembly_path)) {
        $result['message'] = 'Gene set directory does not exist';
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
    
    // Only a configured sequence FASTA is indexed, and its -dbtype comes from the
    // configured type (see sequence_type_blast_dbtype()), never from the filename.
    $seq_type = sequence_type_for_filename($fasta_filename);
    if ($seq_type === null) {
        $result['message'] = 'Not a configured sequence FASTA: ' . $fasta_filename;
        return $result;
    }
    $db_type = sequence_type_blast_dbtype($seq_type);
    
    // Build command with configurable path to makeblastdb
    $makeblastdb_path = moop_tool('makeblastdb', 'blast_tools');
    if ($makeblastdb_path === '') {
        $result['message'] = "makeblastdb is not installed or not on the web server's PATH.";
        return $result;
    }
    $cd_cmd = 'cd ' . escapeshellarg($assembly_path);
    // Every argument escaped (CLAUDE.md §8). The filename was interpolated raw here,
    // on the reasoning that we had cd'd into its directory -- which has nothing to do
    // with shell metacharacters. Only '/' was rejected upstream.
    $makeblastdb_cmd = escapeshellarg($makeblastdb_path) . ' -in ' . escapeshellarg($fasta_filename)
                     . ' -dbtype ' . escapeshellarg($db_type) . ' -parse_seqids 2>&1';
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
            $index_ext = $db_type === 'prot' ? ['phr', 'pin', 'psq'] : ['nhr', 'nin', 'nsq'];
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

/**
 * Extract a genomic region from a samtools-indexed FASTA using pure PHP.
 * Uses the .fai 5-column index to fseek directly — no external tools required.
 *
 * @param string $fasta_path
 * @param string $fai_path
 * @param string $seqname    Must match first column of .fai exactly
 * @param int    $start      1-based inclusive
 * @param int    $end        1-based inclusive
 * @return string|null  Uppercase DNA sequence, or null if seqname not found
 */
if (!function_exists('extractFastaRegion')) {
function extractFastaRegion(string $fasta_path, string $fai_path, string $seqname, int $start, int $end): ?string
{
    require_once __DIR__ . '/fasta_index.php';
    $entry = moop_fai_lookup($fai_path, [$seqname])[$seqname] ?? null;
    if (!$entry) return null;
    $fh = fopen($fasta_path, 'rb');
    if ($fh === false) return null;
    $seq = moop_fasta_read_at($fh, $entry, $start, $end);
    fclose($fh);
    return $seq;
}
}

/**
 * Read bases $start..$end (1-based, inclusive) of one sequence from an open FASTA, given its
 * .fai entry (moop_fai_lookup()'s shape). The read half of extractFastaRegion(), so a batch
 * can look its sequences up ONCE and cut many regions: extractFastaRegion() scans the .fai
 * from the top on every call, which on Carollia's 1.9 M-line index cost 5.7 ms a region.
 *
 * @param resource $fh
 * @param array{len: int, off: int, lb: int, lw: int} $entry
 * @return string|null uppercase bases; null when the range starts past the sequence's end
 */
function moop_fasta_read_at($fh, array $entry, int $start, int $end): ?string
{
    if ($entry['lb'] <= 0) return null;
    // Clamp to contig boundaries so we never read past the end into the next entry
    if ($end > $entry['len']) $end = $entry['len'];
    if ($start < 1) $start = 1;
    if ($start > $end) return null;

    $s        = $start - 1;
    $len      = $end - $start + 1;
    $byte_pos = $entry['off'] + intdiv($s, $entry['lb']) * $entry['lw'] + ($s % $entry['lb']);
    if (fseek($fh, $byte_pos) !== 0) return null;

    $seq       = '';
    $remaining = $len;
    while ($remaining > 0) {
        $read_len = $remaining + (int)ceil($remaining / $entry['lb']) + 4;
        $chunk    = fread($fh, min(65536, $read_len));
        if ($chunk === false || $chunk === '') break;
        $clean     = str_replace(["\r\n", "\r", "\n"], '', $chunk);
        $take      = min($remaining, strlen($clean));
        $seq      .= substr($clean, 0, $take);
        $remaining -= $take;
    }
    return strtoupper($seq);
}

/**
 * Return the reverse complement of a DNA sequence.
 */
if (!function_exists('reverseComplement')) {
function reverseComplement(string $seq): string
{
    return strrev(strtr(strtoupper($seq), ['A' => 'T', 'T' => 'A', 'C' => 'G', 'G' => 'C']));
}
}

/**
 * The BLAST programs offered on the search page, in the order they are listed.
 *
 * ONE definition, because the dropdown and the help modal must agree about what
 * each program does. They were separate before: the <option> text lived in
 * tools/pages/blast.php and any explanation lived wherever it was last written,
 * so a correction to one silently left the other saying something else.
 * js/modules/utilities.js still keeps its own list of which programs take a
 * nucleotide query -- that one drives sequence-type detection, not description,
 * and is left alone rather than half-merged.
 *
 * 'summary' completes the option label after the em dash. 'when' is the card
 * text in the help modal and is deliberately about 25 words: the format is the
 * constraint that keeps the help readable (see help_modal() in lib/help_ui.php).
 *
 * What RUNS is here too, so the page cannot offer a program the server would run
 * differently:
 *   'binary'  the BLAST+ executable (defaults to the key)
 *   'task'    -task, or '' for none. blastn MUST name one: with no -task, blastn runs
 *             megablast (`blastn -help`: Default = `megablast'), which is how "BLASTn"
 *             quietly meant megablast until 2026-10-13 -- 8 hits became 2 on a related
 *             bat's transcripts.
 *   'filter'  whether DUST/SEG is on by default -- BLAST's own default for that task,
 *             so the checkbox starts where BLAST itself would.
 *   'soft_masking'  BLAST's own -soft_masking default (`<program> -help`): true for
 *             blastn, false for the rest. The form sends this flag on EVERY search, so a
 *             checkbox starting unticked forced blastn to hard masking.
 */
if (!function_exists('blast_programs')) {
function blast_programs(): array
{
    return [
        'blastn' => [
            'query'   => 'DNA',
            'db'      => 'DNA',
            'label'   => 'BLASTn',
            'summary' => 'DNA query vs DNA database',
            'when'    => 'DNA against DNA, for the SAME species: finds a gene, transcript or '
                       . 'genomic region in its own assembly. Between species the DNA drifts '
                       . 'long before the protein does, so BLASTx usually finds more there.',
            'task'    => 'blastn',
            'filter'  => true,
            'soft_masking' => true,
        ],
        'megablast' => [
            'query'   => 'DNA',
            'db'      => 'DNA',
            'label'   => 'Megablast',
            'summary' => 'Fast, for near-identical DNA',
            'when'    => 'Faster than BLASTn but finds only close matches: the same gene in '
                       . 'the same species. Misses diverged and cross-species hits.',
            'binary'  => 'blastn',
            'task'    => 'megablast',
            'filter'  => true,
            'soft_masking' => true,
        ],
        'blastp' => [
            'query'   => 'Protein',
            'db'      => 'Protein',
            'label'   => 'BLASTp',
            'summary' => 'Protein query vs protein database',
            'when'    => 'Protein on both sides. Use it to find homologues of a protein you '
                       . 'already have in amino-acid form.',
            'task'    => '',
            'filter'  => false,
            'soft_masking' => false,
        ],
        'blastx' => [
            'query'   => 'DNA',
            'db'      => 'Protein',
            'label'   => 'BLASTx',
            'summary' => 'DNA query vs protein database (translated)',
            'when'    => 'The default, and the best first search for DNA across species. '
                       . 'Translates your query in all six frames and searches proteins, where '
                       . 'the similarity survives. Also tells you what your DNA codes for.',
            'task'    => '',
            'filter'  => true,
            'soft_masking' => false,
        ],
        'tblastn' => [
            'query'   => 'Protein',
            'db'      => 'DNA',
            'label'   => 'tBLASTn',
            'summary' => 'Protein query vs DNA database (translated)',
            'when'    => 'You have a protein and want to find its gene in a genome that is '
                       . 'not annotated yet. Translates the database, not your query.',
            'task'    => '',
            'filter'  => true,
            'soft_masking' => false,
        ],
        'tblastx' => [
            'query'   => 'DNA',
            'db'      => 'DNA',
            'label'   => 'tBLASTx',
            'summary' => 'DNA query vs DNA database (both translated)',
            'when'    => 'Translates BOTH sides. For distant relationships where the protein '
                       . 'is conserved but the DNA is not. Thorough, and by far the slowest.',
            'task'    => '',
            'filter'  => true,
            'soft_masking' => false,
        ],
        'blastn-short' => [
            'query'   => 'Short DNA',
            'db'      => 'DNA',
            'label'   => 'BLASTn-short',
            'summary' => 'Short DNA query (primer, probe, guide RNA, ~20nt)',
            'when'    => 'Anything under about 30 nt: primers, probes, guide RNAs, adapters, '
                       . 'barcodes. The ordinary settings discard perfect short matches, so '
                       . 'this preset is not optional for them.',
            'color'   => 'success',
            'binary'  => 'blastn',
            'task'    => 'blastn-short',
            'filter'  => false,
            'soft_masking' => true,
        ],
    ];
}
}

/**
 * Below this many letters a DNA query is "short": BLASTn and Megablast lose it at their
 * defaults and BLASTn-short is the right program. ONE value: the BLAST form's suggestion
 * (js/modules/blast-short-nudge.js, passed in from tools/blast.php) and the zero-hit
 * explanation below both read it, and the help ("anything under about 30 nt",
 * includes/blast_short_help_modal.php) says the same.
 */
if (!defined('BLAST_SHORT_QUERY_NT')) define('BLAST_SHORT_QUERY_NT', 30);

/**
 * Why a short DNA query came back with zero hits, for the program that actually ran -- or
 * '' when the query is not short or the program is not one that loses short queries.
 *
 * Per program, because the reasons differ (text review N8, measured 2026-11-02 on the
 * Schmidtea mediterranea CDS): Megablast's 28-letter word cannot seed a 20 nt query at ANY
 * E-value; BLASTn seeds from 11 and finds it, then a strict E-value discards it; BLASTx and
 * tBLASTx translate it to under 7 amino acids, too few to score.
 *
 * @param string $program   blast_programs() key that ran
 * @param int    $length    query length in letters
 * @param array  $options   the options the search ran with (evalue, word_size)
 * @return string  plain-text sentence (caller escapes), or ''
 */
function blast_short_query_reason(string $program, int $length, array $options): string
{
    if ($length <= 0 || $length >= BLAST_SHORT_QUERY_NT) return '';
    if (!in_array($program, ['blastn', 'megablast', 'blastx', 'tblastx'], true)) return '';

    $word   = (int)($options['word_size'] ?? 0);     // tools/blast.php sends 0 for "program default"
    $evalue = trim((string)($options['evalue'] ?? ''));

    if ($program === 'blastx' || $program === 'tblastx') {
        $label = blast_programs()[$program]['label'] ?? $program;
        $aa    = intdiv($length, 3);
        return "This query is $length letters long, only $aa amino acids once translated -- "
             . "too short for $label to score. To find where it sits in the DNA, use BLASTn-short.";
    }
    if ($word > 0) {
        return "This query is $length letters long, which is short for this program.";
    }
    if ($program === 'megablast') {
        return "This query is $length letters long. Megablast needs 28 identical letters in a "
             . "row to start a match, so it cannot find a query this short at any E-value.";
    }
    if ($evalue !== '' && is_numeric($evalue) && (float)$evalue < 1000) {
        return "This query is $length letters long. BLASTn can find a match this short, but at "
             . "E-value $evalue it is discarded as not significant.";
    }
    return "This query is $length letters long. BLASTn-short's smaller word size (7) can find "
         . "matches BLASTn misses.";
}
