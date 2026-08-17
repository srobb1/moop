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
        // ⚠️ WITHOUT THIS, PRIMER_*_EXPLAIN IS NEVER EMITTED AT ALL.
        // parseOutput() reads those tags and the page reports them as "Primer3
        // says: …" when a design returns nothing — which was the only thing
        // telling a user WHICH constraint to loosen. Verified against 2.6.1: the
        // same over-constrained input prints three EXPLAIN lines with the flag
        // set and zero without it, so the message silently read "no explanation
        // given" every time. It matters more now that the page exposes Tm, GC
        // and GC-clamp controls a user can over-tighten.
        'PRIMER_EXPLAIN_FLAG'                  => 1,
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
     * primer3's own hard ceiling on primer length.
     *
     * Measured, not recalled: PRIMER_MAX_SIZE=36 designs primers, 37 returns
     * "PRIMER_ERROR=PRIMER_MAX_SIZE exceeds built-in maximum of 36". Bounding the
     * form field here turns that into a field a user cannot get wrong, rather
     * than a run that fails after they press the button.
     */
    const MAX_PRIMER_SIZE = 36;

    /**
     * The user-settable design options, and what each one is called in primer3.
     *
     * Keyed by FORM FIELD NAME so the page, the validation and the run all read
     * one table. The alternative — a switch in the controller and a set of
     * labels in the view — is how a form ends up offering a control that no
     * longer does anything.
     *
     * 'min'/'max' are the bounds of the FIELD, not of primer3's search. They
     * exist to keep a value out of primer3 that would make it fail, or worse,
     * make it return nothing at all: PRIMER_SALT_CORRECTIONS=3 and
     * PRIMER_GC_CLAMP=6 both yield zero pairs with NO error and NO explanation,
     * which is indistinguishable from "your sequence is difficult".
     */
    const OPTIONS = [
        'size_opt'   => ['tag' => 'PRIMER_OPT_SIZE',         'type' => 'int',   'min' => 8,  'max' => 36,  'label' => 'Primer length (optimum)'],
        'size_min'   => ['tag' => 'PRIMER_MIN_SIZE',         'type' => 'int',   'min' => 8,  'max' => 36,  'label' => 'Primer length (minimum)'],
        'size_max'   => ['tag' => 'PRIMER_MAX_SIZE',         'type' => 'int',   'min' => 8,  'max' => 36,  'label' => 'Primer length (maximum)'],
        'tm_opt'     => ['tag' => 'PRIMER_OPT_TM',           'type' => 'float', 'min' => 30, 'max' => 85,  'label' => 'Tm (optimum)'],
        'tm_min'     => ['tag' => 'PRIMER_MIN_TM',           'type' => 'float', 'min' => 30, 'max' => 85,  'label' => 'Tm (minimum)'],
        'tm_max'     => ['tag' => 'PRIMER_MAX_TM',           'type' => 'float', 'min' => 30, 'max' => 85,  'label' => 'Tm (maximum)'],
        'tm_diff'    => ['tag' => 'PRIMER_PAIR_MAX_DIFF_TM', 'type' => 'float', 'min' => 0,  'max' => 20,  'label' => 'Largest Tm difference within a pair', 'p3_default' => 100],
        'gc_opt'     => ['tag' => 'PRIMER_OPT_GC_PERCENT',   'type' => 'float', 'min' => 0,  'max' => 100, 'label' => 'GC content (optimum)'],
        'gc_min'     => ['tag' => 'PRIMER_MIN_GC',           'type' => 'float', 'min' => 0,  'max' => 100, 'label' => 'GC content (minimum)'],
        'gc_max'     => ['tag' => 'PRIMER_MAX_GC',           'type' => 'float', 'min' => 0,  'max' => 100, 'label' => 'GC content (maximum)'],
        'gc_clamp'   => ['tag' => 'PRIMER_GC_CLAMP',         'type' => 'int',   'min' => 0,  'max' => 5,   'label' => 'GC clamp',                'p3_default' => 0],
        'max_poly_x' => ['tag' => 'PRIMER_MAX_POLY_X',       'type' => 'int',   'min' => 0,  'max' => 20,  'label' => 'Longest run of one base', 'p3_default' => 5],
        'salt_corr'  => ['tag' => 'PRIMER_SALT_CORRECTIONS', 'type' => 'int',   'min' => 0,  'max' => 2,   'label' => 'Salt correction formula', 'p3_default' => 1],
        // How far a primer must reach past a junction mark. This is what decides
        // whether a junction primer actually REJECTS genomic DNA: at primer3's
        // default a primer needs only 4 bases beyond the boundary, and 4 bases of
        // mismatch at the very end still anneals fairly well. Confirmed to be the
        // real defaults by reproducing the identical primer with them set
        // explicitly (7/4) and a different one at 7/3.
        'junction_5p' => ['tag' => 'PRIMER_MIN_5_PRIME_OVERLAP_OF_JUNCTION', 'type' => 'int', 'min' => 1, 'max' => 30, 'label' => 'Bases past a mark, 5′ side', 'p3_default' => 7],
        'junction_3p' => ['tag' => 'PRIMER_MIN_3_PRIME_OVERLAP_OF_JUNCTION', 'type' => 'int', 'min' => 1, 'max' => 30, 'label' => 'Bases past a mark, 3′ side', 'p3_default' => 4],
    ];

    /**
     * 'p3_default' above is primer3's OWN default for a tag MOOP does not set,
     * so a blank field can still show the number it will actually use.
     *
     * ⚠️ MEASURED AGAINST 2.6.1, not read off the manual — the bundled 2.3.5
     * manual and the shipped binary need not agree. Method: run one template with
     * no overrides, then again setting each tag to its claimed default, and check
     * the pair penalty and chosen primer are identical. All four reproduce
     * PRIMER_PAIR_0_PENALTY=0.064143 with the same left primer, which the wrong
     * value does not (salt correction 0 and 2 pick different primers entirely).
     */

    /**
     * Triples primer3 requires to be ordered min ≤ opt ≤ max.
     *
     * Getting one wrong is an error the user can fix, so it is caught here and
     * named in their terms. Left to primer3 it comes back as
     * "PRIMER_{OPT,DEFAULT}_SIZE > PRIMER_MAX_SIZE", which is accurate and
     * unhelpful — it names tags nobody typed.
     */
    const ORDERED = [
        ['label' => 'primer length', 'unit' => ' bases',
         'min' => 'PRIMER_MIN_SIZE', 'opt' => 'PRIMER_OPT_SIZE',       'max' => 'PRIMER_MAX_SIZE'],
        ['label' => 'melting temperature (Tm)', 'unit' => ' °C',
         'min' => 'PRIMER_MIN_TM',   'opt' => 'PRIMER_OPT_TM',         'max' => 'PRIMER_MAX_TM'],
        ['label' => 'GC content', 'unit' => '%',
         'min' => 'PRIMER_MIN_GC',   'opt' => 'PRIMER_OPT_GC_PERCENT', 'max' => 'PRIMER_MAX_GC'],
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
     * Turn submitted form values into primer3 tags.
     *
     * Pure, like buildInput() and for the same reason — no ConfigManager, no
     * filesystem, no primer3 — so the bounds and the ordering rules can be
     * tested without an installed binary.
     *
     * A BLANK FIELD MEANS "use whatever the chosen primer type says", and is not
     * the same as a zero. That is why every value is checked for '' before it is
     * cast: (int)'' is 0, and a GC clamp of 0 is a legitimate setting, so
     * casting first would turn every empty box into a deliberate-looking choice.
     *
     * @param array $input Raw form values, keyed by OPTIONS field name.
     * @param array $base  Tags already decided (the primer-type preset), so an
     *                     ordering check tests what will ACTUALLY run rather
     *                     than only the handful of boxes the user filled in.
     * @return array ['params' => tags to apply, 'errors' => human messages]
     */
    public static function optionParams(array $input, array $base = [])
    {
        $params = [];
        $errors = [];

        foreach (self::OPTIONS as $field => $spec) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $raw = trim((string)$input[$field]);
            if ($raw === '') {
                continue;
            }

            if (!is_numeric($raw)) {
                $errors[] = $spec['label'] . ': "' . $raw . '" is not a number.';
                continue;
            }

            $value = $spec['type'] === 'int' ? (int)$raw : (float)$raw;

            // Rejected, not clamped. Silently substituting a different number
            // would run a design the user did not ask for and report it as
            // theirs — and the two values that matter most here (a salt
            // correction of 3, a GC clamp of 6) return zero pairs with no error
            // at all, so a clamp would be covering up the one case where saying
            // something is essential.
            if ($value < $spec['min'] || $value > $spec['max']) {
                $errors[] = $spec['label'] . ' must be between ' . $spec['min']
                          . ' and ' . $spec['max'] . ' — you gave ' . $raw . '.';
                continue;
            }

            $params[$spec['tag']] = $value;
        }

        // Ordering, checked against the values that will really be used.
        $effective = array_merge(self::DEFAULTS, $base, $params);
        foreach (self::ORDERED as $triple) {
            $min = $effective[$triple['min']] ?? null;
            $opt = $effective[$triple['opt']] ?? null;
            $max = $effective[$triple['max']] ?? null;

            if ($min === null || $max === null) {
                continue;
            }
            if ($min > $max) {
                $errors[] = 'The minimum ' . $triple['label'] . ' (' . $min . $triple['unit']
                          . ') is above the maximum (' . $max . $triple['unit'] . ').';
                continue;
            }
            if ($opt !== null && ($opt < $min || $opt > $max)) {
                $errors[] = 'The optimum ' . $triple['label'] . ' (' . $opt . $triple['unit']
                          . ') is outside the range you set, ' . $min . '–' . $max . $triple['unit'] . '.';
            }
        }

        return ['params' => $params, 'errors' => $errors];
    }

    /**
     * A product-size ladder that prefers the longest amplicon it can get.
     *
     * primer3 takes several ranges and tries them IN ORDER, settling for a later
     * one only when an earlier one yields nothing — so listing 75%, 50%, 25% and
     * then the floor asks for "as long as possible, and here is how far you may
     * climb down". A single wide range like 100-900 does not do this: primer3
     * would happily return a 120 bp product because nothing tells it that longer
     * is better.
     *
     * Ported from the workflow this replaces, whose "Optimal Product Length"
     * field asked "what is the size of the biggest product you would want?".
     *
     * @param int $max Longest acceptable product, usually the template length.
     * @param int $min Shortest worth having.
     * @return string Space-separated ranges, or '' when $max is too small to
     *                make a ladder — the caller then keeps its own range rather
     *                than passing primer3 an inverted one.
     */
    public static function productSizeLadder($max, $min = 100)
    {
        $max = (int)$max;
        $min = (int)$min;

        if ($max < $min) {
            return '';
        }

        $ranges = [];
        foreach ([0.75, 0.50, 0.25] as $fraction) {
            $low = (int)floor($max * $fraction);
            if ($low >= $min) {
                $ranges[] = $low . '-' . $max;
            }
        }
        $ranges[] = $min . '-' . $max;

        // 75% of 400 and 50% of 600 can coincide; a repeated range is not wrong,
        // just noise in a field the user can see.
        return implode(' ', array_unique($ranges));
    }

    /**
     * Build a boulder-IO input document.
     *
     * Boulder-IO is one record per sequence, each terminated by a lone '='.
     * GLOBAL tags (PRIMER_*) persist across records once set; SEQUENCE_* tags do
     * not. So the globals are written once, in the first record, and repeating
     * them would be noise rather than safety.
     *
     * @param array $records Each: ['id' => string, 'template' => string,
     *                        optional 'junctions' => [int,…] positions a primer
     *                        must cross, each meaning "the junction FOLLOWS
     *                        1-based base K"; 'targets' => [[start0, length],…]
     *                        regions the product must contain]
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

            // Force a primer ACROSS a point — an exon junction, a fusion
            // breakpoint, a scaffold join. Callers speak ONE convention:
            // "the junction follows 1-based base K".
            //
            // 🚨 THE −1 IS LOAD-BEARING, AND THE MANUAL WILL TELL YOU IT IS NOT.
            // primer3's docs print "SEQUENCE_OVERLAP_JUNCTION_LIST=20 # 1-based
            // indexes" next to an example whose junction follows the 20th base.
            // 2.6.1 does not behave that way. Holding a junction at 450 and
            // lowering PRIMER_MIN_3_PRIME_OVERLAP_OF_JUNCTION from 4 to 3 moved
            // the chosen primer's edge from 3 bases left of base 450 to 2 —
            // always exactly one fewer than the constraint demands. primer3
            // counts the junction as following base N+1, so its values are
            // effectively 0-based and a caller's K must go in as K−1.
            //
            // Without this, every junction is enforced one base 3' of the real
            // boundary: the primer still LOOKS like it spans, it just has one
            // fewer genuine base on the far side than promised — which is
            // precisely the margin that decides whether it rejects genomic DNA.
            // Nothing in the output would ever say so.
            if (!empty($rec['junctions'])) {
                $zero_based = [];
                foreach ($rec['junctions'] as $k) {
                    $k = (int)$k;
                    if ($k > 0) {
                        $zero_based[] = $k - 1;
                    }
                }
                if ($zero_based) {
                    $out .= 'SEQUENCE_OVERLAP_JUNCTION_LIST=' . implode(' ', $zero_based) . "\n";
                }
            }

            // Regions the product must contain, as primer3's start,length pairs
            // (0-based start). Several are allowed; primer3 requires the product
            // to span ALL of them, which is why [ ] markup and the "Region to
            // include" box must not both be in play at once.
            if (!empty($rec['targets'])) {
                $spans = [];
                foreach ($rec['targets'] as $t) {
                    $spans[] = (int)$t[0] . ',' . (int)$t[1];
                }
                $out .= 'SEQUENCE_TARGET=' . implode(' ', $spans) . "\n";
            }

            if ($first) {
                foreach ($globals as $k => $v) {
                    // ⚠️ A NEWLINE INSIDE A VALUE TRUNCATES THE RECORD SILENTLY.
                    // Boulder-IO ends a record at a lone '=' and stops reading
                    // tags at a blank line: primer3 then echoes back everything
                    // up to that point, emits no primers, no PRIMER_ERROR, and
                    // EXITS 0. Verified on 2.6.1. No current caller can produce
                    // one — every value here is an int, a float, or a string
                    // from a table in this class — but the failure is invisible
                    // enough, and the guard cheap enough, that it is not worth
                    // relying on that staying true. The likeliest source is a
                    // config value with a stray newline, such as the
                    // thermodynamic parameters path.
                    $out .= $k . '=' . str_replace(["\r", "\n"], '', (string)$v) . "\n";
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
     * Parse a "region the product must span", written start,length.
     *
     * ⚠️ THE BOX IS 1-BASED AND primer3's SEQUENCE_TARGET IS 0-BASED. A user
     * reads position 1 as the first base of their sequence; primer3 counts from
     * 0. The conversion happens here, once, so no caller has to remember it —
     * and an off-by-one would silently shift every amplicon by one base, which
     * no error message would ever report.
     *
     * @param string $input    Raw field value.
     * @param int    $template Template length, so a target past the end is
     *                         caught here rather than as a primer3 error naming
     *                         SEQUENCE_INCLUDED_REGION.
     * @return array ['target' => [start0, length]|null, 'error' => string,
     *                'first' => int, 'last' => int]  (first/last are 1-based)
     */
    public static function parseTarget($input, $template)
    {
        $none = ['target' => null, 'error' => '', 'first' => 0, 'last' => 0];

        $input = trim((string)$input);
        if ($input === '') {
            return $none;
        }

        if (!preg_match('/^(\d+)\s*,\s*(\d+)$/', $input, $m)) {
            return array_merge($none, ['error' =>
                'Region to include: give it as start,length — for example 300,100 for 100 bases '
                . 'starting at position 300.']);
        }

        $start  = (int)$m[1];
        $length = (int)$m[2];

        if ($start < 1 || $length < 1) {
            return array_merge($none, ['error' =>
                'Region to include: the start and the length both have to be at least 1.']);
        }

        $last = $start + $length - 1;
        if ($last > $template) {
            return array_merge($none, ['error' =>
                'Region to include: ' . number_format($start) . '–' . number_format($last)
                . ' runs past the end of your ' . number_format($template) . ' bp sequence.']);
        }

        return ['target' => [$start - 1, $length], 'error' => '', 'first' => $start, 'last' => $last];
    }

    /**
     * Say a primer3 error in the words of the person who caused it.
     *
     * primer3's messages name TAGS, which is exactly right for a program driving
     * it and wrong for a user who typed "2000-3000" into a box called Product
     * size. "SEQUENCE_INCLUDED_REGION length < min PRIMER_PRODUCT_SIZE_RANGE" is
     * the one a user hits most — it means the product they asked for is longer
     * than the sequence they pasted, which is both easy to do and easy to fix.
     *
     * ⚠️ Anything not recognised is returned UNCHANGED. A translation table that
     * swallowed unknown errors would replace a precise message with a vague one
     * at exactly the moment precision matters.
     *
     * @param string $error    primer3's own text.
     * @param int    $template Template length, for a message that can be specific.
     * @return string
     */
    public static function friendlyError($error, $template = 0)
    {
        if (strpos($error, 'SEQUENCE_INCLUDED_REGION length < min PRIMER_PRODUCT_SIZE_RANGE') !== false) {
            return 'The product size you asked for is larger than the sequence you gave'
                 . ($template > 0 ? ', which is ' . number_format($template) . ' bp' : '')
                 . '. Ask for a smaller product, or paste a longer sequence — a primer pair '
                 . 'cannot amplify more than it is given.';
        }

        return $error;
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
