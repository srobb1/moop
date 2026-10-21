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

// Toolbox buttons: the live selection, via the shared js/modules/tool-launch.js.
function handleToolClick(toolId) {
    postSelectionToTool(toolId, selectedOrganisms);
}

// Update search manager with currently selected organisms
function updateGroupSearchManager(newSelectedOrganisms) {
    selectedOrganisms = newSelectedOrganisms;
    searchManager.config.organismsVar = selectedOrganisms;
    searchManager.config.totalVar = selectedOrganisms.length;
    searchManager.updateOrganismNote();
}

// Initialize organism selection after page loads
$(document).ready(function() {
    // Initialize organism selection with callback to update search manager
    // This returns the array of currently selected organisms and sets up event listeners
    const initialSelected = initializeOrganismSelection(groupOrganisms, updateGroupSearchManager);
    selectedOrganisms = initialSelected;
    
    // Initialize search manager with selected organisms
    searchManager = new AnnotationSearch({
        formSelector: '#groupSearchForm',
        organismsVar: selectedOrganisms,
        totalVar: selectedOrganisms.length,
        hideSections: ['#groupDescription', '#organismsSection'],
        scrollToResults: false,
        extraAjaxParams: {group: groupName},
        noReadMoreButton: false
    });
    
    searchManager.init();
    
    
});

