<?php
/**
 * Primer3Design - build primer3 input, run it, and parse what comes back.
 *
 * The design half of the primer tools. PrimerBlast/PrimerPairs check primers
 * that already exist; this one makes them.
 *
 * ⚠️ WRITTEN AGAINST primer3 2.6.1's ACTUAL OUTPUT, NOT AGAINST THE PERL.
 * The scripts this is ported from (/home/smr/primer3_tab) target primer3 1.x
 * tag names, and three of them no longer exist:
 *
 *     Perl expects            2.6.1 emits
 *     PRIMER_SEQUENCE_ID  →   SEQUENCE_ID
 *     SEQUENCE            →   SEQUENCE_TEMPLATE
 *     PRIMER_SELF_ANY     →   PRIMER_LEFT_0_SELF_ANY_TH
 *
 * Transliterating it would have produced a parser that finds primers but never
 * finds the record id they belong to — every result filed under a null key.
 * Verified by running 2.6.1 and diffing the tags, not by reading the manual.
 *
 * ⚠️ primer3_core EXITS 0 EVEN WHEN IT FAILS. A missing thermodynamic
 * parameters path gives PRIMER_ERROR=… on stdout with status 0, so the exit
 * code is worthless and the OUTPUT is the only thing that says what happened.
 *
 * @package MOOP\Primer
 */

require_once __DIR__ . '/Primer3.php';

class Primer3Design
{
    /**
     * Thermodynamic alignment on by default.
     *
     * It is what makes SELF_ANY_TH / HAIRPIN_TH meaningful, and it is the reason
     * the parameters directory has to exist at all. Turning it off would make
     * primer3 work without those tables — and would silently lose the secondary
     * structure numbers a user judges a primer by.
     */
    const DEFAULTS = [
        'PRIMER_TASK'                          => 'generic',
        'PRIMER_PICK_LEFT_PRIMER'              => 1,
        'PRIMER_PICK_RIGHT_PRIMER'             => 1,
        'PRIMER_PICK_INTERNAL_OLIGO'           => 0,
        'PRIMER_NUM_RETURN'                    => 5,
        'PRIMER_THERMODYNAMIC_OLIGO_ALIGNMENT' => 1,
        'PRIMER_OPT_SIZE'                      => 20,
        'PRIMER_MIN_SIZE'                      => 18,
        'PRIMER_MAX_SIZE'                      => 25,
        'PRIMER_OPT_TM'                        => 60.0,
        'PRIMER_MIN_TM'                        => 57.0,
        'PRIMER_MAX_TM'                        => 63.0,
        'PRIMER_MIN_GC'                        => 40.0,
        'PRIMER_MAX_GC'                        => 60.0,
        'PRIMER_PRODUCT_SIZE_RANGE'            => '100-400',
    ];

    /**
     * Per-pair tags to lift out, keyed by the NAME they get in the result.
     *
     * Keyed by name rather than read positionally, because the whole point of
     * the output is a table a user pastes into a spreadsheet, and positional
     * column identity is what produced four silent defects in the results table
     * on 2026-07-23 (CLAUDE.md §9b).
     *
     * '%s' is the pair index. Missing tags are simply absent from the result —
     * primer3 omits what it did not compute, and inventing a 0 would read as a
     * measurement.
     */
    const PAIR_FIELDS = [
        'left_sequence'     => 'PRIMER_LEFT_%s_SEQUENCE',
        'right_sequence'    => 'PRIMER_RIGHT_%s_SEQUENCE',
        'left_tm'           => 'PRIMER_LEFT_%s_TM',
        'right_tm'          => 'PRIMER_RIGHT_%s_TM',
        'left_gc'           => 'PRIMER_LEFT_%s_GC_PERCENT',
        'right_gc'          => 'PRIMER_RIGHT_%s_GC_PERCENT',
        'left_self_any'     => 'PRIMER_LEFT_%s_SELF_ANY_TH',
        'right_self_any'    => 'PRIMER_RIGHT_%s_SELF_ANY_TH',
        'left_self_end'     => 'PRIMER_LEFT_%s_SELF_END_TH',
        'right_self_end'    => 'PRIMER_RIGHT_%s_SELF_END_TH',
        'left_hairpin'      => 'PRIMER_LEFT_%s_HAIRPIN_TH',
        'right_hairpin'     => 'PRIMER_RIGHT_%s_HAIRPIN_TH',
        'pair_compl_any'    => 'PRIMER_PAIR_%s_COMPL_ANY_TH',
        'pair_compl_end'    => 'PRIMER_PAIR_%s_COMPL_END_TH',
        'product_size'      => 'PRIMER_PAIR_%s_PRODUCT_SIZE',
        'product_tm'        => 'PRIMER_PAIR_%s_PRODUCT_TM',
        'pair_penalty'      => 'PRIMER_PAIR_%s_PENALTY',
        'left_penalty'      => 'PRIMER_LEFT_%s_PENALTY',
        'right_penalty'     => 'PRIMER_RIGHT_%s_PENALTY',
    ];

    /**
     * Build a boulder-IO input document.
     *
     * Boulder-IO is one record per sequence, each terminated by a lone '='.
     * GLOBAL tags (PRIMER_*) persist across records once set; SEQUENCE_* tags do
     * not. So the globals are written once, in the first record, and repeating
     * them would be noise rather than safety.
     *
     * @param array $records Each: ['id' => string, 'template' => string,
     *                        optional 'junctions' => [int,…] 1-based positions a
     *                        primer must span, 'target' => [start, length]]
     * @param array $params  Overrides merged over DEFAULTS.
     * @return string
     */
    public static function buildInput(array $records, array $params = [])
    {
        // No ConfigManager here on purpose. This is a pure function — input in,
        // boulder-IO out — so it stays testable with no site config, no
        // installed primer3 and no filesystem. run() is what knows where this
        // deployment keeps its thermodynamic tables and injects the path.
        $globals = array_merge(self::DEFAULTS, $params);

        $out   = '';
        $first = true;
        foreach ($records as $rec) {
            $id = preg_replace('/[^A-Za-z0-9._:|-]/', '_', (string)($rec['id'] ?? 'seq'));
            $out .= "SEQUENCE_ID=$id\n";
            $out .= 'SEQUENCE_TEMPLATE=' . strtoupper(preg_replace('/\s+/', '', (string)$rec['template'])) . "\n";

            if (!empty($rec['target'])) {
                $out .= 'SEQUENCE_TARGET=' . (int)$rec['target'][0] . ',' . (int)$rec['target'][1] . "\n";
            }

            // The RT-PCR case: force a primer ACROSS a junction, which is what
            // makes the pair unable to amplify genomic DNA. primer3 takes the
            // positions the oligo must overlap.
            if (!empty($rec['junctions'])) {
                $out .= 'SEQUENCE_OVERLAP_JUNCTION_LIST=' . implode(' ', array_map('intval', $rec['junctions'])) . "\n";
            }

            if ($first) {
                foreach ($globals as $k => $v) {
                    $out .= "$k=$v\n";
                }
                $first = false;
            }
            $out .= "=\n";
        }

        return $out;
    }

    /**
     * Parse primer3 boulder-IO output into one entry per input record.
     *
     * @return array Each: ['id','template','error','pairs' => [ …keyed fields… ]]
     */
    public static function parseOutput($raw)
    {
        $results = [];

        // Records are separated by a lone '='. Splitting on that rather than
        // on SEQUENCE_ID keeps a record that FAILED — one with an error and no
        // primers still has to be reported, or a bad input silently vanishes.
        foreach (preg_split('/^=\s*$/m', (string)$raw) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $tags = [];
            foreach (explode("\n", $chunk) as $line) {
                $eq = strpos($line, '=');
                if ($eq === false) {
                    continue;
                }
                $tags[substr($line, 0, $eq)] = substr($line, $eq + 1);
            }
            if (empty($tags)) {
                continue;
            }

            $entry = [
                'id'       => $tags['SEQUENCE_ID'] ?? '',
                'template' => $tags['SEQUENCE_TEMPLATE'] ?? '',
                'error'    => $tags['PRIMER_ERROR'] ?? ($tags['SEQUENCE_ID_ERROR'] ?? ''),
                'explain'  => [
                    'left'  => $tags['PRIMER_LEFT_EXPLAIN'] ?? '',
                    'right' => $tags['PRIMER_RIGHT_EXPLAIN'] ?? '',
                    'pair'  => $tags['PRIMER_PAIR_EXPLAIN'] ?? '',
                ],
                'pairs'    => [],
            ];

            $n = isset($tags['PRIMER_PAIR_NUM_RETURNED']) ? (int)$tags['PRIMER_PAIR_NUM_RETURNED'] : 0;
            for ($i = 0; $i < $n; $i++) {
                $pair = ['rank' => $i + 1];

                foreach (self::PAIR_FIELDS as $name => $pattern) {
                    $tag = sprintf($pattern, $i);
                    if (isset($tags[$tag])) {
                        $pair[$name] = $tags[$tag];
                    }
                }

                // "start,length" pairs. primer3 reports the LEFT primer's start
                // 0-based at its 5' end, and the RIGHT primer's start 0-based at
                // its 5' end too — which for the right primer is its RIGHTMOST
                // base, because it is on the other strand. Converted to 1-based
                // inclusive spans here so nothing downstream has to remember.
                if (isset($tags["PRIMER_LEFT_$i"])) {
                    list($s, $len) = array_map('intval', explode(',', $tags["PRIMER_LEFT_$i"]));
                    $pair['left_start']  = $s + 1;
                    $pair['left_end']    = $s + $len;
                    $pair['left_length'] = $len;
                }
                if (isset($tags["PRIMER_RIGHT_$i"])) {
                    list($s, $len) = array_map('intval', explode(',', $tags["PRIMER_RIGHT_$i"]));
                    $pair['right_end']    = $s + 1;          // 5' end, highest coordinate
                    $pair['right_start']  = $s - $len + 2;   // 3' end, lowest coordinate
                    $pair['right_length'] = $len;
                }

                $entry['pairs'][] = $pair;
            }

            $results[] = $entry;
        }

        return $results;
    }

    /**
     * Run primer3 over a set of records.
     *
     * @return array ['success' => bool, 'error' => string, 'results' => array]
     */
    public static function run(array $records, array $params = [])
    {
        $status = Primer3::status();
        if (!$status['ok']) {
            return [
                'success' => false,
                'error'   => $status['problem'] === 'missing'
                    ? 'primer3 is not installed on this server.'
                    : 'primer3 is installed but its thermodynamic parameters are missing, so it '
                      . 'cannot run. Ask an administrator to re-run scripts/install_primer3.sh.',
                'results' => [],
            ];
        }

        // Injected HERE, not in buildInput(): where the thermodynamic tables live
        // is an installation fact, and it is never the caller's to supply — a
        // hardcoded value would be wrong on whichever install route this
        // deployment did not take.
        $params['PRIMER_THERMODYNAMIC_PARAMETERS_PATH'] = $status['config'];

        $input = self::buildInput($records, $params);

        // An explicit temp file, not a pipe through /tmp by name: php-fpm runs
        // with PrivateTmp, so anything exec'd from a web request gets its own
        // /tmp that is invisible from a shell (CLAUDE.md §11).
        $in_file = tempnam(sys_get_temp_dir(), 'moop_p3_');
        if ($in_file === false) {
            return ['success' => false, 'error' => 'Could not create a temporary file.', 'results' => []];
        }
        file_put_contents($in_file, $input);

        $cmd = escapeshellarg($status['binary']) . ' ' . escapeshellarg($in_file) . ' 2>&1';
        $raw = shell_exec($cmd);
        @unlink($in_file);

        if ($raw === null || trim((string)$raw) === '') {
            return ['success' => false, 'error' => 'primer3 produced no output.', 'results' => []];
        }

        $results = self::parseOutput($raw);

        // A global failure (bad parameters path, malformed input) shows up as an
        // error on the FIRST record with no primers anywhere. Surfaced as a run
        // failure rather than as an empty table, which reads as "no primers
        // found" — a scientific answer, not a broken install.
        $any_pairs = false;
        foreach ($results as $r) {
            if (!empty($r['pairs'])) { $any_pairs = true; break; }
        }
        if (!$any_pairs && !empty($results[0]['error'])) {
            return ['success' => false, 'error' => $results[0]['error'], 'results' => $results];
        }

        return ['success' => true, 'error' => '', 'results' => $results];
    }
}
