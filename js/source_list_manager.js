/**
 * Source List Manager - Shared JavaScript for managing organism/assembly source selections
 * Used by BLAST, retrieve_sequences, and other tools with source lists
 * 
 * Provides consistent filtering, clearing, and selection behavior across all pages
 */

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
        if (line && !line.classList.contains('hidden')) {
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
 * Save the currently selected source to browser storage
 * Allows maintaining selection across filter operations
 * Saves the index position to handle duplicate organism|assembly pairs in different groups
 * 
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 */
function saveSourceSelection(radioName = 'selected_source', sourceListClass = 'fasta-source-line') {
    const checkedRadio = document.querySelector(`input[name="${radioName}"]:checked`);
    if (checkedRadio) {
        const line = checkedRadio.closest('.' + sourceListClass);
        if (line) {
            // Save both the value and the index to handle duplicate values in different groups
            const allLines = document.querySelectorAll('.' + sourceListClass);
            const index = Array.from(allLines).indexOf(line);
            sessionStorage.setItem('lastSelectedSource', checkedRadio.value);
            sessionStorage.setItem('lastSelectedSourceIndex', index.toString());
        }
    }
}

/**
 * Try to restore previously selected source if it's visible
 * Uses saved index to handle duplicate organism|assembly pairs in different groups
 * Falls back to first visible if saved selection is hidden
 * 
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @returns {HTMLElement|null} The restored radio button, or null if not found
 */
function restoreSourceSelection(radioName = 'selected_source', sourceListClass = 'fasta-source-line') {
    const savedSource = sessionStorage.getItem('lastSelectedSource');
    const savedIndex = parseInt(sessionStorage.getItem('lastSelectedSourceIndex'), 10);
    
    if (!savedSource) {
        return null;
    }
    
    const allLines = document.querySelectorAll('.' + sourceListClass);
    const allRadios = document.querySelectorAll(`input[name="${radioName}"]`);
    let restoredRadio = null;
    
    // Try to restore using the saved index first (most accurate)
    if (!isNaN(savedIndex) && savedIndex < allLines.length) {
        const savedLine = allLines[savedIndex];
        const radio = savedLine.querySelector(`input[name="${radioName}"]`);
        if (radio && radio.value === savedSource && !savedLine.classList.contains('hidden')) {
            restoredRadio = radio;
        }
    }
    
    // If index restore failed, try to find by value (in case order changed)
    if (!restoredRadio) {
        allRadios.forEach(radio => {
            if (radio.value === savedSource && !restoredRadio) {
                const line = radio.closest('.' + sourceListClass);
                if (line && !line.classList.contains('hidden')) {
                    restoredRadio = radio;
                }
            }
        });
    }
    
    if (restoredRadio) {
        // Uncheck all first
        allRadios.forEach(radio => radio.checked = false);
        // Restore the saved one
        restoredRadio.checked = true;
        restoredRadio.dispatchEvent(new Event('change', { bubbles: true }));
        // Scroll into view
        scrollSourceIntoView(restoredRadio, sourceListClass);
        return restoredRadio;
    }
    
    // Saved selection is hidden, fall back to first visible
    return autoSelectFirstVisibleSource(radioName, sourceListClass, '.fasta-source-list');
}

/**
 * Clear all source filters and show all items
 * Maintains the previously selected source if it becomes visible
 * 
 * @param {string} filterId - ID of the filter input element (default: 'sourceFilter')
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @param {string} filterMessageId - ID of the filter message element to hide (optional)
 */
function clearSourceFilters(filterId = 'sourceFilter', radioName = 'selected_source', sourceListClass = 'fasta-source-line', filterMessageId = null) {
    const filterInput = document.getElementById(filterId);
    const sourceLines = document.querySelectorAll('.' + sourceListClass);
    const filterMessage = filterMessageId ? document.getElementById(filterMessageId) : null;
    
    // Save current selection before clearing
    saveSourceSelection(radioName, sourceListClass);
    
    // Clear filter input
    if (filterInput) {
        filterInput.value = '';
    }
    
    // Show all source lines (remove hidden class)
    sourceLines.forEach(line => {
        line.classList.remove('hidden');
    });
    
    // Hide the filter message if it exists
    if (filterMessage) {
        filterMessage.style.display = 'none';
    }
    
    // Try to restore previously selected source, or select first visible
    const selectedRadio = restoreSourceSelection(radioName, sourceListClass);
    
    // Focus back on filter input
    if (filterInput) {
        filterInput.focus();
    }
    
    return selectedRadio;
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
            saveSourceSelection(radioName, sourceListClass);
            if (onSelectionChange) {
                onSelectionChange(this);
            }
        });
    });
    
    // Apply initial filter and auto-select first visible
    applySourceFilter(filterId, sourceListClass);
    autoSelectFirstVisibleSource(radioName, sourceListClass, '.fasta-source-list');
}
