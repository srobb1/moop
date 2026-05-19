/**
 * MOOPmart — MOOP Mega Search UI
 *
 * Requires globals injected via inline_scripts:
 *   annotationSources — [source_name, ...]
 *   moopSite          — '/moop' (no trailing slash)
 */

(function () {
    'use strict';

    const PREVIEW_URL = moopSite + '/api/moopmart_preview.php';
    const EXPORT_URL  = moopSite + '/api/moopmart_export.php';

    const FASTA_LABELS = {
        gene:       'Gene sequence',
        upstream:   'Upstream',
        downstream: 'Downstream',
        exons:      'Exons / sub-features',
        protein:    'Protein',
        transcript: 'Transcript (mRNA)',
        cds:        'CDS',
    };

    let currentFastaMode = 'gene';

    // -------------------------------------------------------
    // Scope tree — checkbox propagation (HTML rendered by PHP)
    // -------------------------------------------------------

    function updateScopeSummary() {
        const total   = document.querySelectorAll('.mm-gs-cb').length;
        const checked = document.querySelectorAll('.mm-gs-cb:checked').length;
        const el = document.getElementById('mm-scope-summary');
        if (!el) return;
        el.textContent = checked === total
            ? `All ${total} gene set${total !== 1 ? 's' : ''} selected`
            : `${checked} of ${total} gene set${total !== 1 ? 's' : ''} selected`;
    }

    function updateAnnSummary() {
        const total   = document.querySelectorAll('.mm-ann-col').length;
        const checked = document.querySelectorAll('.mm-ann-col:checked').length;
        const el = document.getElementById('mm-ann-summary');
        if (!el) return;
        el.textContent = total
            ? (checked === total ? `All ${total} sources` : `${checked} of ${total} sources selected`)
            : '';
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
                const type = cb.dataset.type;
                document.querySelectorAll(`.mm-ann-col[data-type="${type}"]`)
                    .forEach(c => { c.checked = cb.checked; c.indeterminate = false; });
            } else if (cb.classList.contains('mm-ann-col')) {
                syncAnnTypeCb(cb.dataset.type);
            }
            updateAnnSummary();
        });

        document.querySelectorAll('.mm-ann-type-cb').forEach(cb => syncAnnTypeCb(cb.dataset.type));
    }

    function syncParent(org, asm) {
        const container = document.getElementById('mm-scope-tree');

        const gsCbs = Array.from(container.querySelectorAll(`.mm-gs-cb[data-org="${org}"][data-asm="${asm}"]`));
        const asmEl = container.querySelector(`.mm-asm-cb[data-org="${org}"][data-asm="${asm}"]`);
        if (asmEl && gsCbs.length) {
            const allOn  = gsCbs.every(c => c.checked);
            const allOff = gsCbs.every(c => !c.checked);
            asmEl.checked       = allOn;
            asmEl.indeterminate = !allOn && !allOff;
        }

        const orgGsCbs = Array.from(container.querySelectorAll(`.mm-gs-cb[data-org="${org}"]`));
        const orgEl    = container.querySelector(`.mm-org-cb[data-org="${org}"]`);
        if (orgEl && orgGsCbs.length) {
            const allOn  = orgGsCbs.every(c => c.checked);
            const allOff = orgGsCbs.every(c => !c.checked);
            orgEl.checked       = allOn;
            orgEl.indeterminate = !allOn && !allOff;
        }
    }

    function initScopeTree() {
        const container = document.getElementById('mm-scope-tree');
        if (!container) return;

        // Expand/collapse toggles
        container.addEventListener('click', function (e) {
            const toggle = e.target.closest('.mm-toggle');
            if (!toggle) return;
            const body = document.getElementById(toggle.dataset.target);
            if (!body) return;
            const open = body.style.display !== 'none';
            body.style.display = open ? 'none' : '';
            toggle.classList.toggle('fa-chevron-down',  !open);
            toggle.classList.toggle('fa-chevron-right',  open);
        });

        // Checkbox propagation
        container.addEventListener('change', function (e) {
            const cb = e.target;
            if (cb.classList.contains('mm-org-cb')) {
                const org = cb.dataset.org;
                container.querySelectorAll(`.mm-asm-cb[data-org="${org}"], .mm-gs-cb[data-org="${org}"]`)
                    .forEach(c => { c.checked = cb.checked; c.indeterminate = false; });
            } else if (cb.classList.contains('mm-asm-cb')) {
                const { org, asm } = cb.dataset;
                container.querySelectorAll(`.mm-gs-cb[data-org="${org}"][data-asm="${asm}"]`)
                    .forEach(c => { c.checked = cb.checked; c.indeterminate = false; });
                syncParent(org, asm);
            } else if (cb.classList.contains('mm-gs-cb')) {
                syncParent(cb.dataset.org, cb.dataset.asm);
            }
            updateScopeSummary();
        });

        // Scope filter input
        const filterInput = document.getElementById('mm-scope-filter');
        if (filterInput) {
            filterInput.addEventListener('input', function () {
                const q = this.value.toLowerCase();
                container.querySelectorAll('.mm-org').forEach(orgDiv => {
                    const orgText = orgDiv.querySelector('label')?.textContent.toLowerCase() || '';
                    let orgVisible = false;
                    orgDiv.querySelectorAll('.mm-asm').forEach(asmDiv => {
                        const asmText = asmDiv.querySelector('label')?.textContent.toLowerCase() || '';
                        const match = !q || orgText.includes(q) || asmText.includes(q);
                        asmDiv.style.display = match ? '' : 'none';
                        if (match) orgVisible = true;
                    });
                    orgDiv.style.display = orgVisible || !q ? '' : 'none';
                });
            });
        }

        updateScopeSummary();
    }

    // -------------------------------------------------------
    // Filter collection helpers
    // -------------------------------------------------------

    function getSelectedSources() {
        return Array.from(document.querySelectorAll('.mm-gs-cb:checked')).map(c => c.dataset.key);
    }

    function getSelectedAnnotationColumns() {
        return Array.from(document.querySelectorAll('.mm-ann-col:checked')).map(c => c.value);
    }

    function buildFormData(extra = {}) {
        const fd = new FormData();

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fd.append('csrf_token', csrf);

        getSelectedSources().forEach(s => fd.append('sources[]', s));

        const annSrc = document.getElementById('mm-annotation-source')?.value;
        if (annSrc) fd.append('annotation_source', annSrc);

        const annAcc = document.getElementById('mm-annotation-accession')?.value?.trim();
        if (annAcc) fd.append('annotation_accession', annAcc);

        const annKw = document.getElementById('mm-annotation-keyword')?.value?.trim();
        if (annKw) fd.append('annotation_keyword', annKw);

        const chr   = document.getElementById('mm-coord-chr')?.value?.trim();
        const start = document.getElementById('mm-coord-start')?.value;
        const end   = document.getElementById('mm-coord-end')?.value;
        if (chr)   fd.append('coord_chr',   chr);
        if (start) fd.append('coord_start', start);
        if (end)   fd.append('coord_end',   end);

        getSelectedAnnotationColumns().forEach(c => fd.append('annotation_columns[]', c));

        Object.entries(extra).forEach(([k, v]) => fd.append(k, v));

        return fd;
    }

    // -------------------------------------------------------
    // Preview results (count + DataTable)
    // -------------------------------------------------------

    let resultsTable = null;

    function previewResults() {
        const btn     = document.getElementById('mm-preview-btn');
        const spinner = document.getElementById('mm-count-spinner');
        const result  = document.getElementById('mm-count-result');

        spinner.classList.remove('d-none');
        btn.disabled = true;
        result.textContent = '';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(PREVIEW_URL, {
            method:  'POST',
            headers: { 'X-CSRF-Token': csrf },
            body:    buildFormData(),
        })
        .then(r => r.json())
        .then(data => {
            const total = data.count ?? 0;
            const rows  = data.rows  ?? [];

            result.textContent = total > 0
                ? `${total.toLocaleString()} feature${total !== 1 ? 's' : ''} matched`
                : 'No features matched';
            result.className = total > 0 ? 'small text-success' : 'small text-muted';

            renderResultsTable(rows, total);
        })
        .catch(() => {
            result.textContent = 'Error fetching preview.';
            result.className = 'small text-danger';
        })
        .finally(() => {
            spinner.classList.add('d-none');
            btn.disabled = false;
        });
    }

    function renderResultsTable(rows, total) {
        const section = document.getElementById('mm-results-section');
        const caption = document.getElementById('mm-results-caption');

        if (!rows.length) {
            section.classList.add('d-none');
            return;
        }

        const showing = rows.length < total
            ? `— showing first ${rows.length.toLocaleString()} of ${total.toLocaleString()}`
            : `— ${total.toLocaleString()} result${total !== 1 ? 's' : ''}`;
        caption.textContent = showing;
        section.classList.remove('d-none');

        section.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (resultsTable) {
            resultsTable.destroy();
            resultsTable = null;
        }

        resultsTable = $('#mm-results-table').DataTable({
            data: rows,
            columns: [
                {
                    title: 'Gene ID',
                    data:  'uniquename',
                    render: function (val, type, row) {
                        if (type !== 'display') return val;
                        const url = `${moopSite}/tools/parent.php?uniquename=${encodeURIComponent(val)}&organism=${encodeURIComponent(row.organism_dir)}`;
                        return `<a href="${url}" target="_blank">${val}</a>`;
                    },
                },
                { title: 'Name',        data: 'name',             defaultContent: '' },
                { title: 'Description', data: 'description',      defaultContent: '', className: 'text-truncate', render: (v, t) => t === 'display' && v && v.length > 60 ? `<span title="${v.replace(/"/g,'&quot;')}">${v.slice(0,60)}…</span>` : (v || '') },
                { title: 'Type',        data: 'type',             defaultContent: '' },
                { title: 'Organism',    data: 'organism_dir',     defaultContent: '', render: (v) => v.replace(/_/g, ' ') },
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
            language: {
                info:      'Showing _START_ to _END_ of _TOTAL_ preview rows',
                infoEmpty: 'No results',
                lengthMenu: 'Show _MENU_ rows',
            },
        });
    }

    // -------------------------------------------------------
    // Download via form POST (browser handles file save)
    // -------------------------------------------------------

    function submitDownload(extraFields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = EXPORT_URL;
        form.style.display = 'none';

        const fd = buildFormData(extraFields);
        for (const [key, value] of fd.entries()) {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = key;
            inp.value = value;
            form.appendChild(inp);
        }

        document.body.appendChild(form);
        form.submit();
        setTimeout(() => form.remove(), 5000);
    }

    // -------------------------------------------------------
    // FASTA mode switching
    // -------------------------------------------------------

    function setFastaMode(mode) {
        currentFastaMode = mode;
        const label = document.getElementById('mm-fasta-label');
        if (label) label.textContent = FASTA_LABELS[mode] || mode;

        const flankInput = document.getElementById('mm-flank-input');
        if (flankInput) {
            flankInput.classList.toggle('d-none', !['upstream', 'downstream'].includes(mode));
        }
    }

    // -------------------------------------------------------
    // Init
    // -------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        initScopeTree();
        initAnnSources();

        // Select all / clear all dataset
        document.getElementById('mm-select-all')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-gs-cb').forEach(c => c.checked = true);
            document.querySelectorAll('.mm-org-cb, .mm-asm-cb').forEach(c => {
                c.checked = true;
                c.indeterminate = false;
            });
            updateScopeSummary();
        });
        document.getElementById('mm-clear-all')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-gs-cb, .mm-org-cb, .mm-asm-cb').forEach(c => {
                c.checked = false;
                c.indeterminate = false;
            });
            updateScopeSummary();
        });

        // Annotation columns: all / none (also sync type checkboxes)
        document.getElementById('mm-ann-all')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-ann-col').forEach(c => c.checked = true);
            document.querySelectorAll('.mm-ann-type-cb').forEach(c => { c.checked = true; c.indeterminate = false; });
            updateAnnSummary();
        });
        document.getElementById('mm-ann-none')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-ann-col, .mm-ann-type-cb').forEach(c => { c.checked = false; c.indeterminate = false; });
            updateAnnSummary();
        });

        // Annotation sources filter input — hides items and empty groups
        document.getElementById('mm-ann-filter')?.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.mm-ann-group').forEach(group => {
                let anyVisible = false;
                group.querySelectorAll('.mm-ann-item').forEach(item => {
                    const text = item.querySelector('label')?.textContent.toLowerCase() || '';
                    const show = !q || text.includes(q);
                    item.style.display = show ? '' : 'none';
                    if (show) anyVisible = true;
                });
                group.style.display = anyVisible || !q ? '' : 'none';
            });
        });

        // Preview button
        document.getElementById('mm-preview-btn')?.addEventListener('click', previewResults);

        // TSV download
        document.getElementById('mm-dl-tsv')?.addEventListener('click', function () {
            submitDownload({ output_format: 'tsv' });
        });

        // FASTA download (current mode)
        document.getElementById('mm-dl-fasta-btn')?.addEventListener('click', function () {
            const flank = document.getElementById('mm-flank-bp')?.value || '500';
            submitDownload({ output_format: 'fasta', fasta_mode: currentFastaMode, flank_bp: flank });
        });

        // FASTA mode selection from dropdown
        document.querySelectorAll('.mm-fasta-mode').forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                setFastaMode(this.dataset.mode);
            });
        });

        // Update flank bp label in dropdown when input changes
        document.getElementById('mm-flank-bp')?.addEventListener('input', function () {
            document.querySelectorAll('.mm-flank-label').forEach(el => el.textContent = this.value);
        });

        setFastaMode('gene');
        updateAnnSummary();
    });

})();
