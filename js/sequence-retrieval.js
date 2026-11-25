/**
 * Retrieve Sequences Tool Manager
 * Handles UI logic for sequence retrieval form including source selection,
 * filtering, copy-to-clipboard functionality, and search ID display
 */

/**
 * Flanks can only be cut from a genome: show the flank boxes for a gene set that sits on one
 * (data-has-genome="1", from tools/retrieve_sequences.php) and hide them otherwise. Hidden
 * inputs are also DISABLED, so a value typed for an earlier selection is not submitted.
 * With no selection, or no flag on the radio, the boxes stay as rendered.
 */
function updateFlankControls(radio) {
    const controls = document.getElementById('flankControls');
    const note     = document.getElementById('flankNoGenome');
    if (!controls || !radio || radio.dataset.hasGenome === undefined) return;
    const hasGenome = radio.dataset.hasGenome === '1';
    controls.classList.toggle('d-none', !hasGenome);
    controls.querySelectorAll('input').forEach(function(input) { input.disabled = !hasGenome; });
    if (note) note.style.display = hasGenome ? 'none' : '';
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
    
    // The organism list is the shared selector in single mode (js/modules/scope-selector.js):
    // radios named selected_source, the readout under the list, pre-selection from the server.
    // This page only reacts to the chosen gene set: the hidden fields the form submits, the
    // sample IDs for that gene set, and the flank boxes (shown only where there is a genome).
    function onSourceChange() {
        const radio = document.querySelector('input[name="selected_source"]:checked');
        if (!radio) return;
        const hint = document.getElementById('retrieve-select-hint');
        if (hint) hint.style.display = 'none';   // they just did what it asked
        if (form) {
            form.querySelector('input[name="organism"]').value = radio.dataset.organism;
            form.querySelector('input[name="assembly"]').value = radio.dataset.assembly;
            form.querySelector('input[name="gene_set"]').value = radio.dataset.geneSet || '';
        }
        refreshSampleIds(radio);
        updateFlankControls(radio);
    }
    initScopeSelector({ mode: 'single', onChange: onSourceChange });
    onSourceChange();   // the server's pre-selection, if any

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
                form.querySelector('input[name="gene_set"]').value = checked.dataset.geneSet || '';
            } else {
                // No assembly selected — show the inline reminder instead of a browser alert.
                e.preventDefault();
                const hint = document.getElementById('retrieve-select-hint');
                if (hint) {
                    hint.style.display = 'flex';
                    hint.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
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

        // A long list is a download only (window.retrievalShowMax, the server's same limit), so
        // there is nothing to colour: say so in one line. This box used to draw every ID, on
        // every keystroke, checking each against every found ID -- slow in the browser for a
        // paste of thousands, before the server was even asked.
        const showMax = window.retrievalShowMax || 50;
        if (userInputIds.length > showMax) {
            searchIdsDisplay.textContent = moopT('%1$s IDs entered. Lists of more than %2$s IDs are available as a download only: retrieve to see how many were found.',
                                                 userInputIds.length.toLocaleString(), showMax);
            searchIdsDisplay.style.display = 'block';
            return;
        }

        // Green = found, red = not found -- but only once a retrieval has RUN. IDs pre-filled by a
        // link (a term page's "Get the sequences") have not been looked up, and painting them all
        // red read as "none of these exist" (Sofia, 2026-11-24). Until then, neutral grey.
        const retrievalRan = !!(foundIdsField && foundIdsField.dataset.ran === '1');
        const chipColor = (isFound) => !retrievalRan ? '#e9ecef' : (isFound ? '#d4edda' : '#f8d7da');

        // Found IDs: the server's list (#foundIds), taken from the FASTA headers it returned -- the one
        // source. Until 2026-11-24 an empty list fell back to window.foundIds (the same data again) and
        // then to scraping every ">token" out of the whole page's text, which could count any ">" on
        // the page as a found ID.
        let foundIds = [];
        if (retrievalRan && foundIdsField && foundIdsField.value) {
            try {
                foundIds = JSON.parse(foundIdsField.value);
            } catch (e) {
                foundIds = [];
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
                    '<span style="color: #999;">' + moopTh('Enter IDs above to see expanded list (including children)') + '</span>';
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
            const bgColor = chipColor(isFound);
            
            displayHtml += `<div style="padding: 4px 0;">
                <span style="background: ${bgColor}; padding: 2px 6px; border-radius: 3px; font-weight: bold;">${escapeHtml(id)}</span>`;
            
            processedIds.add(id);
            
            // If this ID has children, show them nested
            if (parentToChildren[id] && Array.isArray(parentToChildren[id])) {
                parentToChildren[id].forEach(childId => {
                    if (processedIds.has(childId)) return;
                    
                    const childFound = foundIds.some(foundId => foundId === childId || foundId.startsWith(childId + ':') || foundId.startsWith(childId + ' '));
                    const childBgColor = chipColor(childFound);
                    
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
            const bgColor = chipColor(isFound);
            
            displayHtml += `<div style="padding: 4px 0;">
                <span style="background: ${bgColor}; padding: 2px 6px; border-radius: 3px;">${escapeHtml(id)}</span>
            </div>`;
            
            processedIds.add(id);
        });
        
        document.getElementById('searchIdsDisplay').innerHTML = displayHtml || 
            '<span style="color: #999;">' + moopTh('No valid IDs entered') + '</span>';
        searchIdsDisplay.style.display = 'block';
    }
    
    if (featureIdsTextarea) {
        // Update on user input
        featureIdsTextarea.addEventListener('input', updateSearchIdsDisplay);
        // Also update on page load to show any pre-filled IDs or expanded ones from server
        updateSearchIdsDisplay();
    }
    // Click-to-copy on sequence blocks (.copyable) is js/modules/copy-to-clipboard.js, which
    // sequences_display.php loads; a second handler here threw on http and doubled the tooltips.
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

// Examples follow the selected source: fetch them from that gene set (api/gene_set_examples.php)
// so "Load examples" always finds something in what the user is about to extract from, and
// the "How IDs and ranges work" text shows IDs that work if pasted. The page arrives seeded
// with the server-selected source's examples; a source with none keeps what was showing.
const examplesBySource = {};
let examplesWanted = '';

function refreshSampleIds(radio) {
    const p = { organism: radio.dataset.organism || '', assembly: radio.dataset.assembly || '', gene_set: radio.dataset.geneSet || '' };
    if (!p.organism || !p.assembly || !p.gene_set) return;
    const key = p.organism + '|' + p.assembly + '|' + p.gene_set;
    examplesWanted = key;
    if (examplesBySource[key]) { showExamples(examplesBySource[key]); return; }

    fetch(window.sitePath + '/api/gene_set_examples.php?' + new URLSearchParams(p), { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : { ids: [] })
        .then(ex => {
            examplesBySource[key] = ex;
            if (key === examplesWanted) showExamples(ex);   // ignore a slower, superseded answer
        })
        .catch(() => {});
}

function showExamples(ex) {
    const ids = Array.isArray(ex.ids) ? ex.ids : [];
    if (!ids.length) return;
    window.sampleFeatureIds = ids;
    const text = document.getElementById('sampleIdsText');
    if (text) text.textContent = ids.join(' · ');
    const row = document.getElementById('sampleIdsRow');
    if (row) row.style.display = '';
    if (ex.gene) document.querySelectorAll('.ex-gene').forEach(el => { el.textContent = ex.gene; });
    if (ex.transcript) document.querySelectorAll('.ex-tx').forEach(el => { el.textContent = ex.transcript; });
}

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

// Example-ID buttons (were inline onclick until 2026-11-25)
function wireSampleIdButtons() {
    document.getElementById('loadSampleIdsBtn')?.addEventListener('click', loadSampleIds);
    document.getElementById('clearFeatureIdsBtn')?.addEventListener('click', clearFeatureIds);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireSampleIdButtons);
} else {
    wireSampleIdButtons();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        const shouldScroll = typeof scrollToResults !== 'undefined' ? scrollToResults : false;
        initializeSequenceRetrieval({ shouldScroll: shouldScroll });
    });
} else {
    const shouldScroll = typeof scrollToResults !== 'undefined' ? scrollToResults : false;
    initializeSequenceRetrieval({ shouldScroll: shouldScroll });
}
