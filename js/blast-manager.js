/**
 * BLAST Search Tool Manager
 * Handles UI logic for BLAST search form, source selection, database filtering,
 * and results management. Works in conjunction with PHP-provided variables:
 * - previouslySelectedDb
 * - previouslySelectedSource  
 * - databasesByAssembly
 */

function blastDownloadPost(content, filename, type) {
    const date = new Date().toISOString().slice(0, 10);
    const form = document.createElement('form');
    form.method = 'POST';
    const site = (typeof sitePath !== 'undefined') ? sitePath : ('/' + window.location.pathname.split('/')[1]);
    form.action = site + '/api/blast_download.php';

    const fields = { content, filename: filename.replace('{date}', date), type };
    for (const [name, value] of Object.entries(fields)) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = name;
        inp.value = value;
        form.appendChild(inp);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function downloadResultsText() {
    const div = document.getElementById('pairwiseOutput');
    if (!div) { alert('No BLAST results to download'); return; }
    blastDownloadPost(div.textContent, 'blast_results_{date}.txt', 'txt');
}

function downloadResultsTabular() {
    const div = document.getElementById('tabularOutput');
    if (!div) { alert('No tabular results to download'); return; }
    blastDownloadPost(div.textContent, 'blast_results_{date}.tsv', 'tsv');
}

function downloadResultsXML() {
    const div = document.getElementById('xmlOutput');
    if (!div) { alert('No XML results to download'); return; }
    blastDownloadPost(div.textContent, 'blast_results_{date}.xml', 'xml');
}

function clearResults() {
    const resultsCard = document.querySelector('.mt-4.card');
    if (resultsCard) {
        resultsCard.remove();
    }
    document.getElementById('query').focus();
}

function clearBlastSourceFilters() {
    window.clearSourceFilters('sourceFilter', 'selected_source', 'fasta-source-line', null, 'blastForm');
}

function initializeBlastManager() {
    const form = document.getElementById('blastForm');

    function updateCurrentSelectionDisplay() {
        window.updateCurrentSelectionDisplay();
        const selectionDiv = document.getElementById('currentSelection');
        const checked = document.querySelector('input[name="selected_source"]:checked');
        if (!checked) {
            selectionDiv.innerHTML = '<span style="color: #999;">None selected</span>';
        }
    }

    // Use centralized source selector initialization
    initializeSourceSelector({
        formId: 'blastForm',
        filterId: 'sourceFilter',
        radioName: 'selected_source',
        sourceListClass: 'fasta-source-line',
        onSelectionChange: function(radio) {
            updateDatabaseList();
            updateCurrentSelectionDisplay();
        },
        onFilterChange: function() {
            updateCurrentSelectionDisplay();
            updateDatabaseList();
        }
    });
    
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
    if (form) {
        form.addEventListener('submit', function(e) {
            const selectedSource = document.querySelector('input[name="selected_source"]:checked');
            const selectedDb = document.querySelector('input[name="blast_db"]:checked');
            
            if (!selectedSource || !selectedDb) {
                e.preventDefault();
                alert('Please select both an assembly and a database.');
                return false;
            }
            
            // Update hidden organism/assembly/gene_set fields
            form.querySelector('input[name="organism"]').value = selectedSource.dataset.organism;
            form.querySelector('input[name="assembly"]').value = selectedSource.dataset.assembly;
            form.querySelector('input[name="gene_set"]').value = selectedSource.dataset.geneSet || '';
            
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

function initPopovers() {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el, { sanitize: false }));
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initializeBlastManager();
        autoScrollToResults();
        initPopovers();
    });
} else {
    initializeBlastManager();
    autoScrollToResults();
    initPopovers();
}

// Load sample sequence
function loadSampleSequence(type) {
    const queryField = document.getElementById('query');
    const programField = document.getElementById('blast_program');
    
    if (!queryField) {
        console.error('query field not found');
        return;
    }
    
    if (type === 'protein' && sampleSequences['protein']) {
        queryField.value = sampleSequences['protein'];
        if (programField) {
            programField.value = 'blastx';
        }
    } else if (type === 'nucleotide' && sampleSequences['nucleotide']) {
        queryField.value = sampleSequences['nucleotide'];
        if (programField) {
            programField.value = 'blastn';
        }
    } else {
        console.error('Sample not found for type:', type);
        return;
    }
    
    // Detect sequence type and update UI if function exists
    if (typeof detectSequenceType === 'function') {
        const result = detectSequenceType(queryField.value);
        
        // Update sequence type message if function exists
        if (typeof updateSequenceTypeInfo === 'function') {
            updateSequenceTypeInfo(result.message, 'sequenceTypeInfo', 'sequenceTypeMessage');
        }
        
        // Filter BLAST programs based on detected type
        if (typeof filterBlastPrograms === 'function' && result.type !== 'unknown') {
            filterBlastPrograms(result.type, 'blast_program');
        }
    }
    
    // Update database list
    if (typeof updateDatabaseList === 'function') {
        updateDatabaseList();
    }
    
    // Scroll to top so user can see the loaded sequence
    queryField.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
