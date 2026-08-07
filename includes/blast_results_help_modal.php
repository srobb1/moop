<?php
/**
 * Help for reading BLAST results, and for tuning a search that returned too
 * much or too little.
 *
 * Separate from the "how to use BLAST" modal because it is opened at a
 * different moment: the results are already on screen, and the question is
 * either "what am I looking at" or "how do I get different ones". Both are
 * answered by controls that live back up the page in Advanced Options, which is
 * why the tuning cards name the control exactly as it is labelled there.
 *
 * Every field named below is one the results actually show (see
 * lib/blast_results_visualizer.php) or one the advanced panel actually offers
 * (tools/pages/blast.php) — not a general account of BLAST output.
 *
 * Requires lib/help_ui.php (loaded globally via config_init).
 */

if (!function_exists('help_modal')) {
    require_once __DIR__ . '/../lib/help_ui.php';
}

echo help_modal(
    'blast-results-help',
    'Reading and tuning your results',
    [
        [
            'heading' => 'What the numbers mean',
            'cards' => [
                [
                    'label' => 'E-value',
                    'color' => 'success',
                    'text'  => 'How many hits this good you would expect BY CHANCE. Smaller is '
                             . 'better: 1e-50 is strong, 1 is noise. It depends on database '
                             . 'size, so the same alignment scores differently against a bigger '
                             . 'one.',
                ],
                [
                    'label' => 'Query coverage',
                    'text'  => 'How much of YOUR sequence took part in the alignment. A 100% '
                             . 'identity hit covering 8% of your query is a shared domain, not '
                             . 'the same gene.',
                ],
                [
                    'label' => 'Subject coverage',
                    'text'  => 'The same, measured on the hit. Low subject coverage against a '
                             . 'long gene usually means you matched one exon or one domain.',
                ],
                [
                    'label' => 'Identity',
                    'text'  => 'Matching positions over the aligned length — shown per HSP, not '
                             . 'for the whole gene. Read it together with coverage or it flatters '
                             . 'a short alignment.',
                ],
                [
                    'label' => 'HSPs',
                    'text'  => 'High-scoring Segment Pairs: the separate stretches that aligned. '
                             . 'Several HSPs against a genome usually means exons split by '
                             . 'introns, which BLAST cannot cross.',
                ],
            ],
        ],
        [
            // The whole point of the section the user asked for: the results are
            // in front of them and the fix is a control further up the page.
            'heading' => 'Too few results?',
            'cards' => [
                [
                    'label' => 'Raise the E-value threshold',
                    'color' => 'info',
                    'text'  => 'The first thing to try. Going from 1e-5 to 1 or 10 keeps weaker '
                             . 'alignments that were being discarded. Advanced Options → E-value '
                             . 'Threshold.',
                ],
                [
                    'label' => 'Lower the word size',
                    'color' => 'info',
                    'text'  => 'A smaller word gives BLAST more places to start, so it finds '
                             . 'weaker similarity. Slower, and the main reason short queries need '
                             . 'their own preset.',
                ],
                [
                    'label' => 'Turn the low-complexity filter off',
                    'color' => 'info',
                    'text'  => 'Repeats and simple sequence are masked by default. If your query '
                             . 'is largely repeat, that masking is what removed your hits.',
                ],
                [
                    'label' => 'Or you are across species',
                    'color' => 'info',
                    'text'  => 'No threshold rescues a DNA-to-DNA search between organisms. That '
                             . 'needs a different PROGRAM, not a looser setting — see "Which '
                             . 'program?" at step 2.',
                ],
            ],
        ],
        [
            'heading' => 'Too many results?',
            'cards' => [
                [
                    'label' => 'Lower the E-value threshold',
                    'color' => 'info',
                    'text'  => 'From 10 down to 1e-5 or 1e-20 keeps only the strong alignments. '
                             . 'The usual first move when a page of weak hits buries the real one.',
                ],
                [
                    'label' => 'Set a percent identity floor',
                    'color' => 'info',
                    'text'  => 'Advanced Options → Percent Identity. Useful when you want close '
                             . 'relatives only and do not care about distant ones.',
                ],
                [
                    'label' => 'Cap the hits',
                    'color' => 'info',
                    'text'  => 'Maximum Hits limits how many are reported, and Culling Limit '
                             . 'drops hits buried under better ones at the same place.',
                ],
                [
                    'label' => 'A short query is meant to do this',
                    'color' => 'info',
                    'text'  => 'BLASTn-short is deliberately permissive. Do not tighten it — sort '
                             . 'by identity and length instead, and look for full-length 100% '
                             . 'sites.',
                ],
            ],
        ],
        [
            'heading' => 'Getting the data out',
            'cards' => [
                [
                    'label' => 'Downloads',
                    'text'  => 'TXT is the readable report, TSV is one row per hit for a '
                             . 'spreadsheet, and XML keeps the full structure for other software.',
                ],
                [
                    'label' => 'Follow a hit',
                    'text'  => 'Where the hit is a known feature, it links to its gene page and '
                             . 'into the genome browser at the matching coordinates.',
                ],
                [
                    'label' => 'Nothing at all came back',
                    'text'  => 'Check the program first, then the threshold. A wrong program '
                             . 'returns an empty result exactly like a genuinely absent gene, and '
                             . 'nothing warns you which happened.',
                ],
            ],
        ],
    ],
    [
        'intro' => 'The two questions this answers: what am I looking at, and how do I get more '
                 . 'or fewer of them. Every setting named here is in Advanced Options, back at '
                 . 'step 2.',
    ]
);
