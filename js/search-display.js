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
        // Debounce source reload so rapid checkbox changes don't spam the endpoint
        clearTimeout(sourcesLoadTimer);
        sourcesLoadTimer = setTimeout(() => {
            loadAnnotationSources(getCheckedOrganisms());
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

    // Build selectedScope object for AnnotationSearch from current tree state
    function buildSelectedScope() {
        const scope = {};
        let hasExclusion = false;

        $('.scope-gs-cb').each(function () {
            const org     = $(this).data('org');
            const asm     = $(this).data('asm');
            const gs      = $(this).data('gs');
            if (!scope[org])       scope[org]       = {};
            if (!scope[org][asm])  scope[org][asm]  = {};
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
            $('#sourcesPanel').html('<p class="text-muted small p-2">Select at least one organism to see annotation sources.</p>');
            $('#sources-summary').text('');
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
                const checked   = sourceOverrides[src.name] !== false;
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

        updateSourcesSummary();
    }

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
        const allSources = $('.source-cb').length;
        // If all checked (or none rendered yet), return null (= no filter)
        return (sources.length === allSources) ? null : sources;
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
        if (!checkedOrgs.length) {
            alert('Please select at least one organism in the Scope panel.');
            return;
        }

        // Inject current scope + sources into the search manager
        searchManager.selectedScope            = buildSelectedScope();
        searchManager.selectedSources          = getCheckedSources();
        searchManager.config.organismsVar      = checkedOrgs;
        searchManager.config.totalVar          = checkedOrgs.length;

        searchManager.handleSearch();
    });

    // ── Init ─────────────────────────────────────────────────────────────────

    updateScopeSummary();
    loadAnnotationSources(allOrganisms);
});
