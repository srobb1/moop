<?php
/**
 * SequenceMarkup — the characters you can put IN a pasted sequence to say where
 * primers must go.
 *
 * Two marks, two meanings, neither tied to a kind of primer:
 *
 *   |   a primer must CROSS this point
 *   [ ] the product must CONTAIN this stretch
 *
 * ⭐ `|` IS NOT AN RT-PCR FEATURE (user, 2026-08-07: "i think a mark to indicate
 * a primer must be here would be useful for more than just qrt primers"). primer3's
 * own tag is SEQUENCE_OVERLAP_JUNCTION_LIST — a generic "overlap this point", with
 * nothing exon-specific about it. A fusion breakpoint, a vector/insert boundary, a
 * scaffold join and an edit site all want the same thing. RT-PCR is simply the case
 * where MOOP can fill the marks in for you from the exon index.
 *
 * ⚠️ MULTIPLE MARKS ARE FREE. primer3 takes a LIST and requires a primer to cross
 * at least one of them, ignoring those it cannot use. Measured on 2.6.1: junctions
 * "3 450 880" produced byte-identical output to "450" alone, because 3 and 880 are
 * too close to the ends to place a primer across. So "give us several and let it
 * work out which one is spannable" needs no logic on our side at all.
 *
 * 🚨 THE INDEX CONVENTION IS AN OFF-BY-ONE TRAP, AND THE MANUAL IS MISLEADING.
 * primer3's manual writes "SEQUENCE_OVERLAP_JUNCTION_LIST=20 # 1-based indexes"
 * beside an example whose junction follows the 20th base. Measured against 2.6.1
 * it does not behave that way. Holding the junction at 450 and lowering
 * PRIMER_MIN_3_PRIME_OVERLAP_OF_JUNCTION from 4 to 3 moves the chosen primer's
 * edge from 3 bases left of base 450 to 2 — always exactly one less than the
 * constraint demands. So primer3 counts the junction as following base N+1:
 * the values are effectively 0-BASED.
 *
 * This class therefore speaks ONE convention — "the junction follows 1-based base
 * K", which is what both front doors naturally produce (a `|` typed after base K,
 * and ExonMap's cumulative exon length) — and Primer3Design::buildInput() does the
 * single conversion to primer3's numbering. Getting it wrong is invisible: the
 * primer still looks like it spans the junction, it just has one fewer real base
 * on the far side than the constraint promised, which is exactly the margin that
 * decides whether it discriminates genomic DNA.
 *
 * @package MOOP\Primer
 */

class SequenceMarkup
{
    /** The documented junction mark. */
    const JUNCTION = '|';

    /**
     * Also accepted, because the workflow this replaces used it.
     *
     * runPrimer3_web.pl:150 splits the pasted sequence on '-' to build its
     * junction list, so anyone moving over from that form will type a dash.
     *
     * ⚠️ But '-' is ALSO the gap character in an aligned sequence, so a sequence
     * copied out of an alignment would silently acquire a junction at every gap.
     * That is why parse() always reports how many marks it found: an accidental
     * one has to be visible, not silent.
     */
    const JUNCTION_LEGACY = '-';

    /**
     * Read a pasted sequence and separate the DNA from the instructions.
     *
     * @param string $raw Sequence as typed, FASTA header already removed.
     * @return array {
     *   @type string $sequence   Bare DNA, upper-cased, no marks, no whitespace.
     *   @type array  $junctions  1-based positions K: the junction FOLLOWS base K.
     *   @type array  $targets    [[start1, length], …] regions the product must contain.
     *   @type array  $errors     Fatal problems; the caller must not design.
     *   @type array  $notes      What was found, so a stray mark is never silent.
     * }
     */
    public static function parse($raw)
    {
        $result = ['sequence' => '', 'junctions' => [], 'targets' => [],
                   'errors' => [], 'notes' => []];

        $clean         = '';
        $bases         = 0;       // bases emitted so far = 1-based position of the last one
        $open_at       = null;    // where the current '[' started, in base coordinates
        $legacy_used   = false;
        $bracket_depth = 0;

        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $ch = $raw[$i];

            if (ctype_space($ch)) {
                continue;
            }

            if ($ch === self::JUNCTION || $ch === self::JUNCTION_LEGACY) {
                if ($ch === self::JUNCTION_LEGACY) {
                    $legacy_used = true;
                }
                // A mark before any base, or two in a row, points at nothing.
                // Dropped rather than recorded as a junction at position 0, which
                // primer3 would take as a real constraint one base into the
                // sequence and quietly satisfy somewhere unintended.
                if ($bases === 0) {
                    $result['notes'][] = 'A junction mark at the very start of the sequence was '
                                       . 'ignored — there is nothing before it for a primer to cross.';
                    continue;
                }
                if (in_array($bases, $result['junctions'], true)) {
                    continue;                       // "||" is one junction, not two
                }
                $result['junctions'][] = $bases;    // junction FOLLOWS this base
                continue;
            }

            if ($ch === '[') {
                if ($bracket_depth > 0) {
                    $result['errors'][] = 'The sequence has a "[" inside another "[…]". Regions to '
                                        . 'include cannot be nested.';
                    return $result;
                }
                $bracket_depth++;
                $open_at = $bases + 1;              // the region starts at the NEXT base
                continue;
            }

            if ($ch === ']') {
                if ($bracket_depth === 0) {
                    $result['errors'][] = 'The sequence has a "]" with no matching "[" before it.';
                    return $result;
                }
                $bracket_depth--;
                $len = $bases - $open_at + 1;
                if ($len < 1) {
                    $result['errors'][] = 'The sequence has an empty "[]" — put the bases you want '
                                        . 'included between the brackets.';
                    return $result;
                }
                $result['targets'][] = [$open_at, $len];
                $open_at = null;
                continue;
            }

            $clean .= $ch;
            $bases++;
        }

        if ($bracket_depth > 0) {
            $result['errors'][] = 'The sequence has a "[" that is never closed. Add the matching "]".';
            return $result;
        }

        $result['sequence'] = strtoupper($clean);

        // ---- report what was found; a mark must never take effect silently ----
        if ($result['junctions']) {
            $n = count($result['junctions']);
            $result['notes'][] = $n . ' junction mark' . ($n === 1 ? '' : 's')
                               . ' found in the sequence, at position'
                               . ($n === 1 ? ' ' : 's ')
                               . implode(', ', array_map('number_format', $result['junctions']))
                               . '. A primer must cross '
                               . ($n === 1 ? 'it' : 'at least one of them') . '.';
        }
        if ($legacy_used) {
            // The likeliest cause of a surprise here is a pasted ALIGNMENT.
            $result['notes'][] = 'Dashes were read as junction marks. If you meant them as alignment '
                               . 'gaps, remove them and use "|" for junctions instead.';
        }
        if ($result['targets']) {
            foreach ($result['targets'] as list($s, $l)) {
                $result['notes'][] = 'Every product will contain bases ' . number_format($s)
                                   . '–' . number_format($s + $l - 1) . ', marked with [ ].';
            }
        }

        return $result;
    }

    /**
     * Does this text contain any markup at all?
     *
     * Used to decide whether the user has said something explicit that should
     * beat a looked-up exon index. Cheap, and keeps that decision readable at
     * the call site.
     */
    public static function hasMarkup($raw)
    {
        return strpbrk((string)$raw, self::JUNCTION . self::JUNCTION_LEGACY . '[]') !== false;
    }

    /**
     * Put junction marks back INTO a sequence, for display.
     *
     * The reverse of parse(), so a looked-up exon structure can be shown in the
     * box the user is reading rather than described in a sentence beside it.
     *
     * ⚠️ Not used to feed primer3 — positions stay the source of truth. This is
     * for showing a user where the junctions are, and for letting them take the
     * marked-up sequence away and edit it.
     *
     * @param string $sequence  Bare DNA.
     * @param array  $junctions 1-based positions K; the mark goes AFTER base K.
     */
    public static function annotate($sequence, array $junctions)
    {
        $sequence  = strtoupper(preg_replace('/\s+/', '', (string)$sequence));
        $positions = array_values(array_unique(array_filter(
            array_map('intval', $junctions),
            fn($p) => $p > 0 && $p < strlen($sequence)      // a mark after the last base is a no-op
        )));
        sort($positions);

        $out  = '';
        $prev = 0;
        foreach ($positions as $p) {
            $out .= substr($sequence, $prev, $p - $prev) . self::JUNCTION;
            $prev = $p;
        }

        return $out . substr($sequence, $prev);
    }
}
