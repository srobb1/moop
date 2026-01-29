/**
 * Organism Display Page Logic
 * Handles search functionality for a single organism's annotations
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - sitePath: the site path prefix
 * - organismName: the organism name
 */

// Initialize search instructions handler
initializeSearchInstructionsHandler();

// Initialize organism instruction handlers when page is ready
$(document).ready(function() {
    // Handle taxonomy lineage info icon
    $(document).on('click', '.taxonomy-lineage-trigger', function(e) {
        e.stopPropagation();
        const instruction = $(this).data('instruction');
        showOrganismInstructionModal(instruction);
    });

    // Handle member of groups info icon
    $(document).on('click', '.member-groups-trigger', function(e) {
        e.stopPropagation();
        const instruction = $(this).data('instruction');
        showOrganismInstructionModal(instruction);
    });

    const searchManager = new AnnotationSearch({
        formSelector: '#organismSearchForm',
        organismsVar: [organismName],
        totalVar: 1,
        hideSections: ['#organismHeader', '#organismContent'],
        scrollToResults: false,
        noReadMoreButton: true
    });

    searchManager.init();
});

/**
 * Show instruction modal for organism page info icons
 */
function showOrganismInstructionModal(instruction) {
    const modalHtml = `
        <div class="modal fade" id="organismInstructionModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Information</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${instruction}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove any existing modal
    $('#organismInstructionModal').remove();
    
    // Add and show new modal
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('organismInstructionModal'));
    modal.show();
    
    // Clean up modal after it's hidden
    $('#organismInstructionModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}
