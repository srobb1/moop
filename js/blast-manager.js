/**
 * BLAST Search Tool Manager
 * Handles UI logic for BLAST search form, source selection, database filtering,
 * and results management. Works in conjunction with PHP-provided variables:
 * - previouslySelectedDb
 * - databasesByAssembly
 * - blastShortPresetApplied, blastProgramDefaults (program defaults / blastn-short preset)
 */

/**
 * Rebuild the Database list for the selected source and BLAST program. Called when either
 * changes. Lived in the shared js/modules/utilities.js until 2026-11-16, although only this
 * page uses it.
 *
 * Each database row carries its type in data-seq-type (genome / transcript / cds / protein),
 * so code that wants a particular database asks for the type -- never for its label, which
 * is translated on a site in another language.
 */
function updateDatabaseList() {
    if (typeof databasesByAssembly === 'undefined') {
        console.error('databasesByAssembly not loaded yet');
        return;
    }

    const selectedRadio = document.querySelector('input[name="selected_source"]:checked');
    const program = document.getElementById('blast_program').value;
    const dbBadges = document.getElementById('databaseBadges');
    if (!dbBadges) {
        console.error('databaseBadges element not found');
        return;
    }

    if (!selectedRadio) {
        dbBadges.innerHTML = '<div style="padding: 15px; text-align: center; color: #666; width: 100%;"><small>'
            + moopTh('Choose an organism first') + '</small></div>';
        return;
    }

    const sourceKey = selectedRadio.value;
    const allDbs = databasesByAssembly[sourceKey] || [];

    // blastp and blastx search a protein database; blastn (and its presets), tblastn and
    // tblastx a nucleotide one.
    const compatibleDbs = allDbs.filter(db => {
        if (['blastp', 'blastx'].includes(program)) return db.type === 'protein';
        if (['blastn', 'megablast', 'blastn-short', 'tblastn', 'tblastx'].includes(program)) return db.type === 'nucleotide';
        return true;
    });

    if (compatibleDbs.length === 0) {
        dbBadges.innerHTML = '<div style="padding: 15px; text-align: center; color: #d9534f; width: 100%;"><small>'
            + moopTh('No compatible databases found for this program') + '</small></div>';
        return;
    }

    // Shown names, by type: literals, so the translation scan finds them.
    const DB_NAMES = {
        genome:     moopT('Genome'),
        transcript: moopT('Transcript'),
        cds:        moopT('CDS'),
        protein:    moopT('Protein'),
    };

    let html = '';
    compatibleDbs.forEach((db, index) => {
        const isProtein = db.type === 'protein';
        const name = DB_NAMES[db.seq_type] || db.name;
        // The badge says what KIND of sequence the database holds ("DNA" over CDS /
        // Transcript / Genome). For the protein database it would only repeat the name.
        const badgeHtml = isProtein
            ? ''
            : `<span class="badge badge-sm bg-primary text-white">${moopTh('DNA')}</span>`;
        // organism|assembly|gene_set|seq_type -- NOT a filesystem path. Carrying the scope
        // lets the server check that the database belongs to the selected assembly; the two
        // are independent controls on this page, and the server rejects a mismatch.
        const dbValue = `${sourceKey}|${db.seq_type}`;
        const dbId = 'db_' + dbValue.replace(/[^\w]/g, '_');

        html += `
            <div class="fasta-source-line">
                <input
                    type="radio"
                    name="blast_db"
                    value="${escapeHtml(dbValue)}"
                    data-seq-type="${escapeHtml(db.seq_type)}"
                    ${index === 0 ? 'checked' : ''}
                    id="${dbId}"
                >
                ${badgeHtml}
                <label for="${dbId}" style="margin: 0; cursor: pointer; flex: 1;">
                    <strong>${escapeHtml(name)}</strong>
                </label>
            </div>
        `;
    });

    dbBadges.innerHTML = html;

    const form = document.getElementById('blastForm');
    if (form) {
        form.querySelector('input[name="organism"]').value = selectedRadio.dataset.organism;
        form.querySelector('input[name="assembly"]').value = selectedRadio.dataset.assembly;
    }
}

function initializeBlastManager() {
    const form = document.getElementById('blastForm');

    // The organism list is the shared selector in single mode (js/modules/scope-selector.js),
    // pre-selected by the server. A changed choice rebuilds the Database list for it.
    initScopeSelector({
        mode: 'single',
        onChange: function () {
            updateDatabaseList();
            const hint = document.getElementById('blast-select-hint');
            if (hint) hint.style.display = 'none';   // they just did what it asked
        },
    });
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
                const hint = document.getElementById('blast-select-hint');
                if (hint) {
                    hint.style.display = 'flex';
                    hint.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            // Update hidden organism/assembly/gene_set fields
            form.querySelector('input[name="organism"]').value = selectedSource.dataset.organism;
            form.querySelector('input[name="assembly"]').value = selectedSource.dataset.assembly;
            form.querySelector('input[name="gene_set"]').value = selectedSource.dataset.geneSet || '';
            
            // Say it is running, on the button the user just pressed, and stop a second
            // submit. This used to look for #progressIndicator, which the page has never
            // had -- so a long search showed nothing at all and could be submitted twice.
            const searchBtn = document.getElementById('searchBtn');
            if (searchBtn) {
                searchBtn.disabled = true;
                searchBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>' + moopTh('Running BLAST… (large databases can take a minute)');
            }
            watchQueue(form, searchBtn);
        });
    }

    // Searches wait their turn when the server is busy (lib/blast_queue.php). A random ticket
    // goes with the form; while the page waits for the answer it asks where that ticket stands
    // and says so on the button, so a wait in line does not look like a hang.
    function watchQueue(form, searchBtn) {
        let field = form.querySelector('input[name="queue_ticket"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'queue_ticket';
            form.appendChild(field);
        }
        const bytes = new Uint8Array(8);
        crypto.getRandomValues(bytes);
        const ticket = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
        field.value = ticket;
        if (!searchBtn) return;

        // "3rd" is English; a page in another language gets the plain number (window.MOOP_I18N is
        // set only there), so its translation can say "puesto 3".
        const ordinal = n => {
            if (window.MOOP_I18N) return String(n);
            const teen = n % 100 >= 11 && n % 100 <= 13;
            return n + (teen ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] || 'th'));
        };
        const url = window.sitePath + '/api/blast_queue_status.php?ticket=' + ticket;
        const spin = '<i class="fa fa-spinner fa-spin me-1"></i>';
        const poll = () => fetch(url, { cache: 'no-store' })
            .then(r => r.ok ? r.json() : null)
            .then(st => {
                if (st && st.state === 'waiting') {
                    searchBtn.innerHTML = spin + moopTh('Server busy: your search is %s in line (%s running). It starts automatically.',
                                                      ordinal(st.position), st.running);
                } else if (st && st.state === 'running') {
                    searchBtn.innerHTML = spin + moopTh('Running BLAST… (large databases can take a minute)');
                }
            })
            .catch(() => {})
            .finally(() => setTimeout(poll, 1500));
        setTimeout(poll, 800);
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
    
    // Custom E-value box shows only when "custom" is picked
    const evalueSelect = document.getElementById('evalue');
    if (evalueSelect) {
        evalueSelect.addEventListener('change', function() {
            const customContainer = document.getElementById('evalue_custom_container');
            if (this.value === 'custom') {
                customContainer.style.display = 'block';
                document.getElementById('evalue_custom').focus();
            } else {
                customContainer.style.display = 'none';
                document.getElementById('evalue_custom').value = '';
            }
        });
    }

    // Program dropdown: databases for the program, then its own default settings.
    // Registered here, before blast-short-nudge.js adds its listener -- the same order the
    // inline onchange had.
    const programSelect = document.getElementById('blast_program');
    if (programSelect) {
        programSelect.addEventListener('change', function() {
            updateDatabaseList();
            applyBlastProgramDefaults(this.value);
        });
    }

    // Sample sequence buttons (data-sample="protein" | "nucleotide")
    document.querySelectorAll('button[data-sample]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            loadSampleSequence(this.dataset.sample);
        });
    });
}

// Scroll to the results when the page comes back with them. Never worked before: it looked
// for #blast-results-section (the card is #blastResultsCard), and it added a
// DOMContentLoaded listener from INSIDE a DOMContentLoaded handler, after the event had fired.
function autoScrollToResults() {
    const resultsCard = document.getElementById('blastResultsCard');
    if (resultsCard) {
        resultsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ── Program defaults and the blastn-short preset ─────────────────────────────────────
// Was an inline <script> in tools/pages/blast.php until 2026-11-16 (CLAUDE.md §7: page logic
// in a JS file, only PHP values inline). Called from the program dropdown's change listener.
// Globals from tools/blast.php's inline_scripts:
//   blastShortPresetApplied -- whether the preset owns the advanced values (true when the
//                              page comes back from a blastn-short search), so switching
//                              away can put them back
//   blastProgramDefaults    -- each program's own BLAST defaults for the filter and
//                              soft-masking checkboxes, from blast_programs()
function applyBlastProgramDefaults(program) {
    const defaults = blastProgramDefaults[program];
    if (defaults) {
        const f = document.getElementById('filter_seq');
        const m = document.getElementById('soft_masking');
        if (f) f.checked = defaults.filter;
        if (m) m.checked = defaults.soft_masking;
    }
    const notice    = document.getElementById('blastn-short-notice');
    const advNotice = document.getElementById('adv-short-notice');
    const badge     = document.getElementById('adv-modified-badge');
    const isShort   = program === 'blastn-short';

    notice?.classList.toggle('d-none', !isShort);
    advNotice?.classList.toggle('d-none', !isShort);
    // The collapsed bar carries the signal instead of the panel being forced open.
    badge?.classList.toggle('d-none', !isShort);
    document.querySelectorAll('.adv-short-param').forEach(el => {
        el.classList.toggle('blast-short-highlight', isShort);
    });

    if (isShort) {
        // Advanced Options stays CLOSED on purpose. It used to spring open here so the
        // user could "see what was set", which meant a first-time user picking a primer
        // search was immediately faced with a dozen controls they had not asked for. The
        // badge in the bar says the values changed; anyone who wants the detail opens it,
        // and the changed fields are still highlighted when they do.

        // Pre-fill advanced fields
        const wordSize  = document.getElementById('word_size');
        const evalue    = document.getElementById('evalue');
        const gapopen   = document.getElementById('gapopen');
        const gapextend = document.getElementById('gapextend');
        if (wordSize)  wordSize.value   = '7';
        // 1000, not 10. blastn-short's own default IS 1000 (`blastn -help`); passing 10
        // overrode it and cut a real 20nt primer from 566 hits to 26. The notice below
        // has always SAID 1000 -- only the code disagreed.
        if (evalue)    evalue.value     = '1000';
        // DUST off -- set above from blastProgramDefaults (`no` for blastn-short upstream).
        // Left on, it silently returns ZERO hits for any primer with a simple repeat.
        if (gapopen)   gapopen.value    = '5';
        if (gapextend) gapextend.value  = '2';
        // Auto-select the genome database if there is one. By its TYPE (data-seq-type), not
        // its label: the label is translated on a site in another language, and matching
        // /genome/ on it would then silently stop finding it.
        const genomeRadio = document.querySelector('#databaseBadges input[name="blast_db"][data-seq-type="genome"]');
        if (genomeRadio) genomeRadio.checked = true;
    } else if (blastShortPresetApplied) {
        // Switching AWAY from the short preset must put the values back, or the user runs
        // an ordinary blastn at E-value 1000 with word size 7 and no filter, with nothing
        // on screen saying so -- the badge has just been hidden.
        //
        // This was harmless until 2026-08-03 only because word_size and friends were never
        // passed to BLAST at all. Now that they are, leaving them behind is a real wrong
        // result. Blank means "use the program default" on the server side.
        const wordSize  = document.getElementById('word_size');
        const evalue    = document.getElementById('evalue');
        const gapopen   = document.getElementById('gapopen');
        const gapextend = document.getElementById('gapextend');
        if (wordSize)  wordSize.value   = '';
        if (evalue)    evalue.value     = '1e-3';
        if (gapopen)   gapopen.value    = '';
        if (gapextend) gapextend.value  = '';
    }

    blastShortPresetApplied = isShort;
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initializeBlastManager();
        autoScrollToResults();
    });
} else {
    initializeBlastManager();
    autoScrollToResults();
}

// Load sample sequence
function loadSampleSequence(type) {
    const queryField = document.getElementById('query');
    const programField = document.getElementById('blast_program');
    
    if (!queryField) {
        console.error('query field not found');
        return;
    }

    // Never wipe a visitor's own sequence without asking (someone took the sample control for
    // the search button and lost what they had pasted, 2026-11-25).
    // Compare without whitespace: the textarea turns the sample's \r\n into \n.
    const squash = v => String(v).replace(/\s+/g, '');
    const current = squash(queryField.value);
    const isSample = Object.values(sampleSequences || {}).some(s => squash(s) === current);
    if (current !== '' && !isSample && !confirm(moopT('Replace your sequence with the sample?'))) {
        return;
    }
    
    // blastp for the protein sample (this said blastx, a DNA-query program; detection below
    // corrected it, which hid the mistake). The 'change' event runs the page's handler, so
    // a preset left over from BLASTn-short is put back -- setting .value alone fires nothing.
    if (type === 'protein' && sampleSequences['protein']) {
        queryField.value = sampleSequences['protein'];
        if (programField) {
            programField.value = 'blastp';
            programField.dispatchEvent(new Event('change'));
        }
    } else if (type === 'nucleotide' && sampleSequences['nucleotide']) {
        queryField.value = sampleSequences['nucleotide'];
        if (programField) {
            programField.value = 'blastn';
            programField.dispatchEvent(new Event('change'));
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
