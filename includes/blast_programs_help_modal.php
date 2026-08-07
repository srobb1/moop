<?php
/**
 * Help for choosing a BLAST program.
 *
 * Cards are GENERATED from blast_programs() (lib/blast_functions.php), which is
 * the same array the dropdown renders from. That is the point: the option text
 * and its explanation cannot drift apart, because there is only one of them.
 *
 * Kept out of the general BLAST help because this answers a question asked
 * BEFORE searching — "which of these six do I want" — and picking wrong returns
 * an empty result that reads as "not in this genome" rather than "wrong tool".
 *
 * Requires lib/help_ui.php (loaded globally via config_init) and
 * lib/blast_functions.php.
 */

if (!function_exists('help_modal')) {
    require_once __DIR__ . '/../lib/help_ui.php';
}
if (!function_exists('blast_programs')) {
    require_once __DIR__ . '/../lib/blast_functions.php';
}

// Resolved rather than assumed: a link built from a missing $site renders as
// "//tools/…" and fails silently on whatever page borrows this next.
$bp_site = '/' . ($site ?? ConfigManager::getInstance()->getString('site'));

// One card per program. The query→database line carries the mechanical fact and
// the sentence carries the judgement, so a reader scanning for "which one takes
// a protein" never has to read prose to find out.
$program_cards = [];
foreach (blast_programs() as $prog) {
    $program_cards[] = [
        'label' => $prog['label'],
        'color' => $prog['color'] ?? '',
        'html'  => true,
        'text'  => '<code>' . htmlspecialchars($prog['query']) . ' &rarr; '
                 . htmlspecialchars($prog['db']) . '</code><br>'
                 . htmlspecialchars($prog['when']),
    ];
}

echo help_modal(
    'blast-programs-help',
    'Choosing a BLAST program',
    [
        [
            'heading' => 'Start here',
            'cards' => [
                [
                    'label' => 'Paste your sequence first',
                    'color' => 'success',
                    'text'  => 'The page works out whether you pasted DNA or protein and '
                             . 'suggests a program. If that is all you need, you can stop here.',
                ],
                [
                    'label' => 'Across species? Search PROTEIN',
                    'color' => 'success',
                    'text'  => 'Protein stays conserved where DNA has long since drifted. For a '
                             . 'homologue in another organism, always search a protein database — '
                             . 'never DNA against DNA.',
                ],
                [
                    'label' => 'So do not reach for BLASTn',
                    'html'  => true,
                    'text'  => 'Having DNA is not a reason to pick it. Aim DNA at a protein '
                             . 'database and it becomes <code>BLASTx</code>; aim it at another '
                             . 'genome and it becomes <code>tBLASTx</code>. Both translate for '
                             . 'you.',
                ],
                [
                    'label' => 'Two things have to match',
                    'text'  => 'What YOU have, and what the database holds. Most wrong choices '
                             . 'are a protein query aimed at a DNA database, or the reverse.',
                ],
                [
                    'label' => 'Wrong program looks like no result',
                    'text'  => 'It does not warn you. It returns nothing, which reads as "this '
                             . 'gene is not in this genome" when the search was never possible.',
                ],
                [
                    'label' => 'Translated means six frames',
                    'text'  => 'BLASTx, tBLASTn and tBLASTx convert DNA to protein in all six '
                             . 'reading frames, so you do not need to know the frame yourself.',
                ],
            ],
        ],
        [
            'heading' => 'The six programs',
            'cards' => $program_cards,
        ],
        [
            // Use cases, not reference. Every card in this section carries the same
            // badge colour so the section reads as a different KIND of card at a
            // glance -- you arrive here with a task, not with a program name.
            'heading' => 'Which one do I want?',
            'cards' => [
                [
                    'label' => 'My gene, in the SAME species',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTn</code> — both sides are DNA, and within a species '
                             . 'the DNA still matches.',
                ],
                [
                    'label' => 'My gene, in ANOTHER species\' genome',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>tBLASTx</code> — your DNA and the genome are both '
                             . 'translated, so the comparison happens in protein, where the '
                             . 'similarity survives. Slow: narrow the database first.',
                ],
                [
                    'label' => 'Same, but I have the PROTEIN',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>tBLASTn</code> — much faster than tBLASTx and the usual '
                             . 'choice once the protein is to hand. Works on genomes with no '
                             . 'gene annotation at all.',
                ],
                [
                    'label' => 'Why is tBLASTn greyed out?',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => 'You pasted DNA, and tBLASTn needs a protein query. Paste the '
                             . 'protein, or use <code>tBLASTx</code> instead.',
                ],
                [
                    'label' => 'My transcript — what does it code for?',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTx</code> — translates your DNA and searches proteins.',
                ],
                [
                    'label' => 'A protein from another species',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTp</code> against proteins, or <code>tBLASTn</code> '
                             . 'against a genome if the protein set is missing or incomplete.',
                ],
                [
                    'label' => 'Nothing found, and I expected a match',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => 'If you searched DNA against DNA across species, that is why. '
                             . 'Go through protein: <code>BLASTx</code>, <code>tBLASTn</code> '
                             . 'or <code>tBLASTx</code>.',
                ],
                [
                    'label' => 'A primer, probe or guide RNA',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTn-short</code> — see the next section.',
                ],
            ],
        ],
        [
            'heading' => 'Short sequences and primer pairs',
            'cards' => [
                [
                    'label' => 'Under 30 nt needs BLASTn-short',
                    'color' => 'success',
                    'text'  => 'A perfect 20 nt match returns ZERO hits on a default search. The '
                             . 'word size is too big to seed and the E-value throws the score '
                             . 'away. The preset fixes both.',
                ],
                [
                    'label' => 'It is not only for primers',
                    'text'  => 'In-situ and qPCR probes, CRISPR guide RNAs, siRNA targets, '
                             . 'sequencing adapters, sample barcodes, cloning sites and tags all '
                             . 'behave the same way.',
                ],
                [
                    'label' => 'Checking a primer PAIR? Use Primer BLAST',
                    'color' => 'success',
                    'html'  => true,
                    'text'  => 'It pairs the hits, keeps only those facing each other, reports '
                             . 'every product size, searches genome and transcriptome together, '
                             . 'and draws the products in the browser.<br>'
                             . '<a href="' . htmlspecialchars($bp_site . '/tools/primer_blast.php')
                             . '" class="btn btn-sm btn-tool-orange mt-2">'
                             . '<i class="fa fa-vials me-1"></i>Open Primer BLAST</a>',
                ],
                [
                    'label' => 'Stay here for a single oligo',
                    'text'  => 'Primer BLAST needs a pair. To find where one probe or guide RNA '
                             . 'lands, and how many times, this page is the right tool.',
                ],
            ],
        ],
    ],
    [
        'intro' => 'Six programs, and the difference is only ever two things: what you paste, '
                 . 'and what it is compared against.',
    ]
);
