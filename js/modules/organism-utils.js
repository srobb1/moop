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
    
    // Handle "Select All" button — respects active filter
    $(document).on('click', '.selectAllOrganisms', function() {
        const filterActive = ($('#organismFilter').val() || '').trim().length > 0;
        if (filterActive) {
            $('.organism-card-col:visible .organism-checkbox').prop('checked', true);
            $('.organism-card-col:visible .organism-selector-card').addClass('selected');
            $('.organism-card-col:visible .organism-checkbox').each(function() {
                const org = $(this).data('organism');
                if (!selectedOrganisms.includes(org)) selectedOrganisms.push(org);
            });
        } else {
            selectedOrganisms = [...allOrganisms];
            $('.organism-checkbox').prop('checked', true);
            $('.organism-selector-card').addClass('selected');
        }
        if (updateCallback) updateCallback(selectedOrganisms);
    });

    // Handle "Deselect All" button — always clears everything
    $(document).on('click', '.deselectAllOrganisms', function() {
        selectedOrganisms = [];
        $('.organism-checkbox').prop('checked', false);
        $('.organism-selector-card').removeClass('selected');
        if (updateCallback) updateCallback(selectedOrganisms);
    });

    // Handle organism filter input
    $(document).on('input', '#organismFilter', function() {
        const q = $(this).val().toLowerCase().trim();
        $('.organism-card-col').each(function() {
            const text = $(this).data('filter-text') || '';
            $(this).toggle(q === '' || text.includes(q));
        });
    });
    
    // Initialize visual state for already-checked boxes
    $('.organism-checkbox:checked').each(function() {
        $(this).closest('.organism-selector-card').addClass('selected');
    });
    
    return selectedOrganisms;
}
