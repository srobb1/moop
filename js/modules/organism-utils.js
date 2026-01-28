/**
 * Shared Organism Selection Utilities
 * Common functions for organism selection across multiple pages
 * (groups, multi-organism search, etc.)
 */

/**
 * Initialize organism selection checkboxes
 * @param {array} allOrganisms - Complete array of organisms available
 * @param {function} updateCallback - Callback function to call when selection changes
 * @returns {array} The initially selected organisms
 */
function initializeOrganismSelection(allOrganisms, updateCallback) {
    // Track which organisms are selected (default: all selected)
    let selectedOrganisms = [...allOrganisms];
    
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
        
        // Call update callback
        if (updateCallback) {
            updateCallback(selectedOrganisms);
        }
    });
    
    // Handle "Select All" button
    $(document).on('click', '.selectAllOrganisms', function() {
        selectedOrganisms = [...allOrganisms];
        $('.organism-checkbox').prop('checked', true);
        $('.organism-selector-card').addClass('selected');
        if (updateCallback) {
            updateCallback(selectedOrganisms);
        }
    });
    
    // Handle "Deselect All" button
    $(document).on('click', '.deselectAllOrganisms', function() {
        selectedOrganisms = [];
        $('.organism-checkbox').prop('checked', false);
        $('.organism-selector-card').removeClass('selected');
        if (updateCallback) {
            updateCallback(selectedOrganisms);
        }
    });
    
    // Initialize visual state for already-checked boxes
    $('.organism-checkbox:checked').each(function() {
        $(this).closest('.organism-selector-card').addClass('selected');
    });
    
    return selectedOrganisms;
}
