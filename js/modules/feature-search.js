/**
 * Feature ID Search — Tab 2 on the index page
 *
 * Exact-match lookup of feature_uniquename across all accessible SQLite
 * databases.  Submits on Enter or button click; displays a results table
 * with links to each feature's parent page.
 */
(function () {
    'use strict';

    const TYPE_STYLE = {
        gene:        { bg: '#0891b2', label: 'gene' },
        pseudogene:  { bg: '#64748b', label: 'pseudogene' },
        mRNA:        { bg: '#0e7490', label: 'mRNA' },
        transcript:  { bg: '#0e7490', label: 'transcript' },
        protein:     { bg: '#0369a1', label: 'protein' },
        polypeptide: { bg: '#0369a1', label: 'protein' },
    };

    function typeChip(type) {
        const s   = TYPE_STYLE[type] || { bg: '#64748b', label: type };
        return `<span style="background:${s.bg};color:#fff;padding:1px 7px;border-radius:3px;`
             + `font-size:0.7rem;font-weight:600;letter-spacing:0.03em;white-space:nowrap;">${s.label}</span>`;
    }

    function setStatus(msg, isError) {
        const el = document.getElementById('fs-status');
        if (!el) return;
        el.textContent = msg;
        el.className   = 'small mt-2 ' + (isError ? 'text-danger' : 'text-muted');
        el.style.display = msg ? '' : 'none';
    }

    function renderResults(results) {
        const wrap = document.getElementById('fs-results');
        if (!wrap) return;

        if (!results.length) {
            wrap.innerHTML = '';
            setStatus('No matches found.');
            return;
        }

        setStatus('');

        const orgDisplay = org => `<em>${org.replace(/_/g, ' ')}</em>`;

        const rowHtml = r => {
            const secondary = [orgDisplay(r.organism), r.assembly, r.gene_set]
                .filter(Boolean).join(' <span style="color:#cbd5e1">·</span> ');
            return `<tr data-search="${(r.organism + ' ' + r.type + ' ' + r.assembly + ' ' + r.gene_set).toLowerCase()}">
               <td style="line-height:1.2;">
                 <a href="${r.url}" target="_blank"
                    class="fw-semibold font-monospace" style="font-size:0.85rem;">${r.uniquename}</a>
                 <div class="text-muted" style="font-size:0.75rem; margin-top:1px;">${secondary}</div>
               </td>
               <td class="text-end align-top" style="padding-top:6px;">${typeChip(r.type)}</td>
             </tr>`;
        };

        const filterBar = results.length > 5
            ? `<div class="mt-2 mb-1">
                 <input type="text" id="fs-filter" class="form-control form-control-sm moop-input"
                        placeholder="Filter results…" autocomplete="off">
               </div>`
            : '';

        wrap.innerHTML =
            `${filterBar}
             <div class="table-responsive mt-2">
               <table class="table table-sm table-hover mb-0" id="fs-table"
                      style="border-top:2px solid #0891b2;">
                 <tbody>${results.map(rowHtml).join('')}</tbody>
               </table>
             </div>`;

        const filterInput = document.getElementById('fs-filter');
        if (filterInput) {
            filterInput.addEventListener('input', function () {
                const term = this.value.toLowerCase();
                document.querySelectorAll('#fs-table tbody tr').forEach(tr => {
                    tr.style.display = tr.dataset.search.includes(term) ? '' : 'none';
                });
            });
            filterInput.focus();
        }
    }

    function search() {
        const input = document.getElementById('fs-input');
        if (!input) return;
        const q = input.value.trim();
        if (!q) { setStatus('Enter a feature ID to search.'); return; }

        const btn  = document.getElementById('fs-go');
        const wrap = document.getElementById('fs-results');

        if (btn)  { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }
        if (wrap) wrap.innerHTML = '';
        setStatus('Searching…');

        const site = (typeof moopSite !== 'undefined') ? moopSite : '/moop';
        fetch(`${site}/api/feature_search.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) { setStatus(data.error, true); return; }
                renderResults(data.results || []);
            })
            .catch(() => setStatus('Search failed. Please try again.', true))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = 'Go'; }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('fs-input');
        const btn   = document.getElementById('fs-go');
        if (!input || !btn) return;

        btn.addEventListener('click', search);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); search(); }
        });

        document.querySelectorAll('.fs-example-chip').forEach(chip => {
            chip.addEventListener('click', function (e) {
                e.stopPropagation();   // prevent qs-example-chip handler from also firing
                input.value = chip.textContent.trim();
                input.focus();
                search();
            });
        });
    });
})();
