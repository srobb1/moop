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
        scopeCounts: (typeof scopeCounts !== 'undefined') ? scopeCounts : null
    });

    searchManager.init();
});
