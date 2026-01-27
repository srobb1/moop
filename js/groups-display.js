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

// Track which organisms are selected (default: all selected)
let selectedOrganisms = [...groupOrganisms];

// Initialize organism selection checkboxes
function initOrganismSelection() {
    // Handle individual organism checkboxes
    $(document).on('change', '.organism-checkbox', function() {
        const organism = $(this).data('organism');
        const isChecked = $(this).is(':checked');
        
        if (isChecked && !selectedOrganisms.includes(organism)) {
            selectedOrganisms.push(organism);
        } else if (!isChecked && selectedOrganisms.includes(organism)) {
            selectedOrganisms = selectedOrganisms.filter(o => o !== organism);
        }
        
        // Update search manager with selected organisms
        updateSearchManager();
    });
    
    // Handle "Select All" button
    $('#selectAllOrganisms').on('click', function() {
        selectedOrganisms = [...groupOrganisms];
        $('.organism-checkbox').prop('checked', true);
        updateSearchManager();
    });
    
    // Handle "Deselect All" button
    $('#deselectAllOrganisms').on('click', function() {
        selectedOrganisms = [];
        $('.organism-checkbox').prop('checked', false);
        updateSearchManager();
    });
}

// Update search manager with currently selected organisms
function updateSearchManager() {
    searchManager.config.organismsVar = selectedOrganisms;
    searchManager.config.totalVar = selectedOrganisms.length;
}

const searchManager = new AnnotationSearch({
    formSelector: '#groupSearchForm',
    organismsVar: selectedOrganisms,
    totalVar: selectedOrganisms.length,
    hideSections: ['#groupDescription', '#organismsSection'],
    scrollToResults: false,
    extraAjaxParams: {group: groupName},
    noReadMoreButton: false
});

searchManager.init();

// Initialize organism selection after page loads
$(document).ready(function() {
    initOrganismSelection();
});

