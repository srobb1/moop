/**
 * Groups Display Page Logic
 * Handles search functionality for searching annotations across all organisms in a group
 * Supports selecting a subset of organisms in the group for targeted searches
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - groupOrganisms: array of organism names in this group
 * - groupName: the group name
 * - sitePath: the site path prefix
 */

let searchManager;
let selectedOrganisms;
let groupScope;   // the "Refine selection" selector (js/modules/scope-selector.js)

// Toolbox buttons: the live selection, via the shared js/modules/tool-launch.js.
function handleToolClick(toolId) {
    postSelectionToTool(toolId, selectedOrganisms);
}

// "Refine selection" decides what the search covers: the organisms with something selected,
// and, when some of their gene sets are unticked, those exclusions (AnnotationSearch's
// selectedScope). Until 2026-11-16 that was split between the "In search" bars on the organism
// cards and a pop-up tree; the cards are now for browsing only.
function onGroupScopeChange() {
    selectedOrganisms = groupScope ? groupScope.organisms() : groupOrganisms.slice();
    if (!searchManager) return;
    searchManager.config.organismsVar = selectedOrganisms;
    searchManager.config.totalVar = selectedOrganisms.length;
    searchManager.selectedScope = groupScope ? groupScope.scope() : null;
}

$(document).ready(function() {
    groupScope = initScopeSelector({ onChange: onGroupScopeChange });
    selectedOrganisms = groupScope ? groupScope.organisms() : groupOrganisms.slice();

    searchManager = new AnnotationSearch({
        formSelector: '#groupSearchForm',
        organismsVar: selectedOrganisms,
        totalVar: selectedOrganisms.length,
        hideSections: ['#groupDescription', '#organismsSection'],
        scrollToResults: false,
        extraAjaxParams: {group: groupName},
        noReadMoreButton: false,
        noScopeFilter: true,     // no pop-up tree: "Refine selection" does that
        noOrganismNote: true,    // the "Refine selection" bar says what is searched
    });
    searchManager.init();
    onGroupScopeChange();

    // The organism cards' own filter (browsing only; it does not change the search).
    $(document).on('input', '#organismFilter', function() {
        const q = $(this).val().toLowerCase().trim();
        $('.organism-card-col').each(function() {
            const text = $(this).data('filter-text') || '';
            $(this).toggle(q === '' || text.includes(q));
        });
    });
});
