/**
 * Retrieve Sequences Tool Manager
 * Handles UI logic for sequence retrieval form including source selection,
 * filtering, copy-to-clipboard functionality, and search ID display
 */

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function clearSourceFilter() {
    document.getElementById('sourceFilter').value = '';
    const filterInput = document.getElementById('sourceFilter');
    filterInput.dispatchEvent(new Event('keyup'));
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
    
    // Initialize source list manager with form-specific callback
    initializeSourceListManager({
        filterId: 'sourceFilter',
        radioName: 'selected_source',
        sourceListClass: 'fasta-source-line',
        onSelectionChange: function(radio) {
            // Update hidden form fields when selection changes
            if (form) {
                form.querySelector('input[name="organism"]').value = radio.dataset.organism;
                form.querySelector('input[name="assembly"]').value = radio.dataset.assembly;
            }
            // Update display text
            updateCurrentSelectionDisplay();
        }
    });
    
    // Disable hidden radios on page load
    document.querySelectorAll('input[name="selected_source"]').forEach(radio => {
        const line = radio.closest('.fasta-source-line');
        if (line && !isSourceVisible(line)) {
            radio.disabled = true;
        } else {
            radio.disabled = false;
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
    
    // Add event listener for filter input changes
    const filterInput = document.getElementById('sourceFilter');
    if (filterInput) {
        filterInput.addEventListener('keyup', function() {
            // After filter is applied, check visibility and update display
            setTimeout(function() {
                document.querySelectorAll('input[name="selected_source"]').forEach(radio => {
                    const line = radio.closest('.fasta-source-line');
                    if (line && !isSourceVisible(line)) {
                        if (radio.checked) {
                            radio.checked = false;
                            if (form) {
                                form.querySelector('input[name="organism"]').value = '';
                                form.querySelector('input[name="assembly"]').value = '';
                            }
                        }
                        radio.disabled = true;
                    } else {
                        radio.disabled = false;
                    }
                });
                updateCurrentSelectionDisplay();
            }, 10);
        });
    }
    
    // Update search IDs display when textarea changes
    const featureIdsTextarea = document.getElementById('featureIds');
    const expandedUniqueamesField = document.getElementById('expandedUniqueames');
    const foundIdsField = document.getElementById('foundIds');
    
    function updateSearchIdsDisplay() {
        if (!featureIdsTextarea) return;
        
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
                // JSON parse failed
            }
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
                return;
            }
            idsToDisplay = userInputIds;
        }
        
        // Display the IDs - color based on whether they were found by blastdbcmd
        const displayHtml = idsToDisplay
            .map((id) => {
                // Always use found/not found coloring (foundIds will be empty before search, but that's OK)
                const bgColor = foundIds.includes(id) ? '#d4edda' : '#f8d7da';  // Green=found, Red=not found
                return `<div style="padding: 4px 0;"><span style="background: ${bgColor}; padding: 2px 6px; border-radius: 3px;">${escapeHtml(id)}</span></div>`;
            })
            .join('');
        
        document.getElementById('searchIdsDisplay').innerHTML = displayHtml || 
            '<span style="color: #999;">No valid IDs entered</span>';
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

// Initialize on DOM ready
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
