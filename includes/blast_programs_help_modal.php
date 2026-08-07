<?php
/**
 * Help for choosing a BLAST program.
 *
 * Ordered the way a new user arrives at it: what am I trying to DO, then which
 * program does that, then the special case. Not the other way round — a reader
 * who already knew the program names would not have opened this.
 *
 * The program cards are GENERATED from blast_programs() (lib/blast_functions.php),
 * the same array the dropdown renders from. That is the point: the option text
 * and its explanation cannot drift apart, because there is only one of them.
 *
 * Kept out of the general BLAST help because this answers a question asked
 * BEFORE searching, and picking wrong returns an empty result that reads as
 * "not in this genome" rather than "wrong tool".
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

// Gotchas belong WITH the programs, not before them: they only make sense once
// you know there are six things to choose between.
$program_cards[] = [
    'label' => 'Translated means six frames',
    'html'  => true,
    'text'  => '<code>BLASTx</code>, <code>tBLASTn</code> and <code>tBLASTx</code> convert DNA '
             . 'to protein in all six reading frames, so you never have to know the frame.',
];
$program_cards[] = [
    'label' => 'Greyed out means wrong query type',
    'html'  => true,
    'text'  => 'Not a missing database and not a permission — that program wants the OTHER kind '
             . 'of sequence. <code>tBLASTn</code> and <code>BLASTp</code> need protein; the rest '
             . 'need DNA.',
];
$program_cards[] = [
    'label' => 'The database list follows the program',
    'html'  => true,
    'text'  => 'Pick a program and only the databases it can search remain. '
             . '<code>BLASTp</code> and <code>BLASTx</code> leave the protein databases; the rest '
             . 'leave the DNA ones. You cannot pair them wrongly by accident.',
];
$program_cards[] = [
    'label' => '"No compatible databases"',
    'text'  => 'Means this assembly has none of the type your program needs — commonly a '
             . 'transcriptome with no protein set. Change program, or pick another source.',
];

echo help_modal(
    'blast-programs-help',
    'Choosing a BLAST program',
    [
        [
            // Tasks first. A reader opens this because they have a job, not
            // because they want to compare six algorithms — so the job is the
            // way in, and every card ends at a program name.
            'heading' => 'What do you want to do?',
            'cards' => [
                [
                    'label' => 'ACROSS SPECIES? Search protein',
                    'color' => 'success',
                    'html'  => true,
                    'text'  => 'The rule behind most of the cards below. Protein stays conserved '
                             . 'where DNA has long since drifted, so never compare DNA to DNA '
                             . 'between organisms — go through protein with <code>BLASTx</code>, '
                             . '<code>tBLASTn</code> or <code>tBLASTx</code>.',
                ],
                [
                    'label' => 'Find my gene in the SAME species',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTn</code> — both sides are DNA, and within a species '
                             . 'the DNA still matches.',
                ],
                [
                    'label' => 'Find my gene in ANOTHER species\' genome',
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
                    'label' => 'Work out what my transcript codes for',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTx</code> — translates your DNA and searches proteins.',
                ],
                [
                    'label' => 'Find relatives of a protein',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTp</code> against proteins, or <code>tBLASTn</code> '
                             . 'against a genome if the protein set is missing or incomplete.',
                ],
                [
                    'label' => 'Locate a primer, probe or guide RNA',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTn-short</code> — anything under about 30 nt. See the '
                             . 'last section; the ordinary settings throw these away.',
                ],
                [
                    'label' => 'Check a primer PAIR',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => 'Not this page — Primer BLAST pairs the hits and reports product '
                             . 'sizes. Link in the last section.',
                ],
                [
                    'label' => 'I found nothing and expected a match',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => 'If you searched DNA against DNA across species, that is why — go '
                             . 'through protein instead. For a short query, the cause is almost '
                             . 'always the wrong program rather than a missing match.',
                ],
            ],
        ],
        [
            'heading' => 'The six programs',
            'cards' => $program_cards,
        ],
        [
            'heading' => 'Short sequences, and primer pairs',
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
                    'label' => 'Expect a lot of hits',
                    'text'  => 'The permissive threshold is doing its job. Sort by identity and '
                             . 'length: a usable site is full-length and 100%.',
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
        'intro' => 'Paste your sequence first — the page detects DNA or protein and disables the '
                 . 'programs that cannot take it, so the list narrows itself before you choose.',
    ]
);
