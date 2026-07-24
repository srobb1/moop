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

    // ── Scope tree ──────────────────────────────────────────────────────────

    // Row click — toggle selection (no visible checkbox; whole row is the target).
    //
    // In SIMPLE view a row stands for a whole organism (its per-gene-set rows are collapsed
    // into one), so clicking it selects or deselects ALL of that organism's gene sets — which
    // is what the help promises and what makes the collapse honest. In DETAIL view each row
    // is its own gene set and toggles just itself. Mirrors MOOPmart's initScopeList().
    $(document).on('click', '.scope-gs-full-row', function (e) {
        const cb = $(this).find('.scope-gs-cb')[0];
        if (!cb || e.target === cb) return;

        if ($('#scope-show-detail').is(':checked')) {
            cb.checked = !cb.checked;
            $(this).toggleClass('selected', cb.checked);
        } else {
            const org    = cb.dataset.org;
            const orgCbs = $('.scope-gs-cb').filter(function () { return this.dataset.org === org; });
            // Toggle off only when every one of the organism's gene sets is already on; from a
            // partial state (some picked in Detail view) a click selects them all.
            const allOn = orgCbs.toArray().every(c => c.checked);
            orgCbs.each(function () {
                this.checked = !allOn;
                $(this).closest('.scope-gs-full-row').toggleClass('selected', !allOn);
            });
        }
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
            panel.innerHTML = '<div class="text-muted small p-2 fst-italic">None yet — pick at least one organism</div>';
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

    // Save original HTML of each detail span so we can restore after highlight
    $('.scope-row-detail').each(function () {
        $(this).data('orig-html', this.innerHTML);
    });

    // Wrap matched text in text nodes only (skip HTML tags) with <mark class="scope-hl">
    function highlightInDetail($row, query) {
        const $detail = $row.find('.scope-row-detail');
        if (!$detail.length) return;
        const orig = $detail.data('orig-html');
        if (orig === undefined) return;
        if (!query) { $detail[0].innerHTML = orig; return; }
        const esc = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        $detail[0].innerHTML = orig.replace(/([^<>]+)/g, text =>
            text.replace(new RegExp(esc, 'gi'), m => `<mark class="scope-hl">${m}</mark>`)
        );
    }

    // Scope filter — show/hide rows; if the match is in hidden detail, force-reveal + highlight
    // the exact text. Always matches the FULL search text (organism, common name, group,
    // assembly AND gene set) because that is what the placeholder promises.
    function runScopeFilter() {
        const q           = $('#scope-filter').val().trim().toLowerCase();
        const detailHidden = !$('#scope-show-detail').is(':checked');

        $('.scope-gs-full-row').each(function () {
            const $row = $(this);
            if (!q) {
                // Back to the stylesheet's decision — which in simple view keeps the secondary
                // rows hidden. Setting .show() here would defeat the collapse.
                this.style.display = '';
                $row.removeClass('scope-detail-forced scope-detail-matched');
                highlightInDetail($row, '');
                return;
            }
            const all    = String($row.data('search') || '');
            const simple = String($row.data('search-simple') || all);
            const detail = String($row.data('search-detail') || '');

            if (!all.includes(q)) {
                this.style.display = 'none';
                $row.removeClass('scope-detail-forced scope-detail-matched');
                highlightInDetail($row, '');
                return;
            }

            const detailMatch = detail.includes(q);
            const detailOnly  = detailMatch && !simple.includes(q);

            // Simple view: a SECONDARY row shows only when the query hit its own assembly or
            // gene set. Filtering by an organism name must not re-expose the very duplicate
            // rows the collapse hid.
            if (detailHidden && $row.hasClass('scope-row-secondary') && !detailOnly) {
                this.style.display = 'none';
                $row.removeClass('scope-detail-forced scope-detail-matched');
                highlightInDetail($row, '');
                return;
            }

            this.style.display = 'flex';
            $row.toggleClass('scope-detail-forced scope-detail-matched', detailHidden && detailOnly);
            highlightInDetail($row, detailMatch ? q : '');
        });
    }

    $('#scope-filter').on('input', runScopeFilter);

    // Detail toggle switch
    $('#scope-show-detail').on('change', function () {
        $('#scope-org-list').toggleClass('scope-detail-hidden', !this.checked);
        runScopeFilter(); // re-evaluate forced reveals
    });

    // One toggle instead of an All / None pair. Its label states what the click will do, so it
    // doubles as a readout of whether everything is selected. Deselecting is immediate;
    // selecting all still routes through the "this can take a while" warning, because an
    // every-organism search is the one query we most want people to choose deliberately.
    $('.scope-toggle-all').on('click', function () {
        const boxes = $('.scope-gs-cb');
        const allOn = boxes.length > 0 && boxes.toArray().every(cb => cb.checked);

        if (allOn) {
            boxes.prop('checked', false);
            $('.scope-gs-full-row').removeClass('selected');
            onScopeChange();
            return;
        }

        const countEl = document.getElementById('select-all-orgs-count');
        if (countEl) countEl.textContent = boxes.length + (boxes.length === 1 ? ' gene set' : ' gene sets');
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('select-all-orgs-modal'));
        $('#select-all-orgs-confirm').off('click.selall').on('click.selall', function () {
            modal.hide();
            boxes.prop('checked', true);
            $('.scope-gs-full-row').addClass('selected');
            onScopeChange();
        });
        modal.show();
    });

    // Called whenever any scope checkbox changes
    let sourcesLoadTimer = null;
    function onScopeChange() {
        clearSelectHint();   // they just did the thing the hint asked for
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

    // The counts line under the list, plus the toggle's label. Says organisms / assemblies /
    // gene sets in full rather than a bare number, because those three are exactly what a
    // click can change without the list making it obvious — selecting one organism row in
    // simple view can take four gene sets with it. Same readout as MOOPmart's #mm-scope-counts.
    function updateScopeSummary() {
        const boxes = $('.scope-gs-cb');
        const allOn = boxes.length > 0 && boxes.toArray().every(cb => cb.checked);
        $('.scope-toggle-all-label').text(allOn ? 'Deselect all' : 'Select all');

        const el = document.getElementById('scope-counts');
        if (!el) return;

        const checked = boxes.filter(':checked').toArray();
        if (!checked.length) {
            el.textContent = 'Select at least one organism above';
            return;
        }
        const orgs = new Set(checked.map(c => c.dataset.org)).size;
        const asms = new Set(checked.map(c => c.dataset.org + '|' + c.dataset.asm)).size;
        const gs   = checked.length;
        el.textContent = `${orgs} organism${orgs !== 1 ? 's' : ''}, `
                       + `${asms} assembl${asms !== 1 ? 'ies' : 'y'}, `
                       + `${gs} gene set${gs !== 1 ? 's' : ''} selected`;
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
            $('#sourcesPanel').html('<p class="text-muted small p-3">Choose organisms in Step <span class="step-ref">2</span> — the types listed here are the ones those organisms actually carry.</p>');
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

    $(document).on('click', '.source-ind-row, .source-type-row', clearSelectHint);

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

    // Same single toggle as the scope list, so the two selectors on this page behave alike.
    // No confirmation here: annotation types narrow a search that is already scoped to chosen
    // organisms, so selecting all of them is not the runaway query the organism list guards.
    $('.sources-toggle-all').on('click', function () {
        const boxes = $('.source-cb');
        if (!boxes.length) return;
        const next = !boxes.toArray().every(cb => cb.checked);
        boxes.each(function () {
            this.checked = next;
            sourceOverrides[$(this).data('source')] = next;
        });
        $('.source-ind-row').toggleClass('selected', next);
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

    // The one inline "you skipped a step" note, in place of the browser alert() and the modal
    // interrupt this page used to fire. It names the step that is actually blocking — the old
    // modal always pointed at Step 3, which with no organisms picked is an empty box.
    function showSelectHint(html) {
        const hint = document.getElementById('search-select-hint');
        if (!hint) return;
        hint.innerHTML = '<i class="fa fa-arrow-up me-1"></i> ' + html;
        hint.style.display = 'flex';
        hint.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function clearSelectHint() {
        const hint = document.getElementById('search-select-hint');
        if (hint) hint.style.display = 'none';
    }
    // Fixing what the hint complained about should retract it, or it reads as a stale scold.
    $(document).on('input', '#searchKeywords', clearSelectHint);

    $('#searchForm').on('submit', function (e) {
        e.preventDefault();

        const keywords    = $('#searchKeywords').val().trim();
        const checkedOrgs = getCheckedOrganisms();

        // Guards run in page order, so the hint always points at the FIRST thing to fix.
        if (keywords.length < 3) {
            showSelectHint('Enter at least three characters in Step <span class="step-ref">1</span>.');
            return;
        }
        // A search is never run unscoped. Falling back to every organism used to be the
        // behaviour here; across 85 databases that is the single most expensive query the
        // site can issue, and nobody asked for it on purpose.
        if (!checkedOrgs.length) {
            showSelectHint('Choose at least one organism in Step <span class="step-ref">2</span>.');
            return;
        }
        if ($('.source-cb:checked').length === 0) {
            showSelectHint('Choose at least one annotation type in Step <span class="step-ref">3</span>.');
            return;
        }

        clearSelectHint();
        searchManager.selectedScope       = buildSelectedScope();
        searchManager.selectedSources     = getCheckedSources();
        searchManager.config.organismsVar = checkedOrgs;
        searchManager.config.totalVar     = checkedOrgs.length;
        searchManager.handleSearch();
        // Scroll results into view after a brief delay so they start rendering
        setTimeout(() => {
            const results = document.getElementById('searchResults');
            if (results) results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 200);
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

    $('#search-cancel-btn, #search-cancel-progress-btn').on('click', () => searchManager.cancel());

    applyContextScope(typeof scopeContext !== 'undefined' ? scopeContext : null);
    updateScopeSummary();
    // Only load annotation types for pre-selected organisms; otherwise wait for user to select
    const preselectedOrgs = getCheckedOrganisms();
    loadAnnotationSources(preselectedOrgs);

    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
});
