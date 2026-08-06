/**
 * Primer BLAST page — source-list filtering and selection feedback.
 *
 * includes/source-list.php calls these two by name (it renders
 * onclick/onchange attributes), so the names are a contract with that partial.
 */

/** Filter the source list by the text box, matching each line's data-search. */
function filterPrimerSources() {
    var box = document.getElementById('sourceFilter');
    if (!box) { return; }

    var term = box.value.trim().toLowerCase();

    document.querySelectorAll('.fasta-source-line').forEach(function (line) {
        var hay = (line.getAttribute('data-search') || '').toLowerCase();
        line.style.display = (term === '' || hay.indexOf(term) !== -1) ? '' : 'none';
    });
}

/** Clear button on the source filter. */
function clearPrimerSourceFilters() {
    var box = document.getElementById('sourceFilter');
    if (box) {
        box.value = '';
    }
    filterPrimerSources();
}

/**
 * Selection changed. Read only within this page's own source list — never a
 * document-wide query — so a page rendering the component more than once cannot
 * read another instance's state.
 */
function updatePrimerSelection() {
    var chosen = document.querySelector('.fasta-source-selector input[name="selected_source"]:checked');
    var target = document.getElementById('primerCurrentSelection');
    if (!target) { return; }

    if (!chosen) {
        target.innerHTML = '<span class="text-muted">None selected</span>';
        return;
    }

    var organism = chosen.getAttribute('data-organism') || '';
    var assembly = chosen.getAttribute('data-assembly') || '';
    var geneSet  = chosen.getAttribute('data-gene-set') || '';

    var label = organism.replace(/_/g, ' ') + ' — ' + assembly;
    if (geneSet !== '') {
        label += ' — ' + geneSet;
    }

    target.textContent = label;
}

/** Largest file we will load into the box, matching the server-side limit. */
var PRIMER_MAX_UPLOAD_BYTES = 1048576;

/**
 * Load a chosen file straight into the text box, then clear the file input.
 *
 * This makes the TEXT BOX the single source of truth. The alternative -- letting
 * the file win server-side while the box still shows something else -- means a
 * user who picks a file and then edits the text watches their edits be silently
 * discarded, and cannot see what is actually about to be searched.
 *
 * Clearing the input afterwards is the point, not tidiness: with no file attached,
 * only the box is submitted, so what the user sees is exactly what runs. The
 * server keeps its own file handling for the no-JavaScript case.
 */
function loadPrimerFileIntoBox() {
    var file     = document.getElementById('primer_file');
    var textarea = document.getElementById('primers');
    var notice   = document.getElementById('fileOverrideNotice');
    var name     = document.getElementById('fileOverrideName');

    if (!file || !textarea || !file.files || file.files.length === 0) {
        return;
    }

    var chosen = file.files[0];

    if (chosen.size > PRIMER_MAX_UPLOAD_BYTES) {
        if (notice && name) {
            notice.classList.remove('d-none', 'alert-info');
            notice.classList.add('alert-danger');
            name.textContent = chosen.name + ' is larger than 1 MB and was not loaded.';
        }
        file.value = '';
        return;
    }

    var reader = new FileReader();

    reader.onload = function (e) {
        textarea.value = String(e.target.result).trim();

        // The file has been read into the box; detach it so only the box submits.
        file.value = '';

        if (notice && name) {
            notice.classList.remove('d-none', 'alert-danger');
            notice.classList.add('alert-info');
            name.textContent = 'Loaded ' + chosen.name + ' — edit it below if you need to.';
        }
    };

    reader.onerror = function () {
        if (notice && name) {
            notice.classList.remove('d-none', 'alert-info');
            notice.classList.add('alert-danger');
            name.textContent = 'Could not read ' + chosen.name + '.';
        }
        file.value = '';
    };

    reader.readAsText(chosen);
}

/** Empty the file input. Assigning '' is the only reliable cross-browser reset. */
function clearPrimerFile() {
    var file   = document.getElementById('primer_file');
    var notice = document.getElementById('fileOverrideNotice');
    if (file) {
        file.value = '';
    }
    if (notice) {
        notice.classList.add('d-none');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var box = document.getElementById('sourceFilter');
    if (box) {
        box.addEventListener('input', filterPrimerSources);
    }
    updatePrimerSelection();

    var file = document.getElementById('primer_file');
    if (file) {
        file.addEventListener('change', loadPrimerFileIntoBox);
    }

    var clearBtn = document.getElementById('clearPrimerFile');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearPrimerFile);
    }

    var resetBtn = document.getElementById('resetPrimerForm');
    if (resetBtn) {
        resetBtn.addEventListener('click', resetPrimerForm);
    }

    // Bootstrap tooltips are initialised per module in this codebase, not globally,
    // so the "!" markers on unusable sources need it here. Without this they still
    // work -- the title attribute gives a native tooltip -- just unstyled.
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    }
});

/**
 * Return the whole form to its starting state: primers, assembly, and options.
 *
 * The form's native reset() is NOT enough. After a search the page re-renders
 * with the SUBMITTED values as the field defaults, so reset() would restore the
 * last search rather than clear it -- the opposite of what the button says.
 * Defaults therefore come from data attributes written by PHP, so they cannot
 * drift from the server's idea of them.
 */
function resetPrimerForm() {
    var form = document.getElementById('primerBlastForm');
    if (!form) { return; }

    var textarea = document.getElementById('primers');
    if (textarea) {
        textarea.value = '';
    }

    clearPrimerFile();

    // Deselect the assembly, and clear the filter that may be hiding most of the list.
    form.querySelectorAll('input[name="selected_source"]:checked').forEach(function (radio) {
        radio.checked = false;
    });
    var filter = document.getElementById('sourceFilter');
    if (filter) {
        filter.value = '';
    }
    filterPrimerSources();
    updatePrimerSelection();

    var mismatch = document.getElementById('max_mismatch');
    if (mismatch) {
        mismatch.value = form.getAttribute('data-default-mismatch') || '1';
    }

    var product = document.getElementById('max_product');
    if (product) {
        product.value = form.getAttribute('data-default-product') || '10000';
    }

    var mode = form.getAttribute('data-default-mode') || 'genome';
    var modeRadio = form.querySelector('input[name="search_mode"][value="' + mode + '"]');
    if (modeRadio) {
        modeRadio.checked = true;
    }

    // Take away the previous search's output as well. It is server-rendered HTML
    // sitting above and below the form, so clearing the INPUTS alone leaves an
    // error or a result table describing a search the form no longer represents --
    // which reads as "clearing did nothing".
    var stale = document.getElementById('primerSearchError');
    if (stale) {
        stale.remove();
    }
    var results = document.getElementById('primerResults');
    if (results) {
        results.innerHTML = '';
    }

    if (textarea) {
        textarea.focus();
    }
}
