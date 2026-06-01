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

    // Collapse / expand organism body via chevron
    $(document).on('click', '.scope-toggle', function () {
        const target = $('#' + $(this).data('target'));
        const open   = target.is(':visible');
        target.toggle(!open);
        $(this).toggleClass('fa-chevron-down', !open).toggleClass('fa-chevron-right', open);
    });

    // Org checkbox → cascade down to all asm + gs beneath it
    $(document).on('change', '.scope-org-cb', function () {
        const org     = $(this).data('org');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-org="' + org + '"].scope-asm-cb').prop({ checked, indeterminate: false });
        $('[data-org="' + org + '"].scope-gs-cb').prop('checked', checked);
        onScopeChange();
    });

    // Assembly checkbox → cascade down to gs, sync org up
    $(document).on('change', '.scope-asm-cb', function () {
        const org     = $(this).data('org');
        const asm     = $(this).data('asm');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-org="' + org + '"][data-asm="' + asm + '"].scope-gs-cb').prop('checked', checked);
        syncScopeAsmCb(org, asm);
        syncScopeOrgCb(org);
        onScopeChange();
    });

    // Gene-set checkbox → sync asm + org up
    $(document).on('change', '.scope-gs-cb', function () {
        const org = $(this).data('org');
        const asm = $(this).data('asm');
        syncScopeAsmCb(org, asm);
        syncScopeOrgCb(org);
        onScopeChange();
    });

    function syncScopeAsmCb(org, asm) {
        const boxes   = $('[data-org="' + org + '"][data-asm="' + asm + '"].scope-gs-cb');
        const total   = boxes.length;
        const checked = boxes.filter(':checked').length;
        const cb      = $('[data-org="' + org + '"][data-asm="' + asm + '"].scope-asm-cb')[0];
        if (!cb) return;
        cb.checked       = checked === total;
        cb.indeterminate = checked > 0 && checked < total;
    }

    function syncScopeOrgCb(org) {
        const boxes   = $('[data-org="' + org + '"].scope-gs-cb');
        const total   = boxes.length;
        const checked = boxes.filter(':checked').length;
        const cb      = $('[data-org="' + org + '"].scope-org-cb')[0];
        if (!cb) return;
        cb.checked       = checked === total;
        cb.indeterminate = checked > 0 && checked < total;
    }

    // Scope filter — flat rows use data-search attribute
    $('#scope-filter').on('input', function () {
        const q = $(this).val().trim().toLowerCase();
        $('.scope-flat-row').each(function () {
            const matches = !q || $(this).data('search').includes(q);
            $(this).toggle(matches);
        });
    });

    // Select All / Deselect All scope
    $('#scope-select-all').on('click', function () {
        $('.scope-org-cb, .scope-asm-cb, .scope-gs-cb').prop({ checked: true, indeterminate: false });
        onScopeChange();
    });
    $('#scope-deselect-all').on('click', function () {
        $('.scope-org-cb, .scope-asm-cb, .scope-gs-cb').prop({ checked: false, indeterminate: false });
        onScopeChange();
    });

    // Called whenever any scope checkbox changes
    let sourcesLoadTimer = null;
    function onScopeChange() {
        updateScopeSummary();
        clearTimeout(sourcesLoadTimer);
        sourcesLoadTimer = setTimeout(() => {
            const orgs = getCheckedOrganisms();
            loadAnnotationSources(orgs.length ? orgs : allOrganisms);
        }, 300);
    }

    function getCheckedOrganisms() {
        const orgs = [];
        $('.scope-org-cb').each(function () {
            // Include organism if at least one of its gene sets is checked
            const org     = $(this).data('org');
            const anyGs   = $('[data-org="' + org + '"].scope-gs-cb:checked').length > 0;
            if (anyGs) orgs.push(org);
        });
        return orgs;
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
        if (!organisms.length) organisms = allOrganisms;

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

    function renderSourcesPanel(sourceTypes) {
        if (!Object.keys(sourceTypes).length) {
            $('#sourcesPanel').html('<p class="text-muted small p-2">No annotation sources found for selected organisms.</p>');
            updateSourcesSummary();
            return;
        }

        let html = '';
        for (const [type, typeData] of Object.entries(sourceTypes)) {
            const color  = typeData.color  || 'secondary';
            const desc   = typeData.desc   || typeData.description || '';
            const sources = typeData.sources || [];

            html += `<div class="source-group mb-2">`;
            html += `<div class="d-flex align-items-center px-2 py-1 rounded mb-1"
                          style="background:#f1f3f5;">
                       <input type="checkbox" class="form-check-input me-2 mb-0 source-type-cb flex-shrink-0"
                              id="stype_${CSS.escape(type)}" data-type="${type}">
                       <label for="stype_${CSS.escape(type)}"
                              class="form-check-label fw-semibold mb-0 me-auto"
                              style="cursor:pointer; font-size:0.88rem;">
                         <span class="badge bg-${color} me-1">${type}</span>
                       </label>
                     </div>`;

            html += `<div class="ps-3">`;
            for (const src of sources) {
                const checked   = sourceOverrides[src.name] === true;
                const checkedAt = checked ? 'checked' : '';
                const count     = src.count ? ` <span class="text-muted">(${src.count.toLocaleString()})</span>` : '';
                html += `<div class="d-flex align-items-center gap-1 px-1 py-1">
                           <input type="checkbox" class="form-check-input flex-shrink-0 source-cb mb-0"
                                  id="src_${CSS.escape(src.name)}" data-source="${src.name}"
                                  data-type="${type}" ${checkedAt}>
                           <label for="src_${CSS.escape(src.name)}"
                                  class="form-check-label mb-0"
                                  style="cursor:pointer; font-size:0.82rem;">
                             ${src.name}${count}
                           </label>
                         </div>`;
            }
            html += `</div></div>`;
        }

        $('#sourcesPanel').html(html);

        // Sync type-level checkboxes
        $('.source-type-cb').each(function () {
            syncSourceTypeCb($(this).data('type'));
        });

        $('#sources-filter-wrap').show();
        filterSources();
        updateSourcesSummary();
    }

    function filterSources() {
        const q = ($('#sources-filter').val() || '').trim().toLowerCase();
        if (!q) {
            $('.source-group').show();
            $('.source-cb').closest('.d-flex').show();
            return;
        }
        $('.source-group').each(function () {
            let anyVisible = false;
            $(this).find('.source-cb').each(function () {
                const matches = String($(this).data('source')).toLowerCase().includes(q);
                $(this).closest('.d-flex').toggle(matches);
                if (matches) anyVisible = true;
            });
            $(this).toggle(anyVisible);
        });
    }

    $(document).on('input', '#sources-filter', filterSources);

    function syncSourceTypeCb(type) {
        const boxes   = $('[data-type="' + type + '"].source-cb');
        const total   = boxes.length;
        const checked = boxes.filter(':checked').length;
        const cb      = $('[data-type="' + type + '"].source-type-cb')[0];
        if (!cb) return;
        cb.checked       = checked === total;
        cb.indeterminate = checked > 0 && checked < total;
    }

    // Source type → cascade to all its sources
    $(document).on('change', '.source-type-cb', function () {
        const type    = $(this).data('type');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-type="' + type + '"].source-cb').each(function () {
            $(this).prop('checked', checked);
            sourceOverrides[$(this).data('source')] = checked;
        });
        updateSourcesSummary();
    });

    // Individual source checkbox
    $(document).on('change', '.source-cb', function () {
        const type = $(this).data('type');
        sourceOverrides[$(this).data('source')] = $(this).is(':checked');
        syncSourceTypeCb(type);
        updateSourcesSummary();
    });

    // Select All / Deselect All sources
    $('#sources-select-all').on('click', function () {
        $('.source-cb').each(function () {
            $(this).prop('checked', true);
            sourceOverrides[$(this).data('source')] = true;
        });
        $('.source-type-cb').prop({ checked: true, indeterminate: false });
        updateSourcesSummary();
    });
    $('#sources-deselect-all').on('click', function () {
        $('.source-cb').each(function () {
            $(this).prop('checked', false);
            sourceOverrides[$(this).data('source')] = false;
        });
        $('.source-type-cb').prop({ checked: false, indeterminate: false });
        updateSourcesSummary();
    });

    function getCheckedSources() {
        const sources = [];
        $('.source-cb:checked').each(function () {
            sources.push($(this).data('source'));
        });
        const total = $('.source-cb').length;
        // none checked OR all checked = no filter
        return (sources.length === 0 || sources.length === total) ? null : sources;
    }

    function updateSourcesSummary() {
        const checked = $('.source-cb:checked').length;
        const total   = $('.source-cb').length;
        if (!total) { $('#sources-summary').text(''); return; }
        $('#sources-summary').text(checked + ' / ' + total + ' source' + (total !== 1 ? 's' : '') + ' selected');
    }

    // ── Search form submit ───────────────────────────────────────────────────

    $('#searchForm').on('submit', function (e) {
        e.preventDefault();

        const checkedOrgs = getCheckedOrganisms();
        const activeOrgs  = checkedOrgs.length ? checkedOrgs : allOrganisms;

        searchManager.selectedScope            = buildSelectedScope();
        searchManager.selectedSources          = getCheckedSources();
        searchManager.config.organismsVar      = activeOrgs;
        searchManager.config.totalVar          = activeOrgs.length;

        searchManager.handleSearch();
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

        onScopeChange();
    }

    applyContextScope(typeof scopeContext !== 'undefined' ? scopeContext : null);
    updateScopeSummary();
    loadAnnotationSources(allOrganisms);
});
