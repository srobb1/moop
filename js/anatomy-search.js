/**
 * PAGE search by anatomy (tools/anatomy_search.php).
 *  - Under the relationship choice: what the search covers (api/ontology_expand.php) -- one line,
 *    with the full list in a <details>. Refreshed when the term or relationship changes.
 *  - Results table: sortable / paged with DataTables (already on every page).
 * Text is set with textContent only.
 */
(function () {
    'use strict';
    const T = (s, ...a) => (typeof moopT === 'function' ? moopT(s, ...a) : s.replace(/%[sd]/g, () => a.shift()));
    document.addEventListener('DOMContentLoaded', function () {
        const box  = document.getElementById('anatomyTermBox');
        const out  = document.getElementById('anatomyCovers');
        const term = document.getElementById('anatomyTerm');
        if (box && out && term) {
            let seq = 0;
            const show = function () {
                if (!term.value) { out.textContent = ''; return; }
                const rel = (document.querySelector('#anatomyRelations input:checked') || {}).value || 'rdfs:subClassOf';
                const mine = ++seq;
                fetch((window.sitePath || '') + '/api/ontology_expand.php?term=' + encodeURIComponent(term.value)
                      + '&rel=' + encodeURIComponent(rel))
                    .then(r => r.json())
                    .then(d => {
                        if (mine !== seq) return;
                        out.textContent = '';
                        if (!d.count) { out.textContent = T('Covers %s only: no other term is related to it by "%s".', d.term.label || d.term.id, d.relation.label); return; }
                        const det = document.createElement('details');
                        const sum = document.createElement('summary');
                        sum.textContent = T('Covers %s and %d terms related to it by "%s"', d.term.label || d.term.id, d.count, d.relation.label);
                        det.appendChild(sum);
                        const ul = document.createElement('ul');
                        ul.className = 'mb-0 mt-1';
                        ul.style.columns = '2 18rem';
                        d.terms.forEach(t => { const li = document.createElement('li'); li.textContent = t.label || t.id; ul.appendChild(li); });
                        det.appendChild(ul);
                        out.appendChild(det);
                    })
                    .catch(() => { if (mine === seq) out.textContent = ''; });
            };
            box.addEventListener('onto:pick', show);
            document.querySelectorAll('#anatomyRelations input').forEach(r => r.addEventListener('change', show));
            show();
        }
        if (window.jQuery && jQuery.fn.DataTable && document.getElementById('pageResults')) {
            jQuery('#pageResults').DataTable({ pageLength: 25, order: [], autoWidth: false });
        }
    });
})();
