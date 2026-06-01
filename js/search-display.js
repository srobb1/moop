/**
 * Generic Search Page
 *
 * Expects PHP-injected globals:
 *   sitePath    — e.g. '/moop'
 *   allOrganisms — flat array of all accessible organism names
 *   scopeTree   — {organism: {assembly: [gene_set, ...]}}
 */

$(document).ready(function () {

    // ── AnnotationSearch instance ────────────────────────────────────────────

    const searchManager = new AnnotationSearch({
        formSelector:    '#searchForm',
        organismsVar:    allOrganisms.slice(),
        totalVar:        allOrganisms.length,
        noScopeFilter:   true,   // scope is handled by the inline tree
        noReadMoreButton: false,
        scrollToResults: true,
        sitePath:        sitePath,
    });
    // Don't call init() — we handle form submit ourselves to inject scope + sources first.
    // Still want search-instructions handler wired up.
    initializeSearchInstructionsHandler();

    // ── Scope tree ──────────────────────────────────────────────────────────

    // Row click — toggle selection (no visible checkbox; whole row is the target)
    $(document).on('click', '.scope-gs-full-row', function () {
        const cb = $(this).find('.scope-gs-cb')[0];
        cb.checked = !cb.checked;
        $(this).toggleClass('selected', cb.checked);
        onScopeChange();
    });

    // Gene-set checkbox change → sync row highlight + panel
    $(document).on('change', '.scope-gs-cb', function () {
        $(this).closest('.scope-gs-full-row').toggleClass('selected', this.checked);
        onScopeChange();
    });

    function updateSearchSelectedPanel() {
        const panel     = document.getElementById('scope-selected-panel');
        const countBadge = document.getElementById('scope-selected-count');
        if (!panel) return;

        // Collect selected gene-sets grouped by organism
        const byOrg = {};
        $('.scope-gs-cb:checked').each(function () {
            const org = $(this).data('org');
            if (!byOrg[org]) byOrg[org] = { label: '', cn: '', rows: [] };
            byOrg[org].label = $(this).data('label') || org.replace(/_/g, ' ');
            byOrg[org].cn    = $(this).data('cn') || '';
            const asmDisplay = $(this).data('asm-display') || $(this).data('asm');
            const gs         = $(this).data('gs');
            byOrg[org].rows.push(asmDisplay + ' › ' + gs);
        });

        const orgCount = Object.keys(byOrg).length;
        if (countBadge) countBadge.textContent = orgCount;

        if (!orgCount) {
            panel.innerHTML = '<div class="text-muted small p-2 fst-italic">None — will search all organisms</div>';
            return;
        }

        let html = '';
        for (const [org, d] of Object.entries(byOrg)) {
            html += `<div class="px-2 py-1 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                  <span><strong><em>${d.label}</em></strong>${d.cn ? ' <span class="text-muted small">· ' + d.cn + '</span>' : ''}</span>
                  <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-danger scope-deselect-org flex-shrink-0"
                          data-org="${org}" title="Remove"><i class="fas fa-times"></i></button>
                </div>
                ${d.rows.map(r => `<div class="text-muted ps-2" style="font-size:0.75rem;">› ${r}</div>`).join('')}
              </div>`;
        }
        panel.innerHTML = html;
    }

    // Remove organism from selected panel (× button)
    $(document).on('click', '.scope-deselect-org', function () {
        const org = $(this).data('org');
        $('[data-org="' + org + '"].scope-gs-cb').each(function () {
            $(this).prop('checked', false);
            $(this).closest('.scope-gs-full-row').removeClass('selected');
        });
        onScopeChange();
    });

    // Scope filter
    $('#scope-filter').on('input', function () {
        const q = $(this).val().trim().toLowerCase();
        $('.scope-gs-full-row').each(function () {
            $(this).toggle(!q || String($(this).data('search')).includes(q));
        });
    });

    // Select All / Deselect All
    $('#scope-select-all').on('click', function () {
        $('.scope-gs-cb').prop('checked', true);
        $('.scope-gs-full-row').addClass('selected');
        onScopeChange();
    });
    $('#scope-deselect-all').on('click', function () {
        $('.scope-gs-cb').prop('checked', false);
        $('.scope-gs-full-row').removeClass('selected');
        onScopeChange();
    });

    // Called whenever any scope checkbox changes
    let sourcesLoadTimer = null;
    function onScopeChange() {
        updateScopeSummary();
        updateSearchSelectedPanel();
        clearTimeout(sourcesLoadTimer);
        sourcesLoadTimer = setTimeout(() => {
            loadAnnotationSources(getCheckedOrganisms());
        }, 300);
    }

    function getCheckedOrganisms() {
        const seen = new Set();
        $('.scope-gs-cb:checked').each(function () { seen.add($(this).data('org')); });
        return Array.from(seen);
    }

    function updateScopeSummary() {
        const checkedGs  = $('.scope-gs-cb:checked').length;
        const totalGs    = $('.scope-gs-cb').length;
        const checkedOrg = getCheckedOrganisms().length;
        const totalOrg   = allOrganisms.length;
        let txt = checkedOrg + ' / ' + totalOrg + ' organism' + (totalOrg !== 1 ? 's' : '');
        if (checkedGs < totalGs) txt += ', ' + checkedGs + ' / ' + totalGs + ' gene sets';
        $('#scope-summary').text(txt);
    }

    // Build selectedScope object for AnnotationSearch from current tree state.
    // Returns null (= no filter) when nothing is checked or everything is checked.
    function buildSelectedScope() {
        const checkedOrgs = getCheckedOrganisms();
        if (!checkedOrgs.length) return null;

        const scope = {};
        let hasExclusion = false;

        $('.scope-gs-cb').each(function () {
            const org = $(this).data('org');
            const asm = $(this).data('asm');
            const gs  = $(this).data('gs');
            if (!scope[org])      scope[org]      = {};
            if (!scope[org][asm]) scope[org][asm] = {};
            scope[org][asm][gs] = $(this).is(':checked');
            if (!scope[org][asm][gs]) hasExclusion = true;
        });

        return hasExclusion ? scope : null;
    }

    // ── Annotation sources panel ─────────────────────────────────────────────

    // Track per-source override: null = use default (all checked), else {name: bool}
    let sourceOverrides = {};

    function loadAnnotationSources(organisms) {
        if (!organisms.length) {
            $('#sourcesPanel').html('<p class="text-muted small p-3">Select organisms in section&nbsp;② to see available annotation types.</p>');
            $('#sources-filter-wrap').hide();
            updateSourcesSummary();
            updateAnnotTypesPanel();
            return;
        }

        $('#sourcesPanel').html('<div class="text-center p-3 text-muted"><i class="fa fa-spinner fa-spin me-1"></i> Loading…</div>');

        $.getJSON(sitePath + '/tools/get_annotation_sources_grouped.php',
            { organisms: organisms.join(',') },
            function (data) {
                renderSourcesPanel(data.source_types || {});
            }
        ).fail(function () {
            $('#sourcesPanel').html('<p class="text-muted small p-2">Could not load annotation sources.</p>');
        });
    }

    function renderSourcesPanel(sourceTypesRaw) {
        // Sort types by config order if available
        const order = (typeof annTypeOrder !== 'undefined') ? annTypeOrder : [];
        const sourceTypes = Object.fromEntries(
            Object.entries(sourceTypesRaw).sort(([a], [b]) => {
                const ai = order.indexOf(a), bi = order.indexOf(b);
                if (ai === -1 && bi === -1) return a.localeCompare(b);
                if (ai === -1) return 1;
                if (bi === -1) return -1;
                return ai - bi;
            })
        );
        if (!Object.keys(sourceTypes).length) {
            $('#sourcesPanel').html('<p class="text-muted small p-3">No annotation types found for selected organisms.</p>');
            updateSourcesSummary();
            updateAnnotTypesPanel();
            return;
        }

        let html = '';
        for (const [type, typeData] of Object.entries(sourceTypes)) {
            const color   = typeData.color || 'secondary';
            const sources = typeData.sources || [];

            // Type header row — clickable, no visible checkbox
            html += `<div class="org-select-row source-type-row" data-type="${type}" style="background:#f8f9fa;">
                       <input type="checkbox" class="source-type-cb visually-hidden" data-type="${type}">
                       <span class="fw-semibold" style="font-size:0.88rem;">
                         <span class="badge bg-${color} me-1">${type}</span>
                       </span>
                       <span class="org-check ms-auto"><i class="fas fa-check text-success"></i></span>
                     </div>`;

            // Individual source rows — indented, clickable
            for (const src of sources) {
                const on    = sourceOverrides[src.name] === true;
                const count = src.count ? ` <span class="text-muted small">(${src.count.toLocaleString()})</span>` : '';
                html += `<div class="org-select-row source-ind-row ps-4${on ? ' selected' : ''}"
                              data-source="${src.name}" data-type="${type}" style="font-size:0.82rem;">
                           <input type="checkbox" class="source-cb visually-hidden"
                                  data-source="${src.name}" data-type="${type}"${on ? ' checked' : ''}>
                           <span>${src.name}${count}</span>
                           <span class="org-check ms-auto"><i class="fas fa-check text-success"></i></span>
                         </div>`;
            }
        }

        $('#sourcesPanel').html(html);

        // Sync type row visual state from source rows
        syncAllTypeRows();

        $('#sources-filter-wrap').show();
        filterSources();
        updateSourcesSummary();
        updateAnnotTypesPanel();
    }

    function syncAllTypeRows() {
        $('.source-type-row').each(function () {
            const type  = $(this).data('type');
            const all   = $('[data-type="' + type + '"].source-cb');
            const on    = all.filter(':checked').length;
            const total = all.length;
            const cb    = $(this).find('.source-type-cb')[0];
            if (cb) { cb.checked = on === total; cb.indeterminate = on > 0 && on < total; }
            $(this).toggleClass('selected', on > 0).toggleClass('partial', on > 0 && on < total);
        });
    }

    // Type header row click — toggle all sources of that type
    $(document).on('click', '.source-type-row', function () {
        const type     = $(this).data('type');
        const srcRows  = $('[data-type="' + type + '"].source-ind-row');
        const anyOn    = srcRows.filter('.selected').length > 0;
        const next     = !anyOn;
        srcRows.each(function () {
            const cb = $(this).find('.source-cb')[0];
            cb.checked = next;
            sourceOverrides[cb.dataset.source] = next;
            $(this).toggleClass('selected', next);
        });
        const typeCb = $(this).find('.source-type-cb')[0];
        if (typeCb) { typeCb.checked = next; typeCb.indeterminate = false; }
        $(this).toggleClass('selected', next).toggleClass('partial', false);
        updateSourcesSummary();
        updateAnnotTypesPanel();
    });

    // Individual source row click
    $(document).on('click', '.source-ind-row', function () {
        const cb   = $(this).find('.source-cb')[0];
        cb.checked = !cb.checked;
        sourceOverrides[cb.dataset.source] = cb.checked;
        $(this).toggleClass('selected', cb.checked);
        syncAllTypeRows();
        updateSourcesSummary();
        updateAnnotTypesPanel();
    });

    function filterSources() {
        const q = ($('#sources-filter').val() || '').trim().toLowerCase();
        if (!q) {
            $('.source-type-row, .source-ind-row').show();
            return;
        }
        $('.source-type-row').each(function () {
            const type = $(this).data('type');
            let anyVisible = String(type).toLowerCase().includes(q);
            $('[data-type="' + type + '"].source-ind-row').each(function () {
                const matches = String($(this).data('source')).toLowerCase().includes(q);
                $(this).toggle(matches);
                if (matches) anyVisible = true;
            });
            $(this).toggle(anyVisible);
        });
    }

    $(document).on('input', '#sources-filter', filterSources);

    // Select All / Deselect All sources
    $('#sources-select-all').on('click', function () {
        $('.source-cb').each(function () {
            this.checked = true;
            sourceOverrides[$(this).data('source')] = true;
        });
        $('.source-ind-row').addClass('selected');
        syncAllTypeRows();
        updateSourcesSummary();
        updateAnnotTypesPanel();
    });
    $('#sources-deselect-all').on('click', function () {
        $('.source-cb').each(function () {
            this.checked = false;
            sourceOverrides[$(this).data('source')] = false;
        });
        $('.source-ind-row').removeClass('selected');
        syncAllTypeRows();
        updateSourcesSummary();
        updateAnnotTypesPanel();
    });

    // Remove annotation type from selected panel (× button)
    $(document).on('click', '.deselect-ann-type', function () {
        const type = $(this).data('type');
        $('[data-type="' + type + '"].source-cb').each(function () {
            this.checked = false;
            sourceOverrides[$(this).data('source')] = false;
        });
        $('[data-type="' + type + '"].source-ind-row').removeClass('selected');
        syncAllTypeRows();
        updateSourcesSummary();
        updateAnnotTypesPanel();
    });

    function getCheckedSources() {
        const sources = [];
        $('.source-cb:checked').each(function () {
            sources.push($(this).data('source'));
        });
        return sources.length ? sources : null;
    }

    function updateSourcesSummary() {
        const checked = $('.source-cb:checked').length;
        const total   = $('.source-cb').length;
        if (!total) { $('#sources-summary').text(''); return; }
        $('#sources-summary').text(checked + ' / ' + total + ' annotation type' + (total !== 1 ? 's' : '') + ' selected');
    }

    function updateAnnotTypesPanel() {
        const panel     = document.getElementById('ann-types-selected-panel');
        const countBadge = document.getElementById('ann-types-selected-count');
        if (!panel) return;

        const selected = [];
        $('.source-type-cb').each(function () {
            if (this.checked || this.indeterminate) {
                selected.push({
                    type:    $(this).data('type'),
                    partial: this.indeterminate,
                });
            }
        });

        if (countBadge) countBadge.textContent = selected.length;

        if (!selected.length) {
            panel.innerHTML = '<div class="text-muted small p-2 fst-italic">No types selected</div>';
            return;
        }

        panel.innerHTML = selected.map(({ type, partial }) =>
            `<div class="d-flex align-items-center justify-content-between px-2 py-1 border-bottom">
               <span class="small">${type}${partial ? ' <span class="text-muted fst-italic">(partial)</span>' : ''}</span>
               <button type="button" class="btn btn-link btn-sm p-0 text-danger deselect-ann-type"
                       data-type="${type}" title="Remove"><i class="fas fa-times"></i></button>
             </div>`
        ).join('');
    }

    // ── Search form submit ───────────────────────────────────────────────────

    $('#searchForm').on('submit', function (e) {
        e.preventDefault();

        const checkedOrgs  = getCheckedOrganisms();
        const checkedTypes = $('.source-cb:checked').length;

        // Warn about searching all organisms first (most impactful)
        if (checkedOrgs.length === 0) {
            if (!confirm('No organisms selected — this will search across all ' + allOrganisms.length + ' organisms and may take a while. Continue?')) {
                return;
            }
        }

        // Require at least one annotation type
        if (checkedTypes === 0) {
            alert('Please select at least one annotation type in section 3 before searching.');
            return;
        }

        const proceed = () => {
            const activeOrgs = checkedOrgs.length ? checkedOrgs : allOrganisms;
            searchManager.selectedScope       = buildSelectedScope();
            searchManager.selectedSources     = getCheckedSources();
            searchManager.config.organismsVar = activeOrgs;
            searchManager.config.totalVar     = activeOrgs.length;
            searchManager.handleSearch();
            // Scroll results into view after a brief delay so they start rendering
            setTimeout(() => {
                const results = document.getElementById('searchResults');
                if (results) results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 200);
        };

        proceed();
    });

    // ── Init ─────────────────────────────────────────────────────────────────

    // Pre-filter scope tree when launched from a context page (organism/assembly/gene_set/group)
    function applyContextScope(ctx) {
        if (!ctx) return;

        const keepOrgs = new Set(ctx.organisms || (ctx.organism ? [ctx.organism] : []));
        if (!keepOrgs.size) return;

        $('.scope-gs-cb').each(function () {
            let keep = keepOrgs.has(this.dataset.org);
            if (keep && ctx.assembly) keep = (this.dataset.asm === ctx.assembly);
            if (keep && ctx.gene_set) keep = (this.dataset.gs  === ctx.gene_set);
            this.checked = keep;
        });

        // Sync assembly checkboxes
        $('.scope-asm-cb').each(function () {
            const boxes = $(`.scope-gs-cb[data-org="${this.dataset.org}"][data-asm="${this.dataset.asm}"]`);
            const cnt   = boxes.filter(':checked').length;
            this.checked       = cnt === boxes.length;
            this.indeterminate = cnt > 0 && cnt < boxes.length;
        });

        // Sync organism checkboxes
        $('.scope-org-cb').each(function () {
            const boxes = $(`.scope-gs-cb[data-org="${this.dataset.org}"]`);
            const cnt   = boxes.filter(':checked').length;
            this.checked       = cnt === boxes.length;
            this.indeterminate = cnt > 0 && cnt < boxes.length;
        });

        // Sync flat row highlights after context is applied
        $('.scope-gs-full-row').each(function () {
            $(this).toggleClass('selected', $(this).find('.scope-gs-cb').is(':checked'));
        });
        updateSearchSelectedPanel();
        onScopeChange();
    }

    $('#search-cancel-btn').on('click', () => searchManager.cancel());

    applyContextScope(typeof scopeContext !== 'undefined' ? scopeContext : null);
    updateScopeSummary();
    // Only load annotation types for pre-selected organisms; otherwise wait for user to select
    const preselectedOrgs = getCheckedOrganisms();
    loadAnnotationSources(preselectedOrgs);
});
