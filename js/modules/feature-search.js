/**
 * Feature ID Search — Tab 2 on the index page
 *
 * Exact-match lookup of feature_uniquename across all accessible SQLite
 * databases.  Submits on Enter or button click; displays a results table
 * with links to each feature's parent page.
 */
(function () {
    'use strict';

    const TYPE_COLORS = {
        gene:        '#2171b5',
        mRNA:        '#e8833a',
        transcript:  '#e8833a',
        protein:     '#6a3d9a',
        polypeptide: '#6a3d9a',
    };

    function typeChip(type) {
        const bg = TYPE_COLORS[type] || '#6c757d';
        return `<span style="background:${bg};color:#fff;padding:1px 8px;border-radius:10px;`
             + `font-size:0.72rem;font-weight:600;white-space:nowrap;">${type}</span>`;
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

        const rowHtml = r =>
            `<tr data-search="${(r.organism + ' ' + r.type + ' ' + r.assembly + ' ' + r.gene_set).toLowerCase()}">
               <td><a href="${r.url}" target="_blank" class="fw-semibold">${r.uniquename}</a></td>
               <td>${typeChip(r.type)}</td>
               <td class="text-muted small">${r.organism.replace(/_/g, ' ')}</td>
               <td class="text-muted small">${r.assembly}</td>
               <td class="text-muted small">${r.gene_set}</td>
             </tr>`;

        const filterBar = results.length > 5
            ? `<div class="mt-3 mb-1">
                 <input type="text" id="fs-filter" class="form-control form-control-sm"
                        placeholder="Filter by organism, type, assembly…" autocomplete="off">
               </div>`
            : '';

        wrap.innerHTML =
            `${filterBar}
             <div class="table-responsive${results.length <= 5 ? ' mt-3' : ''}">
               <table class="table table-sm table-hover mb-0" id="fs-table">
                 <thead class="table-light">
                   <tr>
                     <th>Sequence ID</th>
                     <th>Type</th>
                     <th>Organism</th>
                     <th>Assembly</th>
                     <th>Gene Set</th>
                   </tr>
                 </thead>
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
