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
        
        refreshToggleAllLabel();

        // Call update callback
        if (updateCallback) {
            updateCallback(selectedOrganisms);
        }
    });
    
    // One toggle instead of a Select All / Deselect All pair. Two buttons that were
    // each valid only half the time read as unfinished, and one of them was always
    // a no-op. The label states what the button will DO next, so it doubles as a
    // readout of whether everything is currently selected.
    $(document).on('click', '.toggleAllOrganisms', function() {
        // "Everything" means everything VISIBLE — with a filter active the user is
        // looking at a subset, and acting on hidden rows they cannot see would be
        // surprising. Select All already respected the filter; deselect now does too.
        //
        // Scoped to .organism-selector-card, NOT the .organism-card-col wrapper: only
        // multi_organism.php has that wrapper, so scoping to it silently did nothing
        // on the groups page. The card exists on both, and jQuery :visible is false
        // for a card whose wrapper the filter hid, so filtering is still respected.
        const $cards = $('.organism-selector-card:visible');
        const $boxes = $cards.find('.organism-checkbox');
        const allOn  = $boxes.length > 0 && $boxes.filter(':not(:checked)').length === 0;

        $boxes.prop('checked', !allOn);
        $cards.toggleClass('selected', !allOn);

        $boxes.each(function() {
            const org = $(this).data('organism');
            const has = selectedOrganisms.includes(org);
            if (!allOn && !has) {
                selectedOrganisms.push(org);
            } else if (allOn && has) {
                selectedOrganisms = selectedOrganisms.filter(o => o !== org);
            }
        });

        refreshToggleAllLabel();
        if (updateCallback) updateCallback(selectedOrganisms);
    });

    /**
     * Keep the toggle's label describing its next action.
     *
     * Reads the visible checkboxes rather than selectedOrganisms, so it stays honest
     * while a filter is narrowing what "all" refers to.
     */
    function refreshToggleAllLabel() {
        const $boxes = $('.organism-selector-card:visible .organism-checkbox');
        const allOn  = $boxes.length > 0 && $boxes.filter(':not(:checked)').length === 0;
        $('.toggleAllOrganisms .toggle-all-label').text(allOn ? 'Deselect all' : 'Select all');

        // Count in the section bar, where the user is while ticking boxes.
        const $all = $('.organism-selector-card .organism-checkbox');
        $('.organism-count-badge .oc-n').text($all.filter(':checked').length);
        $('.organism-count-badge .oc-t').text($all.length);
    }

    // Handle organism filter input
    $(document).on('input', '#organismFilter', function() {
        const q = $(this).val().toLowerCase().trim();
        $('.organism-card-col').each(function() {
            const text = $(this).data('filter-text') || '';
            $(this).toggle(q === '' || text.includes(q));
        });
        // "All" now refers to a different set, so the toggle may mean the opposite.
        refreshToggleAllLabel();
    });

    // Initialize visual state for already-checked boxes
    $('.organism-checkbox:checked').each(function() {
        $(this).closest('.organism-selector-card').addClass('selected');
    });
    refreshToggleAllLabel();

    return selectedOrganisms;
}
