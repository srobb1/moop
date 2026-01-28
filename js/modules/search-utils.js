/**
 * Shared Search Utilities
 * Common functions used across all search pages (organism, assembly, multi-organism, groups)
 */

/**
 * Initialize search instructions info icon handler
 * Call this on pages that have .search-instructions-trigger elements
 */
function initializeSearchInstructionsHandler() {
    $(document).on('click', '.search-instructions-trigger', function(e) {
        e.stopPropagation();
        const instruction = $(this).data('instruction');
        showSearchInstructionModal(instruction);
    });
}

/**
 * Show search instruction modal
 * @param {string} instruction - HTML content to display in modal
 */
function showSearchInstructionModal(instruction) {
    const modalHtml = `
        <div class="modal fade" id="searchInstructionModal" tabindex="-1">
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
    $('#searchInstructionModal').remove();
    
    // Add and show modal
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('searchInstructionModal'));
    modal.show();
}
