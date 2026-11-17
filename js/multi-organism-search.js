/**
 * Multi-Organism Search Page Logic
 * Handles search functionality for searching annotations across multiple selected organisms
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - allOrganisms: array of all organisms available
 * - selectedOrganisms: initial array of selected organisms
 * - totalOrganisms: number of organisms to search
 * - sitePath: the site path prefix
 */

let refineScope;   // the "Refine selection" selector (js/modules/scope-selector.js)

// Toolbox buttons: the live selection, via the shared js/modules/tool-launch.js.
function handleToolClick(toolId) {
    postSelectionToTool(toolId, selectedOrganisms);
}

// "Refine selection" decides what the search covers: the organisms with something selected,
// plus any gene sets unticked under them (AnnotationSearch's selectedScope). Until 2026-11-16
// that was the cards' "In search" bars and a pop-up tree; the cards are now for browsing.
function onRefineChange() {
    selectedOrganisms = refineScope ? refineScope.organisms() : allOrganisms.slice();
    searchManager.config.organismsVar = selectedOrganisms;
    searchManager.config.totalVar = selectedOrganisms.length;
    searchManager.selectedScope = refineScope ? refineScope.scope() : null;
}

const searchManager = new AnnotationSearch({
    formSelector: '#multiOrgSearchForm',
    organismsVar: selectedOrganisms,
    totalVar: totalOrganisms,
    scrollToResults: true,
    noReadMoreButton: false,
    noScopeFilter: true,     // no pop-up tree: "Refine selection" does that
    noOrganismNote: true,    // the "Refine selection" bar says what is searched
});

searchManager.init();

$(document).ready(function() {
    refineScope = initScopeSelector({ onChange: onRefineChange });
    onRefineChange();

    // The organism cards' own filter (browsing only; it does not change the search).
    $(document).on('input', '#organismFilter', function() {
        const q = $(this).val().toLowerCase().trim();
        $('.organism-card-col').each(function() {
            const text = $(this).data('filter-text') || '';
            $(this).toggle(q === '' || text.includes(q));
        });
    });
});
