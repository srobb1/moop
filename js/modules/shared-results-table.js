/**
 * Shared Results Table
 *
 * The search-results table used by EVERY search page — search, organism, assembly,
 * gene_set, groups, multi_organism — via js/modules/annotation-search.js.
 *
 * ---------------------------------------------------------------------------
 * ONE COLUMN REGISTRY — read this before adding a column
 *
 * RESULT_COLUMNS below is the single declaration of what a results table contains.
 * The table markup, the `data-column-index` attributes, the DataTables `columnDefs`
 * (indices and visibility), and every table variant are all DERIVED from it.
 *
 * Adding a column is therefore ONE array entry, not an edit in a dozen places.
 *
 * It used to be otherwise, and that is why this exists. The table was hand-written
 * three times — the full table, the "simple" unique-feature table, and a third copy
 * built inline when the user clicks "Expand All Matches" — each with its own <th>
 * block, its own <td> block, its own hardcoded `data-column-index="6"` and its own
 * `columnDefs: [{ targets: 6 }]`. Column identity was POSITIONAL, so inserting a
 * column meant renumbering every column after it, in three copies, by hand. The
 * three copies had already drifted apart (one highlighted search terms and rendered
 * a literal "undefined" for a nameless feature; another did neither).
 *
 * Assembly and Gene Set are the columns that forced the issue: this table predates
 * MOOP's gene_set layer, so a result row could not say which assembly or gene set it
 * came from — and an organism can have several of each, which makes "gene X in
 * Nematostella" ambiguous on its own.
 *
 * ---------------------------------------------------------------------------
 * VARIANTS
 *
 *   'full'       feature x annotation rows, with the three annotation columns
 *   'uniquename' a Feature-ID search: same, minus the annotation columns
 *   'simple'     one row per unique feature, plus a "Matches" count
 *
 * A column declares which variants it appears in; '*' means all three. That replaces
 * the three near-identical builders with one filter over one list.
 *
 * ---------------------------------------------------------------------------
 * HIDDEN-BUT-EXPORTED COLUMNS (`hidden: true`)
 *
 * Species, Assembly and Gene Set are hidden by default to keep the table readable,
 * but they are ALWAYS present in Copy/CSV/Excel exports and can be switched on with
 * the colvis button. Hiding is expressed ONCE, as DataTables `visible: false`.
 *
 * Do NOT also tag these columns `className: 'export-only'`. That class carries
 * `display: none` from css/parent.css, which DataTables cannot override when colvis
 * re-shows the column: the <th> reappears but its <td>s stay collapsed, so the header
 * row gains a column the body does not have and every heading after it sits above the
 * wrong data. That is a real bug this file shipped for a long time — turning Species
 * on via colvis produced exactly that misaligned table. Export inclusion does not come
 * from the class anyway; it comes from `exportOptions.columns` in
 * js/modules/datatable-config.js, which includes hidden columns by design.
 */

/**
 * THE COLUMN REGISTRY.
 *
 *   key       stable identifier (never positional)
 *   label     header text
 *   width     header width hint
 *   variants  ['*'] or a subset of ['full','uniquename','simple']
 *   hidden    default-hidden, still exported, toggleable via colvis
 *   wrap      long free text — gets the .wrap-text class
 *   orderable false to disable sorting
 *   filter    false to suppress the per-column filter box
 *   render    (result, ctx, item) => cell HTML
 *
 * `select` must remain first: datatable-config.js excludes column index 0 from every
 * export, which is what keeps the checkbox out of your spreadsheet.
 */
const RESULT_COLUMNS = [
    {
        key: 'select', label: 'Select', width: 120, variants: ['*'],
        orderable: false, filter: false, exportable: false,
        render: () => '<input type="checkbox" class="row-select">'
    },
    {
        key: 'species', label: 'Species', width: 200, variants: ['*'], hidden: true,
        render: r => `<em>${r.genus || ''} ${r.species || ''}</em>` +
                     (r.common_name ? `<br><small class="text-muted">${r.common_name}</small>` : '')
    },
    {
        key: 'assembly', label: 'Assembly', width: 200, variants: ['*'], hidden: true,
        render: r => resultAssemblyLabel(r)
    },
    {
        key: 'gene_set', label: 'Gene Set', width: 160, variants: ['*'], hidden: true,
        render: r => r.gene_set || '—'
    },
    {
        key: 'type', label: 'Type', width: 120, variants: ['*'],
        render: r => r.feature_type || ''
    },
    {
        key: 'feature_id', label: 'Feature ID', width: 280, variants: ['*'],
        cssClass: 'dt-feature-id',   // located by class, not header text — see datatable-config.js
        render: (r, ctx) =>
            `<a href="${resultFeatureUrl(ctx, r.feature_uniquename)}" target="_blank">${r.feature_uniquename}</a>`
    },
    {
        key: 'name', label: 'Name', width: 180, variants: ['*'],
        render: (r, ctx) => highlightSearchTerms(r.feature_name, ctx.keywords) || '—'
    },
    {
        key: 'description', label: 'Description', width: 300, variants: ['*'], wrap: true,
        render: (r, ctx) => highlightSearchTerms(r.feature_description, ctx.keywords) || '—'
    },
    {
        key: 'ann_source', label: 'Annotation Source', width: 300, variants: ['full'],
        render: r => r.annotation_source_name || ''
    },
    {
        key: 'ann_id', label: 'Annotation ID', width: 220, variants: ['full'],
        render: r => r.annotation_accession || ''
    },
    {
        key: 'ann_desc', label: 'Annotation Description', width: 500, variants: ['full'], wrap: true,
        render: (r, ctx) => highlightSearchTerms(r.annotation_description, ctx.keywords) || ''
    },
    {
        key: 'matches', label: 'Matches', width: 120, variants: ['simple'],
        render: (r, ctx, item) =>
            `<span class="badge bg-secondary">${item.annotations ? item.annotations.length : 0}</span>`
    }
];

/** The columns of one variant, in order. Index in this array IS the DataTables index. */
function resultColumnsFor(variant) {
    return RESULT_COLUMNS.filter(c => c.variants.includes('*') || c.variants.includes(variant));
}

/**
 * How an assembly is named in a results row.
 *
 * Human-readable name plus accession when a name exists, so a downloaded table is
 * self-explanatory and still carries the precise identifier; the accession alone
 * otherwise. Matches the "Name (Accession)" convention the organism and scope pages use.
 */
function resultAssemblyLabel(r) {
    const acc  = r.genome_accession || '';
    const name = r.genome_name || '';
    if (name && name !== acc) return acc ? `${name} (${acc})` : name;
    return acc || '—';
}

/** Feature page URL, carrying multi-organism search context when the page has it. */
function resultFeatureUrl(ctx, uniquename) {
    let url = `${ctx.sitePath}/${ctx.linkBasePath}?organism=${encodeURIComponent(ctx.organism)}`
            + `&uniquename=${encodeURIComponent(uniquename)}`;
    if (typeof pageContext !== 'undefined' && pageContext.multi_search && Array.isArray(pageContext.multi_search)) {
        pageContext.multi_search.forEach(org => {
            url += '&multi_search[]=' + encodeURIComponent(org);
        });
    }
    return url;
}

/** Wrap search terms in a highlight span. Quoted input highlights the phrase as a unit. */
function highlightSearchTerms(text, keywords) {
    if (!text) return text || '';
    if (!keywords) return text;
    const HL  = `<strong style="background-color: #fff3cd; font-weight: bold;">$&</strong>`;
    const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    const trimmed = keywords.trim();
    if (/^".+"$/.test(trimmed)) {
        return text.replace(new RegExp(esc(trimmed.slice(1, -1)), 'gi'), HL);
    }
    const terms = trimmed.split(/\s+/).filter(t => t.length >= 3);
    if (terms.length === 0) return text;
    let highlighted = text;
    terms.forEach(term => {
        highlighted = highlighted.replace(new RegExp(esc(term), 'gi'), HL);
    });
    return highlighted;
}

/**
 * <thead> for a variant — filter boxes, the Select All button and column indices are all
 * derived from the registry.
 *
 * The controls are rendered INTO the header here rather than injected after DataTables
 * initialises. That matters for the default-hidden columns: DataTables detaches a hidden
 * column's <th> from the DOM, so a post-init pass over `.column-filter-container` never
 * sees them, and a column later revealed via colvis would come back with an empty filter
 * cell. Building them up-front means every column owns its control whether it is currently
 * shown or not.
 */
/**
 * The Select All button's DOM id. Derived in ONE place because the header builder writes
 * the button and the init wires its click — if those two ever computed the id differently
 * the button would render but do nothing.
 */
function resultsSelectAllId(selectId, viewType = '') {
    return 'toggle-select-btn' + selectId + (viewType ? '-' + viewType : '');
}

function buildResultsThead(variant, buttonId) {
    const FILTER_INPUT = '<input style="text-align:left; border: solid 1px #f0f0f0; border-radius: 4px; width: 100%; background-color: #ffffff; color: #212529;" type="text" placeholder="Filter..." class="column-search">';
    const selectAllBtn = '<button style="width:110px; height: 32px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0; text-align: left; padding-left: 8px; display: flex; align-items: center;" class="btn btn_select_all" id="' + buttonId + '"><span>Select All</span></button>';

    let html = '<thead><tr>';
    resultColumnsFor(variant).forEach((col, idx) => {
        html += `<th style="width: ${col.width}px;">`
              + `<div class="column-filter-container" data-column-index="${idx}">`
              + (col.filter === false ? selectAllBtn : FILTER_INPUT)
              + `</div>`
              + `<div>${col.label}</div>`
              + `</th>`;
    });
    return html + '</tr></thead>';
}

/**
 * <tbody> for a variant.
 *
 * @param {Array} items [{ result, annotations }] — annotations is set for 'simple',
 *                      where a row stands for a feature and counts its matches.
 */
function buildResultsTbody(variant, items, ctx) {
    const cols = resultColumnsFor(variant);
    let html = '<tbody>';
    items.forEach(item => {
        const r = item.result;
        // Both attributes are read by the FASTA export in datatable-config.js. The gene set
        // is REQUIRED, not decoration: sequences live in
        // organisms/{organism}/{assembly}/{gene_set}/, so without it the retrieval endpoint
        // fell back to a gene set named "v1" — of which zero exist anywhere in the data tree —
        // and every per-table FASTA silently returned no sequences.
        html += `<tr data-genome-accession="${encodeURIComponent(r.genome_accession || '')}"`
              + ` data-gene-set="${encodeURIComponent(r.gene_set || '')}">`;
        cols.forEach(col => { html += `<td>${col.render(r, ctx, item)}</td>`; });
        html += '</tr>';
    });
    return html + '</tbody>';
}

/** DataTables columnDefs for a variant — targets are computed, never hand-numbered. */
function resultsColumnDefs(variant) {
    // The no-export marker is read at call time so this file does not depend on
    // datatable-config.js having loaded first; the fallback keeps the literal in one
    // conceptual place if load order ever changes.
    const noExport = (typeof DataTableExportConfig !== 'undefined' && DataTableExportConfig.NO_EXPORT_CLASS)
                   || 'dt-no-export';

    return resultColumnsFor(variant).map((col, idx) => {
        const def = { targets: idx };
        if (col.orderable === false) def.orderable = false;
        if (col.hidden)              def.visible   = false;

        const classes = [];
        if (col.wrap)               classes.push('wrap-text');
        if (col.exportable === false) classes.push(noExport);
        if (col.cssClass)           classes.push(col.cssClass);
        if (classes.length)         def.className = classes.join(' ');

        return def;
    });
}

/** Normalise a plain results array into the row-item shape buildResultsTbody expects. */
function resultItems(results) {
    return results.map(r => ({ result: r, annotations: null }));
}

/**
 * Creates an organism results table ('full', or 'uniquename' when the search matched
 * feature identifiers and there are no annotation columns).
 */
function createOrganismResultsTable(organism, results, sitePath, linkBasePath = 'tools/parent.php', imageUrl = '', searchKeywords = '') {
    const tableId  = 'resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const selectId = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const variant  = (results[0]?.uniquename_search ?? false) ? 'uniquename' : 'full';
    const ctx      = { sitePath, linkBasePath, organism, keywords: searchKeywords };

    const anchorId = 'results-' + selectId;
    const html = `
        <div class="organism-results" id="${anchorId}">
            <h5>${organismHeaderHtml(organism, results, sitePath, imageUrl, true)}
                <span class="badge results_annotation_count">${results.length} annotation${results.length !== 1 ? 's' : ''}</span>
            </h5>
            <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                <table id="${tableId}" class="table table-sm table-striped table-hover results-table">
                    ${buildResultsThead(variant, resultsSelectAllId(selectId))}
                    ${buildResultsTbody(variant, resultItems(results), ctx)}
                </table>
            </div>
        </div>
    `;

    setTimeout(() => initializeResultsTable('#' + tableId, selectId, variant === 'uniquename'), 100);
    return html;
}

/** The organism heading (thumbnail + linked binomial + common name) shared by both tables. */
function organismHeaderHtml(organism, results, sitePath, imageUrl, linked) {
    const genus      = results[0]?.genus || '';
    const species    = results[0]?.species || '';
    const commonName = results[0]?.common_name || '';
    const fallbackId = 'icon-' + organism.replace(/[^a-zA-Z0-9]/g, '_');

    const imageHtml = imageUrl
        ? `<img src="${imageUrl}" class="organism-thumbnail" style="height: 24px; width: 24px; margin-right: 8px; border-radius: 3px;" onerror="this.style.display='none'; document.getElementById('${fallbackId}').style.display='inline';" onload="document.getElementById('${fallbackId}').style.display='none';">
           <i class="fa fa-dna" id="${fallbackId}" style="margin-right: 8px; display: none;"></i>`
        : `<i class="fa fa-dna" style="margin-right: 8px;"></i>`;

    const name = linked
        ? `<a href="${sitePath}/tools/organism.php?organism=${encodeURIComponent(organism)}" style="text-decoration: none; color: #0f766e;"><em>${genus} ${species}</em> <i class="fa fa-external-link-alt" style="font-size: 0.8em; margin-left: 0.25rem;"></i></a>`
        : `<em>${genus} ${species}</em>`;

    return imageHtml + name + (commonName ? ` (${commonName})` : '');
}

/**
 * Initializes a DataTable with export functionality and column filtering
 *
 * @param {string} tableId - jQuery selector for the table (e.g., '#resultsTable_organism')
 * @param {string} selectId - Unique identifier for the select all button
 * @param {boolean} isUniquenameSearch - Whether this is a uniquename-only search (no annotation columns)
 * @param {string} viewType - Optional view type identifier ('simple' or 'expanded') for unique button IDs
 */
function initializeResultsTable(tableId, selectId, isUniquenameSearch, viewType = '') {
    initResultsDataTable(tableId, isUniquenameSearch ? 'uniquename' : 'full',
                         resultsSelectAllId(selectId, viewType));
}

/**
 * The one DataTable setup — filter boxes, columnDefs, exports, select-all wiring.
 * Shared by every variant so their behaviour cannot drift apart again.
 */
function initResultsDataTable(tableSelector, variant, buttonId) {
    const table = $(tableSelector).DataTable({
        dom: 'Brtlpi',
        pageLength: 25,
        stateSave: false,
        orderCellsTop: false,
        buttons: DataTableExportConfig.getSearchResultsButtons(),
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        fixedHeader: false,
        columnDefs: resultsColumnDefs(variant),
        colReorder: true,
        retrieve: true,
        initComplete: function () {
            // Force remove sorting classes from the Select column
            $(tableSelector + ' thead th:first-child').removeClass('sorting sorting_asc sorting_desc');
        }
    });

    // Per-column filtering. Delegated from <thead> rather than bound to each input, so a
    // column that colvis re-attaches later arrives already wired. Filtering runs against
    // DataTables' full data model, so it spans every page, not just the one on screen.
    $(tableSelector + ' thead')
        .off('keyup.moopfilter change.moopfilter')
        .on('keyup.moopfilter change.moopfilter', 'input.column-search', function () {
            const columnIndex = $(this).closest('.column-filter-container').data('column-index');
            if (columnIndex === undefined) return;
            const dt = $(tableSelector).DataTable();
            if (dt.column(columnIndex).search() !== this.value) {
                dt.column(columnIndex).search(this.value).draw();
            }
        });

    // Clicking a filter box must not sort the column underneath it.
    $(tableSelector + ' thead')
        .off('click.moopfilter')
        .on('click.moopfilter', 'input.column-search', e => e.stopPropagation());

    const syncSelectAllLabel = () => {
        const allRows = table.rows().nodes();
        const total   = $(allRows).find('input.row-select').length;
        const checked = $(allRows).find('input.row-select:checked').length;
        $('#' + buttonId).find('span').text(checked === total && total > 0 ? 'Deselect All' : 'Select All');
    };

    $('#' + buttonId).off('click').on('click', function (e) {
        e.preventDefault();
        const allRows = table.rows().nodes();
        const total   = $(allRows).find('input.row-select').length;
        const checked = $(allRows).find('input.row-select:checked').length;
        $(allRows).find('input.row-select').prop('checked', checked !== total);
        syncSelectAllLabel();
    });

    $(tableSelector).on('change', '.row-select', syncSelectAllLabel);

    return table;
}

/**
 * Groups search results by feature_uniquename, preserving database sort order
 *
 * @param {Array} results - Array of result objects
 * @returns {Array} Array of [uniquename, data] pairs in database sort order
 */
function groupResultsByFeature(results) {
    const grouped = {};
    const order = [];

    results.forEach(result => {
        const key = result.feature_uniquename;
        if (!grouped[key]) {
            grouped[key] = { feature: result, annotations: [] };
            order.push(key);
        }
        grouped[key].annotations.push(result);
    });

    return order.map(key => [key, grouped[key]]);
}

/**
 * Creates the 'simple' results table — one row per unique feature, with a match count.
 */
function createSimpleResultsTable(organism, results, sitePath, linkBasePath = 'tools/parent.php', imageUrl = '', searchKeywords = '') {
    const tableId  = 'simple_resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const selectId = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const anchorId = 'results-' + selectId;
    const ctx      = { sitePath, linkBasePath, organism, keywords: searchKeywords };

    const grouped = groupResultsByFeature(results);
    const items   = grouped.map(([, data]) => ({ result: data.feature, annotations: data.annotations }));
    const uniqueFeatureCount = grouped.length;

    const html = `
        <div class="organism-results" id="${anchorId}">
            <h5 style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span style="display: flex; align-items: center; gap: 0.5rem;">
                    ${organismHeaderHtml(organism, results, sitePath, imageUrl, true)}
                </span>
                <span class="badge results_feature_count">${uniqueFeatureCount} feature${uniqueFeatureCount !== 1 ? 's' : ''}</span>
                <span class="badge results_annotation_count">${results.length} annotation match${results.length !== 1 ? 'es' : ''}</span>
                <button type="button" class="btn btn-sm toggle-view-btn" data-organism="${selectId}" data-view="simple" title="Toggle between simple and detailed view" style="margin-left: auto;">
                    <i class="fa fa-expand"></i> Expand All Matches
                </button>
            </h5>
            <div class="info-box mb-3" id="info-${selectId}" style="display: none; background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 0.25rem; padding: 0.75rem 1.25rem;">
                <p><strong>Simple View:</strong> Displays a unique list of feature/sequence IDs that have matches to your search terms. Providing an overview of all features found without duplication.</p>

                <p><strong>What Gets Matched:</strong> Your search terms are matched against:</p>
                <ul style="margin-bottom: 0.5rem;">
                    <li><strong>Sequence Name:</strong> The name of the sequence</li>
                    <li><strong>Sequence Description:</strong> The description of the sequence</li>
                    <li><strong>Annotations:</strong> Matches from comparative analyses like BLAST, which compare these sequences against sequences in other organisms</li>
                </ul>

                <p style="margin-bottom: 0.5rem;"><strong>Why Different Results:</strong> A feature/sequence may have a name and description different from what matched your search. This means one of its annotations (from analyses like BLAST) matched your search terms, not the sequence name or description itself.</p>

                <p style="margin-bottom: 0;"><strong>View All Matches:</strong> Click "Expand All Matches" to see all matching annotations for each sequence. This shows exactly which search terms were found and where they were found (name, description, or specific annotation).</p>
            </div>
            <div class="simple-view-container" data-organism="${selectId}" style="background-color: #f0f8f0; padding: 0.75rem; border-radius: 0.25rem;">
                <div class="table-responsive" style="overflow-x: auto; width: 100%; background-color: #f0f8f0; border-radius: 0.25rem;">
                    <table id="${tableId}" class="table table-sm table-striped table-hover results-table">
                        ${buildResultsThead('simple', resultsSelectAllId(selectId, 'simple'))}
                        ${buildResultsTbody('simple', items, ctx)}
                    </table>
                </div>
            </div>
            <div class="expanded-view-container" data-organism="${selectId}" style="display: none; background-color: #f0f8ff; padding: 0.75rem; border-radius: 0.25rem;">
                <!-- Full table is built on first expand -->
            </div>
        </div>
    `;

    setTimeout(() => {
        initializeSimpleResultsTable(tableId, selectId, organism, results, sitePath, searchKeywords, linkBasePath);
    }, 100);

    return html;
}

/**
 * Initialize simple results table with DataTables and toggle functionality
 */
function initializeSimpleResultsTable(tableId, selectId, organism, results, sitePath, searchKeywords = '', linkBasePath = 'tools/parent.php') {
    // Wire the toggle first: a failure in the DataTable setup below must not
    // leave the "Expand All Matches" button without its click handler.
    initializeViewToggle(organism, results, sitePath, selectId, searchKeywords, linkBasePath);

    initResultsDataTable('#' + tableId, 'simple', resultsSelectAllId(selectId, 'simple'));

    // Setup info icon toggle
    $(document).on('click', `.info-icon-btn[data-info-id="info-${selectId}"]`, function (e) {
        e.preventDefault();
        $(`#info-${selectId}`).slideToggle(200);
    });
}

/**
 * Toggle between simple and expanded views.
 *
 * The expanded table is built from the SAME registry as every other variant — it used
 * to be a third hand-written copy of the markup, which is how it drifted (it rendered
 * a literal "undefined" where the main table showed a blank, and dropped the
 * multi-organism search context from its feature links).
 */
function initializeViewToggle(organism, results, sitePath, selectId, searchKeywords = '', linkBasePath = 'tools/parent.php') {
    // Look the results block up by its own id. A document-wide [data-organism]
    // scan also matches the organism selector cards on the multi-organism and
    // group search pages, so it only picks the right element while the results
    // container happens to precede those cards in the markup.
    const parentDiv = document.getElementById('results-' + selectId);
    if (!parentDiv) {
        console.warn(`initializeViewToggle: no results block found for "${selectId}"`);
        return;
    }

    const toggleBtn        = parentDiv.querySelector('.toggle-view-btn');
    const simpleContainer  = parentDiv.querySelector(`.simple-view-container[data-organism="${selectId}"]`);
    const expandedContainer = parentDiv.querySelector(`.expanded-view-container[data-organism="${selectId}"]`);

    if (!toggleBtn || !simpleContainer || !expandedContainer) {
        console.warn(`initializeViewToggle: missing toggle button or view containers for "${selectId}"`);
        return;
    }

    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();

        if (expandedContainer.style.display === 'none') {
            if (expandedContainer.querySelector('table') === null) {
                const tableId = 'resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
                const ctx     = { sitePath, linkBasePath, organism, keywords: searchKeywords };

                expandedContainer.innerHTML = `
                    <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                        <table id="${tableId}" class="table table-sm table-striped table-hover results-table">
                            ${buildResultsThead('full', resultsSelectAllId(selectId, 'expanded'))}
                            ${buildResultsTbody('full', resultItems(results), ctx)}
                        </table>
                    </div>`;

                setTimeout(() => {
                    initializeResultsTable('#' + tableId, organism.replace(/[^a-zA-Z0-9]/g, '_'), false, 'expanded');
                }, 50);
            }
            simpleContainer.style.display = 'none';
            expandedContainer.style.display = 'block';
            toggleBtn.innerHTML = '<i class="fa fa-compress"></i> Show Simple View';
        } else {
            simpleContainer.style.display = 'block';
            expandedContainer.style.display = 'none';
            toggleBtn.innerHTML = '<i class="fa fa-expand"></i> Expand All Matches';
        }
    });
}
