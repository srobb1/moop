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
    
    // Handle organism instructions info icon click
    $(document).on('click', '.organism-instructions-trigger', function(e) {
        e.stopPropagation();
        const instruction = $(this).data('instruction');
        showInstructionModal(instruction);
    });
    
    // Initialize search instructions handler
    initializeSearchInstructionsHandler();
});

/**
 * Show instruction modal (for organism selection tips)
 */
function showInstructionModal(instruction) {
    const modalHtml = `
        <div class="modal fade" id="instructionModal" tabindex="-1">
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
    $('#instructionModal').remove();
    
    // Add and show modal
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('instructionModal'));
    modal.show();
}

