/**
 * Retrieve Sequences Tool Manager
 * Handles UI logic for sequence retrieval form including source selection,
 * filtering, copy-to-clipboard functionality, and search ID display
 */

function clearSourceFilter() {
    clearSourceFilters('sourceFilter', 'selected_source', 'fasta-source-line', null, 'downloadForm');
}

function initializeSequenceRetrieval(options = {}) {
    const form = document.getElementById('downloadForm');
    const errorAlert = document.querySelector('.alert-danger');
    const shouldScroll = options.shouldScroll || false;
    
    // Scroll to sequences section if results were found
    if (shouldScroll) {
        const sequencesSection = document.getElementById('sequences-section');
        if (sequencesSection) {
            sequencesSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    
    function updateCurrentSelectionDisplay() {
        window.updateCurrentSelectionDisplay('currentSelection', 'fasta-source-line', true);
        const selectionDiv = document.getElementById('currentSelection');
        const checked = document.querySelector('input[name="selected_source"]:checked');
        if (!checked) {
            selectionDiv.innerHTML = '<span style="color: #999;">None selected</span>';
        }
    }
    
    // Use centralized source selector initialization
    initializeSourceSelector({
        formId: 'downloadForm',
        filterId: 'sourceFilter',
        radioName: 'selected_source',
        sourceListClass: 'fasta-source-line',
        shouldAutoSelect: typeof window.shouldAutoSelect !== 'undefined' ? window.shouldAutoSelect : true,
        onSelectionChange: function(radio) {
            // Update hidden form fields when selection changes
            if (form) {
                form.querySelector('input[name="organism"]').value = radio.dataset.organism;
                form.querySelector('input[name="assembly"]').value = radio.dataset.assembly;
            }
            // Update display text
            updateCurrentSelectionDisplay();
        },
        onFilterChange: function() {
            updateCurrentSelectionDisplay();
        }
    });
    
    // Update display on page load
    updateCurrentSelectionDisplay();
    
    // Dismiss error alert on form submission
    if (form) {
        form.addEventListener('submit', function() {
            if (errorAlert) {
                const bsAlert = new bootstrap.Alert(errorAlert);
                bsAlert.close();
            }
        });
    }
    
    // Update hidden fields on form submit and validate
    if (form) {
        form.addEventListener('submit', function(e) {
            const checked = document.querySelector('input[name="selected_source"]:checked');
            if (checked) {
                form.querySelector('input[name="organism"]').value = checked.dataset.organism;
                form.querySelector('input[name="assembly"]').value = checked.dataset.assembly;
            } else {
                // No assembly selected - prevent submit and alert user
                e.preventDefault();
                alert('Please select an assembly before searching.');
                return false;
            }
        });
    }
    
    // Update search IDs display when textarea changes
    const featureIdsTextarea = document.getElementById('featureIds');
    const expandedUniqueamesField = document.getElementById('expandedUniqueames');
    const foundIdsField = document.getElementById('foundIds');
    
    function updateSearchIdsDisplay() {
        if (!featureIdsTextarea) return;
        
        // Check if searchIdsDisplay element exists before trying to update it
        const searchIdsDisplay = document.getElementById('searchIdsDisplay');
        if (!searchIdsDisplay) {
            return; // Element doesn't exist on this page, skip
        }
        
        
        // Get user input IDs
        const rawIds = featureIdsTextarea.value.trim();
        const userInputIds = rawIds
            .split(/[\n,]+/)
            .map(id => id.trim())
            .filter(id => id.length > 0);
        
        
        // Get found IDs from server (IDs that were actually returned by blastdbcmd)
        let foundIds = [];
        if (foundIdsField && foundIdsField.value) {
            try {
                foundIds = JSON.parse(foundIdsField.value);
            } catch (e) {
                // If field has JSON, try to use it
                foundIds = [];
            }
        }
        // Also check window.foundIds as fallback
        if (!Array.isArray(foundIds) || foundIds.length === 0) {
            foundIds = (window.foundIds && Array.isArray(window.foundIds)) ? window.foundIds : [];
        }
        
        // If still empty, try to extract from displayed FASTA sequences on the page
        if (foundIds.length === 0) {
            // Look for FASTA headers (lines starting with >) in the sequence display areas
            const fastaRegex = />([^\s]+)/g;
            const displayContent = document.body.textContent;
            let match;
            while ((match = fastaRegex.exec(displayContent)) !== null) {
                foundIds.push(match[1]);
            }
            foundIds = [...new Set(foundIds)]; // Remove duplicates
        }
        
        // First check if server sent expanded uniquenames (after form submission)
        let idsToDisplay = [];
        if (expandedUniqueamesField && expandedUniqueamesField.value) {
            try {
                const expanded = JSON.parse(expandedUniqueamesField.value);
                if (Array.isArray(expanded) && expanded.length > 0) {
                    idsToDisplay = expanded;
                }
            } catch (e) {
                // JSON parse failed, fall back to user input
            }
        }
        
        // If no expanded IDs, use user input
        if (idsToDisplay.length === 0) {
            if (!rawIds) {
                document.getElementById('searchIdsDisplay').innerHTML = 
                    '<span style="color: #999;">Enter IDs above to see expanded list (including children)</span>';
                searchIdsDisplay.style.display = 'block';
                return;
            }
            idsToDisplay = userInputIds;
        }
        
        // Display the IDs with parent-child hierarchy
        let displayHtml = '';
        const parentToChildren = window.parentToChildren || {};
        const processedIds = new Set();
        
        // Ensure foundIds is an array
        if (!Array.isArray(foundIds)) {
            foundIds = [];
        }
        
        idsToDisplay.forEach((id) => {
            if (processedIds.has(id)) return;
            
            const isFound = foundIds.some(foundId => foundId === id || foundId.startsWith(id + ':') || foundId.startsWith(id + ' '));
            const bgColor = isFound ? '#d4edda' : '#f8d7da';  // Green=found, Red=not found
            
            displayHtml += `<div style="padding: 4px 0;">
                <span style="background: ${bgColor}; padding: 2px 6px; border-radius: 3px; font-weight: bold;">${escapeHtml(id)}</span>`;
            
            processedIds.add(id);
            
            // If this ID has children, show them nested
            if (parentToChildren[id] && Array.isArray(parentToChildren[id])) {
                parentToChildren[id].forEach(childId => {
                    if (processedIds.has(childId)) return;
                    
                    const childFound = foundIds.some(foundId => foundId === childId || foundId.startsWith(childId + ':') || foundId.startsWith(childId + ' '));
                    const childBgColor = childFound ? '#d4edda' : '#f8d7da';
                    
                    displayHtml += `<div style="padding: 4px 0; margin-left: 20px; border-left: 2px solid #ccc; padding-left: 8px;">
                        <span style="background: ${childBgColor}; padding: 2px 6px; border-radius: 3px;">${escapeHtml(childId)}</span>
                    </div>`;
                    
                    processedIds.add(childId);
                });
            }
            
            displayHtml += `</div>`;
        });
        
        // Add any IDs that weren't parents (in case they have no parent in display)
        idsToDisplay.forEach((id) => {
            if (processedIds.has(id)) return;
            
            const isFound = foundIds.some(foundId => foundId === id || foundId.startsWith(id + ':') || foundId.startsWith(id + ' '));
            const bgColor = isFound ? '#d4edda' : '#f8d7da';
            
            displayHtml += `<div style="padding: 4px 0;">
                <span style="background: ${bgColor}; padding: 2px 6px; border-radius: 3px;">${escapeHtml(id)}</span>
            </div>`;
            
            processedIds.add(id);
        });
        
        document.getElementById('searchIdsDisplay').innerHTML = displayHtml || 
            '<span style="color: #999;">No valid IDs entered</span>';
        searchIdsDisplay.style.display = 'block';
    }
    
    if (featureIdsTextarea) {
        // Update on user input
        featureIdsTextarea.addEventListener('input', updateSearchIdsDisplay);
        // Also update on page load to show any pre-filled IDs or expanded ones from server
        updateSearchIdsDisplay();
    }

    // Handle copy to clipboard for sequences
    const copyables = document.querySelectorAll(".copyable");
    copyables.forEach(el => {
        let resetColorTimeout;
        el.addEventListener("click", function () {
            const text = el.innerText.trim();
            navigator.clipboard.writeText(text).then(() => {
                el.classList.add("bg-success", "text-white");
                if (resetColorTimeout) clearTimeout(resetColorTimeout);
                resetColorTimeout = setTimeout(() => {
                    el.classList.remove("bg-success", "text-white");
                }, 1500);
            }).catch(err => console.error("Copy failed:", err));
        });
    });
}

// Reinitialize tooltips for copy-to-clipboard elements
function initializeCopyTooltips() {
    const copyables = document.querySelectorAll(".copyable");
    copyables.forEach(el => {
        // Custom simple tooltip that follows cursor
        el.addEventListener("mouseenter", function() {
            // Remove any existing tooltip
            const existing = document.getElementById("custom-copy-tooltip");
            if (existing) existing.remove();
            
            // Create simple tooltip
            const tooltip = document.createElement("div");
            tooltip.id = "custom-copy-tooltip";
            tooltip.textContent = "Click to copy";
            tooltip.style.cssText = `
                position: fixed;
                background-color: #000;
                color: #fff;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                pointer-events: none;
                z-index: 9999;
            `;
            document.body.appendChild(tooltip);
            
            // Update position on mousemove
            const updatePosition = (e) => {
                tooltip.style.left = (e.clientX + 10) + "px";
                tooltip.style.top = (e.clientY - 30) + "px";
            };
            
            el.addEventListener("mousemove", updatePosition);
            
            // Initial position
            updatePosition(event);
            
            el.addEventListener("mouseleave", function() {
                const existing = document.getElementById("custom-copy-tooltip");
                if (existing) existing.remove();
                el.removeEventListener("mousemove", updatePosition);
            }, { once: true });
        });
    });
}


// Clear feature IDs textarea
function clearFeatureIds() {
    const featureIds = document.getElementById('featureIds');
    
    if (!featureIds) {
        console.error('featureIds field not found');
        return;
    }
    
    featureIds.value = '';
    featureIds.focus();
}

// Initialize on DOM ready

// Load sample feature IDs into textarea
function loadSampleIds() {
    const sampleIds = typeof window.sampleFeatureIds !== 'undefined' ? window.sampleFeatureIds : [];
    const featureIds = document.getElementById('featureIds');
    
    if (!featureIds) {
        console.error('featureIds field not found');
        return;
    }
    
    if (sampleIds.length === 0) {
        console.error('No sample IDs configured');
        return;
    }
    
    // Load samples one per line
    featureIds.value = sampleIds.join('\n');
    featureIds.focus();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Check if shouldScroll option was provided via PHP
        const shouldScroll = typeof scrollToResults !== 'undefined' ? scrollToResults : false;
        initializeSequenceRetrieval({ shouldScroll: shouldScroll });
        
        // Delay tooltip initialization to ensure Bootstrap is loaded
        setTimeout(initializeCopyTooltips, 500);
    });
} else {
    // Already loaded
    const shouldScroll = typeof scrollToResults !== 'undefined' ? scrollToResults : false;
    initializeSequenceRetrieval({ shouldScroll: shouldScroll });
    
    setTimeout(initializeCopyTooltips, 500);
}
