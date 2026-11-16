/**
 * Generic Search Page
 *
 * Expects PHP-injected globals:
 *   sitePath    — e.g. '/moop'
 *   allOrganisms — flat array of all accessible organism names
 *   scopeContext — what to pre-select (lib/scope_selector.php), or null
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

    // ── Organism selector ───────────────────────────────────────────────────
    // The list, filter, Details, select-all, Selected panel and counts are the shared
    // component (js/modules/scope-selector.js, also the Data Exporter's). This page only
    // reacts: a changed selection changes which annotation types exist (Step 3).
    let sourcesLoadTimer = null;
    function onScopeChange() {
        clearSelectHint();   // they just did the thing the hint asked for
        clearTimeout(sourcesLoadTimer);
        sourcesLoadTimer = setTimeout(loadAnnotationSources, 300);
    }

    function getCheckedOrganisms() {
        const seen = new Set();
        $('.scope-gs-cb:checked').each(function () { seen.add($(this).data('org')); });
        return Array.from(seen);
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

    // What the user changed, by source name: {name: bool}. A source not in here is TICKED --
    // every type is searched by default (user, 2026-10-21). Required selection was meant to keep
    // searches cheap, but measured, the type filter barely changes a search's cost: it narrows
    // rows the full-text match has already read. The cost is the organisms, which stay required.
    // A type that appears when more organisms are added arrives ticked; one the user unticked
    // stays unticked.
    let sourceOverrides = {};
    // ?source= (scopeContext.source): on the first list, tick ONLY that source, then run the search
    // if a keyword is already there. Used once; after it the visitor's own ticks rule.
    let onlySource = (typeof scopeContext !== 'undefined' && scopeContext && scopeContext.source) || '';

    // The gene sets ticked for each organism that is only PARTLY ticked, as
    // "organism/accession/gene_set" -- so the counts in Step 3 are the selection's own, not the
    // whole organism's. Fully ticked organisms are left out: their organism-wide counts are
    // already right, and listing every gene set of a big selection would overflow the URL.
    function partialGeneSets() {
        const byOrg = {};
        $('.scope-gs-cb').each(function () {
            (byOrg[this.dataset.org] ??= []).push(this);
        });
        const out = [];
        Object.values(byOrg).forEach(boxes => {
            const on = boxes.filter(cb => cb.checked);
            if (on.length && on.length < boxes.length) {
                on.forEach(cb => out.push(`${cb.dataset.org}/${cb.dataset.asm}/${cb.dataset.gs}`));
            }
        });
        return out;
    }

    function loadAnnotationSources() {
        const organisms = getCheckedOrganisms();
        if (!organisms.length) {
            // One message at a time. The static "all types are searched; untick any you don't want"
            // line is hidden here for the same reason the filter box is: with no types
            // listed there is nothing to tick and nothing to filter, so both would be
            // rules about a control that is not on screen. Shown again in
            // renderSourcesPanel() once there IS a list.
            $('#sourcesPanel').html('<p class="text-muted small p-3">Choose organisms in Step <span class="step-ref">2</span> — the types they carry appear here.</p>');
            $('#sources-filter-wrap').hide();
            $('#sources-help-line').hide();
            updateSourcesSummary();
            updateAnnotTypesPanel();
            return;
        }

        $('#sourcesPanel').html('<div class="text-center p-3 text-muted"><i class="fa fa-spinner fa-spin me-1"></i> Loading…</div>');

        const params  = { organisms: organisms.join(',') };
        const partial = partialGeneSets();
        if (partial.length) params.gene_sets = JSON.stringify(partial);
        $.getJSON(sitePath + '/tools/get_annotation_sources_grouped.php', params,
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

        // ?source=: only that one, when this scope has it (an unknown name changes nothing)
        let runNow = false;
        if (onlySource) {
            const all = Object.values(sourceTypes).flatMap(td => (td.sources || []).map(s => s.name));
            if (all.includes(onlySource)) {
                all.forEach(n => { sourceOverrides[n] = (n === onlySource); });
                runNow = $('#searchKeywords').val().trim() !== '';
            }
            onlySource = '';
        }

        // Type and source names come from the databases, so they are escaped like any other
        // data -- into text and into attributes alike.
        let html = '';
        for (const [type, typeData] of Object.entries(sourceTypes)) {
            const color   = escapeHtml(typeData.color || 'secondary');
            const sources = typeData.sources || [];
            const t       = escapeHtml(type);

            // Type header row — clickable, no visible checkbox
            html += `<div class="org-select-row source-type-row" data-type="${t}" style="background:#f8f9fa;">
                       <input type="checkbox" class="source-type-cb visually-hidden" data-type="${t}">
                       <span class="fw-semibold" style="font-size:0.88rem;">
                         <span class="badge bg-${color} me-1">${t}</span>
                       </span>
                       <span class="org-check ms-auto"><i class="fas fa-check text-success"></i></span>
                     </div>`;

            // Individual source rows — indented, clickable
            for (const src of sources) {
                const on    = sourceOverrides[src.name] !== false;
                const name  = escapeHtml(src.name);
                const count = src.count ? ` <span class="text-muted small">(${Number(src.count).toLocaleString()})</span>` : '';
                html += `<div class="org-select-row source-ind-row ps-4${on ? ' selected' : ''}"
                              data-source="${name}" data-type="${t}" style="font-size:0.82rem;">
                           <input type="checkbox" class="source-cb visually-hidden"
                                  data-source="${name}" data-type="${t}"${on ? ' checked' : ''}>
                           <span>${name}${count}</span>
                           <span class="org-check ms-auto"><i class="fas fa-check text-success"></i></span>
                         </div>`;
            }
        }

        $('#sourcesPanel').html(html);

        // Sync type row visual state from source rows
        syncAllTypeRows();

        $('#sources-filter-wrap').show();
        $('#sources-help-line').show();
        filterSources();
        updateSourcesSummary();
        updateAnnotTypesPanel();
        if (runNow) $('#searchForm').trigger('submit');
    }

    function syncAllTypeRows() {
        $('.source-type-row').each(function () {
            const type  = $(this).data('type');
            const all   = $('[data-type="' + CSS.escape(String(type)) + '"].source-cb');
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
        const srcRows  = $('[data-type="' + CSS.escape(String(type)) + '"].source-ind-row');
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
            $('[data-type="' + CSS.escape(String(type)) + '"].source-ind-row').each(function () {
                const matches = String(this.dataset.source).toLowerCase().includes(q);
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
            sourceOverrides[this.dataset.source] = next;
        });
        $('.source-ind-row').toggleClass('selected', next);
        syncAllTypeRows();
        updateSourcesSummary();
        updateAnnotTypesPanel();
    });

    // Remove annotation type from selected panel (× button)
    $(document).on('click', '.deselect-ann-type', function () {
        const type = $(this).data('type');
        $('[data-type="' + CSS.escape(String(type)) + '"].source-cb').each(function () {
            this.checked = false;
            sourceOverrides[this.dataset.source] = false;
        });
        $('[data-type="' + CSS.escape(String(type)) + '"].source-ind-row').removeClass('selected');
        syncAllTypeRows();
        updateSourcesSummary();
        updateAnnotTypesPanel();
    });

    // The source filter for the search: null = no filter, when every listed type is ticked.
    // Not a list of every name: ANY source filter takes the search off its fast path
    // (searchFeaturesAndAnnotations(), lib/database_queries.php -- the pool is chosen inside the
    // full-text index only when nothing is filtered), so "all, spelled out" costs far more than
    // "all" for the same rows. The list shows only sources with data in this selection, so
    // nothing outside it could match anyway. (The submit guard rejects nothing-ticked first.)
    function getCheckedSources() {
        const boxes   = $('.source-cb');
        const checked = boxes.filter(':checked');
        if (checked.length === boxes.length) return null;
        return checked.toArray().map(cb => cb.dataset.source);
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
            panel.innerHTML = '<div class="text-muted small p-2 fst-italic">' + moopTh('No types selected') + '</div>';
            return;
        }

        panel.innerHTML = selected.map(({ type, partial }) =>
            `<div class="d-flex align-items-center justify-content-between px-2 py-1 border-bottom">
               <span class="small">${escapeHtml(type)}${partial ? ' <span class="text-muted fst-italic">(partial)</span>' : ''}</span>
               <button type="button" class="btn btn-link btn-sm p-0 text-danger deselect-ann-type"
                       data-type="${escapeHtml(type)}" title="Remove"><i class="fas fa-times"></i></button>
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
        // Per-term, not total length: `F8` is a real gene and must be searchable, while a
        // lone `1` matches 1.86M rows. See js/modules/search-terms.js.
        if (!moopSearchInputIsUsable(keywords)) {
            showSelectHint('Step <span class="step-ref">1</span>: ' + moopSearchInputHint());
            return;
        }
        // A search is never run unscoped. Falling back to every organism used to be the
        // behaviour here; across 85 databases that is the single most expensive query the
        // site can issue, and nobody asked for it on purpose.
        if (!checkedOrgs.length) {
            showSelectHint(moopTm('Choose at least one organism in Step %s.', '<span class="step-ref">2</span>'));
            return;
        }
        if ($('.source-cb:checked').length === 0) {
            showSelectHint(moopTm('Choose at least one annotation type in Step %s.', '<span class="step-ref">3</span>'));
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

    $('#search-cancel-btn, #search-cancel-progress-btn').on('click', () => searchManager.cancel());

    // Pre-selects from the page the visitor came from (scopeContext), then hands every later
    // change to onScopeChange. The context does not go through onScopeChange: Step 3 loads
    // once, here, rather than once scheduled plus once now.
    initScopeSelector({
        context:  typeof scopeContext !== 'undefined' ? scopeContext : null,
        onChange: onScopeChange,
    });
    loadAnnotationSources();

    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
});
