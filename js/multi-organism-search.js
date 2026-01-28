/**
 * Multi-Organism Search Page Logic
 * Handles search functionality for searching annotations across multiple selected organisms
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - selectedOrganisms: array of organism names to search
 * - totalOrganisms: number of organisms to search
 * - sitePath: the site path prefix
 */

// Handle search instructions info icon click
$(document).on('click', '.search-instructions-trigger', function(e) {
    e.stopPropagation();
    const instruction = $(this).data('instruction');
    showInstructionModal(instruction);
});

/**
 * Show instruction modal
 */
function showInstructionModal(instruction) {
    const modalHtml = `
        <div class="modal fade" id="instructionModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Search Tips</h5>
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

const searchManager = new AnnotationSearch({
    formSelector: '#multiOrgSearchForm',
    organismsVar: selectedOrganisms,
    totalVar: totalOrganisms,
    scrollToResults: true,
    noReadMoreButton: false
});

searchManager.init();

