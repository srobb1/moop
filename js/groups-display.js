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

let selectedOrganisms = [];

// Update search manager with currently selected organisms
function updateGroupSearchManager(newSelectedOrganisms) {
    selectedOrganisms = newSelectedOrganisms;
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
    // Initialize organism selection with callback to update search manager
    selectedOrganisms = initializeOrganismSelection(groupOrganisms, updateGroupSearchManager);
    
    // Handle organism instructions info icon click
    $(document).on('click', '.organism-instructions-trigger', function(e) {
        e.stopPropagation();
        const instruction = $(this).data('instruction');
        showGroupInstructionModal(instruction);
    });
    
    // Initialize search instructions handler
    initializeSearchInstructionsHandler();
});

/**
 * Show group instruction modal (for organism selection tips)
 * Uses different modal ID to avoid conflict with search tips
 */
function showGroupInstructionModal(instruction) {
    const modalHtml = `
        <div class="modal fade" id="groupInstructionModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Instructions</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${instruction}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if present
    $('#groupInstructionModal').remove();
    
    // Add and show modal
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('groupInstructionModal'));
    modal.show();
}

