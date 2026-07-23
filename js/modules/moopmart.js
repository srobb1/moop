/**
 * MOOPmart — Gene List Builder UI
 *
 * Requires globals injected via inline_scripts:
 *   annotationSources — [source_name, ...]
 *   moopSite          — '/moop' (no trailing slash)
 *   scopeContext      — {organisms:[...]} | null
 */

(function () {
    'use strict';

    // Preview fans out one request per organism so results fill in progressively.
    const PREVIEW_ORG_URL = moopSite + '/api/moopmart_preview_organism.php';
    const EXPORT_URL      = moopSite + '/api/moopmart_export.php';
    const CHRS_URL        = moopSite + '/api/moopmart_chrs.php';

    // Max rows rendered in the progressive preview table (count stays exact).
    const PREVIEW_ROW_CAP = 100;

    // -------------------------------------------------------
    // Step 1 — Organism scope (flat list)
    // -------------------------------------------------------

    function updateScopeSummary() {
        const el        = document.getElementById('mm-scope-counts');
        const namesEl   = document.getElementById('mm-scope-names');
        if (!el) return;
        refreshAnnotationCounts();   // keep Step 3 availability counts in sync with the selection
        const checked = Array.from(document.querySelectorAll('.mm-gs-cb:checked'));
        if (!checked.length) {
            el.textContent = 'Select at least one organism above';
            if (namesEl) namesEl.textContent = '';
            return;
        }
        const orgs = new Set(checked.map(c => c.dataset.org)).size;
        const asms = new Set(checked.map(c => c.dataset.org + '|' + c.dataset.asm)).size;
        const gs   = checked.length;
        el.textContent = `${orgs} organism${orgs !== 1 ? 's' : ''}, ${asms} assembl${asms !== 1 ? 'ies' : 'y'}, ${gs} gene set${gs !== 1 ? 's' : ''} selected`;

        if (namesEl) {
            const orgNames = [...new Map(checked.map(c => [
                c.dataset.org,
                c.closest('.mm-scope-row')?.querySelector('em')?.textContent || c.dataset.org.replace(/_/g, ' ')
            ])).values()];
            const MAX = 6;
            namesEl.textContent = orgNames.slice(0, MAX).join(', ') + (orgNames.length > MAX ? ` + ${orgNames.length - MAX} more` : '');
        }
    }

    // -------------------------------------------------------
    // Step 3 — annotation availability counts for the selected organisms
    // -------------------------------------------------------

    let annCountTimer = null;
    let annCountSeq   = 0;   // discard out-of-order responses

    // Update the per-source count badges in Step 3 to reflect the organisms selected
    // in Step 1. Counts are across ALL genes of those organisms (not the gene filter),
    // so a source with a count but an empty preview means the current gene subset lacks
    // it, while 0 means the selected organisms have none. Reuses the annotation-search
    // pages' endpoint. Debounced so bulk (de)selection fires a single request.
    function refreshAnnotationCounts() {
        clearTimeout(annCountTimer);
        annCountTimer = setTimeout(() => {
            const badges  = document.querySelectorAll('.mm-ann-count');
            const summary = document.getElementById('mm-ann-counts');
            const orgs    = getSelectedOrganisms();
            if (!orgs.length) {
                // No selection yet — clear counts and show the full list (subject to text filter)
                badges.forEach(b => { b.textContent = ''; b.classList.add('d-none'); });
                document.querySelectorAll('.mm-ann-item').forEach(it => it.classList.remove('mm-ann-zerocount'));
                applyAnnVisibility();
                if (summary) summary.textContent = '';
                return;
            }
            if (summary) summary.textContent = 'Counting annotations…';
            const seq = ++annCountSeq;
            const url = `${moopSite}/tools/get_annotation_sources_grouped.php?organisms=`
                      + encodeURIComponent(orgs.join(','));
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (seq !== annCountSeq) return;   // superseded by a newer selection
                    const counts = {};
                    Object.values(data.source_types || {}).forEach(td =>
                        (td.sources || []).forEach(s => { counts[s.name] = (counts[s.name] || 0) + s.count; }));
                    badges.forEach(b => {
                        const n = counts[b.dataset.src] || 0;
                        // Mark sources with no annotations for this selection; applyAnnVisibility() hides them
                        b.closest('.mm-ann-item')?.classList.toggle('mm-ann-zerocount', n === 0);
                        if (n > 0) {
                            b.textContent = n.toLocaleString();
                            b.classList.remove('d-none', 'bg-light', 'text-muted');
                            b.classList.add('bg-secondary');
                        } else {
                            b.textContent = '';
                            b.classList.add('d-none');
                        }
                    });
                    applyAnnVisibility();
                    if (summary) {
                        const total = data.total_annotations || 0;
                        summary.textContent = total > 0
                            ? `${total.toLocaleString()} annotations across ${data.total_sources || 0} source${(data.total_sources || 0) !== 1 ? 's' : ''} for the selected organism${orgs.length !== 1 ? 's' : ''}`
                            : 'No annotations for the selected organisms';
                    }
                })
                .catch(() => { if (summary) summary.textContent = ''; /* keep prior badges */ });
        }, 250);
    }

    // Show an annotation source only when it passes the text filter AND has annotations
    // for the current organism selection (marked via .mm-ann-zerocount). Groups with no
    // visible sources are hidden. Shared by the text filter and the count refresh so the
    // two never fight over the same d-none class.
    function applyAnnVisibility() {
        const q = (document.getElementById('mm-ann-filter')?.value || '').toLowerCase();
        document.querySelectorAll('.mm-ann-group').forEach(group => {
            let anyVisible = false;
            group.querySelectorAll('.mm-ann-item').forEach(item => {
                const passText = !q || (item.querySelector('label')?.textContent.toLowerCase() || '').includes(q);
                const show = passText && !item.classList.contains('mm-ann-zerocount');
                item.classList.toggle('d-none', !show);
                if (show) anyVisible = true;
            });
            group.classList.toggle('d-none', !anyVisible);
        });
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
            const total   = list.querySelectorAll('.mm-gs-cb').length;
            const countEl = document.getElementById('mm-select-all-count');
            if (countEl) countEl.textContent = total + (total === 1 ? ' gene set' : ' gene sets');
            const modalEl = document.getElementById('mm-select-all-modal');
            const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
            const confirmBtn = document.getElementById('mm-select-all-confirm');
            confirmBtn.onclick = function () {
                modal.hide();
                list.querySelectorAll('.mm-gs-cb').forEach(cb => {
                    cb.checked = true;
                    cb.closest('.mm-scope-row')?.classList.add('selected');
                });
                updateScopeSummary();
                updateCoordState();
            };
            modal.show();
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
    // Step 2 — Annotation criteria rows (add / remove)
    // -------------------------------------------------------

    function initAnnCriteria() {
        const container = document.getElementById('mm-ann-criteria');
        if (!container) return;

        document.getElementById('mm-add-criterion')?.addEventListener('click', function () {
            const newRow = document.createElement('div');
            newRow.className = 'mm-ann-criterion row g-2 mb-2 align-items-center';
            newRow.innerHTML =
                '<div class="col-sm-4">' + mmAnnDropdownHtml + '</div>' +
                '<div class="col-sm-4"><input type="text" class="form-control form-control-sm moop-input mm-ann-accession" placeholder="e.g. GO:0006351"></div>' +
                '<div class="col-sm-3"><input type="text" class="form-control form-control-sm moop-input mm-ann-keyword" placeholder="e.g. transporter"></div>' +
                '<div class="col-sm-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger py-0 mm-remove-criterion" title="Remove"><i class="fa fa-times"></i></button></div>';
            container.appendChild(newRow);
        });

        container.addEventListener('click', function (e) {
            const btn = e.target.closest('.mm-remove-criterion');
            if (btn) btn.closest('.mm-ann-criterion').remove();
        });
    }

    // -------------------------------------------------------
    // Step 3 — Feature column list (ordered, toggleable)
    // -------------------------------------------------------

    function renumberColList(list, startAt = 1) {
        let n = startAt;
        list.querySelectorAll('.mm-col-item').forEach(item => {
            const badge = item.querySelector('.mm-col-num');
            const label = item.querySelector('.mm-col-label');
            if (item.classList.contains('selected')) {
                badge.textContent = n++;
                badge.style.visibility = 'visible';
                item.style.background = 'rgba(8,145,178,0.09)';
                item.style.borderColor = '#0891b2';
                label.style.color = '';
            } else {
                badge.style.visibility = 'hidden';
                item.style.background = '#fff';
                item.style.borderColor = '#dee2e6';
                label.style.color = '#adb5bd';
            }
        });
        return n; // next available number
    }

    function renumberBothColLists() {
        const featList = document.getElementById('mm-feat-col-list');
        const annList  = document.getElementById('mm-ann-col-list');
        const next = featList ? renumberColList(featList, 1) : 1;
        if (annList) renumberColList(annList, next);
    }

    function initColList() {
        ['mm-feat-col-list', 'mm-ann-col-list'].forEach(id => {
            const list = document.getElementById(id);
            if (!list) return;

            list.querySelectorAll('.mm-col-item').forEach(item => item.classList.add('selected'));

            list.addEventListener('click', function (e) {
                const item = e.target.closest('.mm-col-item');
                if (!item) return;

                if (e.target.closest('.mm-col-up')) {
                    const prev = item.previousElementSibling;
                    if (prev) list.insertBefore(item, prev);
                } else if (e.target.closest('.mm-col-down')) {
                    const next = item.nextElementSibling;
                    if (next) list.insertBefore(next, item);
                } else {
                    item.classList.toggle('selected');
                }
                renumberBothColLists();
            });
        });
        renumberBothColLists();
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
    // Step 3 — Wide / Long switch label colours
    // -------------------------------------------------------

    function initWideLongSwitch() {
        const sw      = document.getElementById('mm-ann-wide-switch');
        const lblLong = document.getElementById('mm-label-long');
        const lblWide = document.getElementById('mm-label-wide');
        if (!sw) return;

        function updateLabels() {
            const isWide = sw.checked;
            if (lblLong) { lblLong.style.color = isWide ? '#adb5bd' : '#0891b2'; lblLong.style.fontWeight = isWide ? 'normal' : '600'; }
            if (lblWide) { lblWide.style.color = isWide ? '#0891b2' : '#adb5bd'; lblWide.style.fontWeight = isWide ? '600' : 'normal'; }
        }

        sw.addEventListener('change', updateLabels);
        updateLabels();
    }

    // -------------------------------------------------------
    // Step 3 — Output format toggle (TSV / FASTA)
    // -------------------------------------------------------

    function initFormatToggle() {
        const sw        = document.getElementById('mm-format-switch');
        const tsvOpts   = document.getElementById('mm-tsv-options');
        const fastaOpts = document.getElementById('mm-fasta-options');
        const dlLabel   = document.getElementById('mm-dl-label');
        const lblTsv    = document.getElementById('mm-label-tsv');
        const lblFasta  = document.getElementById('mm-label-fasta');
        if (!sw) return;

        function updateFormat() {
            const isFasta = sw.checked;
            tsvOpts?.classList.toggle('d-none', isFasta);
            fastaOpts?.classList.toggle('d-none', !isFasta);
            if (dlLabel) dlLabel.textContent = isFasta ? 'Download FASTA' : 'Download TSV';
            if (lblTsv)  { lblTsv.style.color  = isFasta ? '#adb5bd' : '#0891b2'; lblTsv.style.fontWeight  = isFasta ? 'normal' : '600'; }
            if (lblFasta){ lblFasta.style.color = isFasta ? '#0891b2' : '#adb5bd'; lblFasta.style.fontWeight = isFasta ? '600' : 'normal'; }
            // Clear stale preview when switching formats
            document.getElementById('mm-results-section')?.classList.add('d-none');
            document.getElementById('mm-fasta-preview-section')?.classList.add('d-none');
        }

        sw.addEventListener('change', updateFormat);
        updateFormat();

        // FASTA sequence type → show/hide flank input
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

        document.getElementById('mm-ann-filter')?.addEventListener('input', applyAnnVisibility);

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

    // Distinct organism dirs among the selected sources — the fan-out unit for the
    // progressive preview (one request per organism).
    function getSelectedOrganisms() {
        const seen = new Set();
        document.querySelectorAll('.mm-gs-cb:checked').forEach(c => seen.add(c.dataset.org));
        return Array.from(seen);
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

        // Section: Feature IDs
        const rawIds = document.getElementById('mm-feature-ids')?.value?.trim();
        if (rawIds) parseIds(rawIds).forEach(id => fd.append('feature_ids[]', id));

        // Section: Name
        const name = document.getElementById('mm-gene-name')?.value?.trim();
        if (name) fd.append('gene_name', name);

        // Section: Description
        const desc = document.getElementById('mm-gene-description')?.value?.trim();
        if (desc) fd.append('gene_description', desc);

        // Section: Wide/Long toggle
        const annFormat = document.getElementById('mm-ann-wide-switch')?.checked ? 'wide' : 'long';
        fd.append('ann_format', annFormat);

        // Section: Annotation — repeatable criteria rows (AND between them)
        document.querySelectorAll('.mm-ann-criterion').forEach(row => {
            const src = row.querySelector('.mm-ann-src-select')?.value?.trim() || '';
            const acc = row.querySelector('.mm-ann-accession')?.value?.trim() || '';
            const kw  = row.querySelector('.mm-ann-keyword')?.value?.trim() || '';
            if (src || acc || kw) {
                fd.append('ann_criteria_src[]', src);
                fd.append('ann_criteria_acc[]', acc);
                fd.append('ann_criteria_kw[]',  kw);
            }
        });

        // Section: Location
        const chr   = document.getElementById('mm-coord-chr')?.value?.trim();
        const start = document.getElementById('mm-coord-start')?.value;
        const end   = document.getElementById('mm-coord-end')?.value;
        if (chr)   fd.append('coord_chr',   chr);
        if (start) fd.append('coord_start', start);
        if (end)   fd.append('coord_end',   end);

        // Feature columns — ordered by list position, selected only
        document.querySelectorAll('#mm-feat-col-list .mm-col-item.selected').forEach(item => fd.append('feature_columns[]', item.dataset.col));

        // Annotation basic columns — ordered by list position, selected only
        document.querySelectorAll('#mm-ann-col-list .mm-col-item.selected').forEach(item => fd.append('ann_columns[]', item.dataset.col));

        // Annotation sources
        document.querySelectorAll('.mm-ann-col:checked').forEach(c => fd.append('annotation_columns[]', c.value));

        Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
        return fd;
    }

    // -------------------------------------------------------
    // Preview
    // -------------------------------------------------------

    let resultsTable = null;

    // Fully tear down the preview DataTable: destroy the instance if one exists (checking
    // the DOM, not just our module var, in case they desync) and empty the <table> so the
    // next build can use a different column set. Re-initialising DataTables on leftover
    // header/body DOM throws "Cannot reinitialise". Safe to call when no table exists.
    function destroyResultsTable() {
        if ($.fn.dataTable.isDataTable('#mm-results-table')) {
            $('#mm-results-table').DataTable().clear().destroy();
        }
        $('#mm-results-table').empty();
        resultsTable = null;
    }

    function previewResults() {
        const btn     = document.getElementById('mm-preview-btn');
        const spinner = document.getElementById('mm-count-spinner');
        const result  = document.getElementById('mm-count-result');

        if (!getSelectedSources().length) {
            if (result) { result.textContent = 'Select at least one organism in Step 1 first.'; result.className = 'small text-warning'; }
            return;
        }

        spinner?.classList.remove('d-none');
        if (btn) btn.disabled = true;
        if (result) result.textContent = '';

        const csrf   = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const format = document.getElementById('mm-format-switch')?.checked ? 'fasta' : 'tsv';

        if (format === 'fasta') {
            const fastaMode = document.querySelector('input[name="mm-fasta-type"]:checked')?.value || 'gene';
            const flank     = document.getElementById('mm-flank-bp')?.value || '1000';
            const fd = buildFormData({ output_format: 'fasta', fasta_mode: fastaMode, flank_bp: flank, fasta_preview: '1' });
            fetch(EXPORT_URL, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
                .then(r => r.text())
                .then(text => {
                    if (result) {
                        result.textContent = text.trim() ? 'Showing first 10 sequences' : 'No sequences matched';
                        result.className = 'small ' + (text.trim() ? 'text-success' : 'text-muted');
                    }
                    renderFastaPreview(text);
                })
                .catch(() => {
                    if (result) { result.textContent = 'Error fetching FASTA preview.'; result.className = 'small text-danger'; }
                })
                .finally(() => {
                    spinner?.classList.add('d-none');
                    if (btn) btn.disabled = false;
                });
        } else {
            runTsvPreviewFanout(csrf, btn, spinner, result);
        }
    }

    // Progressive TSV preview: fan out one request per selected organism (concurrency
    // 5, mirroring the AnnotationSearch pattern) so the count and table fill in as each
    // organism reports, instead of blocking on one all-organisms request. The running
    // total stays exact; the table shows the first PREVIEW_ROW_CAP rows.
    function runTsvPreviewFanout(csrf, btn, spinner, result) {
        const organisms = getSelectedOrganisms();
        const total = organisms.length;

        resetResultsTable();
        const accRows = [];
        let totalRows = 0, totalGenes = 0, completed = 0, failed = 0, annHeaders = null;

        const showStatus = (done) => {
            if (!result) return;
            // A partial run must never look complete: the totals below only cover the
            // organisms that answered, so say so rather than reporting an exact-looking
            // number that silently omits the ones that failed.
            const failNote = failed ? ` · ${failed} organism${failed !== 1 ? 's' : ''} failed` : '';
            if (totalRows > 0) {
                result.textContent =
                    `${totalGenes.toLocaleString()} gene${totalGenes !== 1 ? 's' : ''} → `
                    + `${totalRows.toLocaleString()} transcript row${totalRows !== 1 ? 's' : ''}`
                    + (done ? '' : ` · ${completed}/${total} organism${total !== 1 ? 's' : ''}…`)
                    + failNote;
                result.className = 'small ' + (failed ? 'text-warning' : 'text-success');
            } else if (done && failed) {
                result.textContent = `Preview failed for all ${failed} organism${failed !== 1 ? 's' : ''}.`;
                result.className = 'small text-danger';
            } else {
                result.textContent = done
                    ? 'No features matched'
                    : `Searching… ${completed}/${total} organism${total !== 1 ? 's' : ''}`;
                result.className = 'small text-muted';
            }
        };

        const onData = (data) => {
            totalGenes += data.gene_count ?? 0;
            totalRows  += data.count ?? 0;
            if (annHeaders === null) annHeaders = data.ann_col_headers ?? [];

            const before = accRows.length;
            for (const r of (data.rows || [])) {
                if (accRows.length >= PREVIEW_ROW_CAP) break;
                accRows.push(r);
            }
            if (!accRows.length) return;
            if (!resultsTable) buildResultsTable(accRows, totalRows, annHeaders || []);
            else               updateResultsTable(accRows, totalRows, accRows.length !== before);
        };

        let nextIndex = 0;
        const launchNext = () => {
            if (nextIndex >= total) return;
            const organism = organisms[nextIndex++];
            fetch(PREVIEW_ORG_URL, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body: buildFormData({ organism }),
            })
                .then(r => {
                    // A 500 returns an HTML error page, which r.json() would reject on with a
                    // parse error that says nothing useful. Fail loudly on the status instead.
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                })
                .then(onData)
                .catch(err => {
                    // One organism failing shouldn't abort the run, but it must be counted so
                    // showStatus() can flag the totals as incomplete.
                    failed++;
                    console.error(`MOOPmart preview failed for ${organism}:`, err);
                })
                .finally(() => {
                    completed++;
                    const done = completed >= total;
                    showStatus(done);
                    if (done) {
                        spinner?.classList.add('d-none');
                        if (btn) btn.disabled = false;
                    } else {
                        launchNext();
                    }
                });
        };

        showStatus(false);
        const concurrency = Math.min(5, total);
        for (let i = 0; i < concurrency; i++) launchNext();
    }

    function renderFastaPreview(text) {
        const tableSection = document.getElementById('mm-results-section');
        const fastaSection = document.getElementById('mm-fasta-preview-section');
        const pre          = document.getElementById('mm-fasta-preview-text');
        tableSection?.classList.add('d-none');
        destroyResultsTable();
        if (!text.trim()) { fastaSection?.classList.add('d-none'); return; }
        if (pre) pre.textContent = text;
        fastaSection?.classList.remove('d-none');
        fastaSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Column specs keyed by the UI data-col value — annotation basic cols omitted (not in preview data)
    const FEAT_COL_SPECS = {
        organism:         { title: 'Organism',          data: 'organism_dir',     defaultContent: '', render: (v) => v?.replace(/_/g, ' ') || '' },
        assembly:         { title: 'Assembly',          data: 'genome_accession', defaultContent: '' },
        gene_set:         { title: 'Gene Set',          data: 'gene_set_name',    defaultContent: '' },
        gene_id:          { title: 'Gene ID',           data: 'uniquename',       defaultContent: '',
                            render: (val, type, row) => type !== 'display' ? val
                                : `<a href="${moopSite}/tools/parent.php?uniquename=${encodeURIComponent(val)}&organism=${encodeURIComponent(row.organism_dir)}" target="_blank">${val}</a>` },
        gene_name:        { title: 'Gene Name',         data: 'name',             defaultContent: '' },
        gene_description: { title: 'Gene Description',  data: 'description',      defaultContent: '',
                            render: (v, t) => t === 'display' && v?.length > 60 ? `<span title="${v.replace(/"/g, '&quot;')}">${v.slice(0, 60)}…</span>` : (v || '') },
        mrna_id:          { title: 'mRNA ID',           data: 'mrna_id',          defaultContent: '' },
        protein_id:       { title: 'Protein ID',        data: 'protein_id',       defaultContent: '' },
        chr:              { title: 'Chr',               data: 'chr',              defaultContent: '' },
        start:            { title: 'Start',             data: 'start',            defaultContent: '' },
        stop:             { title: 'Stop',              data: 'end',              defaultContent: '' },
        strand:           { title: 'Strand',            data: 'strand',           defaultContent: '' },
        why_included:     { title: 'Inclusion Criteria', data: 'match_reason',     defaultContent: '',
                            render: (v, t) => t === 'display' && v?.length > 80
                                ? `<span title="${v.replace(/"/g, '&quot;')}">${v.slice(0, 80)}…</span>`
                                : (v || '') },
    };

    function getPreviewColumns() {
        const items = document.querySelectorAll('#mm-feat-col-list .mm-col-item.selected');
        const cols = Array.from(items)
            .map(item => FEAT_COL_SPECS[item.dataset.col])
            .filter(Boolean);
        // Fall back to all columns in default order if nothing selected
        return cols.length ? cols : Object.values(FEAT_COL_SPECS);
    }

    function setResultsCaption(shown, total) {
        const caption = document.getElementById('mm-results-caption');
        if (!caption) return;
        caption.textContent = shown < total
            ? `— showing first ${shown.toLocaleString()} of ${total.toLocaleString()}`
            : `— ${total.toLocaleString()} result${total !== 1 ? 's' : ''}`;
    }

    // Tear down any existing preview table and hide the section — call at the start of
    // a fresh preview run so progressive updates begin from a clean slate.
    function resetResultsTable() {
        destroyResultsTable();
        document.getElementById('mm-results-section')?.classList.add('d-none');
    }

    // Build the preview DataTable once, revealing and scrolling to the section a single
    // time. Columns (feature cols + annotation headers) are fixed for the run, so later
    // organisms only add rows via updateResultsTable().
    function buildResultsTable(rows, total, annColHeaders = []) {
        const section = document.getElementById('mm-results-section');
        setResultsCaption(rows.length, total);
        section?.classList.remove('d-none');
        section?.scrollIntoView({ behavior: 'smooth', block: 'start' });

        const annCols = annColHeaders.map(header => ({
            title: header,
            data: (row) => row[header] ?? '',
            defaultContent: '',
        }));

        destroyResultsTable();
        resultsTable = $('#mm-results-table').DataTable({
            data: rows,
            columns: [...getPreviewColumns(), ...annCols],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']],
            autoWidth: false,
            dom: 'ltipr',
            language: { info: 'Showing _START_ to _END_ of _TOTAL_ preview rows', infoEmpty: 'No results', lengthMenu: 'Show _MENU_ rows' },
        });
    }

    // Refresh the preview table as more organisms report. Re-adds rows only when the
    // row set actually grew (preserving paging); always refreshes the caption so the
    // running total stays live even after the row cap is reached.
    function updateResultsTable(rows, total, rowsChanged) {
        if (!resultsTable) { buildResultsTable(rows, total); return; }
        if (rowsChanged) {
            resultsTable.clear();
            resultsTable.rows.add(rows);
            resultsTable.draw(false);
        }
        setResultsCaption(rows.length, total);
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
        initAnnCriteria();
        initColList();
        initAccordionHeaders();
        initWideLongSwitch();
        initFormatToggle();
        initAnnSources();

        if (typeof scopeContext !== 'undefined' && scopeContext) {
            applyContextScope(scopeContext);
        }

        // Popovers — close on outside click
        const popovers = [];
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
            popovers.push(new bootstrap.Popover(el, { trigger: 'click' }));
        });
        document.addEventListener('click', e => {
            popovers.forEach(pop => {
                if (!pop._element.contains(e.target) && !document.querySelector('.popover')?.contains(e.target)) {
                    pop.hide();
                }
            });
        });

        // Search instructions trigger (same modal as annotation search)

        // Preview
        document.getElementById('mm-preview-btn')?.addEventListener('click', previewResults);

        // Download (single button, format from radio)
        document.getElementById('mm-dl-btn')?.addEventListener('click', function () {
            const result = document.getElementById('mm-count-result');
            if (!getSelectedSources().length) {
                if (result) { result.textContent = 'Select at least one organism in Step 1 first.'; result.className = 'small text-warning'; }
                return;
            }
            const format    = document.getElementById('mm-format-switch')?.checked ? 'fasta' : 'tsv';
            const fastaMode = document.querySelector('input[name="mm-fasta-type"]:checked')?.value || 'gene';
            const flank     = document.getElementById('mm-flank-bp')?.value || '1000';
            submitDownload({ output_format: format, fasta_mode: fastaMode, flank_bp: flank });
        });

        updateCoordState();

        // Chevron rotation for step 3 collapse
        const designCollapse = document.getElementById('mm-design-body');
        const designChevron  = document.getElementById('mm-design-chevron');
        if (designCollapse && designChevron) {
            designCollapse.addEventListener('show.bs.collapse',  () => designChevron.style.transform = 'rotate(180deg)');
            designCollapse.addEventListener('hide.bs.collapse',  () => designChevron.style.transform = 'rotate(0deg)');
        }
    });

})();
