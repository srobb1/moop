<?php
/**
 * PrimerPairs - turn BLAST hits into the products a primer pair would amplify.
 *
 * THE CENTRAL RULE: count PRODUCTS, not primer hits.
 *
 * Measured on this host: a real pair had 15 genomic hits for one primer and 1 for
 * the other, yet formed exactly ONE product. Reporting "15 hits" would be alarming
 * and wrong. As the user put it: "sometimes one primer can be more abundant if the
 * 2nd of the pair is more unique -- the combination is what works together to make
 * your fragment."
 *
 * Per-primer counts are still returned alongside, because they say WHERE the
 * specificity comes from: if it rests on one primer alone, revising that primer
 * silently destroys the assay and a bare "1 product" would never warn anyone.
 *
 * @package MOOP\Primer
 */

class PrimerPairs
{
    /**
     * Upper bound on product size, in bp.
     *
     * 10,000 bp, chosen to satisfy two conflicting needs with one control.
     *
     * Standard PCR realistically amplifies ~2 kb, so 2 kb is the instinctive
     * default -- but it is WRONG here, because the same bound also applies to the
     * GENOMIC search, where an intron-spanning pair legitimately measures much
     * larger. Measured: the wallaby pair spans 7,742 bp of genomic sequence purely
     * because it crosses introns. At a 2 kb bound that product vanished, the page
     * reported "no products" for the genome, and the genomic-vs-cDNA comparison
     * behind the intron-spanning verdict disappeared with it.
     *
     * 10 kb keeps most intron-spanning pairs visible while still being well beyond
     * what standard PCR makes -- so a product at this size reads as DIAGNOSTIC
     * (this is what contaminating gDNA would give) rather than as something to try
     * to amplify.
     *
     * Whatever it discards is COUNTED and reported -- never silently dropped.
     */
    const MAX_PRODUCT_DEFAULT = 10000;

    /**
     * Largest product standard PCR realistically makes, in bp.
     *
     * Distinct from MAX_PRODUCT_DEFAULT, which governs what is REPORTED. This
     * governs what COUNTS: a real 8 kb off-target is still listed because it
     * exists, but it will not amplify under normal conditions and so must not turn
     * an otherwise clear pair ambiguous.
     */
    const AMPLIFIABLE_MAX = 2000;

    /**
     * Ignore a site outright at this many total mismatches or more.
     *
     * NCBI Primer-BLAST's default. Beyond this the site is not a plausible primer
     * binding event at all.
     */
    const IGNORE_AT_TOTAL_MISMATCH = 6;

    /**
     * Mismatches within the 3'-end window that stop a site priming.
     *
     * Primer-BLAST dismisses an unintended target when a primer carries 2 or more
     * mismatches in its last 5 bases, because the polymerase extends from the 3'
     * end: a mismatch there all but prevents extension, while the same mismatch
     * near the 5' end is tolerated. Position, not count, is the dominant factor —
     * which is why a total-mismatch threshold alone was the wrong test.
     */
    const THREE_PRIME_BLOCKING = 2;

    /**
     * Would standard PCR make this product?
     *
     * Three conditions, in the order they matter:
     *   1. short enough to amplify at all;
     *   2. neither primer so mismatched that the site is implausible;
     *   3. neither primer blocked at its 3' end.
     *
     * Condition 3 is the one a mismatch COUNT cannot express, and it is the one
     * that decides most real cases.
     */
    public static function isAmplifiable(array $product)
    {
        if ($product['size'] > self::AMPLIFIABLE_MAX) {
            return false;
        }

        foreach ($product['hits'] as $hit) {
            if ($hit['mismatch'] >= self::IGNORE_AT_TOTAL_MISMATCH) {
                return false;
            }
            if (($hit['three_prime_mismatch'] ?? 0) >= self::THREE_PRIME_BLOCKING) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find every product a pair's hits could form on a single database.
     *
     * @param array $hits    ['forward' => [...], 'reverse' => [...]] from PrimerBlast.
     * @param array $options ['max_product' => int]
     * @return array {
     *     @type array $products      Each: subject, start, end, size, primers, mismatch info.
     *     @type int   $product_count
     *     @type array $primer_hits   ['forward' => n, 'reverse' => n]
     *     @type array $paired_hits   How many of those matches actually ended up in a
     *                                product. The difference is matches that found no
     *                                partner -- which is the normal fate of most of them,
     *                                and needs saying, or a user who RAISES a limit sees
     *                                the match count rise with nothing to show for it.
     *     @type int   $over_max      Combinations discarded by the size bound.
     *     @type string|null $carried_by  'forward'|'reverse' when specificity rests on one primer.
     * }
     */
    public static function findProducts(array $hits, array $options = [])
    {
        $max_product = isset($options['max_product'])
            ? (int)$options['max_product']
            : self::MAX_PRODUCT_DEFAULT;

        $forward = $hits['forward'] ?? [];
        $reverse = $hits['reverse'] ?? [];

        $result = [
            'products'      => [],
            'product_count' => 0,
            'primer_hits'   => ['forward' => count($forward), 'reverse' => count($reverse)],
            'paired_hits'   => ['forward' => 0, 'reverse' => 0],
            'over_max'      => 0,
            'carried_by'    => null,
        ];

        // Tag every hit with which primer it came from, then group by subject.
        // Both primers are considered together because a product can also form
        // between two hits of the SAME primer -- a real source of unwanted
        // amplification that a forward-vs-reverse-only rule would never report.
        $by_subject = [];
        foreach (['forward', 'reverse'] as $side) {
            foreach ($hits[$side] ?? [] as $hit) {
                $hit['primer'] = $side;
                $by_subject[$hit['subject']][] = $hit;
            }
        }

        foreach ($by_subject as $subject => $subject_hits) {
            $n = count($subject_hits);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $product = self::product($subject_hits[$i], $subject_hits[$j], $max_product, $result);
                    if ($product !== null) {
                        $result['products'][] = $product;
                    }
                }
            }
        }

        usort($result['products'], function ($a, $b) {
            return $a['size'] <=> $b['size'];
        });

        $result['product_count'] = count($result['products']);

        // Which individual matches actually took part in a product. Counted by
        // identity rather than by summing, since one match can pair more than once.
        $used = ['forward' => [], 'reverse' => []];
        foreach ($result['products'] as $prod) {
            foreach ($prod['hits'] as $h) {
                $used[$h['primer']][$h['subject'] . ':' . $h['start'] . '-' . $h['end'] . $h['strand']] = true;
            }
        }
        $result['paired_hits'] = [
            'forward' => count($used['forward']),
            'reverse' => count($used['reverse']),
        ];

        // Flag the asymmetric case: one primer is promiscuous, the other is what
        // makes the pair specific. Revising the unique one would destroy the assay.
        if ($result['product_count'] > 0 && $result['product_count'] < 3) {
            $f = $result['primer_hits']['forward'];
            $r = $result['primer_hits']['reverse'];
            if ($f > 0 && $r > 0) {
                if ($f >= $r * 3 && $r <= 2) {
                    $result['carried_by'] = 'reverse';
                } elseif ($r >= $f * 3 && $f <= 2) {
                    $result['carried_by'] = 'forward';
                }
            }
        }

        return $result;
    }

    /**
     * The product a user would call "the" product for this pair.
     *
     * Needed because the genomic-vs-cDNA size comparison (the intron-spanning
     * verdict) has to compare LIKE WITH LIKE. Taking products[0] after a sort by
     * size is wrong: a self-pairing artefact that happens to be smaller than the
     * real amplicon would silently become the number the verdict is computed from,
     * and the verdict would be confidently incorrect.
     *
     * Preference order: a genuine forward+reverse product, then fewest mismatches,
     * then smallest.
     *
     * @return array|null
     */
    public static function primaryProduct(array $found)
    {
        if (empty($found['products'])) {
            return null;
        }

        $candidates = $found['products'];

        usort($candidates, function ($a, $b) {
            // Real pairs beat same-primer artefacts.
            $self = ($a['self_pairing'] ? 1 : 0) <=> ($b['self_pairing'] ? 1 : 0);
            if ($self !== 0) {
                return $self;
            }
            // Then the cleanest match.
            $mm = $a['max_mismatch'] <=> $b['max_mismatch'];
            if ($mm !== 0) {
                return $mm;
            }
            return $a['size'] <=> $b['size'];
        });

        return $candidates[0];
    }

    /**
     * Would these two hits amplify a fragment between them?
     *
     * Two conditions, and BOTH are required:
     *   1. Opposite strands. Two same-strand hits cannot bracket anything.
     *   2. They must FACE each other. A plus-strand primer extends towards higher
     *      coordinates, a minus-strand primer towards lower ones, so the plus hit
     *      must lie to the LEFT. Reversed, the primers point away and nothing is
     *      amplified -- a case that looks like a valid pair on a plain BLAST page.
     *
     * Note neither hit is assumed to be "the forward primer": on a minus-strand
     * gene the user's forward primer aligns to the minus strand. That is exactly
     * the measured case, and hard-coding the roles would have missed it.
     */
    private static function product(array $a, array $b, $max_product, array &$result)
    {
        if ($a['strand'] === $b['strand']) {
            return null;
        }

        $plus  = ($a['strand'] === '+') ? $a : $b;
        $minus = ($a['strand'] === '+') ? $b : $a;

        if ($plus['start'] > $minus['end']) {
            return null;   // pointing away from each other
        }

        $size = $minus['end'] - $plus['start'] + 1;

        if ($size > $max_product) {
            $result['over_max']++;
            return null;
        }

        $primers = [$a['primer'], $b['primer']];
        sort($primers);

        return [
            'subject'      => $a['subject'],
            'start'        => $plus['start'],
            'end'          => $minus['end'],
            'size'         => $size,
            'primers'      => $primers,
            'self_pairing' => ($a['primer'] === $b['primer']),
            'mismatch'     => [
                $a['primer'] . '_1' => $a['mismatch'],
                $b['primer'] . '_2' => $b['mismatch'],
            ],
            'max_mismatch' => max($a['mismatch'], $b['mismatch']),
            'hits'         => [$a, $b],
        ];
    }
}
