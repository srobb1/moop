/**
 * MOOPmart — MOOP Mega Search UI
 *
 * Requires globals injected via inline_scripts:
 *   scopeTree         — {organism: {assembly: [gene_sets]}}
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
    // Scope tree rendering
    // -------------------------------------------------------

    function renderScopeTree() {
        const container = document.getElementById('mm-scope-tree');
        if (!container || typeof scopeTree === 'undefined') return;

        let html = '';
        for (const org in scopeTree) {
            const orgSafe = org.replace(/[^a-z0-9]/gi, '_');
            const orgId   = 'mm-org-' + orgSafe;
            html += `
<div class="mm-org mb-1">
  <div class="d-flex align-items-center">
    <button type="button" class="btn btn-link btn-sm p-0 pe-1 mm-toggle" data-target="${orgId}-ch" style="line-height:1;">
      <i class="fa fa-caret-down mm-caret text-muted" style="width:10px;"></i>
    </button>
    <div class="form-check mb-0">
      <input type="checkbox" class="form-check-input mm-org-cb" id="${orgId}" data-org="${org}" checked>
      <label class="form-check-label small fw-semibold" for="${orgId}">${org.replace(/_/g, ' ')}</label>
    </div>
  </div>
  <div id="${orgId}-ch" class="ms-3">`;

            const asms = scopeTree[org];
            for (const asm in asms) {
                const asmSafe = asm.replace(/[^a-z0-9]/gi, '_');
                const asmId   = orgId + '_' + asmSafe;
                const geneSets = asms[asm];
                html += `
    <div class="mm-asm mb-1">
      <div class="d-flex align-items-center">
        <button type="button" class="btn btn-link btn-sm p-0 pe-1 mm-toggle" data-target="${asmId}-ch" style="line-height:1;">
          <i class="fa fa-caret-down mm-caret text-muted" style="width:10px;"></i>
        </button>
        <div class="form-check mb-0">
          <input type="checkbox" class="form-check-input mm-asm-cb" id="${asmId}"
                 data-org="${org}" data-asm="${asm}" checked>
          <label class="form-check-label small" for="${asmId}">${asm}</label>
        </div>
      </div>
      <div id="${asmId}-ch" class="ms-3">`;

                for (const gs of geneSets) {
                    const gsSafe = (gs || 'default').replace(/[^a-z0-9]/gi, '_');
                    const gsId   = asmId + '_' + gsSafe;
                    const gsKey  = `${org}|${asm}|${gs}`;
                    html += `
        <div class="form-check mb-0">
          <input type="checkbox" class="form-check-input mm-gs-cb" id="${gsId}"
                 data-org="${org}" data-asm="${asm}" data-gs="${gs}" data-key="${gsKey}" checked>
          <label class="form-check-label small text-muted" for="${gsId}">${gs || '(default)'}</label>
        </div>`;
                }
                html += `\n      </div>\n    </div>`;
            }
            html += `\n  </div>\n</div>`;
        }

        container.innerHTML = html || '<p class="text-muted small p-2">No accessible sources.</p>';

        // Expand/collapse toggles
        container.addEventListener('click', function (e) {
            const btn = e.target.closest('.mm-toggle');
            if (!btn) return;
            const children = document.getElementById(btn.dataset.target);
            const caret    = btn.querySelector('.mm-caret');
            if (!children) return;
            const open = children.style.display !== 'none';
            children.style.display = open ? 'none' : '';
            caret.classList.toggle('fa-caret-down',  !open);
            caret.classList.toggle('fa-caret-right',  open);
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
        });
    }

    function syncParent(org, asm) {
        const container = document.getElementById('mm-scope-tree');

        // Sync assembly checkbox
        const gsCbs = Array.from(container.querySelectorAll(`.mm-gs-cb[data-org="${org}"][data-asm="${asm}"]`));
        const asmEl = container.querySelector(`.mm-asm-cb[data-org="${org}"][data-asm="${asm}"]`);
        if (asmEl && gsCbs.length) {
            const allOn  = gsCbs.every(c => c.checked);
            const allOff = gsCbs.every(c => !c.checked);
            asmEl.checked       = allOn;
            asmEl.indeterminate = !allOn && !allOff;
        }

        // Sync organism checkbox
        const orgGsCbs = Array.from(container.querySelectorAll(`.mm-gs-cb[data-org="${org}"]`));
        const orgEl    = container.querySelector(`.mm-org-cb[data-org="${org}"]`);
        if (orgEl && orgGsCbs.length) {
            const allOn  = orgGsCbs.every(c => c.checked);
            const allOff = orgGsCbs.every(c => !c.checked);
            orgEl.checked       = allOn;
            orgEl.indeterminate = !allOn && !allOff;
        }
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

        const featureType = document.getElementById('mm-feature-type')?.value;
        if (featureType) fd.append('feature_types[]', featureType);

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

        // Scroll to results
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
            dom: 'ltipr',   // length, table, info, pagination — no built-in search (filter panel is the filter)
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
        renderScopeTree();

        // Select all / clear all dataset
        document.getElementById('mm-select-all')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-gs-cb').forEach(c => c.checked = true);
            document.querySelectorAll('.mm-org-cb, .mm-asm-cb').forEach(c => {
                c.checked = true;
                c.indeterminate = false;
            });
        });
        document.getElementById('mm-clear-all')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-gs-cb, .mm-org-cb, .mm-asm-cb').forEach(c => {
                c.checked = false;
                c.indeterminate = false;
            });
        });

        // Annotation columns: all / none
        document.getElementById('mm-ann-all')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-ann-col').forEach(c => c.checked = true);
        });
        document.getElementById('mm-ann-none')?.addEventListener('click', function () {
            document.querySelectorAll('.mm-ann-col').forEach(c => c.checked = false);
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
    });

})();
