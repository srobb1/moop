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
        const $card = $(this).closest('.organism-selector-card');
        
        // Update visual state
        if (isChecked) {
            $card.addClass('selected');
        } else {
            $card.removeClass('selected');
        }
        
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
        $('.organism-selector-card').addClass('selected');
        updateSearchManager();
    });
    
    // Handle "Deselect All" button
    $('#deselectAllOrganisms').on('click', function() {
        selectedOrganisms = [];
        $('.organism-checkbox').prop('checked', false);
        $('.organism-selector-card').removeClass('selected');
        updateSearchManager();
    });
    
    // Initialize visual state for already-checked boxes
    $('.organism-checkbox:checked').each(function() {
        $(this).closest('.organism-selector-card').addClass('selected');
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

