/**
 * Gene Set Display Page Logic
 * Handles annotation search scoped to a single gene set.
 *
 * Expects these variables defined by the PHP controller:
 * - sitePath, organismName, assemblyAccession, geneSetName
 */

initializeSearchInstructionsHandler();

const searchManager = new AnnotationSearch({
    formSelector: '#geneSetSearchForm',
    organismsVar: [organismName],
    totalVar: 1,
    hideSections: ['#geneSetHeader', '#geneSetStats'],
    scrollToResults: false,
    extraAjaxParams: {assembly: assemblyAccession, gene_set: geneSetName},
    noReadMoreButton: true,
    noScopeFilter: true
});

searchManager.init();
