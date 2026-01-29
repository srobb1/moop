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
        const helpType = $(this).data('help-type') || 'basic';
        const instruction = SEARCH_HELP[helpType] || $(this).data('instruction');
        showSearchInstructionModal(instruction, 'Search Tips');
    });
    
    // Handle search results help trigger
    $(document).on('click', '.search-results-help-trigger', function(e) {
        e.stopPropagation();
        const helpType = $(this).data('help-type') || 'results';
        const instruction = SEARCH_HELP[helpType] || $(this).data('instruction');
        showSearchInstructionModal(instruction, 'Search Results Help');
    });
    
    // Also handle search hints trigger (in results section)
    $(document).on('click', '.search-hints-trigger', function(e) {
        e.stopPropagation();
        const helpType = $(this).data('help-type') || 'results';
        const instruction = SEARCH_HELP[helpType] || $(this).data('instruction');
        showSearchInstructionModal(instruction, 'Search Results Help');
    });
}

/**
 * Show search instruction modal
 * @param {string} instruction - HTML content to display in modal
 * @param {string} title - Modal title
 */
function showSearchInstructionModal(instruction, title = 'Search Help') {
    const modalHtml = `
        <div class="modal fade" id="searchInstructionModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
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
