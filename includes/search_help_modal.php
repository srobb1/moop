<?php
/**
 * Reusable "How to search" modal — the help behind the (i) on the search box.
 *
 * ONE home for it, included by every page with a search box: search, organism, assembly,
 * gene_set, groups, multi_organism and moopmart. Before this it came from a chain of
 * concatenated strings in js/modules/help-text.js (BASIC_SEARCH_HELP + ORGANISM_SELECTION_INFO
 * + RESULTS_LIMIT + ASSEMBLY_SEARCH_INFO + FILTERING_INFO), assembled per page and patched
 * mid-sentence with .replace().
 *
 * That assembly had a silent bug: the .replace() calls targeted the substring
 * "when searching from group or multi-organism pages", which does not appear anywhere in
 * ORGANISM_SELECTION_INFO — so they were no-ops and the 'group' and 'multiOrganism' variants
 * rendered byte-identical text. The per-page tailoring the code appeared to do had never
 * worked. Structured cards make that class of failure visible instead of silent: a card is
 * either in the array or it is not.
 *
 * ---------------------------------------------------------------------------
 * WHERE THE LINE SITS AGAINST THE RESULTS MODAL (non-redundancy — read before adding)
 *
 *   THIS modal      = composing a query.        Words, quotes, what gets searched, scope.
 *   results modal   = reading what came back.   Counts, the cap, ranking, views, export.
 *
 * Query-composition help lived in BOTH before this split. Do not restate a card from one in
 * the other — point at it instead. The same explanation in two places drifts, and a reader
 * cannot tell which copy is lying.
 *
 * ---------------------------------------------------------------------------
 * USAGE
 *
 *   $search_help_scope = 'multi';   // 'single' (default) or 'multi'
 *   include __DIR__ . '/../../includes/search_help_modal.php';
 *
 * 'multi' is for pages that search several organisms at once (groups, multi_organism) —
 * they get an organism-selection card and a "per organism" phrasing of the cap. Everything
 * else is identical, which is why this is one file and not three.
 *
 * Trigger it with help_modal_trigger('search-help', '', 'How to search').
 */

$scope = ($search_help_scope ?? 'single') === 'multi' ? 'multi' : 'single';
$cap   = number_format(moop_search_results_limit());

// Scope section varies; everything above it is identical on every page.
$scope_cards = [];
if ($scope === 'multi') {
    $scope_cards[] = [
        'label' => 'Choose your organisms',
        'text'  => 'Use the checkboxes to include or exclude organisms before you search. '
                 . 'Fewer organisms returns faster.',
    ];
    $scope_cards[] = [
        'label' => 'Result limit',
        'text'  => 'Each organism returns at most ' . $cap . ' results, so a broad term across '
                 . 'many organisms can hit the limit in several of them at once.',
    ];
} else {
    $scope_cards[] = [
        'label' => 'Search a single assembly',
        'text'  => 'From an organism page, open one of its assemblies to restrict the search '
                 . 'to that assembly alone.',
    ];
    $scope_cards[] = [
        'label' => 'Result limit',
        'text'  => 'A search returns at most ' . $cap . ' results. Exactly ' . $cap . ' means '
                 . 'there are probably more — add another term to narrow it.',
    ];
}

echo help_modal(
    'search-help',
    'How to search',
    [
        [
            'heading' => 'How your words are matched',
            'cards'   => [
                [
                    'label' => 'One word',
                    'text'  => 'Finds every record containing it. Example: <code>kinase</code>',
                    'html'  => true,
                ],
                [
                    'label' => 'Several words',
                    'text'  => 'All of them must appear, in any order and anywhere in the text. '
                             . '<code>kinase domain</code> finds records containing both.',
                    'html'  => true,
                ],
                [
                    'label' => 'An exact phrase',
                    'text'  => 'Put it in quotes. <code>"ABC transporter"</code> matches that '
                             . 'phrase only, not the two words apart.',
                    'html'  => true,
                ],
                [
                    'label' => 'Short words are skipped',
                    'text'  => 'Terms under three characters are ignored, so <code>P53 tumor</code> '
                             . 'searches only for <em>tumor</em>. Quote it — <code>"P53"</code> — '
                             . 'to search it anyway.',
                    'html'  => true,
                ],
            ],
        ],
        [
            'heading' => 'What gets searched',
            'cards'   => [
                [
                    'label' => 'Gene and transcript IDs first',
                    'text'  => 'A single term is checked against feature identifiers first. If it '
                             . 'matches one, those are your results.',
                ],
                [
                    'label' => 'Then annotations',
                    'text'  => 'If no identifier matches — or you typed more than one word — the '
                             . 'search runs across annotation descriptions and related fields.',
                ],
                [
                    'label' => 'Limit which annotations',
                    'text'  => 'The filter button restricts the search to chosen annotation types, '
                             . 'for example only Ensembl human homologs.',
                ],
            ],
        ],
        [
            'heading' => 'Scope and limits',
            'cards'   => $scope_cards,
        ],
    ],
    ['intro' => 'How MOOP reads what you type, and what it looks at.']
);
