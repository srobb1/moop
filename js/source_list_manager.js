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
 * @param {boolean} saveToSession - Whether to save to session storage (default: true)
 */
function autoSelectFirstVisibleSource(radioName = 'selected_source', sourceListClass = 'fasta-source-line', scrollContainerSelector = null, saveToSession = true) {
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
        // Save to session if requested
        if (saveToSession) {
            saveSourceSelection(radioName, sourceListClass);
        }
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
 * Saves organism, assembly, and the group badge to handle duplicates in different groups
 * 
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 */
function saveSourceSelection(radioName = 'selected_source', sourceListClass = 'fasta-source-line') {
    const checkedRadio = document.querySelector(`input[name="${radioName}"]:checked`);
    if (checkedRadio) {
        const line = checkedRadio.closest('.' + sourceListClass);
        if (line) {
            // Extract the group name from the first badge (group badge is first)
            const groupBadge = line.querySelector('.badge');
            const groupName = groupBadge ? groupBadge.textContent.trim() : '';
            
            // Save the selection details
            sessionStorage.setItem('lastSelectedOrganism', checkedRadio.dataset.organism);
            sessionStorage.setItem('lastSelectedAssembly', checkedRadio.dataset.assembly);
            sessionStorage.setItem('lastSelectedGroup', groupName);
        }
    }
}

/**
 * Try to restore previously selected source if it's visible
 * Restores by organism, assembly, and group - will NOT jump to same assembly in different group
 * 
 * @param {string} radioName - Name attribute of radio buttons (default: 'selected_source')
 * @param {string} sourceListClass - CSS class of source line items (default: 'fasta-source-line')
 * @returns {HTMLElement|null} The restored radio button, or null if not found/visible
 */
function restoreSourceSelection(radioName = 'selected_source', sourceListClass = 'fasta-source-line') {
    const savedOrganism = sessionStorage.getItem('lastSelectedOrganism');
    const savedAssembly = sessionStorage.getItem('lastSelectedAssembly');
    const savedGroup = sessionStorage.getItem('lastSelectedGroup');
    
    // If no saved selection, return null (nothing to restore)
    if (!savedOrganism || !savedAssembly) {
        return null;
    }
    
    const allRadios = document.querySelectorAll(`input[name="${radioName}"]`);
    let restoredRadio = null;
    let fallbackRadio = null;
    
    // Find the EXACT radio with matching organism, assembly, AND group that is visible
    allRadios.forEach(radio => {
        if (radio.dataset.organism === savedOrganism && radio.dataset.assembly === savedAssembly) {
            const line = radio.closest('.' + sourceListClass);
            if (isSourceVisible(line)) {
                // Check if this matches the saved group
                const groupBadge = line.querySelector('.badge');
                const groupName = groupBadge ? groupBadge.textContent.trim() : '';
                
                if (groupName === savedGroup) {
                    // Exact match - preferred
                    restoredRadio = radio;
                } else if (!fallbackRadio) {
                    // Same organism-assembly but different group - fallback only
                    fallbackRadio = radio;
                }
            }
        }
    });
    
    // Use exact match if found, otherwise use fallback
    const targetRadio = restoredRadio || fallbackRadio;
    
    if (targetRadio) {
        // Uncheck any currently checked radios
        allRadios.forEach(radio => radio.checked = false);
        // Check the target radio
        targetRadio.checked = true;
        // Trigger change event
        targetRadio.dispatchEvent(new Event('change', { bubbles: true }));
        // Scroll into view
        scrollSourceIntoView(targetRadio, sourceListClass);
        return targetRadio;
    }
    
    // Saved selection not found or not visible
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
    
    // Hide the filter message if it exists
    if (filterMessage) {
        filterMessage.style.display = 'none';
    }
    
    // Try to restore previously selected source, or select first visible
    let selectedRadio = restoreSourceSelection(radioName, sourceListClass);
    
    // If restore failed (saved selection not visible), select first visible
    if (!selectedRadio) {
        selectedRadio = autoSelectFirstVisibleSource(radioName, sourceListClass, '.fasta-source-list', false);
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
    
    // Apply initial filter only (don't auto-select - let caller decide)
    applySourceFilter(filterId, sourceListClass);
}
