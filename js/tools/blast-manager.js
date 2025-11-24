/**
 * BLAST Search Tool Manager
 * Handles UI logic for BLAST search form, source selection, database filtering,
 * and results management. Works in conjunction with PHP-provided variables:
 * - previouslySelectedDb
 * - previouslySelectedSource  
 * - databasesByAssembly
 */

function downloadResultsHTML() {
    const resultsCard = document.querySelector('.card');
    if (!resultsCard) return;
    
    const content = resultsCard.innerHTML;
    const blob = new Blob([content], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'blast_results_' + new Date().toISOString().slice(0, 10) + '.html';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function downloadResultsText() {
    const pairwiseDiv = document.getElementById('pairwiseOutput');
    if (!pairwiseDiv) {
        alert('No BLAST results to download');
        return;
    }
    
    const textContent = pairwiseDiv.textContent || pairwiseDiv.innerText;
    const blob = new Blob([textContent], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'blast_results_' + new Date().toISOString().slice(0, 10) + '.txt';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function clearResults() {
    const resultsCard = document.querySelector('.mt-4.card');
    if (resultsCard) {
        resultsCard.remove();
    }
    document.getElementById('query').focus();
}

function initializeBlastManager() {
    function updateCurrentSelectionDisplay() {
        const checked = document.querySelector('input[name="selected_source"]:checked');
        const selectionDiv = document.getElementById('currentSelection');
        
        if (checked) {
            const line = checked.closest('.fasta-source-line');
            const groupBadge = line.querySelector('.badge');
            const group = groupBadge ? groupBadge.textContent.trim() : 'Unknown';
            const organism = checked.dataset.organism || 'Unknown';
            const assembly = checked.dataset.assembly || 'Unknown';
            
            selectionDiv.innerHTML = `
                <div style="color: #28a745; font-weight: bold;">
                    ${group} > ${organism} > ${assembly}
                </div>
            `;
        } else {
            selectionDiv.innerHTML = '<span style="color: #999;">None selected</span>';
        }
    }
    
    // Initialize source list manager with callback
    initializeSourceListManager({
        filterId: 'sourceFilter',
        radioName: 'selected_source',
        sourceListClass: 'fasta-source-line',
        onSelectionChange: function(radio) {
            updateDatabaseList();
            updateCurrentSelectionDisplay();
        }
    });
    
    // Restore previously selected source (if form was resubmitted)
    if (previouslySelectedSource) {
        const allSourceRadios = document.querySelectorAll(`input[name="selected_source"][value="${previouslySelectedSource}"]`);
        let prevSourceRadio = null;
        
        for (let radio of allSourceRadios) {
            const line = radio.closest('.fasta-source-line');
            if (line && !line.classList.contains('hidden')) {
                prevSourceRadio = radio;
                break;
            }
        }
        
        if (prevSourceRadio) {
            prevSourceRadio.checked = true;
        } else {
            // Fall back to first visible if restoration failed
            const firstRadio = document.querySelector('input[name="selected_source"]');
            const firstLine = firstRadio ? firstRadio.closest('.fasta-source-line') : null;
            if (firstLine && !firstLine.classList.contains('hidden')) {
                firstRadio.checked = true;
            }
        }
    }
    
    // Only auto-select first if nothing was restored
    if (!previouslySelectedSource) {
        const allRadios = document.querySelectorAll('input[name="selected_source"]');
        for (let radio of allRadios) {
            const line = radio.closest('.fasta-source-line');
            if (line && !line.classList.contains('hidden') && !radio.checked) {
                radio.click();
                break;
            }
        }
    }
    
    // Update display on page load
    updateCurrentSelectionDisplay();
    
    // Update database list based on selected source
    updateDatabaseList();
    
    // Restore previously selected database (after updateDatabaseList renders the radio buttons)
    if (previouslySelectedDb) {
        const prevDbRadio = document.querySelector(`input[name="blast_db"][value="${previouslySelectedDb}"]`);
        if (prevDbRadio) {
            prevDbRadio.checked = true;
        }
    }
    
    // Handle form submission
    const form = document.getElementById('blastForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const selectedSource = document.querySelector('input[name="selected_source"]:checked');
            const selectedDb = document.querySelector('input[name="blast_db"]:checked');
            
            if (!selectedSource || !selectedDb) {
                e.preventDefault();
                alert('Please select both an assembly and a database.');
                return false;
            }
            
            // Update hidden organism/assembly fields
            form.querySelector('input[name="organism"]').value = selectedSource.dataset.organism;
            form.querySelector('input[name="assembly"]').value = selectedSource.dataset.assembly;
            
            // Show progress indicator
            const progressIndicator = document.getElementById('progressIndicator');
            if (progressIndicator) {
                progressIndicator.style.display = 'block';
                document.getElementById('searchBtn').disabled = true;
            }
        });
    }
    
    // Add sequence type detection on textarea change
    const queryTextarea = document.getElementById('query');
    if (queryTextarea) {
        queryTextarea.addEventListener('input', function() {
            const result = detectSequenceType(this.value);
            
            // Update UI
            updateSequenceTypeInfo(result.message, 'sequenceTypeInfo', 'sequenceTypeMessage');
            
            // Filter programs
            if (result.type !== 'unknown') {
                filterBlastPrograms(result.type, 'blast_program');
                updateDatabaseList();
            }
        });
        
        // Run once on page load if there's already a sequence
        if (queryTextarea.value) {
            const result = detectSequenceType(queryTextarea.value);
            updateSequenceTypeInfo(result.message, 'sequenceTypeInfo', 'sequenceTypeMessage');
            if (result.type !== 'unknown') {
                filterBlastPrograms(result.type, 'blast_program');
            }
        }
    }
    
    // Hide progress indicator after page has loaded
    const resultsCard = document.querySelector('.mt-4.card');
    const progressIndicator = document.getElementById('progressIndicator');
    if (resultsCard && progressIndicator) {
        progressIndicator.style.display = 'none';
    }
    
    // Re-enable search button
    const searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        searchBtn.disabled = false;
    }
    
    // Toggle custom evalue input visibility
    window.toggleEvalueCustom = function() {
        const select = document.getElementById('evalue');
        const customContainer = document.getElementById('evalue_custom_container');
        if (select.value === 'custom') {
            customContainer.style.display = 'block';
            document.getElementById('evalue_custom').focus();
        } else {
            customContainer.style.display = 'none';
            document.getElementById('evalue_custom').value = '';
        }
    };
}

// Auto-scroll to results section when page loads with results
function autoScrollToResults() {
    document.addEventListener('DOMContentLoaded', function() {
        const resultsSection = document.getElementById('blast-results-section');
        if (resultsSection) {
            resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initializeBlastManager();
        autoScrollToResults();
    });
} else {
    // Already loaded
    initializeBlastManager();
    autoScrollToResults();
}
