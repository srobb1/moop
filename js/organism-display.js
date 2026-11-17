/**
 * Organism Display Page Logic
 * Handles search functionality for a single organism's annotations
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - sitePath: the site path prefix
 * - organismName: the organism name
 */

$(document).ready(function() {
    const searchManager = new AnnotationSearch({
        formSelector: '#organismSearchForm',
        organismsVar: [organismName],
        totalVar: 1,
        hideSections: ['#organismHeader', '#organismContent'],
        scrollToResults: false,
        noReadMoreButton: true,
        // What this organism holds, from PHP. Lets the note say what is being searched at
        // page load; the scope filter only learns the same numbers after its AJAX fetch.
        scopeCounts: (typeof scopeCounts !== 'undefined') ? scopeCounts : null,
        noScopeFilter: true,   // no pop-up tree: "Refine selection" narrows, when there is a choice
        // With more than one gene set the "Refine selection" bar says what is searched; with
        // one, the line under the box does ("Searching this organism's only gene set").
        noOrganismNote: typeof organismRefine !== 'undefined' && organismRefine,
    });

    searchManager.init();

    // "Refine selection" (2026-11-16, replaces the pop-up tree): the gene sets unticked there
    // become AnnotationSearch's selectedScope exclusions.
    if (typeof organismRefine !== 'undefined' && organismRefine && typeof initScopeSelector === 'function') {
        const refine = initScopeSelector({
            onChange: () => { searchManager.selectedScope = refine ? refine.scope() : null; },
        });
    }
});
