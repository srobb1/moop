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

/**
 * Handle tool button clicks on the group page.
 * POSTs the live selectedOrganisms list to the tool URL in a new tab,
 * so unchecked organisms are excluded and URL length is never an issue.
 */
function handleToolClick(toolId) {
    if (!selectedOrganisms || selectedOrganisms.length === 0) {
        alert('Please select at least one organism');
        return;
    }

    const toolBtn = document.getElementById(`tool-btn-${toolId}`);
    if (!toolBtn) return;

    const toolPath = toolBtn.getAttribute('data-tool-path');
    if (!toolPath) return;

    const siteName = (typeof sitePath !== 'undefined' ? sitePath : window.sitePath).replace(/^\//,'').split('/')[0];

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/${siteName}${toolPath}`;
    form.target = '_blank';

    selectedOrganisms.forEach(org => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'organisms[]';
        input.value = org;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
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

