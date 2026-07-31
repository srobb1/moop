/**
 * Reusable Annotation Search Module
 * Consolidates shared search logic for annotation searches across organisms
 *
 * Usage:
 *   const search = new AnnotationSearch({
 *     formSelector: '#groupSearchForm',
 *     organismsVar: groupOrganisms,
 *     totalVar: totalOrganisms,
 *     hideSections: ['#groupDescription'],
 *     scrollToResults: false,
 *     extraAjaxParams: {group: groupName},
 *     noReadMoreButton: false
 *   });
 *   search.init();
 */

class AnnotationSearch {
    constructor(config) {
        this.config = {
            formSelector: config.formSelector,
            organismsVar: config.organismsVar || [],
            totalVar: config.totalVar || config.organismsVar.length,
            hideSections: config.hideSections || [],
            scrollToResults: config.scrollToResults || false,
            extraAjaxParams: config.extraAjaxParams || {},
            noReadMoreButton: config.noReadMoreButton || false,
            noScopeFilter: config.noScopeFilter || false,
            sitePath: config.sitePath || window.sitePath
        };

        this.allResults = [];
        this.zeroResultOrganisms = [];
        this.currentKeywords = '';
        this.cappedOrganisms = [];
        // null = no filter; [] = gene-only mode; [...] = specific sources
        this.selectedSources = null;
        // selectedScope: {org: {accession: {gene_set: bool}}} or null (= all included)
        this.selectedScope = null;
    }

    init() {
        $(this.config.formSelector).on('submit', (e) => {
            e.preventDefault();
            this.handleSearch();
        });

        this.addFilterButton();
        this.updateOrganismNote();
    }

    /**
     * Add advanced search filter button and clear button to form
     */
    addFilterButton() {
        const form = $(this.config.formSelector);
        if (form.find('.search-controls').length === 0) {
            const submitBtn = form.find('button[type="submit"]');

            // No separate "clear" button for EITHER filter: clearing is reopening the
            // modal and ticking things back on, which is where the user already is. A
            // second icon that appears and disappears next to the first one costs more
            // attention than the action it saves.
            //
            // The scope filter never had one; the source filter did, which made two
            // controls that behave the same look like they behave differently. The
            // filter button already shows a count badge when a filter is applied, so
            // the state is visible without a second icon to explain it.
            const scopeBtn = this.config.noScopeFilter ? '' : `
                    <!-- Scope filter button (organisms / assemblies / gene sets) -->
                    <button type="button" class="btn btn-icon btn-scope-filter btn-outline-secondary"
                            title="Scope Filter (Organisms / Assemblies / Gene Sets)"
                            data-bs-toggle="tooltip" data-bs-placement="bottom">
                        <i class="fa fa-sitemap"></i>
                    </button>`;

            const buttonGroup = `
                <div class="search-controls d-flex gap-2 align-items-center ms-2">
                    <!-- Annotation source filter button -->
                    <button type="button" class="btn btn-icon btn-advanced-filter"
                            title="Annotation Source Filter"
                            data-bs-toggle="tooltip" data-bs-placement="bottom">
                        <i class="fa fa-sliders-h"></i>
                    </button>

                    ${scopeBtn}
                </div>
            `;

            submitBtn.after(buttonGroup);

            form.find('.btn-advanced-filter').on('click', () => this.showFilterModal());

            if (!this.config.noScopeFilter) {
                form.find('.btn-scope-filter').on('click', () => this.showScopeFilterModal());
            }

            const tooltipTriggerList = [].slice.call(form.find('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }
    }

    /**
     * Render or update the "Searching across N organisms" note below the search form.
     * Called on init and whenever the organism selection changes.
     */
    updateOrganismNote() {
        const form = $(this.config.formSelector);
        const parent = form.parent();
        let note = parent.find('.search-org-note');
        if (note.length === 0) {
            note = $('<div class="search-org-note text-muted small mt-2"></div>');
            form.after(note);
        }
        // Count organisms the scope filter actually leaves, not the checkbox total --
        // scoping to one gene set announced "across 49 organisms" while querying one.
        const n = this.organismsInScope().length;
        const selected = this.config.totalVar;
        // Names BOTH ways to narrow, because the scope filter is an unlabelled icon that a
        // new user has no reason to open. Kept to one short sentence: this sits under the
        // search box on every results page, so length here is a tax on every search.
        // "gene set" is a live link -- it opens the same modal the icon does, which is the
        // only affordance telling a first-time user that icon exists.
        const narrow = 'Narrow by <a href="#organismsSection">organism</a> or '
                     + '<a href="#" class="scope-note-link">gene set</a>.';
        if (n > 1) {
            note.html(`Searching <strong>${n}</strong> organisms. ${narrow}`);
        } else if (n === 1 && selected > 1) {
            // Narrowed by the scope filter rather than by the organism list; saying nothing
            // here would read as "the filter did nothing".
            note.html(`Searching <strong>1</strong> organism, narrowed by gene set. ${narrow}`);
        } else {
            note.html('');
        }

        // Delegated once per note render; .off() first so repeated renders do not stack
        // handlers and open the modal N times.
        note.off('click.scopeNote').on('click.scopeNote', '.scope-note-link', (e) => {
            e.preventDefault();
            this.showScopeFilterModal();
        });
    }

    /**
     * Confirm a large cross-organism search before running it.
     *
     * A Bootstrap modal rather than the browser's confirm() — same reason the select-first
     * reminders are inline now: a native dialog is jarring. The modal is built once and
     * reused; the confirm button's handler is rebound each call so it runs the right
     * continuation.
     *
     * @param {number}   n         organism count, shown to the user
     * @param {function} onConfirm run when the user chooses to proceed
     */
    confirmLargeSearch(n, onConfirm) {
        let modalEl = document.getElementById('large-search-modal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.className = 'modal fade';
            modalEl.id = 'large-search-modal';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML =
                '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
                '<div class="modal-header py-2">' +
                  '<h5 class="modal-title fw-bold"><i class="fa fa-triangle-exclamation text-warning me-2"></i>Search every organism?</h5>' +
                  '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                '</div>' +
                '<div class="modal-body">' +
                  'You are about to search across <strong class="ls-count"></strong> organisms. ' +
                  'The search runs on every one, so this can take a while. You can narrow the selection ' +
                  'first, or go ahead.' +
                '</div>' +
                '<div class="modal-footer py-2">' +
                  '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Narrow it down</button>' +
                  '<button type="button" class="btn btn-warning ls-confirm"></button>' +
                '</div>' +
                '</div></div>';
            document.body.appendChild(modalEl);
        }
        modalEl.querySelector('.ls-count').textContent = n.toLocaleString();
        const confirmBtn = modalEl.querySelector('.ls-confirm');
        confirmBtn.textContent = `Search all ${n.toLocaleString()}`;
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        // Rebind cleanly so a stale continuation from a previous call cannot fire.
        confirmBtn.onclick = () => { modal.hide(); onConfirm(); };
        modal.show();
    }

    /**
     * Update filter button visual state based on applied filters
     */
    updateFilterButtonState() {
        const form = $(this.config.formSelector);
        const filterBtn = form.find('.btn-advanced-filter');
        const submitBtn = form.find('button[type="submit"]');

        if (this.selectedSources && this.selectedSources.length > 0) {
            filterBtn.removeClass('btn-outline-secondary').addClass('btn-primary');
            filterBtn.html('<i class="fa fa-sliders-h"></i><span class="badge badge-filter">' + this.selectedSources.length + '</span>');
            submitBtn.addClass('btn-success');
        } else {
            filterBtn.removeClass('btn-primary').addClass('btn-outline-secondary');
            filterBtn.html('<i class="fa fa-sliders-h"></i>');
            submitBtn.removeClass('btn-success');
        }
    }

    /**
     * Show advanced search filter modal
     */
    showFilterModal() {
        const organisms = this.config.organismsVar;

        const selectedSourcesObj = {};
        if (this.selectedSources && this.selectedSources.length > 0) {
            this.selectedSources.forEach(source => {
                selectedSourcesObj[source] = true;
            });
        }

        const filter = new AdvancedSearchFilter({
            organisms: organisms,
            sitePath: this.config.sitePath,
            selectedSources: selectedSourcesObj,
            onApply: (selectedSources) => {
                this.selectedSources = selectedSources;
                this.updateFilterButtonState();
            }
        });

        filter.show();
    }

    // ── Scope filter ─────────────────────────────────────────────────────────

    showScopeFilterModal() {
        const filter = new ScopeFilter({
            sitePath: this.config.sitePath,
            organisms: this.config.organismsVar,
            selectedScope: this.selectedScope,
            onApply: (selectedScope) => {
                this.selectedScope = selectedScope;
                this.updateScopeButtonState();
                this.updateOrganismNote();   // the note counts scoped organisms now
            }
        });
        filter.show();
    }

    /**
     * Gene sets currently in scope, as {included, total}.
     *
     * The badge deliberately reports INCLUDED, not excluded. Counting exclusions is
     * correct arithmetic but answers the wrong question: selecting 1 of 49 gene sets
     * produced a badge reading "48", and unticking a single gene set produced "1",
     * which looks like "1 selected" and means the opposite. Showing included/total
     * is unambiguous in both directions.
     */
    countScopedGeneSets() {
        if (!this.selectedScope) return { included: 0, total: 0 };
        let included = 0, total = 0;
        for (const org in this.selectedScope) {
            for (const accession in this.selectedScope[org]) {
                for (const gs in this.selectedScope[org][accession]) {
                    total++;
                    if (this.selectedScope[org][accession][gs]) included++;
                }
            }
        }
        return { included, total };
    }

    updateScopeButtonState() {
        const form     = $(this.config.formSelector);
        const scopeBtn = form.find('.btn-scope-filter');
        if (!scopeBtn.length) return;

        const { included, total } = this.countScopedGeneSets();
        // "Filtered" means something is excluded. Everything-selected is the same as no
        // filter at all, so the button stays in its resting state rather than claiming
        // 49/49 is a filter.
        if (total > 0 && included < total) {
            scopeBtn.removeClass('btn-outline-secondary').addClass('btn-primary');
            // Badge is tiny -- the bare number only. The "of N" lives in the tooltip.
            scopeBtn.attr('title', `Scope Filter — searching ${included} of ${total} gene sets`);
            scopeBtn.html(`<i class="fa fa-sitemap"></i><span class="badge badge-filter">${included}</span>`);
        } else {
            scopeBtn.removeClass('btn-primary').addClass('btn-outline-secondary');
            scopeBtn.attr('title', 'Scope Filter (Organisms / Assemblies / Gene Sets)');
            scopeBtn.html('<i class="fa fa-sitemap"></i>');
        }
    }

    /**
     * The organisms a search will actually query, after the scope filter.
     * getScopePairsForOrganism() returns [] for an organism scoped out entirely, and
     * null when there is no filter at all.
     */
    organismsInScope() {
        return (this.config.organismsVar || []).filter(org => {
            const pairs = this.getScopePairsForOrganism(org);
            return !(pairs !== null && pairs.length === 0);
        });
    }

    /**
     * Returns scope pairs for one organism for the AJAX call.
     * - null  → no scope filter; search everything for that organism
     * - []    → organism is entirely excluded; skip the AJAX call
     * - [...] → search only these (assembly, gene_set) pairs
     */
    getScopePairsForOrganism(organism) {
        if (!this.selectedScope || !(organism in this.selectedScope)) return null;

        const orgScope = this.selectedScope[organism];
        const pairs = [];
        let allSelected = true;

        for (const accession in orgScope) {
            for (const gs in orgScope[accession]) {
                if (orgScope[accession][gs]) {
                    pairs.push({ assembly: accession, gene_set: gs });
                } else {
                    allSelected = false;
                }
            }
        }

        if (allSelected) return null;
        return pairs;
    }

    handleSearch() {
        const keywords = $('#searchKeywords').val().trim();
        this.currentKeywords = keywords;

        // Per-term, not total length -- see js/modules/search-terms.js for the measurements.
        if (!moopSearchInputIsUsable(keywords)) {
            alert(moopSearchInputHint());
            return;
        }

        // "Do you really want every organism?" — a cross-organism search fans out to every
        // selected organism, and cold that is seconds per organism, so a large selection can
        // be genuinely slow. Warn once before running it, and let the user narrow first. The
        // guard keys on totalVar, which is 1 on the single-organism pages, so it only ever
        // fires on the multi pages (groups / multi-organism / taxonomy). Confirmed once per
        // page, so a user who accepts the cost is not nagged on every subsequent search.
        // Count what will really be queried. Scoping down to a single gene set still
        // prompted "search 48 organisms?" while 47 of them were about to be skipped.
        const LARGE_SEARCH = 15;
        const scopedCount = this.organismsInScope().length;
        if (scopedCount >= LARGE_SEARCH && !this._largeSearchConfirmed) {
            this.confirmLargeSearch(scopedCount, () => {
                this._largeSearchConfirmed = true;
                this.handleSearch();
            });
            return;
        }

        const quotedSearch = /^".+"$/.test(keywords);

        let searchExplanation = '';

        if (!quotedSearch) {
            // EVERY term is searched, including short ones. This used to split the terms
            // and announce that anything under three characters had been "ignored" -- it
            // never was, the unsplit string always went to the server, and it should not
            // be: in this domain the short token is usually a gene number and the most
            // specific thing the user typed. "histone deacetylase 1" ranks HDAC1 first;
            // dropping the "1" ranks HDAC8 first. The 3-character minimum applies to the
            // whole query (see the length guard above), not to individual words.
            const terms = keywords.trim().split(/\s+/).filter(t => t.length > 0);
            const boldTerms = terms.map(t => `<strong>${t}</strong>`).join(', ');

            if (terms.length > 1) {
                searchExplanation = `<br><small class="text-muted">Searching with: ${boldTerms} — a record must contain all of them.</small>`;
                searchExplanation += `<br><small class="text-muted">Tip: Use quotes like <code>"exact phrase"</code> to search for exact phrase instead of individual terms.</small>`;
            }
        }

        if ($('#searchResults').is(':hidden')) {
            this.config.hideSections.forEach(selector => {
                $(selector).slideUp();
            });
        }

        this.allResults = [];
        this.zeroResultOrganisms = [];
        this.warnings = [];
        this.cappedOrganisms = [];
        this.cancelled = false;
        $('#searchResults').show();
        $('#resultsContainer').html('');
        $('#search-cancel-btn').show();

        // Wire progress-area cancel (injected into #searchProgress below)
        $(document).off('click.cancel').on('click.cancel', '#search-cancel-progress-btn', () => this.cancel());

        let searchInfo = `Searched for any record containing <strong>${keywords}</strong>`;
        if (this.selectedSources && this.selectedSources.length > 0) {
            searchInfo += ` (limited to ${this.selectedSources.join(', ')})`;
        }
        if (searchExplanation) {
            searchInfo += searchExplanation;
        }
        $('#searchInfo').html(searchInfo);

        $('#searchProgress').html(`
            <div class="search-progress-bar">
                <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
            </div>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <small class="text-muted" id="progressText">Starting search…</small>
                <button type="button" class="btn btn-sm btn-outline-danger ms-3" id="search-cancel-progress-btn">
                    <i class="fa fa-ban me-1"></i>Cancel
                </button>
            </div>
        `);

        $('#searchBtn').prop('disabled', true).addClass('btn-searching').html('<i class="fa fa-search"></i>');

        this.searchAllOrganisms(keywords, quotedSearch);
    }

    /**
     * Search all organisms in parallel with a concurrency limit of 5.
     */
    searchAllOrganisms(keywords, quotedSearch) {
        // Organisms the scope filter leaves something to search. Everything the user is
        // told -- the confirm prompt, this progress bar, the note under the form -- counts
        // THESE, not the organism checkboxes. Scoping down to one gene set used to still
        // announce "48 organisms" and count 48 steps of progress, 47 of which completed
        // instantly, because the counts read config.totalVar and ignored the scope filter.
        const organisms = this.organismsInScope();
        const total = organisms.length;
        let nextIndex = 0;
        let completed = 0;

        const launchNext = () => {
            if (this.cancelled || nextIndex >= total) return;
            const index = nextIndex++;
            const organism = organisms[index];

            // Non-null with entries, or null for "no filter" — organisms scoped out
            // entirely are already gone, filtered by organismsInScope() above.
            const scopePairs = this.getScopePairsForOrganism(organism);

            const ajaxData = {
                search_keywords: keywords,
                organism: organism,
                quoted: quotedSearch ? '1' : '0',
                ...this.config.extraAjaxParams
            };
            if (this.selectedSources !== null) {
                if (this.selectedSources.length === 0) {
                    // Explicit "no annotation sources" — search gene fields only
                    ajaxData.no_annotations = '1';
                } else {
                    ajaxData.source_names = this.selectedSources.join(',');
                }
            }
            if (scopePairs !== null) {
                ajaxData.scope = JSON.stringify(scopePairs);
            }

            $.ajax({
                url: this.config.sitePath + '/tools/annotation_search_ajax.php',
                method: 'GET',
                data: ajaxData,
                dataType: 'json',
                success: (data) => {
                    if (this.cancelled) return;
                    if (data.results && data.results.length > 0) {
                        this.allResults = this.allResults.concat(data.results);
                        this.displayOrganismResults(data);
                    } else {
                        this.zeroResultOrganisms.push({
                            organism: organism,
                            genus: data.genus || '',
                            species: data.species || ''
                        });
                    }
                    if (data.warning) {
                        this.warnings.push({ organism: data.organism, warning: data.warning });
                    }
                    if (data.capped) {
                        this.cappedOrganisms.push(data.organism);
                    }
                    completed++;
                    const progress = Math.round((completed / total) * 100);
                    $('#progressFill').css('width', progress + '%').text(progress + '%');
                    $('#progressText').html(`Searching... (${completed}/${total} complete)`);
                    if (completed >= total) {
                        this.finishSearch();
                    } else {
                        launchNext();
                    }
                },
                error: () => {
                    if (this.cancelled) return;
                    completed++;
                    const progress = Math.round((completed / total) * 100);
                    $('#progressFill').css('width', progress + '%').text(progress + '%');
                    if (completed >= total) {
                        this.finishSearch();
                    } else {
                        launchNext();
                    }
                }
            });
        };

        const concurrency = Math.min(5, total);
        for (let i = 0; i < concurrency; i++) {
            launchNext();
        }
    }

    displayOrganismResults(data) {
        const organism = data.organism;
        const results = data.results;
        const imageUrl = data.organism_image_path || '';
        const isUniquenameSearch = data.search_type === 'Gene/Transcript ID';

        let tableHtml;
        if (!isUniquenameSearch && results.length > 0) {
            tableHtml = createSimpleResultsTable(organism, results, this.config.sitePath, 'tools/parent.php', imageUrl, this.currentKeywords);
        } else {
            tableHtml = createOrganismResultsTable(organism, results, this.config.sitePath, 'tools/parent.php', imageUrl, this.currentKeywords);
        }

        $('#resultsContainer').append(tableHtml);
        // No table init here: createOrganismResultsTable / createSimpleResultsTable each
        // initialise their own DataTable. A second, dead init used to be queued from here
        // against selectors like '#resultsTable-Organism_name' (hyphen) while the tables are
        // created as '#resultsTable_Organism_name' (underscore), so it never matched anything
        // and was masked by `retrieve: true` on the init.
    }

    cancel() {
        this.cancelled = true;
        $('#search-cancel-btn').hide();
        $('#searchBtn').prop('disabled', false).removeClass('btn-searching').html('<i class="fa fa-search"></i>');
        $('#progressText').html('<span class="text-warning"><i class="fa fa-ban me-1"></i>Search cancelled.</span>');
    }

    finishSearch() {
        $('#search-cancel-btn').hide();
        $('#searchBtn').prop('disabled', false).removeClass('btn-searching').html('<i class="fa fa-search"></i>');

        let capMessageHtml = '';
        if (this.cappedOrganisms.length > 0) {
            const cappedList = this.cappedOrganisms.join(', ');
            // The limit is configurable (search_results_limit), so read it rather than
            // hardcoding -- a message that names the wrong number is worse than none.
            const lim = (window.MOOP_SEARCH_RESULTS_LIMIT || 2500).toLocaleString();
            capMessageHtml = `<div class="alert alert-warning mb-3">
                <strong>Search results are capped at ${lim} per organism.</strong> Use Advanced Filter or add more search terms to refine.
                The following organism searches were capped: <em>${cappedList}</em>
                <br><small>Downloads from this search are capped too, and their filename says so.</small>
            </div>`;
        }

        let warningsHtml = '';
        const otherWarnings = this.warnings.filter(w => !w.warning.includes('2,500') && !w.warning.includes('capped'));
        if (otherWarnings.length > 0) {
            warningsHtml = '<div class="alert alert-warning mb-3">';
            otherWarnings.forEach((item, idx) => {
                warningsHtml += '<strong>' + item.organism + ':</strong> ' + item.warning;
                if (idx < otherWarnings.length - 1) warningsHtml += '<br>';
            });
            warningsHtml += '</div>';
        }

        if (this.allResults.length === 0) {
            $('#searchProgress').html(capMessageHtml + warningsHtml + '<div class="alert alert-warning">No results found. Try different search terms.</div>');
        } else {
            const organismCounts = {};
            const uniqueFeatures = new Set();
            this.allResults.forEach(r => {
                if (!organismCounts[r.organism]) {
                    organismCounts[r.organism] = 0;
                }
                organismCounts[r.organism]++;
                uniqueFeatures.add(r.feature_uniquename);
            });

            let jumpToHtml = '<div class="alert alert-info mb-3 d-flex justify-content-between align-items-start gap-3">';
            jumpToHtml += '<div>';
            jumpToHtml += `<strong>Found ${this.allResults.length} matching annotation${this.allResults.length !== 1 ? 's' : ''} across ${uniqueFeatures.size} feature${uniqueFeatures.size !== 1 ? 's' : ''}:</strong> `;

            Object.keys(organismCounts).forEach((org, idx) => {
                const anchorId = 'results-' + org.replace(/[^a-zA-Z0-9]/g, '_');
                const genus = this.allResults.find(r => r.organism === org)?.genus || '';
                const species = this.allResults.find(r => r.organism === org)?.species || '';
                if (idx > 0) jumpToHtml += ' | ';
                jumpToHtml += `<a href="#${anchorId}" class="jump-link"><em>${genus} ${species}</em> <span class="badge bg-secondary">${organismCounts[org]}</span></a>`;
            });

            this.zeroResultOrganisms.forEach((orgInfo) => {
                jumpToHtml += ` | <em>${orgInfo.genus} ${orgInfo.species}</em> <span class="badge bg-secondary">0</span>`;
            });

            jumpToHtml += '</div>';
            jumpToHtml += `<div class="btn-group flex-shrink-0">
                <button class="btn btn-sm btn-outline-success download-all-results" title="Download all annotation results as CSV"><i class="fa fa-table"></i> Table CSV</button>
                <button class="btn btn-sm btn-outline-primary download-fasta-results" title="Download FASTA sequences for all features found"><i class="fa fa-dna"></i> FASTA</button>
            </div>`;
            jumpToHtml += '</div>';

            $('#searchProgress').html(`
                ${capMessageHtml}
                ${warningsHtml}
                ${jumpToHtml}
            `);
            $('#searchProgress').find('.download-all-results').on('click', () => this.downloadResults());
            $('#searchProgress').find('.download-fasta-results').on('click', () => this.downloadFasta());
        }

        if (this.config.scrollToResults) {
            $('html, body').animate({ scrollTop: $('#searchResults').offset().top - 100 }, 'smooth');
        }
    }

    /**
     * Download filename, which SAYS SO when the data is capped.
     *
     * A per-organism search stops at the results limit, so an export of a broad search is
     * truncated -- 15 organisms x 2,500 was 37,500 rows that looked like a complete answer.
     * The cap was announced on screen and nowhere in the file, so the moment it left the
     * browser nothing recorded that it was partial, and a file outlives the page that
     * produced it.
     *
     * The marker goes in the FILENAME rather than inside the CSV: a comment line before the
     * header breaks every parser that reads the first row as column names, and a trailing
     * note row reads as data. A filename travels with the file, survives being emailed, and
     * breaks nothing.
     */
    exportFilename(ext) {
        const label = this.currentKeywords.replace(/[^a-zA-Z0-9_\-]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
        const date = new Date().toISOString().slice(0, 10);
        const capped = (this.cappedOrganisms || []).length > 0
            ? `_CAPPED-${window.MOOP_SEARCH_RESULTS_LIMIT || 'limit'}-per-organism` : '';
        return (label ? `annotation_search_${label}_${date}` : `annotation_search_${date}`) + capped + '.' + ext;
    }

    downloadResults() {
        const decodeHtml = (html) => {
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        };
        const escape = (val) => {
            const s = decodeHtml(String(val ?? ''));
            return (s.includes(',') || s.includes('"') || s.includes('\n'))
                ? '"' + s.replace(/"/g, '""') + '"'
                : s;
        };

        // Assembly and Gene Set sit with the other provenance columns, right after Species,
        // matching where they appear in the results table. Without them a downloaded row
        // cannot say which assembly or gene set it came from — and an organism can carry
        // several of each. resultAssemblyLabel() is shared with the table (defined in
        // js/modules/shared-results-table.js) so the two can never format an assembly
        // differently. This projection is wider than the on-screen table (it adds Organism,
        // Genus and Score, and spans every organism searched), so it stays an explicit list;
        // if you add a column to RESULT_COLUMNS that belongs in a download, add it here too.
        const headers = ['Organism', 'Genus', 'Species', 'Assembly', 'Gene Set', 'Feature ID', 'Feature Type', 'Feature Name', 'Feature Description', 'Annotation Source', 'Annotation ID', 'Annotation Description', 'Score'];
        const rows = [headers.join(',')];
        this.allResults.forEach(r => {
            rows.push([
                r.organism, r.genus, r.species,
                resultAssemblyLabel(r), r.gene_set,
                r.feature_uniquename, r.feature_type, r.feature_name, r.feature_description,
                r.annotation_source_name, r.annotation_accession, r.annotation_description, r.score
            ].map(escape).join(','));
        });

        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = this.exportFilename('csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    downloadFasta() {
        // Group unique feature IDs by organism
        const byOrganism = {};
        this.allResults.forEach(r => {
            if (!byOrganism[r.organism]) byOrganism[r.organism] = new Set();
            byOrganism[r.organism].add(r.feature_uniquename);
        });

        const features = {};
        Object.keys(byOrganism).forEach(org => {
            features[org] = [...byOrganism[org]];
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = this.config.sitePath + '/api/download_search_fasta.php';
        form.style.display = 'none';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        const featuresInput = document.createElement('input');
        featuresInput.type = 'hidden';
        featuresInput.name = 'features';
        featuresInput.value = JSON.stringify(features);
        form.appendChild(featuresInput);

        const labelInput = document.createElement('input');
        labelInput.type = 'hidden';
        labelInput.name = 'label';
        // Same marker the CSV carries: this export is built from capped results, and the
        // file outlives the page that warned about it.
        labelInput.value = this.currentKeywords
            + ((this.cappedOrganisms || []).length > 0
                ? `_CAPPED-${window.MOOP_SEARCH_RESULTS_LIMIT || 'limit'}-per-organism` : '');
        form.appendChild(labelInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}

// Make AnnotationSearch available globally for non-module scripts
window.AnnotationSearch = AnnotationSearch;
