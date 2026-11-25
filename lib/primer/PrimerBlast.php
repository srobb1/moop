<?php
/**
 * PrimerBlast - run primer sequences against a BLAST database and return hits.
 *
 * Short queries need settings that are the opposite of the usual BLAST defaults,
 * and getting them wrong fails SILENTLY. Both traps are already documented in
 * tools/blast.php:62-95 and are reproduced here deliberately:
 *
 *   -evalue 1000   blastn-short's own default. An explicit low E-value discards
 *                  real hits: a 20 nt primer went from 566 hits to 26 under -evalue 10,
 *                  because a 20 nt perfect match scores ~40 bits, which is not
 *                  "significant" against a whole genome.
 *   -dust no       DUST masks LOW-COMPLEXITY sequence (ATATAT..., poly-A) -- not
 *                  AT-rich sequence. Left on, such a primer is masked to nothing and
 *                  returns ZERO hits with no warning (ATATAT... 202 -> 0, poly-A 2236 -> 0).
 *                  A checker must report a bad primer AS bad, never as "no hits".
 *
 * Measured on this host: two 20-mers against a genome produce ~4,000 raw hits.
 * That is the real noise floor, so a caller must always apply a mismatch bound.
 *
 * @package MOOP\Primer
 */

class PrimerBlast
{
    /** blastn-short's own E-value default. Short queries need this RAISED, not lowered. */
    const EVALUE = 1000;

    /** Word size matching the short-query preset. */
    const WORD_SIZE = 7;

    /**
     * The 3'-end window that decides whether a site can prime, in bases.
     *
     * 5 follows NCBI Primer-BLAST, whose default dismisses an unintended target
     * when a primer carries 2 or more mismatches within its last 5 bases.
     */
    const THREE_PRIME_WINDOW = 5;

    /**
     * Hits shorter than this fraction of the primer are not priming events under
     * any interpretation -- this is the generous hard floor, not a judgement call.
     * 4,087 raw hits for two 20-mers is unusable; something must go.
     */
    const MIN_LENGTH_FRACTION = 0.75;

    /**
     * The search keeps every hit with up to this many mismatches, whatever the page's
     * "Mismatches listed" box says. One under PrimerPairs::IGNORE_AT_TOTAL_MISMATCH
     * (NCBI Primer-BLAST checks targets with up to 5): a single mismatch, or a few toward
     * the 5' end, still amplify, so the VERDICT must see them. The box only decides what
     * is LISTED -- until 2026-10-13 it filtered here, and at its default of 1 a pair could
     * read "specific" over a 2-mismatch off-target that would amplify.
     */
    const SEARCH_MAX_MISMATCH = 5;

    /** Default of the page's "Mismatches listed" box -- a DISPLAY filter, see above. */
    const DISPLAY_MISMATCH_DEFAULT = 2;

    /**
     * A primer that matches more places than this (within SEARCH_MAX_MISMATCH) is called
     * REPETITIVE: its sites stop being stored (they are still counted, so the number reported
     * is exact) and no products are worked out for its pair.
     *
     * Measured 2026-11-24, random 20-mers: normal ones keep 45-914 sites (Acropora, Smed,
     * wallaby genomes -- mostly 3-5 mismatches, which are listed but rarely prime); one in a
     * repeat family kept 6,042 (Smed), a wallaby SINE-like one 46,861, poly-A 106,144 per primer.
     * Without a ceiling poly-A crashed the page: 876,050 blastn lines read into PHP's 128 MB,
     * and pairing 106k x 106k sites could never finish anyway. A BLAST cap on what it reports
     * would instead UNDERCOUNT silently and could call such a pair specific -- see MAX_TARGET_SEQS.
     */
    const MAX_SITES_PER_PRIMER = 5000;

    /**
     * -max_target_seqs: report EVERY subject sequence a primer matches.
     *
     * Left unset until 2026-11-24, blastn used its default of 500 subjects -- so on a
     * scaffold-level assembly (74 of 95 genomes then had more than 500 sequences) matches
     * past the 500 best-scoring scaffolds were silently dropped. GAPDH primers on Carollia
     * (1.9 M scaffolds): 500/500 subjects vs 2,031/2,614 real; 79/84 kept sites vs 166/333,
     * 327 of the dropped ones with ONE mismatch -- exactly what decides "specific". Lifting it
     * cost 62 MB of heap there. Above any assembly's sequence count; memory is bounded by
     * HEAP_LIMIT_MB, not by under-reporting.
     */
    const MAX_TARGET_SEQS = 100000000;

    /**
     * blastn's heap allowance per run, in MB (prlimit --data: private writable memory only,
     * so the memory-mapped database -- shared page cache -- does not count against it).
     *
     * Measured 2026-11-24: a normal pair needs 8-430 MB of heap (wallaby, two runs at once
     * the high end). Poly-A against the wallaby genome took 5.2 GB and 75 s for 1.9 M hits --
     * two at once could exhaust this host. Over the allowance blastn stops ("BLAST ran out of
     * memory", exit 134) and the pair is reported repetitive (see run()). Also the run's cost
     * in the BLAST queue: the most one check can hold.
     */
    const HEAP_LIMIT_MB = 1024;

    /**
     * Sites stored across the whole run (all pairs). Bounds memory when many pairs each sit
     * just under MAX_SITES_PER_PRIMER; past it the run fails with a message, never a crash.
     */
    const MAX_SITES_PER_RUN = 60000;

    /**
     * Run both primers of every pair against one BLAST database.
     *
     * @param array  $pairs        From PrimerInput::parse()['pairs'].
     * @param string $db           Path to the BLAST database (no extension).
     * @param array  $options      ['max_mismatch' => int (search bound, default
     *                             SEARCH_MAX_MISMATCH), 'blastn' => string]
     * @return array {
     *     @type bool   $success
     *     @type string $error
     *     @type array  $hits      Keyed by pair index, then 'forward'/'reverse'.
     *     @type int    $below_floor  Hits discarded by the length floor (whole run).
     *     @type int    $over_mismatch Hits discarded by the mismatch bound (whole run).
     *     @type array  $discards     PER PAIR index => ['below_floor'=>n,'over_mismatch'=>n].
     *                                Reporting the run-wide total against each pair would
     *                                show every pair the same number, which is wrong the
     *                                moment more than one pair is searched.
     *     @type array  $repetitive   PER PAIR index => ['forward'=>n,'reverse'=>n]: the exact
     *                                site count of a primer over MAX_SITES_PER_PRIMER, else 0.
     *                                Such a primer's hits list is EMPTY -- not its real sites.
     *     @type array  $out_of_memory PER PAIR index => bool: blastn ran past HEAP_LIMIT_MB on
     *                                this pair alone -- a primer so repetitive that even
     *                                counting its matches is too costly. Hits are EMPTY and
     *                                which primer it is, is not known.
     * }
     */
    public static function run(array $pairs, $db, array $options = [])
    {
        $result = [
            'success'       => false,
            'error'         => '',
            'hits'          => [],
            'below_floor'   => 0,
            'over_mismatch' => 0,
            'discards'      => [],
            'repetitive'    => [],
            'out_of_memory' => [],
        ];

        if (empty($pairs)) {
            $result['error'] = 'No primer pairs to search.';
            return $result;
        }

        if (!self::databaseExists($db)) {
            $result['error'] = 'The sequence database for this search is missing (' . basename($db) . ').' . ' This needs the site administrator; trying again will not help.';
            return $result;
        }

        $max_mismatch = isset($options['max_mismatch']) ? (int)$options['max_mismatch'] : self::SEARCH_MAX_MISMATCH;
        // The configured blastn, like BLAST itself -- this used to run a bare 'blastn' and
        // ignore blast_tools, so a site that set a full path got it everywhere but here.
        require_once __DIR__ . '/../functions_system.php';
        $blastn       = $options['blastn'] ?? moop_tool('blastn', 'blast_tools');
        if ($blastn === '') {
            $result['error'] = "blastn is not installed or not on the web server's PATH." . ' This needs the site administrator; trying again will not help.';
            return $result;
        }

        // Query ids are positional (q0f, q0r, q1f, ...) rather than the user's
        // names: a primer name may contain spaces or characters BLAST would
        // truncate at, and a mangled id would silently attach hits to the wrong pair.
        $fasta   = '';
        $id_map  = [];
        $lengths = [];
        foreach ($pairs as $i => $pair) {
            foreach (['forward' => 'f', 'reverse' => 'r'] as $side => $tag) {
                $id = 'q' . $i . $tag;
                $fasta .= ">$id\n" . $pair[$side] . "\n";
                $id_map[$id]  = ['index' => $i, 'side' => $side];
                $lengths[$id] = strlen($pair[$side]);
            }
        }

        require_once __DIR__ . '/../temp_dir.php';
        $query_file = tempnam(moop_temp_dir(), 'moop_primer_q');
        if ($query_file === false) {
            $result['error'] = 'The server could not create a temporary file for the search.' . ' Try again; if it happens again, tell the site administrator.';
            return $result;
        }
        file_put_contents($query_file, $fasta);

        // blastn writes its table to a FILE, read back one line at a time. Collecting stdout
        // with exec() held every line in PHP memory: a poly-A pair gave 876,050 lines (47 MB of
        // text, 105 MB as a PHP array) and killed the request before a verdict was reached.
        $out_file = tempnam(moop_temp_dir(), 'moop_primer_o');
        if ($out_file === false) {
            @unlink($query_file);
            $result['error'] = 'The server could not create a temporary file for the search.' . ' Try again; if it happens again, tell the site administrator.';
            return $result;
        }

        // The heap cap (HEAP_LIMIT_MB). --core=0: a capped blastn aborts, and would otherwise
        // leave a core dump the size of its heap. SOFT limits only ("N:"): changing a HARD
        // limit needs SELinux process:setrlimit, which php-fpm may lack -- and blastn never
        // raises its own. No prlimit: run uncapped, as before.
        $prlimit = moop_find_executable('prlimit');
        $cap     = $prlimit === '' ? ''
                 : escapeshellarg($prlimit) . ' --data=' . (self::HEAP_LIMIT_MB * 1048576) . ': --core=0: -- ';

        $cmd = $cap . escapeshellarg($blastn)
             . ' -task blastn-short'
             . ' -evalue ' . escapeshellarg(self::EVALUE)
             . ' -word_size ' . escapeshellarg(self::WORD_SIZE)
             . ' -dust no'
             . ' -max_target_seqs ' . escapeshellarg(self::MAX_TARGET_SEQS)
             . ' -db ' . escapeshellarg($db)
             . ' -query ' . escapeshellarg($query_file)
             // btop carries WHERE the mismatches are. Position dominates count for
             // priming -- a mismatch in the last few bases at the 3' end all but
             // stops extension, while the same mismatch at the 5' end is tolerated --
             // and plain outfmt 6 gives only a total, which cannot express that.
             . ' -outfmt ' . escapeshellarg('6 qseqid sseqid length mismatch gapopen qstart qend sstart send btop')
             . ' -out ' . escapeshellarg($out_file)
             . ' 2>&1';   // what is left on stdout/stderr is BLAST's own messages

        // Through the BLAST queue like every other search, and under the same run-time cap.
        require_once __DIR__ . '/../blast_queue.php';
        $output = [];
        $return_code = 0;
        $queued = blast_queue_run(
            function () use ($cmd, &$output, &$return_code) {
                exec(blast_queue_wrap_command($cmd), $output, $return_code);
            },
            // The heap cap, not blast_queue_cost_units()' 1.2x the .nsq: that counts the
            // memory-mapped database, which is shared page cache, and missed the heap -- a
            // poly-A check was charged ~1 GB on wallaby and took 5.2 GB.
            $prlimit === '' ? blast_queue_cost_units('blastn', $db)
                            : max(1, (int)ceil(self::HEAP_LIMIT_MB / BLAST_QUEUE_UNIT_MB)),
            blast_queue_ticket(null)
        );
        @unlink($query_file);

        $fail = function ($message) use (&$result, $out_file) {
            @unlink($out_file);
            $result['error'] = $message;
            return $result;
        };
        if (!$queued['ran']) {
            return $fail($queued['error']);
        }
        if (blast_queue_timed_out($return_code)) {
            // Not blast_queue_timeout_message(): that one is BLAST's ("a stricter E-value") and
            // Primer BLAST has no E-value box. What helps here is fewer pairs per run.
            return $fail('The search ran past the ' . blast_queue_settings()['max_runtime'] . '-second limit and was stopped. '
                . 'Check fewer pairs at a time; a primer that sits in a repeated sequence can also make the search very slow.');
        }
        if (self::ranOutOfMemory($return_code, $output)) {
            @unlink($out_file);
            // One run holds every pair, so the cap says only that SOME primer is too
            // repetitive. Calling every pair non-specific would be wrong, so with several
            // pairs each is searched alone (only in this rare case) to find the one.
            if (count($pairs) > 1) {
                return self::runEachPairAlone($pairs, $db, $options, $result);
            }
            // A failed search here used to leave the pair with no products on this template,
            // which the verdict read as "no product". It is the opposite: non-specific.
            foreach ($pairs as $i => $pair) {
                $result['hits'][$i]          = ['forward' => [], 'reverse' => []];
                $result['discards'][$i]      = ['below_floor' => 0, 'over_mismatch' => 0];
                $result['repetitive'][$i]    = ['forward' => 0, 'reverse' => 0];
                $result['out_of_memory'][$i] = true;
            }
            $result['success'] = true;
            return $result;
        }
        if ($return_code !== 0) {
            // A non-zero exit with an empty .blastout is exactly the failure mode
            // that made the original scripts report a header-only table as success.
            // Trying again will not help (a missing or damaged index, a broken install): say so,
            // and keep the raw exit and output for the administrator, not the page (2026-11-25).
            error_log('primer_blast: blastn failed on ' . $db . ' (exit ' . $return_code . '): '
                . trim(implode(' ', array_slice($output, 0, 3))));
            return $fail('The search could not run against this sequence database. '
                . 'This needs the site administrator; trying again will not help.');
        }
        $fh = fopen($out_file, 'r');
        if ($fh === false) {
            return $fail('Something went wrong on the server while reading the search results. '
                . 'Try again; if it happens again, tell the site administrator.');
        }

        foreach ($pairs as $i => $pair) {
            $result['hits'][$i]       = ['forward' => [], 'reverse' => []];
            $result['discards'][$i]   = ['below_floor' => 0, 'over_mismatch' => 0];
            $result['repetitive'][$i] = ['forward' => 0, 'reverse' => 0];
            $result['out_of_memory'][$i] = false;
        }
        $kept   = [];   // qid => sites passing both filters, counted past the ceiling
        $stored = 0;    // sites held in memory, whole run

        while (($line = fgets($fh)) !== false) {
            $f = explode("\t", rtrim($line, "\r\n"));
            if (count($f) < 9) {
                continue;   // not a table row
            }

            list($qid, $sid, $length, $mismatch, $gapopen, $qstart, $qend, $sstart, $send) = $f;
            $btop = $f[9] ?? '';

            if (!isset($id_map[$qid])) {
                continue;
            }

            $length   = (int)$length;
            $qstart   = (int)$qstart;
            $qend     = (int)$qend;
            $qlength  = $lengths[$qid];

            $pair_index = $id_map[$qid]['index'];

            if ($length < $qlength * self::MIN_LENGTH_FRACTION) {
                $result['below_floor']++;
                $result['discards'][$pair_index]['below_floor']++;
                continue;
            }

            // Over the WHOLE primer, not just the aligned part. BLAST's mismatch column
            // counts substitutions inside the alignment only: a 20-mer aligned over 16
            // bases read "0 mismatches", and gaps were not counted at all. (BLAT, used
            // for this before, reports the full primer -- this restores that.)
            $aligned_mismatch = (int)$mismatch;
            $mismatch = self::fullLengthMismatches($btop, $qstart, $qend, $qlength);

            if ($mismatch > $max_mismatch) {
                $result['over_mismatch']++;
                $result['discards'][$pair_index]['over_mismatch']++;
                continue;
            }

            // The primer's FULL footprint on the subject: extend the alignment by the
            // primer bases it left uncovered, so product sizes and browser boxes cover
            // the whole primer. Query start maps to the low end on the + strand and to
            // the high end on the - strand.
            list($sstart, $send) = self::fullFootprint((int)$sstart, (int)$send, $qstart, $qend, $qlength);

            $meta = $id_map[$qid];

            // Past the ceiling the primer is repetitive: keep COUNTING (the number shown is
            // exact) but drop its stored sites -- a partial list would make products and the
            // verdict look like a real, smaller answer.
            $kept[$qid] = ($kept[$qid] ?? 0) + 1;
            if ($kept[$qid] > self::MAX_SITES_PER_PRIMER) {
                if ($kept[$qid] === self::MAX_SITES_PER_PRIMER + 1) {
                    $stored -= count($result['hits'][$meta['index']][$meta['side']]);
                    $result['hits'][$meta['index']][$meta['side']] = [];
                }
                $result['repetitive'][$meta['index']][$meta['side']] = $kept[$qid];
                continue;
            }
            if (++$stored > self::MAX_SITES_PER_RUN) {
                fclose($fh);
                return $fail('These primers match too many places in total to check at once. '
                    . 'Check fewer pairs at a time.');
            }

            $result['hits'][$meta['index']][$meta['side']][] = [
                'subject'  => $sid,
                // In -outfmt 6 the subject strand is IMPLIED by coordinate order.
                // There is no strand column, and the pairing rule depends entirely
                // on reading this correctly.
                'strand'   => ($sstart > $send) ? '-' : '+',
                'start'    => min($sstart, $send),
                'end'      => max($sstart, $send),
                'length'   => $length,
                'mismatch' => $mismatch,           // whole primer: substitutions + gaps + uncovered ends
                'aligned_mismatch' => $aligned_mismatch,  // BLAST's own column, aligned part only
                'gapopen'  => (int)$gapopen,
                'qstart'   => $qstart,
                'qend'     => $qend,
                'qlength'  => $qlength,
                // Mismatches inside the 3'-end window, INCLUDING any part of that
                // window the alignment never reached: an unaligned 3' tail is not a
                // match, and treating it as one would call a truncated hit clean.
                'three_prime_mismatch' => self::threePrimeMismatches(
                    $btop, $qstart, $qend, $qlength
                ),
            ];
        }

        fclose($fh);
        @unlink($out_file);

        $result['success'] = true;
        return $result;
    }

    /**
     * Did blastn stop at HEAP_LIMIT_MB? It prints "BLAST ran out of memory" and aborts
     * (exit 134, measured 2026-11-24); under a tighter cap it can segfault instead (139).
     *
     * @param int   $return_code Exit status of the command.
     * @param array $output      Its stdout/stderr lines.
     */
    public static function ranOutOfMemory($return_code, array $output)
    {
        if ($return_code === 0) {
            return false;
        }
        $text = implode("\n", $output);
        return stripos($text, 'out of memory') !== false
            || stripos($text, 'bad_alloc') !== false
            || in_array($return_code, [134, 139], true);
    }

    /**
     * Search each pair on its own and merge the answers under the original pair indexes --
     * used only when a run of several pairs hit the heap cap, to find which pair did.
     *
     * @param array $pairs   The pairs of the capped run.
     * @param array $result  The run's empty result, filled in here.
     */
    private static function runEachPairAlone(array $pairs, $db, array $options, array $result)
    {
        $stored = 0;
        foreach ($pairs as $i => $pair) {
            $one = self::run([$pair], $db, $options);
            if (!$one['success']) {
                $result['error'] = $one['error'];
                return $result;
            }
            foreach (['hits', 'discards', 'repetitive', 'out_of_memory'] as $key) {
                $result[$key][$i] = $one[$key][0];
            }
            $result['below_floor']   += $one['below_floor'];
            $result['over_mismatch'] += $one['over_mismatch'];
            $stored += count($one['hits'][0]['forward']) + count($one['hits'][0]['reverse']);
            // The same whole-run bound as one combined search.
            if ($stored > self::MAX_SITES_PER_RUN) {
                $result['error'] = 'These primers match too many places in total to check at once. '
                    . 'Check fewer pairs at a time.';
                return $result;
            }
        }
        $result['success'] = true;
        return $result;
    }

    /**
     * Mismatches over the whole primer: every BTOP operation (a substitution, or a gap
     * on either side -- each costs one base of agreement) plus the primer bases the
     * alignment never reached at either end.
     *
     * @param string $btop    BLAST traceback operations for the alignment.
     * @param int    $qstart  1-based query start of the alignment.
     * @param int    $qend    1-based query end.
     * @param int    $qlength Full primer length.
     */
    public static function fullLengthMismatches($btop, $qstart, $qend, $qlength)
    {
        // Strip the match run-lengths; what is left is two characters per operation.
        $ops = (int)(strlen(preg_replace('/\d+/', '', $btop)) / 2);
        return $ops + max(0, $qstart - 1) + max(0, $qlength - $qend);
    }

    /**
     * The subject span the whole primer would occupy: the alignment extended by the
     * uncovered query ends. Returns [sstart, send] in BLAST's orientation (sstart > send
     * on the minus strand), never below base 1.
     */
    public static function fullFootprint($sstart, $send, $qstart, $qend, $qlength)
    {
        $lead  = max(0, $qstart - 1);         // uncovered 5' bases
        $trail = max(0, $qlength - $qend);    // uncovered 3' bases
        if ($sstart <= $send) {               // + strand: 5' end is the low coordinate
            return [max(1, $sstart - $lead), $send + $trail];
        }
        return [$sstart + $lead, max(1, $send - $trail)];
    }

    /**
     * How many of the last THREE_PRIME_WINDOW bases of the primer fail to match.
     *
     * BTOP alternates run-lengths of matches with pairs of characters for each
     * mismatch or gap: "7AG10" is 7 matches, a query A against a subject G, then
     * 10 matches. Walking it gives the query position of every mismatch, which is
     * what a total count cannot provide.
     *
     * @param string $btop    BLAST traceback operations for the alignment.
     * @param int    $qstart  1-based query start of the alignment.
     * @param int    $qend    1-based query end.
     * @param int    $qlength Full primer length.
     */
    public static function threePrimeMismatches($btop, $qstart, $qend, $qlength)
    {
        $window_start = max(1, $qlength - self::THREE_PRIME_WINDOW + 1);

        // Bases of the window the alignment never covered count against it.
        $unaligned = max(0, $qlength - $qend);

        $bad = 0;
        $pos = $qstart;
        $i   = 0;
        $len = strlen($btop);

        while ($i < $len) {
            if (ctype_digit($btop[$i])) {
                $run = '';
                while ($i < $len && ctype_digit($btop[$i])) {
                    $run .= $btop[$i];
                    $i++;
                }
                $pos += (int)$run;          // a run of matches
                continue;
            }

            // A two-character operation: query base then subject base.
            $q = $btop[$i];
            $s = $btop[$i + 1] ?? '';
            $i += 2;

            if ($q === '-') {
                continue;                    // insertion in the subject: query does not advance
            }

            if ($pos >= $window_start && $pos <= $qlength) {
                $bad++;
            }
            $pos++;                          // mismatch or query gap consumes a query base
        }

        return $bad + min($unaligned, self::THREE_PRIME_WINDOW);
    }

    /**
     * The real sequence name behind a BLAST subject id.
     *
     * BLAST wraps ids from some databases as "ref|ACC|". That is noise in a
     * table, but it is FATAL in two places: a JBrowse track whose refName
     * carries the wrapper silently renders nothing at all, and an exon-index
     * lookup by the wrapped form finds no row and quietly drops the link.
     * Both failures look like missing data rather than a formatting problem,
     * which is why this lives in one place and not at each call site.
     */
    public static function cleanSubjectId($id)
    {
        if (preg_match('/^(?:ref|gb|emb|dbj|lcl)\|([^|]+)\|?$/', $id, $m)) {
            return $m[1];
        }
        return $id;
    }

    /**
     * A BLAST database is present if any of its index files are.
     */
    public static function databaseExists($db)
    {
        foreach (['.nhr', '.nin', '.ndb', '.nsq'] as $ext) {
            if (file_exists($db . $ext)) {
                return true;
            }
        }
        return false;
    }
}
