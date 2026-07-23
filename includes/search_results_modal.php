<?php
/**
 * Reusable "Understanding your search results" modal.
 *
 * Included by every page that renders a results table, so the explanation has ONE home
 * — search, organism, assembly, gene_set, groups and multi_organism all showed the same
 * help before this, but from a 446-word string in js/modules/help-text.js that covered
 * ten unrelated subjects in a single scroll.
 *
 * Understanding results — and knowing how to make them more useful — is the most
 * important thing on this site, so this help is deliberately NOT thin. It is organised
 * instead: five sections by what the reader is trying to do, short cards inside each,
 * sticky section headings so a long modal stays navigable. Someone who only wants to
 * know why the two counts differ finds it in seconds instead of reading a page of prose.
 *
 * The result cap is read from configuration rather than written into the text, so an
 * admin changing Site Configuration -> Search Results Limit can never leave this help
 * quoting a number the search no longer uses.
 *
 * Trigger it with help_modal_trigger('search-results-help').
 */

$cap = number_format(moop_search_results_limit());

echo help_modal(
    'search-results-help',
    'Understanding your search results',
    [
        [
            'heading' => 'Reading your results',
            'cards'   => [
                [
                    'label' => 'Feature count',
                    'text'  => 'How many unique sequences matched — genes, mRNAs or proteins. '
                             . 'Each one is a single row in Simple view.',
                ],
                [
                    'label' => 'Annotation match count',
                    'text'  => 'How many annotations matched. A feature with three matching '
                             . 'annotations adds three, which is why this number is usually the '
                             . 'larger of the two.',
                ],
                [
                    'label' => 'Why the two differ',
                    'text'  => 'Find 5 genes where one has 3 matching annotations and another has '
                             . '2, and you see 5 features and 7 annotation matches.',
                ],
                [
                    'label' => 'Result limit',
                    'text'  => 'A search returns at most ' . $cap . ' results per organism. Exactly '
                             . $cap . ' means there are probably more — narrow the search to reach them.',
                ],
            ],
        ],
        [
            // Narrowing what is ALREADY on screen. How to compose a better query — quoting,
            // short terms, which fields are searched — belongs to the search-box modal
            // (includes/search_help_modal.php) and is deliberately not restated here.
            'heading' => 'Narrowing to what you want',
            'cards'   => [
                [
                    'label' => 'Filter any column',
                    'text'  => 'The boxes above each column header filter the rows you already have. '
                             . 'Combine several to narrow further.',
                ],
                [
                    'label' => 'Search fewer organisms',
                    'text'  => 'Every selected organism can contribute up to the limit. Fewer '
                             . 'organisms gives each one more room, and returns faster.',
                ],
                [
                    'label' => 'Or change the search itself',
                    'text'  => 'Too many results usually means the term is too broad. The (i) on the '
                             . 'search box explains quoting, multi-word terms and annotation filters.',
                ],
            ],
        ],
        [
            'heading' => 'Ordering by what matters',
            'cards'   => [
                [
                    'label' => 'Sorted by significance',
                    'text'  => 'By default the best matches come first: name and description '
                             . 'matches rank above annotation matches, exact above partial, and '
                             . 'the start of a word above the middle of one.',
                ],
                [
                    'label' => 'Re-sort by any column',
                    'text'  => 'Click a column header to sort by it instead — most useful after '
                             . 'filtering, to re-rank whatever is left.',
                ],
            ],
        ],
        [
            'heading' => 'Seeing more detail',
            'cards'   => [
                [
                    'label' => 'Simple view',
                    'text'  => 'One row per matching feature — what matched, with nothing repeated.',
                ],
                [
                    'label' => 'Expanded view',
                    'text'  => 'Every matching annotation for each feature, with your search terms '
                             . 'highlighted so you can see why it matched.',
                ],
                [
                    'label' => 'Expand all matches',
                    'text'  => 'Opens every row at once, rather than one at a time.',
                ],
                [
                    'label' => 'Open the full record',
                    'text'  => 'Click any gene or annotation link for that feature\'s own page — all '
                             . 'its annotations, its child features, and genome browser links.',
                ],
            ],
        ],
        [
            'heading' => 'Taking results with you',
            'cards'   => [
                [
                    'label' => 'Select the rows you want',
                    'text'  => 'Tick rows to export just those — you are not limited to exporting '
                             . 'the whole table.',
                ],
                [
                    'label' => 'Export formats',
                    'text'  => 'Copy, CSV, Excel, PDF or Print, from the buttons below the table.',
                ],
                [
                    'label' => 'Choose your columns',
                    'text'  => 'Column Visibility hides columns you do not need, and exports follow '
                             . 'whatever is visible.',
                ],
                [
                    'label' => 'Bulk downloads',
                    'text'  => 'For many features at once — sequences and annotation tables — use '
                             . 'MOOPmart rather than exporting search results.',
                ],
            ],
        ],
    ],
    ['intro' => 'What the results are telling you, and how to narrow them down to what you need.']
);
