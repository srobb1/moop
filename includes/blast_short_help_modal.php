<?php
/**
 * Help for short-sequence BLAST (primers, oligos, siRNA, probes).
 *
 * Separate from the general BLAST help because the advice INVERTS: everywhere else
 * "no hits" means loosen nothing and trust the E-value, and here the E-value is the
 * thing actively hiding a perfect match.
 *
 * Every number below was measured on this deployment against Nematostella RS_101 with
 * a real 20 nt primer, not taken from documentation. See tests/blast_alignment_tests.php
 * for the alignment side and the commit message for the search-parameter figures.
 *
 * Requires lib/help_ui.php (loaded globally via config_init).
 */

if (!function_exists('help_modal')) {
    require_once __DIR__ . '/../lib/help_ui.php';
}

echo help_modal(
    'blast-short-help',
    'Searching with primers and other short sequences',
    [
        [
            'heading' => 'Why a normal BLAST misses your primer',
            'cards' => [
                [
                    'label' => 'It is not you',
                    'text'  => 'A 20 nt primer that matches PERFECTLY can return zero hits '
                             . 'on a default search. The match is real; the search threw it away.',
                ],
                [
                    'label' => 'Too short to seed',
                    'text'  => 'BLAST first finds exact "words" and extends them. The default '
                             . 'word is 28 for megablast, so a 20 nt query has nowhere to start.',
                ],
                [
                    'label' => 'Too short to score',
                    'text'  => 'A perfect 20 nt match scores only about 40 bits. Against a whole '
                             . 'gene set that is not statistically surprising, so the default '
                             . 'E-value of 10 discards it.',
                ],
                [
                    'label' => 'Measured here',
                    'html'  => true,
                    'text'  => 'A primer that exists exactly in the database: '
                             . '<code>megablast &rarr; 0 hits</code>, '
                             . '<code>plain blastn &rarr; 20</code>, '
                             . '<code>BLASTn-short &rarr; 566</code>.',
                ],
            ],
        ],
        [
            'heading' => 'What to do',
            'cards' => [
                [
                    'label' => 'Pick BLASTn-short',
                    'color' => 'success',
                    'text'  => 'Choose it in step 2. It sets everything below for you and opens '
                             . 'the advanced panel so you can see exactly what changed.',
                ],
                [
                    'label' => 'Word size 7',
                    'text'  => 'Short enough for a 20 nt query to seed. Raising it back to 11 '
                             . 'loses about a tenth of the hits; at 16 almost everything vanishes.',
                ],
                [
                    'label' => 'E-value 1000',
                    'text'  => 'Deliberately permissive. This is the single most important setting '
                             . 'here: at 10 the same primer drops from 566 hits to 26.',
                ],
                [
                    'label' => 'Low-complexity filter OFF',
                    'text'  => 'A primer containing a simple repeat gets masked to nothing and '
                             . 'returns ZERO hits with no warning. Leave this unticked.',
                ],
                [
                    'label' => 'Expect many hits',
                    'text'  => 'A permissive threshold is doing its job. Sort by identity and '
                             . 'length: a usable primer site is full-length and 100%.',
                ],
            ],
        ],
        [
            'heading' => 'Two primers at once, and product size',
            'cards' => [
                [
                    'label' => 'Join them with a run of Ns',
                    'color' => 'success',
                    'html'  => true,
                    'text'  => 'One record, both primers, N in between. Keeps the pair together '
                             . 'in a single result section:<br>'
                             . '<code>&gt;my-primer-pair<br>GCTTGAGCTGTTATCTGTGC<br>'
                             . 'NNNNNNNNNNNNNNNNNNN<br>GCGGTGCTTCTGGGCTGAGT</code>',
                ],
                [
                    'label' => 'The N run is only a spacer',
                    // Literal em dash, not &mdash;. This card has no 'html' => true, so the
                    // text is escaped and an entity would render as visible markup.
                    'text'  => 'N matches nothing, so BLAST reports the two primers as separate '
                             . 'HSPs. Its length does not have to match the real gap — '
                             . '19 Ns finds a 320 bp product perfectly well.',
                ],
                [
                    'label' => 'Paste the reverse primer as ordered',
                    'text'  => 'Do not reverse-complement it yourself. BLAST searches both '
                             . 'strands, and the strand it lands on is what confirms the pair is '
                             . 'pointing the right way.',
                ],
                [
                    'label' => 'A real pair looks like this',
                    'html'  => true,
                    'text'  => 'Same subject, opposite strands, facing each other. The forward '
                             . 'ASCENDS and the reverse DESCENDS:<br>'
                             . '<code>Sbjct &nbsp;201 &rarr; 220 &nbsp;(plus)<br>'
                             . 'Sbjct &nbsp;520 &rarr; 501 &nbsp;(minus)</code>',
                ],
                [
                    'label' => 'Product size',
                    'html'  => true,
                    'text'  => 'Largest coordinate minus smallest, plus one, across both primers '
                             . 'on that subject:<br><code>520 &minus; 201 + 1 = 320 bp</code>',
                ],
                [
                    'label' => 'What means no product',
                    'text'  => 'Primers on different subjects, on the SAME strand, or facing away '
                             . 'from each other will not amplify however good each match looks '
                             . 'on its own.',
                ],
                [
                    'label' => 'Check every shared subject',
                    'text'  => 'Both primers landing on several sequences means several possible '
                             . 'products. Paralogues and gene families do this routinely.',
                ],
                [
                    'label' => 'Or use two separate records',
                    'html'  => true,
                    'text'  => '<code>&gt;Fwd</code> and <code>&gt;Rev</code> as two entries also '
                             . 'works &mdash; each gets its own collapsible Query section. Handier '
                             . 'for checking one primer, harder for reading off a product size.',
                ],
            ],
        ],
    ],
    [
        'intro' => 'Primers, oligos, siRNA and probes need different settings from a normal '
                 . 'search — the usual defaults hide perfect matches rather than finding them.',
    ]
);
