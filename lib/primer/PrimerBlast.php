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
        ];

        if (empty($pairs)) {
            $result['error'] = 'No primer pairs to search.';
            return $result;
        }

        if (!self::databaseExists($db)) {
            $result['error'] = 'BLAST database not found: ' . basename($db);
            return $result;
        }

        $max_mismatch = isset($options['max_mismatch']) ? (int)$options['max_mismatch'] : self::SEARCH_MAX_MISMATCH;
        // The configured blastn, like BLAST itself -- this used to run a bare 'blastn' and
        // ignore blast_tools, so a site that set a full path got it everywhere but here.
        require_once __DIR__ . '/../functions_system.php';
        $blastn       = $options['blastn'] ?? moop_tool('blastn', 'blast_tools');
        if ($blastn === '') {
            $result['error'] = "blastn is not installed or not on the web server's PATH.";
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
            $result['error'] = 'Could not create a temporary query file.';
            return $result;
        }
        file_put_contents($query_file, $fasta);

        $cmd = escapeshellarg($blastn)
             . ' -task blastn-short'
             . ' -evalue ' . escapeshellarg(self::EVALUE)
             . ' -word_size ' . escapeshellarg(self::WORD_SIZE)
             . ' -dust no'
             . ' -db ' . escapeshellarg($db)
             . ' -query ' . escapeshellarg($query_file)
             // btop carries WHERE the mismatches are. Position dominates count for
             // priming -- a mismatch in the last few bases at the 3' end all but
             // stops extension, while the same mismatch at the 5' end is tolerated --
             // and plain outfmt 6 gives only a total, which cannot express that.
             . ' -outfmt ' . escapeshellarg('6 qseqid sseqid length mismatch gapopen qstart qend sstart send btop')
             . ' 2>&1';

        // Through the BLAST queue like every other search, and under the same run-time cap.
        require_once __DIR__ . '/../blast_queue.php';
        $output = [];
        $return_code = 0;
        $queued = blast_queue_run(
            function () use ($cmd, &$output, &$return_code) {
                exec(blast_queue_wrap_command($cmd), $output, $return_code);
            },
            blast_queue_cost_units('blastn', $db),
            blast_queue_ticket(null)
        );
        @unlink($query_file);

        if (!$queued['ran']) {
            $result['error'] = $queued['error'];
            return $result;
        }
        if (blast_queue_timed_out($return_code)) {
            $result['error'] = blast_queue_timeout_message();
            return $result;
        }
        if ($return_code !== 0) {
            // A non-zero exit with an empty .blastout is exactly the failure mode
            // that made the original scripts report a header-only table as success.
            $result['error'] = 'BLAST failed (exit ' . $return_code . '): '
                . trim(implode(' ', array_slice($output, 0, 3)));
            return $result;
        }

        foreach ($pairs as $i => $pair) {
            $result['hits'][$i]     = ['forward' => [], 'reverse' => []];
            $result['discards'][$i] = ['below_floor' => 0, 'over_mismatch' => 0];
        }

        foreach ($output as $line) {
            $f = explode("\t", $line);
            if (count($f) < 9) {
                continue;   // BLAST warnings on stderr land here; they are not rows
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
