<?php
/**
 * PrimerTails — 5' cloning adapters appended to an ordered oligo.
 *
 * A tail is extra sequence you pay the vendor to put on the 5' end of a primer.
 * It does not come from the template and it is not there to anneal: it ends up
 * incorporated into the amplicon so the product can be cloned, transcribed, or
 * barcoded downstream.
 *
 * ⭐ THE ORDER OF OPERATIONS IS THE SPEC, NOT AN IMPLEMENTATION DETAIL.
 * From the workflow this tool replaces (user, 2026-08-06): "when i ran the
 * checker, i didn't include the t4p, only the primer without it, which is why i
 * find the primer, and get all the stats then append the oligo and report both."
 *
 *     design on the bare template
 *       → compute every statistic on the UNTAILED primer
 *       → check specificity on the UNTAILED primer
 *       → THEN append the tail
 *       → report BOTH forms
 *
 * Two consequences that must never be "optimised" away:
 *
 *  - **Tm and GC belong to the annealing portion.** The tail does not base pair
 *    with the template in the early cycles, so a Tm over the full tailed oligo
 *    is not the Tm of the reaction. primer3 designs on the template and never
 *    sees a tail; keep it that way and never feed a tailed sequence back into a
 *    Tm calculation.
 *  - **Tails are stripped before any genome check.** As the user put it, "the
 *    adaptor is not in the genome, well it should not be" — so a tailed oligo
 *    aligned against a genome can only ever produce a partial, lower-identity
 *    hit. That inflates the mismatch count and can push a perfectly good primer
 *    below the score cutoff. The checker always receives the untailed core.
 *
 * This class therefore only ever ADDS fields. It never rewrites left_sequence or
 * right_sequence, so anything downstream that reads those keys — the Check
 * hand-off to Primer BLAST above all — keeps getting the bare primer by
 * construction rather than by remembering to strip.
 *
 * ⛔ RESTATED BY THE USER, 2026-08-17, and it settles a question this file used
 * to answer differently: "i dont want to add the oligos to the input, but only
 * to the output. they should not be involved in the testing of the output
 * alignment to the genome either. users should only add their primers minus
 * oligos for the testing in our other tool."
 *
 * So a tail belongs to the OUTPUT and to nothing else. It never reaches primer3,
 * never reaches a Tm calculation, and never reaches a genome alignment — not
 * even on its own. An earlier draft of this class BLASTed each tail against the
 * selected assembly as a QC step (notes/PRIMER_BLAST_TOOL_PLAN.md suggests it);
 * that is deliberately not here. The measurement it was based on is recorded in
 * the plan note if it is ever wanted, and it argues the check is near-worthless
 * anyway: a 13-base tail matches a 164 Mb genome exactly once, which is fewer
 * times than chance predicts.
 *
 * @package MOOP\Primer
 */

class PrimerTails
{
    /**
     * Longest custom tail accepted, in bases.
     *
     * Real adapters run from 13 (T4P) to about 33 (Illumina overhang) to 40
     * (Gibson homology arm). 60 leaves room for all of them while still
     * rejecting a template someone pasted into the wrong box.
     */
    const MAX_LENGTH = 60;

    /**
     * Total oligo length above which synthesis gets more expensive and vendors
     * recommend extra purification.
     *
     * IDT recommends purification beyond standard desalting above 40 nt, and
     * tailed primers cross that easily — a 20-mer with a 13-base tail is 33, a
     * 25-mer with the same tail is 38, and a 20-mer with a T7 promoter is 40.
     * Reported as a note, never as an error: it is a cost, not a mistake.
     */
    const LONG_OLIGO = 45;

    /**
     * Built-in adapters.
     *
     * A NAMED, CONFIGURABLE LIST plus free text, deliberately — the two T4P
     * strings were hardcoded in the CGI this replaces, which made a lab-specific
     * choice look like a property of primer design. Deployments add their own
     * through the 'primer_tails' config key; see config/site_config.php.
     *
     * ⚠️ 'forward' goes on the forward (left) primer, 'reverse' on the reverse
     * (right) one. They are NOT reverse complements of each other and must not
     * be derived from one another — an adapter pair is two independent
     * sequences chosen for what happens after the PCR.
     */
    const BUILTIN = [
        [
            'id'      => 't4p',
            'label'   => 'T4P cloning adapter',
            'forward' => 'CATTACCATCCCG',
            'reverse' => 'CCAATTCTACCCG',
            // ⚠️ PLACEHOLDER WORDING, awaiting the user (2026-08-17). An earlier draft said
            // "so the PCR product can be cloned directly without a separate restriction step",
            // which was INFERRED from how tails were described, not stated — and the user
            // rejected it. What is below claims only what this file actually knows: two
            // 13-base sequences, one per primer, that end up in the product. Do not add a
            // mechanism or a downstream workflow here without being told what it is.
            'note'    => '13-base adapters, one for each primer. They are not part of the '
                       . 'sequence that anneals to your template — they end up in the PCR product.',
        ],
        [
            'id'      => 't7',
            'label'   => 'T7 promoter on both primers',
            'forward' => 'TAATACGACTCACTATAGGG',
            'reverse' => 'TAATACGACTCACTATAGGG',
            'note'    => 'Puts a T7 promoter at both ends of the product, so it can be transcribed '
                       . 'from either strand — the usual way to make double-stranded RNA.',
        ],
    ];

    /**
     * The adapters offered on the page.
     *
     * Pure on purpose: the caller reads config and passes it in, so this stays
     * testable with no ConfigManager and no site data — the same rule
     * Primer3Design::buildInput() follows, and for the same reason.
     *
     * Configured entries REPLACE a built-in with the same id rather than being
     * appended beside it, so a deployment that needs a different T4P gets one
     * entry rather than two identically-labelled ones.
     *
     * @param array $configured Entries from the 'primer_tails' config key.
     * @return array Keyed by id.
     */
    public static function catalogue(array $configured = [])
    {
        $out = [];
        foreach (array_merge(self::BUILTIN, $configured) as $entry) {
            if (!is_array($entry) || !isset($entry['id'])) {
                continue;
            }

            $forward = self::normalise($entry['forward'] ?? '');
            $reverse = self::normalise($entry['reverse'] ?? '');

            // A configured entry with no usable sequence is dropped rather than
            // offered: picking it would silently produce untailed oligos, which
            // is the failure that looks most like success.
            if ($forward === '' && $reverse === '') {
                continue;
            }
            if (self::validate($forward) !== '' || self::validate($reverse) !== '') {
                continue;
            }

            $out[(string)$entry['id']] = [
                'id'      => (string)$entry['id'],
                'label'   => (string)($entry['label'] ?? $entry['id']),
                'forward' => $forward,
                'reverse' => $reverse,
                'note'    => (string)($entry['note'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Strip whitespace and upper-case. Does NOT validate — see validate().
     */
    public static function normalise($seq)
    {
        return strtoupper(preg_replace('/\s+/', '', (string)$seq));
    }

    /**
     * Why this sequence cannot be used as a tail, or '' if it can.
     *
     * An empty tail is valid: it means "no tail on this side", which is a real
     * choice when only one primer needs an adapter.
     */
    public static function validate($seq)
    {
        $seq = self::normalise($seq);
        if ($seq === '') {
            return '';
        }
        if (preg_match('/[^ACGT]/', $seq)) {
            // Not N, and not degenerate codes: a tail is synthesised exactly as
            // written, so an ambiguity code is an order the vendor cannot fill.
            return 'A tail can only contain A, C, G and T — it is synthesised exactly as written.';
        }
        if (strlen($seq) > self::MAX_LENGTH) {
            return 'That tail is ' . strlen($seq) . ' bases; ' . self::MAX_LENGTH
                 . ' is the most this tool accepts. Check you have not pasted a template sequence.';
        }
        return '';
    }

    /**
     * Turn the form's tail choice into one adapter definition.
     *
     * @param string $id         Catalogue id, 'none', or 'custom'.
     * @param string $custom_f   Free-text forward tail (used when $id === 'custom').
     * @param string $custom_r   Free-text reverse tail.
     * @param array  $catalogue  From catalogue().
     * @return array ['id','label','forward','reverse','note','errors' => []]
     */
    public static function resolve($id, $custom_f, $custom_r, array $catalogue)
    {
        $none = ['id' => 'none', 'label' => '', 'forward' => '', 'reverse' => '',
                 'note' => '', 'errors' => []];

        if ($id === '' || $id === 'none') {
            return $none;
        }

        if ($id === 'custom') {
            $forward = self::normalise($custom_f);
            $reverse = self::normalise($custom_r);

            $errors = [];
            foreach (['forward' => $forward, 'reverse' => $reverse] as $side => $seq) {
                $err = self::validate($seq);
                if ($err !== '') {
                    $errors[] = ucfirst($side) . ' tail: ' . $err;
                }
            }

            // Asking for a custom tail and giving no sequence is a mistake worth
            // naming. Falling through to "no tail" would design exactly what was
            // asked for and quietly ignore the part the user cared about.
            if ($errors === [] && $forward === '' && $reverse === '') {
                $errors[] = 'You chose a custom tail but did not enter a sequence for either primer.';
            }

            return [
                'id'      => 'custom',
                'label'   => 'Custom tail',
                'forward' => $forward,
                'reverse' => $reverse,
                'note'    => '',
                'errors'  => $errors,
            ];
        }

        if (!isset($catalogue[$id])) {
            // ⚠️ array_merge, NOT the + operator. $none already carries an empty
            // 'errors', and + keeps the LEFT side's value for a duplicate key —
            // so `$none + ['errors' => [...]]` throws the message away and
            // returns a clean "no tail". An unknown adapter would then design
            // untailed primers and say nothing, which is precisely the silent
            // failure this class exists to avoid. Caught by a test, not by
            // reading it.
            return array_merge($none, ['errors' => ['That tail is not one this site offers.']]);
        }

        return array_merge($catalogue[$id], ['errors' => []]);
    }

    /**
     * Add the tailed forms of a primer pair alongside the untailed ones.
     *
     * ⚠️ ADDS KEYS ONLY. left_sequence and right_sequence keep holding the bare
     * primer, because that is what every statistic was computed on and what the
     * specificity check has to receive.
     *
     * @param array $pair From Primer3Design::parseOutput().
     * @param array $tail From resolve().
     * @return array The pair with tail_* keys added when a tail applies.
     */
    public static function apply(array $pair, array $tail)
    {
        $forward = $tail['forward'] ?? '';
        $reverse = $tail['reverse'] ?? '';

        if ($forward === '' && $reverse === '') {
            return $pair;
        }

        $left  = (string)($pair['left_sequence']  ?? '');
        $right = (string)($pair['right_sequence'] ?? '');

        if ($left !== '') {
            $pair['left_tailed'] = $forward . $left;
            $pair['left_oligo_length'] = strlen($pair['left_tailed']);
        }
        if ($right !== '') {
            $pair['right_tailed'] = $reverse . $right;
            $pair['right_oligo_length'] = strlen($pair['right_tailed']);
        }

        // The amplicon that comes out of the tube carries both tails. The
        // untailed number is the insert; this is the band on the gel. Both
        // matter, so both are reported — the workflow this replaces only ever
        // gave the first, which is the one you do NOT measure.
        if (isset($pair['product_size']) && $pair['product_size'] !== '') {
            $pair['product_size_tailed'] = (int)$pair['product_size']
                                         + strlen($forward) + strlen($reverse);
        }

        return $pair;
    }

    /**
     * Oligos exactly as they should be ordered, for the FASTA and TSV surfaces.
     *
     * One code path produces every output form. The CGI this replaces had the
     * table showing tailed and untailed side by side while the FASTA block below
     * it emitted only the untailed form, its tailed branch commented out — so
     * the file you downloaded disagreed with the table you were reading.
     *
     * @param string $record_id Sequence the pairs were designed from.
     * @param array  $pairs     Pairs already through apply().
     * @param array  $tail      From resolve().
     * @return array Each: ['name','sequence','side','rank','tailed','length']
     */
    public static function oligoRecords($record_id, array $pairs, array $tail)
    {
        $base    = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$record_id);
        $tailed  = ($tail['forward'] ?? '') !== '' || ($tail['reverse'] ?? '') !== '';
        $records = [];

        foreach ($pairs as $pair) {
            $rank = $pair['rank'] ?? 0;
            foreach ([['left', 'F'], ['right', 'R']] as list($side, $tag)) {
                $seq = $pair[$side . '_sequence'] ?? '';
                if ($seq === '') {
                    continue;
                }

                $records[] = [
                    'name'     => "{$base}_p{$rank}_{$tag}",
                    'sequence' => $seq,
                    'side'     => $side,
                    'rank'     => $rank,
                    'tailed'   => false,
                    'length'   => strlen($seq),
                ];

                if ($tailed && !empty($pair[$side . '_tailed'])) {
                    $records[] = [
                        'name'     => "{$base}_p{$rank}_{$tag}_" . ($tail['id'] ?? 'tail'),
                        'sequence' => $pair[$side . '_tailed'],
                        'side'     => $side,
                        'rank'     => $rank,
                        'tailed'   => true,
                        'length'   => strlen($pair[$side . '_tailed']),
                    ];
                }
            }
        }

        return $records;
    }
}
