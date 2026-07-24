// DataTables Export/Download Configuration
// Centralized button and export settings for consistent behavior across all pages
//
// ---------------------------------------------------------------------------
// TWO TABLE FAMILIES SHARE THIS TOOLBAR
//
//   search results  js/modules/shared-results-table.js -> getSearchResultsButtons()
//                   Rendered ONE TABLE PER ORGANISM, several on a page at once.
//                   Has a row-select checkbox column; exports honour the selection.
//   gene page       js/modules/parent-tools.js -> reinitialize() -> getAnnotationButtons()
//                   One table per annotation type. No checkbox column.
//
// Both facts matter below. "One table per organism" is why nothing here may read the
// DOM globally, and "no checkbox column" is why column 0 is not special.
//
// ---------------------------------------------------------------------------
// NEVER SELECT ROWS OR COLUMNS WITH A PAGE-WIDE jQUERY SELECTOR
//
// This file used to do `$('input.row-select:checked')` — every checked row ON THE PAGE —
// in the export guard and in the FASTA action, while `exportOptions.rows` correctly
// filtered per table. On any multi-organism search the two disagreed, and produced two
// silent failures:
//
//   - Checking a row in organism A's table satisfied organism B's export guard, so
//     B's CSV downloaded with headers and no rows, and said nothing.
//   - FASTA read the assembly from the first checked row ANYWHERE on the page, so
//     B's FASTA button requested organism B against organism A's assembly — a
//     mismatched pair, i.e. wrong data rather than a wrong-looking page.
//
// DataTables hands every button its own `dt` instance. Scope every read to it:
// `$(dt.rows().nodes())`, never `$(...)` on the document. Same for columns — see
// NO_EXPORT_CLASS and FEATURE_ID_CLASS, which replaced positional index checks.

const DataTableExportConfig = {
    // A column carrying this class is omitted from every export. Set from the column
    // registry in shared-results-table.js (the row-select checkbox sets it).
    //
    // This replaces a hardcoded `idx === 0`, which assumed every table starts with a
    // checkbox. The gene-page tables do not: their column 0 is "Organism", tagged
    // `export-only` precisely BECAUSE it is meant to appear in exports. The positional
    // rule silently dropped it from every gene-page download.
    NO_EXPORT_CLASS: 'dt-no-export',

    // Marks the column holding the feature identifier, for the FASTA action. Previously
    // located by matching header text against 'feature id' — a display string that any
    // rename or translation would have broken without a word of warning.
    FEATURE_ID_CLASS: 'dt-feature-id',

    // Button definitions (DRY)
    //
    // NOTE: there is deliberately no print/PDF button. It could not produce a PDF —
    // that needs pdfmake, which is not vendored — so the button opened the browser
    // print dialog under a label promising a file it could not make. It also had to
    // exclude hidden columns to avoid a misalignment bug that no longer exists. A
    // wide genomic table is not a paper artefact; Copy/CSV/Excel cover taking the
    // data away, and Ctrl+P still prints the page. See
    // notes/RESULTS_TABLE_TOOLBAR_REVIEW.md.
    buttonDefs: {
        copy: {
            extend: 'copy',
            text: '<i class="far fa-copy"></i> Copy'
        },
        csv: {
            extend: 'csv',
            text: '<i class="fas fa-file-csv"></i> CSV',
            title: function() {
                // Remove site title from filename to avoid trailing dash
                const title = document.title;
                return title.replace(' - ' + siteTitle, '').trim();
            }
        },
        excel: {
            extend: 'excel',
            text: '<i class="fas fa-file-excel"></i> Excel',
            title: function() {
                // Remove site title from filename to avoid trailing dash
                const title = document.title;
                return title.replace(' - ' + siteTitle, '').trim();
            }
        }
    },

    // ── Scoped helpers — all reads confined to one table ─────────────────────

    /** Row nodes with a ticked checkbox, in THIS table only. */
    selectedRowNodes: function(dt) {
        return dt.rows(function(idx, data, node) {
            return $(node).find('input.row-select:checked').length > 0;
        }).nodes().toArray();
    },

    /** Index of the feature-identifier column, by class then by header text. */
    featureIdColumn: function(dt) {
        let byClass = -1, byText = -1;
        dt.columns().every(function(idx) {
            const header = $(dt.column(idx).header());
            if (header.hasClass(DataTableExportConfig.FEATURE_ID_CLASS)) byClass = idx;
            if (header.text().trim().toLowerCase() === 'feature id')      byText  = idx;
        });
        return byClass !== -1 ? byClass : byText;
    },

    /**
     * A short note attached to THIS table's toolbar.
     *
     * Replaces alert(), which stopped the page dead for what is guidance, not an error.
     * Same soft amber treatment as .tools-select-hint elsewhere (BLAST, Retrieve
     * Sequences, the search form), so "you skipped a step" reads the same everywhere.
     */
    notify: function(dt, message) {
        const $container = $(dt.table().container());
        let $note = $container.find('.dt-export-note');
        if (!$note.length) {
            $note = $('<div class="tools-select-hint small mt-2 dt-export-note"></div>');
            const $buttons = $container.find('.dt-buttons').first();
            if ($buttons.length) $buttons.after($note);
            else                 $container.prepend($note);
        }
        $note.html('<i class="fa fa-circle-info me-1"></i> ' + message).css('display', 'flex');
        clearTimeout($note.data('hide-timer'));
        $note.data('hide-timer', setTimeout(() => $note.fadeOut(200), 8000));
    },

    /** True when at least one row of THIS table is ticked; otherwise notes and returns false. */
    requireSelectedRows: function(dt) {
        if (DataTableExportConfig.selectedRowNodes(dt).length > 0) return true;
        DataTableExportConfig.notify(dt,
            'Select at least one row first — tick the boxes you want, or use <strong>Select All</strong>.');
        return false;
    },

    /** Feature identifiers from the given row nodes of THIS table, de-duplicated. */
    extractFeatureIds: function(dt, rowNodes) {
        const colIdx = DataTableExportConfig.featureIdColumn(dt);
        if (colIdx === -1) return null;

        const ids = [];
        rowNodes.forEach(node => {
            const cell = dt.row(node).data()[colIdx] || '';
            const div = document.createElement('div');
            div.innerHTML = cell;
            const text = (div.textContent || div.innerText || '').trim();
            if (text) ids.push(text);
        });
        return [...new Set(ids)];
    },

    // ── FASTA export ─────────────────────────────────────────────────────────

    /**
     * FASTA for the selected rows of THIS table.
     *
     * Sequences are resolved against an assembly, so the assembly has to be right. Two
     * things previously made it wrong: the rows were read page-wide, and the assembly
     * was taken from `checkedRows[0]` — the first ticked row anywhere — which on a
     * multi-organism page belonged to a different organism entirely.
     *
     * Rows are now grouped by their own data-genome-accession. One organism can carry
     * several assemblies, so a selection CAN legitimately span two; the retrieval
     * endpoint takes a single assembly, so rather than silently resolving everything
     * against whichever came first, we say so and let the user narrow. (Supporting a
     * genuine multi-assembly fetch means teaching the endpoint to take pairs — noted in
     * notes/RESULTS_TABLE_TOOLBAR_REVIEW.md, not done here.)
     */
    fastaExportAction: function(e, dt, button, config) {
        if (!DataTableExportConfig.requireSelectedRows(dt)) return;

        const rowNodes = DataTableExportConfig.selectedRowNodes(dt);

        // Organism comes from THIS table's own results block. window.currentOrganism is
        // only a fallback: on multi-organism pages it names one organism while the table
        // clicked may belong to another.
        const organismDiv = $(dt.table().node()).closest('.organism-results');
        const anchorId    = organismDiv.attr('id');
        const organism    = (anchorId ? anchorId.replace('results-', '') : '') || window.currentOrganism;

        if (!organism) {
            DataTableExportConfig.notify(dt, 'Could not tell which organism these rows belong to.');
            return;
        }

        // Group by assembly AND gene set. Sequences are stored per gene set
        // (organisms/{organism}/{assembly}/{gene_set}/), so BOTH are needed to locate a
        // FASTA file; sending only the assembly is what made this button return nothing.
        const groups = {};
        rowNodes.forEach(node => {
            const asm = decodeURIComponent($(node).attr('data-genome-accession') || '');
            const gs  = decodeURIComponent($(node).attr('data-gene-set') || '');
            const key = asm + ' ' + gs;
            if (!groups[key]) groups[key] = { assembly: asm, geneSet: gs, nodes: [] };
            groups[key].nodes.push(node);
        });

        const keys = Object.keys(groups).filter(k => groups[k].assembly !== '');
        if (keys.length === 0) {
            DataTableExportConfig.notify(dt, 'Assembly information is not available for the selected rows.');
            return;
        }
        if (keys.length > 1) {
            const labels = keys.map(k => groups[k].assembly + (groups[k].geneSet ? ' / ' + groups[k].geneSet : ''));
            DataTableExportConfig.notify(dt,
                'Your selection spans ' + keys.length + ' assembly/gene-set combinations (' + labels.join(', ') + '). '
                + 'FASTA is fetched from one at a time — switch on the <strong>Assembly</strong> and '
                + '<strong>Gene Set</strong> columns and select rows from a single one.');
            return;
        }

        const assembly   = groups[keys[0]].assembly;
        const geneSet    = groups[keys[0]].geneSet;
        const featureIds = DataTableExportConfig.extractFeatureIds(dt, groups[keys[0]].nodes);

        if (featureIds === null) {
            DataTableExportConfig.notify(dt, 'Feature ID column not found in this table.');
            return;
        }
        if (featureIds.length === 0) {
            DataTableExportConfig.notify(dt, 'No valid Feature IDs found in the selected rows.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';

        let formUrl = sitePath + '/tools/retrieve_selected_sequences.php'
                    + '?organism=' + encodeURIComponent(organism)
                    + '&assembly=' + encodeURIComponent(assembly);
        if (geneSet) formUrl += '&gene_set=' + encodeURIComponent(geneSet);

        // Add context parameters if available (from pageContext global variable)
        if (typeof pageContext !== 'undefined') {
            if (pageContext.context_organism) formUrl += '&context_organism=' + encodeURIComponent(pageContext.context_organism);
            if (pageContext.context_assembly) formUrl += '&context_assembly=' + encodeURIComponent(pageContext.context_assembly);
            if (pageContext.context_group)    formUrl += '&context_group='    + encodeURIComponent(pageContext.context_group);
            if (pageContext.context_page)     formUrl += '&context_page='     + encodeURIComponent(pageContext.context_page);
            if (pageContext.multi_search && Array.isArray(pageContext.multi_search)) {
                pageContext.multi_search.forEach(org => {
                    formUrl += '&multi_search[]=' + encodeURIComponent(org);
                });
            }
            if (pageContext.display_name) formUrl += '&display_name=' + encodeURIComponent(pageContext.display_name);
        }

        form.action = formUrl;
        form.target = '_blank';

        const idInput = document.createElement('input');
        idInput.type  = 'hidden';
        idInput.name  = 'uniquenames';
        idInput.value = featureIds.join(',');

        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    },

    // ── Buttons ──────────────────────────────────────────────────────────────

    /**
     * Build one export button.
     *
     * Columns are chosen by class, not position, so a table declares what is exportable
     * instead of this file assuming it. Hidden columns ARE exported — that is the whole
     * point of the default-hidden Species / Assembly / Gene Set columns: off-screen for
     * readability, present in the file.
     */
    createButton: function(buttonType, selectedRowsOnly = false) {
        const exportOptions = {
            columns: function(idx, data, node) {
                return !$(node).hasClass(DataTableExportConfig.NO_EXPORT_CLASS);
            }
        };

        if (selectedRowsOnly) {
            exportOptions.rows = function(idx, data, node) {
                return $(node).find('input.row-select:checked').length > 0;
            };
        }

        return {
            extend: buttonType,
            ...this.buttonDefs[buttonType],
            className: 'btn btn-sm btn-secondary',
            exportOptions: exportOptions,
            // Guard reads THIS table via dt, so a sibling table's selection cannot
            // satisfy it and produce a headers-only file.
            init: selectedRowsOnly ? function(dt, node, config) {
                $(node).on('click.dt', function(e) {
                    if (DataTableExportConfig.selectedRowNodes(dt).length === 0) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        DataTableExportConfig.requireSelectedRows(dt);
                        return false;
                    }
                });
            } : undefined
        };
    },

    // Get buttons for annotation tables (without column visibility)
    getAnnotationButtons: function() {
        return [
            this.createButton('copy'),
            this.createButton('csv'),
            this.createButton('excel')
        ];
    },

    // Get buttons for search results tables (with column visibility)
    getSearchResultsButtons: function() {
        return [
            this.createButton('copy', true),
            this.createButton('csv', true),
            this.createButton('excel', true),
            {
                text: '<i class="fa fa-dna"></i> FASTA',
                className: 'btn btn-sm btn-secondary',
                action: this.fastaExportAction,
                init: function(dt, node, config) {
                    $(node).on('click.dt', function(e) {
                        if (DataTableExportConfig.selectedRowNodes(dt).length === 0) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            DataTableExportConfig.requireSelectedRows(dt);
                            return false;
                        }
                    });
                }
            },
            'colvis'
        ];
    },

    // Legacy support - use getAnnotationButtons instead
    get buttons() {
        return this.getAnnotationButtons();
    },

    // Standard DataTable options
    defaultOptions: {
        dom: 'Bfrtlip',
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[4, 'asc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        },
        columnDefs: [
            { targets: '_all', className: 'dt-body-left' },
            { targets: [0, 1, 2, 3], visible: false, className: 'export-only' }
        ]
    },

    // Helper function to initialize DataTables with standard config
    initialize: function(tableSelector, customOptions = {}) {
        return $(tableSelector).DataTable({
            buttons: this.buttons,
            ...this.defaultOptions,
            ...customOptions
        });
    },

    // Helper function to reinitialize existing DataTables
    reinitialize: function(tableSelector, customOptions = {}) {
        var $table = $(tableSelector);

        if ($.fn.DataTable.isDataTable(tableSelector)) {
            $table.DataTable().destroy();
        }

        return this.initialize(tableSelector, customOptions);
    }
};

// Custom search plugin for substring matching on tables with 'substring-search' class
$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    // Only apply to tables with the substring-search class
    if (!$(settings.nTable).hasClass('substring-search')) {
        return true;
    }

    var searchValue = settings.oPreviousSearch.sSearch;
    if (!searchValue) {
        return true;
    }

    searchValue = searchValue.toLowerCase();

    // Search through visible columns (skip hidden export-only columns 0-3)
    for (var i = 4; i < data.length; i++) {
        if (data[i].toLowerCase().indexOf(searchValue) !== -1) {
            return true;
        }
    }

    return false;
});
