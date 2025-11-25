/**
 * Organism Display Page Logic
 * Handles search functionality for a single organism's annotations
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - sitePath: the site path prefix
 * - organismName: the organism name
 */

const searchManager = new AnnotationSearch({
    formSelector: '#organismSearchForm',
    organismsVar: [organismName],
    totalVar: 1,
    hideSections: ['#organismHeader', '#organismContent'],
    scrollToResults: false,
    noReadMoreButton: true
});

searchManager.init();

