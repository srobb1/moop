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
         . '<i class="fa fa-arrow-down me-1" aria-hidden="true"></i>' . t('See below for more info.')
         . '</span>';

// Card text that carries markup (a program name in <code>, a glossary word) is written here as
// ONE translatable sentence with the markup passed in: t_markup('%s — …', $c('BLASTn')). The
// helper does not translate an 'html' card itself -- a translation is never trusted to carry
// markup -- so the sentence is translated here, before it gets there.
$c = fn(string $name) => '<code>' . htmlspecialchars($name) . '</code>';

// One card per program. The query→database line carries the mechanical fact and
// the sentence carries the judgement, so a reader scanning for "which one takes
// a protein" never has to read prose to find out.
$program_cards = [];
foreach (blast_programs() as $prog) {
    $program_cards[] = [
        'label' => $prog['label'],
        'color' => $prog['color'] ?? '',
        'html'  => true,
        'text'  => '<code>' . t($prog['query']) . ' &rarr; '
                 . t($prog['db']) . '</code><br>'
                 . t($prog['when']),
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
        'label' => 'A program is grayed out',
        'html'  => true,
        'text'  => t('Not a missing database and not a permission — it wants the OTHER kind of sequence than the one you pasted.')
                 . ' ' . t_markup('%1$s and %2$s need protein; the rest need DNA.', $c('tBLASTn'), $c('BLASTp')),
    ],
    [
        'label' => 'Fewer databases than I expected',
        'html'  => true,
        'text'  => t_markup('The list follows your program: %1$s and %2$s leave the protein databases, the rest leave the DNA ones.', $c('BLASTp'), $c('BLASTx'))
                 . ' ' . t('It is stopping you pairing them wrongly.'),
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
                    'text'  => t_markup('%s — both sides are DNA, and within a species the DNA still matches.', $c('BLASTn')),
                ],
                [
                    'label' => 'Find my gene (nucleotide) in ANOTHER species\' genome',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => t_markup('%s — your DNA and the genome are both translated, so the comparison happens in protein, where the similarity survives.', $c('tBLASTx'))
                             . ' ' . t('Slow: narrow the database first.'),
                ],
                [
                    'label' => 'Find my gene (amino acids) in ANOTHER species\' genome',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => t_markup('%s — much faster than tBLASTx and the better choice whenever the protein is to hand.', $c('tBLASTn'))
                             . ' ' . t('Works on genomes with no gene annotation at all.'),
                ],
                [
                    'label' => 'Work out what my transcript codes for',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => t_markup('%s — translates your DNA and searches proteins.', $c('BLASTx')),
                ],
                [
                    'label' => 'Find relatives of a protein',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => t_markup('%1$s against proteins, or %2$s against a genome if the protein set is missing or incomplete.', $c('BLASTp'), $c('tBLASTn')),
                ],
                [
                    'label' => 'Locate a primer, probe or guide RNA',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => t_markup('%s — anything under about 30 nt.', $c('BLASTn-short'))
                             . ' ' . t('The ordinary settings throw these away.')
                             . $bp_more,
                ],
                [
                    'label' => 'Check a primer PAIR',
                    'color' => 'info',
                    'html'  => true,
                    'text'  => t('Not this page — Primer BLAST pairs the hits and reports every product size for you.')
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
                    // Per program, not "both reasons": BLASTn seeds from 11 letters and DOES
                    // find a 20 nt match, then the 1e-3 default discards it; only Megablast
                    // (word 28) cannot seed it at all. Text review N8, 2026-11-02, measured.
                    'text'  => t('A perfect 20 nt match can return ZERO hits on an ordinary search, for a different reason in each program.')
                             . ' ' . t_markup('%s finds it (it starts from 11 matching letters), then the default E-value of 1e-3 throws it away as not significant.', '<strong>BLASTn</strong>')
                             . ' ' . t_markup('%s never finds it: it needs 28 identical letters in a row, more than the query has.', '<strong>Megablast</strong>')
                             . ' ' . t('BLASTn-short fixes both.'),
                    'html'  => true,
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
                    'text'  => t('It pairs the hits, keeps only those facing each other, reports every product size, searches genome and transcriptome together, and draws the products in the browser.')
                             . ' ' . t('It needs a PAIR — to locate a single oligo, stay on this page.')
                             . (moop_tool_on('primer_blast')   // switched off: no button to it
                                 ? '<br><a href="' . htmlspecialchars($bp_site . '/tools/primer_blast.php')
                                   . '" class="btn btn-sm btn-tool-orange mt-2">'
                                   . '<i class="fa fa-vials me-1"></i>' . t('Open Primer BLAST') . '</a>'
                                 : ''),
                ],
            ],
        ],
    ],
    [
        'intro' => 'Paste your sequence first — the page detects DNA or protein and disables the '
                 . 'programs that cannot take it, so the list narrows itself before you choose.',
    ]
);
