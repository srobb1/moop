/**
 * Source List Manager - Shared JavaScript for managing organism/assembly source selections
 * Used by BLAST, retrieve_sequences, and other tools with source lists
 * 
 * Provides consistent filtering, clearing, and selection behavior across all pages
 */

/**
 * Check if an element is visible (not hidden by display: none or hidden class)
 * 
 * @param {HTMLElement} element - Element to check
 * @returns {boolean} True if element is visible
 */
function isSourceVisible(element) {
    if (!element) return false;
    if (element.classList.contains('hidden')) return false;
    if (element.style.display === 'none') return false;
    return true;
}

/**
 * Apply filter to source list based on search input
 * Shows/hides source items based on matching search text
 * 
 * @param {string} filterId - ID of the filter input element (default: 'sourceFilter')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 */
function applySourceFilter(filterId = 'sourceFilter', sourceListClass = 'fasta-source-line') {
    const filterInput = document.getElementById(filterId);
    const sourceLines = document.querySelectorAll('.' + sourceListClass);
    
    if (!filterInput) {
        console.warn('Filter input not found:', filterId);
        return;
    }
    
    const filterText = (filterInput.value || '').toLowerCase();
    
    sourceLines.forEach(line => {
        const searchText = line.dataset.search || '';
        if (filterText === '' || searchText.includes(filterText)) {
            line.classList.remove('hidden');
        } else {
            line.classList.add('hidden');
        }
    });
}

/**
 * Auto-select the first visible source radio button
 * Useful after filtering to ensure a valid selection is always visible
 * 
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @param {string} scrollContainerSelector - Selector for scrollable container (optional, e.g., '.fasta-source-list')
 */
function autoSelectFirstVisibleSource(radioName = 'selected_source', sourceListClass = 'fasta-source-line', scrollContainerSelector = null) {
    const allRadios = document.querySelectorAll(`input[name="${radioName}"]`);
    let firstVisibleRadio = null;
    
    allRadios.forEach(radio => {
        const line = radio.closest('.' + sourceListClass);
        if (line && isSourceVisible(line)) {
            if (!firstVisibleRadio) {
                firstVisibleRadio = radio;
            }
        }
    });
    
    if (firstVisibleRadio) {
        // Uncheck any currently checked radios
        allRadios.forEach(radio => radio.checked = false);
        // Check the first visible one
        firstVisibleRadio.checked = true;
        // Trigger change event to allow dependent updates
        firstVisibleRadio.dispatchEvent(new Event('change', { bubbles: true }));
        // Scroll into view if container is specified
        if (scrollContainerSelector) {
            scrollSourceIntoView(firstVisibleRadio, sourceListClass, scrollContainerSelector);
        }
        return firstVisibleRadio;
    }
    
    return null;
}

/**
 * Scroll the selected source line into view within its container
 * 
 * @param {HTMLElement} radio - The radio button element
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @param {string} scrollContainerSelector - CSS selector for the scrollable container
 */
function scrollSourceIntoView(radio, sourceListClass = 'fasta-source-line', scrollContainerSelector = '.fasta-source-list') {
    const line = radio.closest('.' + sourceListClass);
    const container = document.querySelector(scrollContainerSelector);
    
    if (line && container) {
        line.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}



/**
 * Restore previously selected source from a checked radio button
 * 
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @returns {HTMLElement|null} The checked radio button, or null if none found
 */
function restoreSourceSelection(radioName = 'selected_source', sourceListClass = 'fasta-source-line') {
    const checked = document.querySelector(`input[name="${radioName}"]:checked`);
    if (checked && isSourceVisible(checked.closest('.' + sourceListClass))) {
        return checked;
    }
    return null;
}

/**
 * Clear all source filters and show all items
 * Maintains the previously selected source if it becomes visible
 * Updates form hidden fields to reflect the selected assembly
 * 
 * @param {string} filterId - ID of the filter input element (default: 'sourceFilter')
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @param {string} filterMessageId - ID of the filter message element to hide (optional)
 * @param {string} formId - ID of the form to update (default: 'downloadForm')
 */
function clearSourceFilters(filterId = 'sourceFilter', radioName = 'selected_source', sourceListClass = 'fasta-source-line', filterMessageId = null, formId = 'downloadForm') {
    const filterInput = document.getElementById(filterId);
    const sourceLines = document.querySelectorAll('.' + sourceListClass);
    const filterMessage = filterMessageId ? document.getElementById(filterMessageId) : null;
    const form = document.getElementById(formId);
    
    // Clear filter input
    if (filterInput) {
        filterInput.value = '';
    }
    
    // Show all source lines (remove inline display: none)
    sourceLines.forEach(line => {
        line.style.display = '';
        line.classList.remove('hidden');
    });
    
    // Re-enable all radio buttons that are now visible
    const allRadios = document.querySelectorAll(`input[name="${radioName}"]`);
    allRadios.forEach(radio => {
        radio.disabled = false;
    });
    
    // Hide the filter message if it exists
    if (filterMessage) {
        filterMessage.style.display = 'none';
    }
    
    // Try to restore previously selected source, or select first visible
    let selectedRadio = restoreSourceSelection(radioName, sourceListClass);
    
    // If restore failed (saved selection not visible), select first visible
    if (!selectedRadio) {
        selectedRadio = autoSelectFirstVisibleSource(radioName, sourceListClass, '.fasta-source-list');
    }
    
    // Update form hidden fields if we have a selected radio
    if (selectedRadio && form) {
        form.querySelector('input[name="organism"]').value = selectedRadio.dataset.organism;
        form.querySelector('input[name="assembly"]').value = selectedRadio.dataset.assembly;
    }
    
    // Focus back on filter input
    if (filterInput) {
        filterInput.focus();
    }
    
    return selectedRadio;
}

/**
 * Update the display of currently selected source (organism > assembly)
 * Shows which source is selected and highlights if it's hidden due to filtering
 * 
 * @param {string} selectionDivId - ID of the div to display selection (default: 'currentSelection')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @param {boolean} showHiddenWarning - Show warning if selected item is filtered out (default: true)
 */
function updateCurrentSelectionDisplay(selectionDivId = 'currentSelection', sourceListClass = 'fasta-source-line', showHiddenWarning = true) {
    const checked = document.querySelector('input[name="selected_source"]:checked');
    const selectionDiv = document.getElementById(selectionDivId);
    
    if (!selectionDiv) {
        console.warn('Selection display div not found:', selectionDivId);
        return;
    }
    
    if (checked) {
        const line = checked.closest('.' + sourceListClass);
        const groupBadge = line ? line.querySelector('.badge') : null;
        const group = groupBadge ? groupBadge.textContent.trim() : 'Unknown';
        const organism = checked.dataset.organism || 'Unknown';
        const assembly = checked.dataset.assembly || 'Unknown';
        const isHidden = (showHiddenWarning && line && line.style.display === 'none') ? ' ⚠️ (HIDDEN - FILTERED OUT)' : '';
        
        selectionDiv.innerHTML = `
            <div style="color: #28a745; font-weight: bold;">
                ${group} > ${organism} > ${assembly}${isHidden}
            </div>
        `;
    } else {
        selectionDiv.innerHTML = '';
    }
}

/**
 * Initialize source list filtering on page load
 * Sets up filter input listeners and applies initial filtering
 * 
 * @param {Object} options - Configuration options
 * @param {string} options.filterId - ID of filter input (default: 'sourceFilter')
 * @param {string} options.radioName - Name of radio buttons (default: 'selected_source')
 * @param {string} options.sourceListClass - CSS class of source items (default: 'fasta-source-line')
 * @param {string} options.filterMessageId - ID of filter message element (optional)
 * @param {Function} options.onSelectionChange - Callback when selection changes (optional)
 */
function initializeSourceListManager(options = {}) {
    const {
        filterId = 'sourceFilter',
        radioName = 'selected_source',
        sourceListClass = 'fasta-source-line',
        scrollContainerSelector = '.fasta-source-list',
        filterMessageId = null,
        onSelectionChange = null
    } = options;
    
    const filterInput = document.getElementById(filterId);
    const radios = document.querySelectorAll(`input[name="${radioName}"]`);
    
    // Setup filter input listener
    if (filterInput) {
        filterInput.addEventListener('keyup', () => {
            applySourceFilter(filterId, sourceListClass);
        });
    }
    
    // Setup radio change listeners
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (onSelectionChange) {
                onSelectionChange(this);
            }
        });
    });
    
    // Apply initial filter only (don't auto-select - let caller decide)
    applySourceFilter(filterId, sourceListClass);
}
