/**
 * Assembly Display Page Logic
 * Handles search functionality for a single assembly's annotations
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - sitePath: the site path prefix
 * - organismName: the organism name
 * - assemblyAccession: the assembly accession
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
    formSelector: '#assemblySearchForm',
    organismsVar: [organismName],
    totalVar: 1,
    hideSections: ['#assemblyHeader', '#assemblyDownloads'],
    scrollToResults: false,
    extraAjaxParams: {assembly: assemblyAccession},
    noReadMoreButton: true
});

searchManager.init();
