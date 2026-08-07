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

// Both short-sequence use cases hand off to the last section rather than repeating
// it. One string so the two cards close identically — a pointer phrased two ways
// reads as two different promises.
$bp_more = '<br><span class="d-inline-block mt-1 fw-semibold">'
         . '<i class="fa fa-arrow-down me-1" aria-hidden="true"></i>See below for more info.'
         . '</span>';

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

// Their own section, because they are SYMPTOMS. A reader meets these with
// something already looking wrong on the page, and scans for the symptom — so
// burying them after six reference cards is the wrong place, and it also left
// the heading "The six programs" sitting above ten cards.
//
// There is deliberately no "translated means six frames" card: every program
// whose answer that is already says so in its own text above, and a card that
// repeats the cards above it is the duplication this modal keeps growing.
$trouble_cards = [
    [
        'label' => 'A program is greyed out',
        'html'  => true,
        'text'  => 'Not a missing database and not a permission — it wants the OTHER kind of '
                 . 'sequence than the one you pasted. <code>tBLASTn</code> and '
                 . '<code>BLASTp</code> need protein; the rest need DNA.',
    ],
    [
        'label' => 'Fewer databases than I expected',
        'html'  => true,
        'text'  => 'The list follows your program: <code>BLASTp</code> and <code>BLASTx</code> '
                 . 'leave the protein databases, the rest leave the DNA ones. It is stopping you '
                 . 'pairing them wrongly.',
    ],
    [
        'label' => '"No compatible databases"',
        'text'  => 'This assembly holds none of the type your program needs — commonly a '
                 . 'transcriptome with no protein set. Change program, or pick another source.',
    ],
    [
        'label' => 'I found nothing and expected a match',
        'text'  => 'If you searched DNA against DNA across species, that is why — go through '
                 . 'protein instead. For a short query, it is almost always the wrong program '
                 . 'rather than a missing match.',
    ],
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
                    // States the RULE and stops. It used to end by naming BLASTx, tBLASTn
                    // and tBLASTx together — which pointed a reader holding DNA at
                    // tBLASTn, a program the page greys out for exactly that reader.
                    // Which program the rule means depends on what you have, and that is
                    // what the next three cards are for.
                    'label' => 'ACROSS SPECIES? Search protein',
                    'color' => 'success',
                    'text'  => 'The rule behind the cards below. Protein stays conserved where '
                             . 'DNA has long since drifted, so never compare DNA to DNA between '
                             . 'organisms. Which program that means depends on what you have — '
                             . 'the next three cards say which.',
                ],
                [
                    'label' => 'Find my gene (nucleotide) in the SAME species',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>BLASTn</code> — both sides are DNA, and within a species '
                             . 'the DNA still matches.',
                ],
                [
                    'label' => 'Find my gene (nucleotide) in ANOTHER species\' genome',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>tBLASTx</code> — your DNA and the genome are both '
                             . 'translated, so the comparison happens in protein, where the '
                             . 'similarity survives. Slow: narrow the database first.',
                ],
                [
                    'label' => 'Find my gene (amino acids) in ANOTHER species\' genome',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => '<code>tBLASTn</code> — much faster than tBLASTx and the better '
                             . 'choice whenever the protein is to hand. Works on genomes with no '
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
                    'text'  => '<code>BLASTn-short</code> — anything under about 30 nt. The '
                             . 'ordinary settings throw these away.'
                             . $bp_more,
                ],
                [
                    'label' => 'Check a primer PAIR',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => 'Not this page — Primer BLAST pairs the hits and reports every '
                             . 'product size for you.'
                             . $bp_more,
                ],
            ],
        ],
        [
            // Exactly the six, so the heading names a count a reader can check.
            'heading' => 'The six programs',
            'cards' => $program_cards,
        ],
        [
            'heading' => 'If something looks wrong',
            'cards' => $trouble_cards,
        ],
        [
            'heading' => 'Short sequences, and primer pairs',
            'cards' => [
                // Only what the use-case cards above do NOT already carry: WHY the default
                // fails, what a good result looks like, and the handoff. "It is not only
                // for primers" and "Stay here for a single oligo" were cut — the task
                // cards and the BLASTn-short program card already say both, and repeating
                // them is what made the earlier "Start here" section redundant.
                [
                    'label' => 'Why the default throws them away',
                    'color' => 'success',
                    'text'  => 'A perfect 20 nt match returns ZERO hits on an ordinary search. '
                             . 'The word size is too big for it to seed, and the E-value discards '
                             . 'the score as unremarkable. The preset fixes both.',
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
                             . 'and draws the products in the browser. It needs a PAIR — to '
                             . 'locate a single oligo, stay on this page.<br>'
                             . '<a href="' . htmlspecialchars($bp_site . '/tools/primer_blast.php')
                             . '" class="btn btn-sm btn-tool-orange mt-2">'
                             . '<i class="fa fa-vials me-1"></i>Open Primer BLAST</a>',
                ],
            ],
        ],
    ],
    [
        'intro' => 'Paste your sequence first — the page detects DNA or protein and disables the '
                 . 'programs that cannot take it, so the list narrows itself before you choose.',
    ]
);
