/**
 * Tools Utilities - Shared JavaScript functions for tools
 * Used by BLAST and other sequence analysis tools
 */

/**
 * Escape text for use in HTML -- the ONE implementation on the site.
 *
 * Loaded on every page by includes/layout.php, so no module needs its own. There were
 * seven (three named escapeHtml, two esc, one escHtml, one escH in an admin page),
 * escaping different character sets -- some left ' or " alone -- largely because this
 * one threw on null or a number (it called text.replace directly). It now accepts
 * anything: null/undefined become '', everything else goes through String().
 *
 * @param {*} text - Value to escape
 * @returns {string} Escaped HTML-safe text
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text == null ? '' : text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Interface text in the visitor's language -- the browser's side of t() (includes/config_init.php;
 * CLAUDE.md section 7b). Write the English as a LITERAL, so the source scan can list it:
 *     el.textContent = moopT('No organisms selected');
 *     moopT('Showing %d of %d hits', shown, total)     // %s / %d / %1$s, filled in order
 * moopT() returns plain text (for textContent, .value, alert). moopTh() returns it HTML-escaped,
 * arguments included -- for text going into an HTML string. moopTm('Looking up %s…', html) escapes
 * the sentence and inserts the caller's markup as given. A translation from the spreadsheet is
 * never trusted to carry markup.
 *
 * The translations are window.MOOP_I18N, written by includes/layout.php ONLY on a page shown in
 * another language. On a one-language site (simrbase) it is never set and these return the
 * English, unchanged. A missing translation shows the English (an admin with "Show gaps" on sees
 * it marked, as on the server).
 */
function moopT(text, ...args) {
    return moopFill(moopLookup(text), args);
}

function moopTh(text, ...args) {
    return escapeHtml(moopT(text, ...args));   // the whole sentence, arguments included
}

/** The sentence escaped, the arguments inserted AS GIVEN -- markup the caller built and escaped. */
function moopTm(text, ...htmlArgs) {
    return moopFill(escapeHtml(moopLookup(text)), htmlArgs);
}

function moopLookup(text) {
    const cat = globalThis.MOOP_I18N;   // window in a browser; also runs in the test sandbox
    if (cat && typeof cat[text] === 'string' && cat[text] !== '') return cat[text];
    return globalThis.MOOP_I18N_GAPS ? '\u27E6' + text + '\u27E7' : text;
}

function moopFill(s, args) {
    if (!args.length) return s;
    let next = 0;
    return s.replace(/%%|%(?:(\d+)\$)?[sd]/g, (m, pos) => {
        if (m === '%%') return '%';
        const v = pos ? args[pos - 1] : args[next++];
        return v === undefined ? m : String(v);
    });
}

/**
 * Escape text for use inside a RegExp, so it matches literally ("1.5" matches a dot).
 * The one implementation; two modules each had their own copy.
 *
 * @param {*} text
 * @returns {string}
 */
function escapeRegExp(text) {
    return String(text == null ? '' : text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Rows of objects -> TSV text: a header from the FIRST row's keys, one line per row, "\n" endings.
 *
 * The header comes from the DATA's keys, so it cannot drift from the table that produced it.
 * null/undefined become empty cells; tabs and line breaks inside a value become spaces -- a stray
 * one would shift every later column of that row, the corruption nobody notices until the numbers
 * are wrong. The SAME rules as moop_rows_to_tsv() (lib/rows_tsv.php), which writes the API's TSV;
 * tests/js_smoke_tests.js runs both on the same rows and fails if they differ by a byte.
 *
 * @param {Object[]} rows
 * @returns {string} '' for no rows
 */
function moopRowsToTsv(rows) {
    if (!rows || !rows.length) { return ''; }
    var header = Object.keys(rows[0]);
    var lines = [header.join('\t')];
    rows.forEach(function (row) {
        lines.push(header.map(function (key) {
            var cell = row[key];
            return (cell === null || cell === undefined ? '' : String(cell)).replace(/[\t\r\n]/g, ' ');
        }).join('\t'));
    });
    return lines.join('\n') + '\n';
}

/**
 * Detect if a sequence is protein or nucleotide
 * 
 * @param {string} sequence - The input sequence (FASTA or raw)
 * @returns {Object} Detection result with type and message
 *   Returns: {type: 'protein|nucleotide|unknown', message: 'Human readable message'}
 */
function detectSequenceType(sequence) {
    sequence = sequence.trim();
    
    if (!sequence) {
        return { type: 'unknown', message: '' };
    }
    
    // Remove all header lines (lines starting with >)
    const lines = sequence.split('\n');
    let seq = lines.filter(line => !line.trim().startsWith('>')).join('').toUpperCase();
    
    // Remove whitespace
    seq = seq.replace(/\s/g, '');
    
    // Determine sequence type
    // Count characters to determine type
    const proteinChars = (seq.match(/[EFIPQZ]/g) || []).length;  // Unique to proteins
    const nucleotideChars = (seq.match(/[U]/g) || []).length;    // U is unique to RNA
    const ambiguousDNA = (seq.match(/[ATGCN]/g) || []).length;   // DNA bases + N
    const allProteinAA = (seq.match(/[ACDEFGHIKLMNPQRSTVWY]/g) || []).length;  // All protein AAs
    
    let detectedType = 'unknown';
    let message = '';
    
    // If has U (RNA), it's nucleotide
    if (nucleotideChars > 0) {
        detectedType = 'nucleotide';
        message = '✓ DNA/RNA sequence detected - BLASTn, BLASTx, and tBLASTx programs available';
    } 
    // If has protein-unique chars, it's protein
    else if (proteinChars > 0) {
        detectedType = 'protein';
        message = '✓ Protein sequence detected - BLASTp and tBLASTn programs available';
    }
    // If mostly DNA bases with no protein chars
    else if (ambiguousDNA > allProteinAA * 0.8) {
        detectedType = 'nucleotide';
        message = '✓ DNA/RNA sequence detected - BLASTn, BLASTx, and tBLASTx programs available';
    }
    // Check ratio of protein-likely chars vs DNA-likely chars
    else {
        // Count how many chars are only in protein (D, H, W, Y are very rare in DNA)
        const proteinLikelyChars = (seq.match(/[DWY]/g) || []).length;  // Less common in DNA
        const nucleotideLikelyChars = (seq.match(/[ATGC]/g) || []).length;
        
        // If more protein-likely chars than DNA-likely chars, it's protein
        if (proteinLikelyChars > nucleotideLikelyChars * 0.5) {
            detectedType = 'protein';
            message = '✓ Protein sequence detected - BLASTp and tBLASTn programs available';
        } else {
            detectedType = 'unknown';
            message = '? Sequence type unclear - All programs available';
        }
    }
    
    return { type: detectedType, message: message };
}

/**
 * Filter BLAST program options based on sequence type
 * 
 * @param {string} sequenceType - The detected sequence type ('protein', 'nucleotide', or 'unknown')
 * @param {string} programSelectId - The ID of the select element
 */
function filterBlastPrograms(sequenceType, programSelectId) {
    const programSelect = document.getElementById(programSelectId);
    if (!programSelect) {
        return;
    }
    
    // BLAST program requirements:
    // blastn (DNA→DNA): needs nucleotide query
    // blastp (Protein→Protein): needs protein query
    // blastx (DNA→Protein): needs nucleotide query
    // tblastn (Protein→DNA): needs protein query
    // tblastx (DNA→DNA): needs nucleotide query
    
    // Every selectable program must appear in exactly one of these lists. A program in
    // NEITHER list is disabled for protein AND for nucleotide, so it is only reachable
    // when detection returns 'unknown' -- which is how blastn-short became permanently
    // greyed out for the nucleotide queries it exists to serve.
    const proteinPrograms = ['blastp', 'tblastn'];        // Protein input programs
    const nucleotidePrograms = ['blastn', 'megablast', 'blastn-short', 'blastx', 'tblastx'];  // Nucleotide input programs
    
    // Disable/enable options based on sequence type
    document.querySelectorAll('#' + programSelectId + ' option').forEach(opt => {
        if (sequenceType === 'protein') {
            opt.disabled = !proteinPrograms.includes(opt.value);
        } else if (sequenceType === 'nucleotide') {
            opt.disabled = !nucleotidePrograms.includes(opt.value);
        } else {
            opt.disabled = false;
        }
    });
    
    // If current selection is disabled, select first available -- and say so with a
    // 'change' event, so the page's own handler runs. Setting selectedIndex fires nothing:
    // pasting a protein while in BLASTn-short switched to BLASTp but kept E-value 1000,
    // word size 7 and the "Adjusted for short sequences" badge.
    if (programSelect.options[programSelect.selectedIndex].disabled) {
        for (let i = 0; i < programSelect.options.length; i++) {
            if (!programSelect.options[i].disabled) {
                programSelect.selectedIndex = i;
                programSelect.dispatchEvent(new Event('change'));
                break;
            }
        }
    }
}

/**
 * Update the sequence type info display
 * Shows a message about the detected sequence type and available programs
 * 
 * @param {string} message - The message to display
 * @param {string} containerElementId - The ID of the container div
 * @param {string} messageElementId - The ID of the element within the container for the message text
 */
function updateSequenceTypeInfo(message, containerElementId, messageElementId) {
    const container = document.getElementById(containerElementId);
    const messageEl = document.getElementById(messageElementId);
    
    if (!container || !messageEl) {
        return;
    }
    
    if (message) {
        messageEl.textContent = message;
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}


// Navbar "view as public" switch (includes/toolbar.php, admins only): flipping it submits
// its little form. Was onchange="this.form.submit()" until 2026-11-25; delegated so it needs
// nothing from the page (utilities.js is loaded on every page that draws the navbar).
// (Guarded: tests/js_smoke_tests.js loads this file where there is no document.)
if (typeof document !== 'undefined') {
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'viewAsToggle' && e.target.form) {
            e.target.form.submit();
        }
    });

    // Navbar "Admin tools" link (admins only): show the loading overlay while the dashboard,
    // which can be slow after a data rebuild, loads. Was an inline <script> in toolbar.php.
    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('#admin_tools_link');
        var overlay = document.getElementById('adminLoadingOverlay');
        if (!link || !overlay) return;
        // Skip modified clicks (open-in-new-tab etc.) -- the current page isn't navigating away.
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        overlay.style.display = 'flex';
        link.style.pointerEvents = 'none';
        link.classList.add('disabled');
    });

    // ── Declarative behaviours: the site-wide replacements for inline on* handlers ─────────
    // (2026-11-25, so the CSP can drop 'unsafe-inline'). The markup says what it wants:
    //   <form data-confirm="Question?">        asks before submitting
    //   <button|a data-confirm="Question?">    asks before its click does anything
    //   data-action="reload"                   location.reload()
    //   data-click="#id"                       clicking it clicks that element
    //   <img data-fallback="next">             if it fails to load: hidden, next sibling shown (flex)
    //   <img data-fallback="#id">              if it fails: hidden, #id shown; if it loads: #id hidden
    // ONE copy of each: error-log.js, manage-users.js and manage-annotations.js each had their
    // own data-confirm listener until this; on a page with two, every question came twice.
    // The questions are asked in the CAPTURE phase, i.e. before any listener on the button or
    // form itself runs -- a page's own click listener (taxonomy "Force re-fetch") must not start
    // the work and then ask. "Cancel" stops the event there.
    document.addEventListener('submit', function (e) {
        var form = e.target.closest && e.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
    document.addEventListener('click', function (e) {
        var asker = e.target.closest && e.target.closest('button[data-confirm], a[data-confirm], input[type="submit"][data-confirm]');
        if (asker && !asker.disabled && !window.confirm(asker.getAttribute('data-confirm'))) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;
        if (e.target.closest('[data-action="reload"]')) {
            window.location.reload();
            return;
        }
        var proxy = e.target.closest('[data-click]');
        if (proxy) {
            var target = document.querySelector(proxy.dataset.click);
            if (target) target.click();
        }
    });

    // Image fallbacks. 'error'/'load' do not bubble, so listen in the capture phase; and an image
    // that already failed before this script ran is caught by the sweep at DOMContentLoaded.
    var fallbackOf = function (img) {
        var f = img.getAttribute('data-fallback');
        return f === 'next' ? img.nextElementSibling : (f ? document.querySelector(f) : null);
    };
    var imgFailed = function (img) {
        var alt = fallbackOf(img);
        img.style.display = 'none';
        if (alt) alt.style.display = img.getAttribute('data-fallback') === 'next' ? 'flex' : 'inline';
    };
    document.addEventListener('error', function (e) {
        if (e.target && e.target.tagName === 'IMG' && e.target.hasAttribute('data-fallback')) imgFailed(e.target);
    }, true);
    document.addEventListener('load', function (e) {
        var img = e.target;
        if (img && img.tagName === 'IMG' && img.hasAttribute('data-fallback') && img.getAttribute('data-fallback') !== 'next') {
            var alt = fallbackOf(img);
            if (alt) alt.style.display = 'none';
        }
    }, true);
    var sweepFailedImages = function () {
        document.querySelectorAll('img[data-fallback]').forEach(function (img) {
            if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) imgFailed(img);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sweepFailedImages);
    else sweepFailedImages();
}
