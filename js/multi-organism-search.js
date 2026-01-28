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

// Update search manager with currently selected organisms
function updateMultiSearchManager(newSelectedOrganisms) {
    selectedOrganisms = newSelectedOrganisms;
    searchManager.config.organismsVar = selectedOrganisms;
    searchManager.config.totalVar = selectedOrganisms.length;
}

const searchManager = new AnnotationSearch({
    formSelector: '#multiOrgSearchForm',
    organismsVar: selectedOrganisms,
    totalVar: totalOrganisms,
    scrollToResults: true,
    noReadMoreButton: false
});

searchManager.init();

// Initialize organism selection after page loads
$(document).ready(function() {
    // Initialize organism selection with callback to update search manager
    selectedOrganisms = initializeOrganismSelection(allOrganisms, updateMultiSearchManager);
    
    // Initialize search instructions handler
    initializeSearchInstructionsHandler();
});

