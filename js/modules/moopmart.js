/**
 * MOOPmart — Feature List Builder UI
 *
 * Requires globals injected via inline_scripts:
 *   annotationSources — [source_name, ...]
 *   moopSite          — '/moop' (no trailing slash)
 *   scopeContext      — {organisms:[...]} | null
 */

(function () {
    'use strict';

    const PREVIEW_URL = moopSite + '/api/moopmart_preview.php';
    const EXPORT_URL  = moopSite + '/api/moopmart_export.php';
    const CHRS_URL    = moopSite + '/api/moopmart_chrs.php';

    // -------------------------------------------------------
    // Step 1 — Organism scope (flat list)
    // -------------------------------------------------------

    function updateScopeSummary() {
        const el   = document.getElementById('mm-scope-counts');
        if (!el) return;
        const checked = Array.from(document.querySelectorAll('.mm-gs-cb:checked'));
        if (!checked.length) {
            el.textContent = 'No organisms selected — will include all accessible gene sets';
            return;
        }
        const orgs = new Set(checked.map(c => c.dataset.org)).size;
        const asms = new Set(checked.map(c => c.dataset.org + '|' + c.dataset.asm)).size;
        const gs   = checked.length;
        el.textContent = `${orgs} organism${orgs !== 1 ? 's' : ''}, ${asms} assembl${asms !== 1 ? 'ies' : 'y'}, ${gs} gene set${gs !== 1 ? 's' : ''} selected`;
    }

    function initScopeList() {
        const list = document.getElementById('mm-scope-list');
        if (!list) return;

        // Row click → toggle checkbox + selected state
        list.addEventListener('click', function (e) {
            const row = e.target.closest('.mm-scope-row');
            if (!row) return;
            const cb = row.querySelector('.mm-gs-cb');
            if (!cb || e.target === cb) return;
            cb.checked = !cb.checked;
            row.classList.toggle('selected', cb.checked);
            updateScopeSummary();
            updateCoordState();
        });
        list.addEventListener('change', function (e) {
            if (e.target.classList.contains('mm-gs-cb')) {
                e.target.closest('.mm-scope-row')?.classList.toggle('selected', e.target.checked);
                updateScopeSummary();
                updateCoordState();
            }
        });

        // Filter input
        document.getElementById('mm-scope-filter')?.addEventListener('input', function () {
            const q      = this.value.toLowerCase();
            const detail = document.getElementById('mm-scope-detail')?.checked;
            list.querySelectorAll('.mm-scope-row').forEach(row => {
                const search = detail
                    ? (row.dataset.search || '')
                    : (row.dataset.searchSimple || '');
                row.style.display = (!q || search.includes(q)) ? '' : 'none';
            });
        });

        // Detail toggle
        document.getElementById('mm-scope-detail')?.addEventListener('change', function () {
            list.classList.toggle('mm-scope-detail-hidden', !this.checked);
            // Re-run filter with new search field
            document.getElementById('mm-scope-filter')?.dispatchEvent(new Event('input'));
        });

        // All / None
        document.getElementById('mm-select-all')?.addEventListener('click', function () {
            list.querySelectorAll('.mm-gs-cb').forEach(cb => {
                cb.checked = true;
                cb.closest('.mm-scope-row')?.classList.add('selected');
            });
            updateScopeSummary();
            updateCoordState();
        });
        document.getElementById('mm-clear-all')?.addEventListener('click', function () {
            list.querySelectorAll('.mm-gs-cb').forEach(cb => {
                cb.checked = false;
                cb.closest('.mm-scope-row')?.classList.remove('selected');
            });
            updateScopeSummary();
            updateCoordState();
        });

        updateScopeSummary();
    }

    // -------------------------------------------------------
    // Step 1 — Coordinate filter (single-assembly only)
    // -------------------------------------------------------

    let lastChrSource = null;

    function getSelectedAssemblies() {
        const seen = new Set();
        document.querySelectorAll('.mm-gs-cb:checked').forEach(cb => {
            seen.add(cb.dataset.org + '|' + cb.dataset.asm);
        });
        return Array.from(seen);
    }

    function updateCoordState() {
        const asms   = getSelectedAssemblies();
        const single = asms.length === 1;
        const note   = document.getElementById('mm-coord-note');
        ['mm-coord-chr', 'mm-coord-start', 'mm-coord-end'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = !single;
            if (!single) el.value = '';
        });
        if (note) {
            note.textContent = single
                ? ''
                : 'Select exactly one assembly in Step 1 to enable location search.';
        }
        if (!single) { lastChrSource = null; return; }

        // Load chr names for the selected assembly
        const cb = Array.from(document.querySelectorAll('.mm-gs-cb:checked'))[0];
        const key = cb ? `${cb.dataset.org}|${cb.dataset.asm}|${cb.dataset.gs}` : null;
        if (!key || key === lastChrSource) return;
        lastChrSource = key;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const fd   = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('source', key);
        fetch(CHRS_URL, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
            .then(r => r.json())
            .then(data => {
                const dl = document.getElementById('mm-chr-datalist');
                if (!dl) return;
                dl.innerHTML = (data.chrs || []).map(c => `<option value="${c}">`).join('');
            })
            .catch(() => {});
    }

    // -------------------------------------------------------
    // Step 2 — AND/OR logic toggle
    // -------------------------------------------------------

    function initLogicToggle() {
        const hint = document.getElementById('mm-logic-hint');
        document.querySelectorAll('input[name="mm-logic"]').forEach(radio => {
            radio.addEventListener('change', function () {
                if (!hint) return;
                hint.textContent = this.value === 'and'
                    ? 'Features must match ALL filled sections'
                    : 'Features match ANY filled section';
            });
        });
    }

    // -------------------------------------------------------
    // Step 2 — Accordion header → panel connection
    // -------------------------------------------------------

    function initAccordionHeaders() {
        // Sync browse-select-header aria-expanded → square bottom corners on its panel
        document.querySelectorAll('.browse-select-header[data-bs-toggle="collapse"]').forEach(header => {
            const targetId = header.dataset.bsTarget?.replace('#', '');
            if (!targetId) return;
            const panel = document.getElementById(targetId);
            if (!panel) return;
            panel.addEventListener('show.bs.collapse', () => header.classList.add('header-open'));
            panel.addEventListener('hide.bs.collapse', () => header.classList.remove('header-open'));
        });
    }

    // -------------------------------------------------------
    // Step 3 — Output format toggle (TSV / FASTA)
    // -------------------------------------------------------

    function initFormatToggle() {
        const tsvOpts   = document.getElementById('mm-tsv-options');
        const fastaOpts = document.getElementById('mm-fasta-options');
        const dlLabel   = document.getElementById('mm-dl-label');

        document.querySelectorAll('input[name="mm-format"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const isFasta = this.value === 'fasta';
                tsvOpts?.classList.toggle('d-none', isFasta);
                fastaOpts?.classList.toggle('d-none', !isFasta);
                if (dlLabel) dlLabel.textContent = isFasta ? 'Download FASTA' : 'Download TSV';
            });
        });

        // FASTA type → show/hide flank input
        document.querySelectorAll('.mm-fasta-mode').forEach(radio => {
            radio.addEventListener('change', function () {
                const flankWrap = document.getElementById('mm-flank-wrap');
                if (flankWrap) {
                    flankWrap.classList.toggle('d-none', !['upstream', 'downstream'].includes(this.value));
                }
            });
        });
    }

    // -------------------------------------------------------
    // Step 3 — Annotation sources panel
    // -------------------------------------------------------

    function updateAnnSummary() {
        const el      = document.getElementById('mm-ann-counts');
        if (!el) return;
        const total   = document.querySelectorAll('.mm-ann-col').length;
        const checked = document.querySelectorAll('.mm-ann-col:checked').length;
        if (!total) { el.textContent = ''; return; }
        el.textContent = checked === total
            ? `— all ${total} selected`
            : `— ${checked} of ${total} selected`;
    }

    function syncAnnTypeCb(type) {
        const sources = Array.from(document.querySelectorAll(`.mm-ann-col[data-type="${type}"]`));
        const typeCb  = document.querySelector(`.mm-ann-type-cb[data-type="${type}"]`);
        if (!typeCb || !sources.length) return;
        const allOn  = sources.every(c => c.checked);
        const allOff = sources.every(c => !c.checked);
        typeCb.checked       = allOn;
        typeCb.indeterminate = !allOn && !allOff;
    }

    function initAnnSources() {
        const panel = document.getElementById('mm-ann-panel');
        if (!panel) return;

        panel.addEventListener('change', function (e) {
            const cb = e.target;
            if (cb.classList.contains('mm-ann-type-cb')) {
                document.querySelectorAll(`.mm-ann-col[data-type="${cb.dataset.type}"]`)
                    .forEach(c => { c.checked = cb.checked; c.indeterminate = false; });
            } else if (cb.classList.contains('mm-ann-col')) {
                syncAnnTypeCb(cb.dataset.type);
            }
            updateAnnSummary();
        });

        document.getElementById('mm-ann-all')?.addEventListener('click', () => {
            document.querySelectorAll('.mm-ann-col').forEach(c => c.checked = true);
            document.querySelectorAll('.mm-ann-type-cb').forEach(c => { c.checked = true; c.indeterminate = false; });
            updateAnnSummary();
        });
        document.getElementById('mm-ann-none')?.addEventListener('click', () => {
            document.querySelectorAll('.mm-ann-col, .mm-ann-type-cb').forEach(c => { c.checked = false; c.indeterminate = false; });
            updateAnnSummary();
        });

        document.getElementById('mm-ann-filter')?.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.mm-ann-group').forEach(group => {
                let anyVisible = false;
                group.querySelectorAll('.mm-ann-item').forEach(item => {
                    const show = !q || (item.querySelector('label')?.textContent.toLowerCase() || '').includes(q);
                    item.classList.toggle('d-none', !show);
                    if (show) anyVisible = true;
                });
                group.classList.toggle('d-none', !anyVisible && !!q);
            });
        });

        document.querySelectorAll('.mm-ann-type-cb').forEach(cb => syncAnnTypeCb(cb.dataset.type));
        updateAnnSummary();
    }

    // -------------------------------------------------------
    // Payload builders
    // -------------------------------------------------------

    function getSelectedSources() {
        return Array.from(document.querySelectorAll('.mm-gs-cb:checked'))
            .map(c => `${c.dataset.org}|${c.dataset.asm}|${c.dataset.gs}`);
    }

    function parseIds(raw) {
        // Accept comma, whitespace, or newline separated IDs
        return raw.split(/[\s,]+/).map(s => s.trim()).filter(Boolean);
    }

    function buildFormData(extra = {}) {
        const fd   = new FormData();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fd.append('csrf_token', csrf);

        getSelectedSources().forEach(s => fd.append('sources[]', s));

        // Logic (AND/OR)
        const logic = document.querySelector('input[name="mm-logic"]:checked')?.value || 'and';
        fd.append('logic', logic);

        // Section: Feature IDs
        const rawIds = document.getElementById('mm-feature-ids')?.value?.trim();
        if (rawIds) parseIds(rawIds).forEach(id => fd.append('feature_ids[]', id));

        // Section: Name
        const name = document.getElementById('mm-gene-name')?.value?.trim();
        if (name) fd.append('gene_name', name);

        // Section: Description
        const desc = document.getElementById('mm-gene-description')?.value?.trim();
        if (desc) fd.append('gene_description', desc);

        // Section: Annotation
        const annSrc = document.getElementById('mm-annotation-source')?.value;
        if (annSrc) fd.append('annotation_source', annSrc);
        const annAcc = document.getElementById('mm-annotation-accession')?.value?.trim();
        if (annAcc) fd.append('annotation_accession', annAcc);
        const annKw  = document.getElementById('mm-annotation-keyword')?.value?.trim();
        if (annKw)  fd.append('annotation_keyword', annKw);

        // Section: Location
        const chr   = document.getElementById('mm-coord-chr')?.value?.trim();
        const start = document.getElementById('mm-coord-start')?.value;
        const end   = document.getElementById('mm-coord-end')?.value;
        if (chr)   fd.append('coord_chr',   chr);
        if (start) fd.append('coord_start', start);
        if (end)   fd.append('coord_end',   end);

        // Feature columns
        document.querySelectorAll('.mm-feat-col:checked').forEach(c => fd.append('feature_columns[]', c.value));

        // Annotation basic columns
        document.querySelectorAll('.mm-ann-col-basic:checked').forEach(c => fd.append('ann_columns[]', c.value));

        // Annotation sources
        document.querySelectorAll('.mm-ann-col:checked').forEach(c => fd.append('annotation_columns[]', c.value));

        Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
        return fd;
    }

    // -------------------------------------------------------
    // Preview
    // -------------------------------------------------------

    let resultsTable = null;

    function previewResults() {
        const btn     = document.getElementById('mm-preview-btn');
        const spinner = document.getElementById('mm-count-spinner');
        const result  = document.getElementById('mm-count-result');

        spinner?.classList.remove('d-none');
        if (btn) btn.disabled = true;
        if (result) result.textContent = '';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(PREVIEW_URL, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: buildFormData() })
            .then(r => r.json())
            .then(data => {
                const total = data.count ?? 0;
                const rows  = data.rows  ?? [];
                if (result) {
                    result.textContent = total > 0
                        ? `${total.toLocaleString()} feature${total !== 1 ? 's' : ''} matched`
                        : 'No features matched';
                    result.className = 'small ' + (total > 0 ? 'text-success' : 'text-muted');
                }
                renderResultsTable(rows, total);
            })
            .catch(() => {
                if (result) { result.textContent = 'Error fetching preview.'; result.className = 'small text-danger'; }
            })
            .finally(() => {
                spinner?.classList.add('d-none');
                if (btn) btn.disabled = false;
            });
    }

    function renderResultsTable(rows, total) {
        const section = document.getElementById('mm-results-section');
        const caption = document.getElementById('mm-results-caption');
        if (!rows.length) { section?.classList.add('d-none'); return; }

        if (caption) caption.textContent = rows.length < total
            ? `— showing first ${rows.length.toLocaleString()} of ${total.toLocaleString()}`
            : `— ${total.toLocaleString()} result${total !== 1 ? 's' : ''}`;
        section?.classList.remove('d-none');
        section?.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (resultsTable) { resultsTable.destroy(); resultsTable = null; }
        resultsTable = $('#mm-results-table').DataTable({
            data: rows,
            columns: [
                { title: 'Feature ID',  data: 'uniquename',
                  render: (val, type, row) => type !== 'display' ? val
                    : `<a href="${moopSite}/tools/parent.php?uniquename=${encodeURIComponent(val)}&organism=${encodeURIComponent(row.organism_dir)}" target="_blank">${val}</a>` },
                { title: 'Name',        data: 'name',             defaultContent: '' },
                { title: 'Type',        data: 'type',             defaultContent: '' },
                { title: 'Description', data: 'description',      defaultContent: '',
                  render: (v, t) => t === 'display' && v?.length > 60 ? `<span title="${v.replace(/"/g,'&quot;')}">${v.slice(0,60)}…</span>` : (v || '') },
                { title: 'Organism',    data: 'organism_dir',     defaultContent: '', render: v => v?.replace(/_/g,' ') || '' },
                { title: 'Assembly',    data: 'genome_accession', defaultContent: '' },
                { title: 'Gene Set',    data: 'gene_set_name',    defaultContent: '' },
                { title: 'Chr',         data: 'chr',              defaultContent: '' },
                { title: 'Start',       data: 'start',            defaultContent: '' },
                { title: 'End',         data: 'end',              defaultContent: '' },
                { title: 'Strand',      data: 'strand',           defaultContent: '' },
            ],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']],
            autoWidth: false,
            dom: 'ltipr',
            language: { info: 'Showing _START_ to _END_ of _TOTAL_ preview rows', infoEmpty: 'No results', lengthMenu: 'Show _MENU_ rows' },
        });
    }

    // -------------------------------------------------------
    // Download
    // -------------------------------------------------------

    function submitDownload(extraFields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = EXPORT_URL;
        form.style.display = 'none';
        for (const [key, value] of buildFormData(extraFields).entries()) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = key; inp.value = value;
            form.appendChild(inp);
        }
        document.body.appendChild(form);
        form.submit();
        setTimeout(() => form.remove(), 5000);
    }

    // -------------------------------------------------------
    // Scope context (pre-select when launched from toolbox)
    // -------------------------------------------------------

    function applyContextScope(ctx) {
        if (!ctx) return;
        const keepOrgs = new Set(ctx.organisms || (ctx.organism ? [ctx.organism] : []));
        if (!keepOrgs.size) return;
        document.querySelectorAll('.mm-gs-cb').forEach(cb => {
            const on = keepOrgs.has(cb.dataset.org);
            cb.checked = on;
            cb.closest('.mm-scope-row')?.classList.toggle('selected', on);
        });
        updateScopeSummary();
        updateCoordState();
    }

    // -------------------------------------------------------
    // Init
    // -------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        initScopeList();
        initLogicToggle();
        initAccordionHeaders();
        initFormatToggle();
        initAnnSources();

        if (typeof scopeContext !== 'undefined' && scopeContext) {
            applyContextScope(scopeContext);
        }

        // Popovers
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
            new bootstrap.Popover(el, { trigger: 'click' });
        });

        // Preview
        document.getElementById('mm-preview-btn')?.addEventListener('click', previewResults);

        // Download (single button, format from radio)
        document.getElementById('mm-dl-btn')?.addEventListener('click', function () {
            const format    = document.querySelector('input[name="mm-format"]:checked')?.value || 'tsv';
            const annFormat = document.querySelector('input[name="mm-ann-format"]:checked')?.value || 'wide';
            const fastaMode = document.querySelector('input[name="mm-fasta-type"]:checked')?.value || 'gene';
            const flank     = document.getElementById('mm-flank-bp')?.value || '1000';
            submitDownload({ output_format: format, ann_format: annFormat, fasta_mode: fastaMode, flank_bp: flank });
        });

        updateCoordState();
    });

})();
