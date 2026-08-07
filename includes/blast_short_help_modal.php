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

// Resolved rather than assumed: this modal is included from blast.php, where $site
// is in scope, but a link built from a missing variable would render as "//tools/…"
// and fail silently on whatever page borrows this next.
$short_help_site = '/' . ($site ?? ConfigManager::getInstance()->getString('site'));
$primer_blast_url = htmlspecialchars($short_help_site . '/tools/primer_blast.php');

echo help_modal(
    'blast-short-help',
    'Searching with primers and other short sequences',
    [
        [
            'heading' => 'Why a normal BLAST misses your short sequence',
            'cards' => [
                [
                    'label' => 'It is not you',
                    'text'  => 'A 20 nt sequence that matches PERFECTLY can return zero hits '
                             . 'on a default search. The match is real; the search threw it away.',
                ],
                [
                    'label' => 'What counts as short',
                    'text'  => 'Anything under about 30 nt: PCR and qPCR primers, sequencing '
                             . 'primers, in-situ and qPCR probes, CRISPR guide RNAs, siRNA and '
                             . 'shRNA targets, sequencing adapters and linkers, sample barcodes, '
                             . 'and cloning-site or tag sequences. All of them fail the same way '
                             . 'for the same two reasons.',
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
            'heading' => 'Good uses for a short search',
            'cards' => [
                [
                    'label' => 'Locate one oligo',
                    'text'  => 'Where does this probe, guide RNA or single primer land, and how '
                             . 'many times? This is what a short BLAST is for, and no other tool '
                             . 'here answers it.',
                ],
                [
                    'label' => 'Check a probe is unique',
                    'text'  => 'One full-length 100% hit and nothing else close means an in-situ '
                             . 'or qPCR probe is specific. Several equally good hits mean it is '
                             . 'not, whatever the target gene was.',
                ],
                [
                    'label' => 'Guide RNA off-targets',
                    'text'  => 'The permissive settings are the point: near-matches are exactly '
                             . 'what you are looking for, and a strict search would hide them.',
                ],
                [
                    'label' => 'Identify a stray sequence',
                    'text'  => 'An adapter, barcode or vector fragment found in your data — search '
                             . 'it to see whether it is genuinely in the genome or contamination.',
                ],
            ],
        ],
        [
            'heading' => 'Checking a primer PAIR? There is a tool for that',
            'cards' => [
                [
                    'label' => 'Use Primer BLAST instead',
                    'color' => 'success',
                    'html'  => true,
                    'text'  => 'Everything in the next section is done FOR you, and correctly: it '
                             . 'pairs the hits, keeps only those facing each other on the same '
                             . 'sequence, reports every product size, and searches the genome and '
                             . 'the transcriptome together so you can see whether the pair spans '
                             . 'an intron.<br>'
                             // btn-tool-orange, not btn-primary: this is the colour the tool
                             // already wears in the toolbox (config/tools_config.php), so the
                             // button here and the one they will look for match. blast.php uses
                             // btn-primary nowhere, so Bootstrap blue would be off-palette too.
                             . '<a href="' . $primer_blast_url . '" class="btn btn-sm btn-tool-orange mt-2">'
                             . '<i class="fa fa-vials me-1"></i>Open Primer BLAST</a>',
                ],
                [
                    'label' => 'What it catches that reading by hand does not',
                    'text'  => 'A primer pairing with a SECOND copy of itself, facing the other '
                             . 'way — a real source of unexpected bands that nobody finds by '
                             . 'scanning forward-versus-reverse combinations. It also judges which '
                             . 'products would actually amplify, using 3\'-end mismatch position '
                             . 'rather than a raw mismatch count.',
                ],
                [
                    'label' => 'And it draws the result',
                    'text'  => 'Each product links into the genome browser with both primer sites '
                             . 'marked. A primer sitting across an exon junction is drawn as two '
                             . 'blocks either side of the intron, which is the picture that shows '
                             . 'why it cannot amplify contaminating genomic DNA.',
                ],
                [
                    'label' => 'When to stay here',
                    'text'  => 'Primer BLAST needs a PAIR. To locate a single primer, or to search '
                             . 'a database it does not offer, the by-hand method below still works.',
                ],
            ],
        ],
        [
            'heading' => 'Two primers at once, by hand',
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
        'intro' => 'Primers, probes, guide RNAs, siRNA, adapters and barcodes need different '
                 . 'settings from a normal search — the usual defaults hide perfect matches '
                 . 'rather than finding them. To check a primer PAIR rather than locate one '
                 . 'sequence, use Primer BLAST: it pairs the hits and reports product sizes for '
                 . 'you.',
    ]
);
