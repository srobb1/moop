/**
 * Feature ID Search — the index page box, and the shared lookup behind the navbar box
 *
 * Exact-match lookup of feature_uniquename across all accessible SQLite
 * databases.  Submits on Enter or button click; displays a results table
 * with links to each feature's parent page.
 *
 * Loaded on EVERY page (includes/layout.php): window.MoopFeatureSearch
 * ({lookup, renderInto}) is what js/modules/navbar-id-search.js uses, so the two boxes
 * share one endpoint, one row layout and one no-match fallback. The index wiring below
 * does nothing on a page without #fs-input.
 */
(function () {
    'use strict';

    const TYPE_STYLE = {
        gene:        { bg: '#0891b2', label: moopTh('gene') },
        pseudogene:  { bg: '#64748b', label: moopTh('pseudogene') },
        mRNA:        { bg: '#0e7490', label: 'mRNA' },
        transcript:  { bg: '#0e7490', label: moopTh('transcript') },
        protein:     { bg: '#0369a1', label: moopTh('protein') },
        polypeptide: { bg: '#0369a1', label: moopTh('protein') },
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

    /**
     * Render a lookup's results into `wrap`. Shared by the index page box and the navbar
     * box, which can both be on one page — so every element read here is found INSIDE
     * `wrap`, never by a page-wide id (the filter used to be #fs-filter/#fs-table).
     *
     *   opts.target  link target for the feature links ('_blank' on the index page)
     */
    function renderInto(wrap, results, q, opts) {
        opts = opts || {};
        const target = opts.target ? ` target="${opts.target}"` : '';

        if (!results.length) {
            // An exact-ID lookup finding nothing usually means the visitor typed a name or a
            // word ("BRCA1", "kinase"). Offer the search that handles those, term filled in.
            const site = sitePath;
            // Annotation Search switched off on this site (includes/toolbar.php marks the page):
            // no link into it.
            const searchOn = !document.querySelector('[data-no-search="1"]');
            wrap.innerHTML = '<div class="small mt-2 text-muted">' + moopTh('No gene, transcript or protein has that exact ID.') + ' '
                + (q && searchOn ? `<a href="${site}/tools/search.php?q=${encodeURIComponent(q)}">${moopTh('Search annotations for “%s” →', q)}</a>` : '')
                + '</div>';
            return;
        }

        const orgDisplay = org => `<em>${org.replace(/_/g, ' ')}</em>`;

        const rowHtml = r => {
            const secondary = [orgDisplay(r.organism), r.assembly, r.gene_set]
                .filter(Boolean).join(' <span style="color:#cbd5e1">·</span> ');
            return `<tr data-search="${(r.organism + ' ' + r.type + ' ' + r.assembly + ' ' + r.gene_set).toLowerCase()}">
               <td style="line-height:1.2;">
                 <a href="${r.url}"${target}
                    class="fw-semibold font-monospace" style="font-size:0.85rem;color:var(--moop-tool-header);">${r.uniquename}</a>
                 <div class="text-muted" style="font-size:0.75rem; margin-top:1px;">${secondary}</div>
               </td>
               <td class="text-end align-top" style="padding-top:6px;">${typeChip(r.type)}</td>
             </tr>`;
        };

        const filterBar = results.length > 5
            ? `<div class="mt-2 mb-1">
                 <input type="text" class="form-control form-control-sm moop-input fs-filter"
                        placeholder="${moopTh('Filter results…')}" autocomplete="off">
               </div>`
            : '';

        wrap.innerHTML =
            `${filterBar}
             <div class="table-responsive mt-2">
               <table class="table table-sm table-hover mb-0 fs-table"
                      style="border-top:2px solid var(--moop-tool-header);">
                 <tbody>${results.map(rowHtml).join('')}</tbody>
               </table>
             </div>`;

        const filterInput = wrap.querySelector('.fs-filter');
        if (filterInput) {
            filterInput.addEventListener('input', function () {
                const term = this.value.toLowerCase();
                wrap.querySelectorAll('.fs-table tbody tr').forEach(tr => {
                    tr.style.display = tr.dataset.search.includes(term) ? '' : 'none';
                });
            });
            filterInput.focus();
        }
    }

    /** The lookup itself: resolves to the results array, rejects with a message. */
    function lookup(q) {
        return fetch(`${sitePath}/api/feature_search.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            // Network failure or a non-JSON reply (a rate-limit page): one plain message,
            // not a JSON parser error.
            .catch(() => { throw new Error(moopT('Search failed. Please try again.')); })
            .then(data => {
                if (data.error) throw new Error(data.error);
                return data.results || [];
            });
    }

    window.MoopFeatureSearch = { lookup, renderInto };

    function search() {
        const input = document.getElementById('fs-input');
        if (!input) return;
        const q = input.value.trim();
        if (!q) { setStatus(moopT('Enter a feature ID to search.')); return; }

        const btn  = document.getElementById('fs-go');
        const wrap = document.getElementById('fs-results');

        // Restore the button's own label afterwards -- it was reset to "Go" while the
        // button reads "Search".
        const label = btn ? btn.innerHTML : '';
        if (btn)  { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }
        if (wrap) wrap.innerHTML = '';
        setStatus(moopT('Searching…'));

        lookup(q)
            .then(results => { setStatus(''); if (wrap) renderInto(wrap, results, q, { target: '_blank' }); })
            .catch(e => setStatus(e.message, true))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = label; }
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
