/**
 * Assembly Display Page Logic
 * Handles search functionality for a single assembly's annotations
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - sitePath: the site path prefix
 * - organismName: the organism name
 * - assemblyAccession: the assembly accession
 */

const searchManager = new AnnotationSearch({
    formSelector: '#assemblySearchForm',
    organismsVar: [organismName],
    totalVar: 1,
    hideSections: ['#assemblyHeader', '#assemblyDownloads'],
    scrollToResults: false,
    extraAjaxParams: {assembly: assemblyAccession},
    noReadMoreButton: true
});

searchManager.init();
