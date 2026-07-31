/**
 * Assembly Display Page Logic
 * Handles search functionality for a single assembly's annotations
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - sitePath: the site path prefix
 * - organismName: the organism name
 * - assemblyAccession: the assembly accession
 */

// Initialize search instructions handler

const searchManager = new AnnotationSearch({
    formSelector: '#assemblySearchForm',
    organismsVar: [organismName],
    totalVar: 1,
    // #assemblyGeneSets now sits INSIDE #assemblyHeader (the gene set chips moved into the
    // overview grid), so hiding the header hides it too. Listing both would be a stale
    // selector waiting to be wrong.
    hideSections: ['#assemblyHeader'],
    scrollToResults: false,
    extraAjaxParams: {assembly: assemblyAccession},
    noReadMoreButton: true,
    noScopeFilter: true,
    // What this assembly holds, from PHP. There is no scope filter here, so the note points
    // at the Gene Sets list on the page instead of a modal that does not exist.
    scopeCounts: (typeof scopeCounts !== 'undefined') ? scopeCounts : null,
    scopeNoteTarget: '#assemblyGeneSets',
    scopeNoun: 'assembly'
});

searchManager.init();
