/**
 * PAGE search by anatomy (tools/anatomy_search.php): when a term is picked or the relationship
 * changes, show what the search covers -- api/ontology_expand.php. Text set with textContent only.
 */
(function () {
    'use strict';
    const T = (s, ...a) => (typeof moopT === 'function' ? moopT(s, ...a) : s.replace(/%[sd]/g, () => a.shift()));
    document.addEventListener('DOMContentLoaded', function () {
        const box = document.getElementById('anatomyTermBox');
        const out = document.getElementById('anatomyCovers');
        const term = document.getElementById('anatomyTerm');
        if (!box || !out || !term) return;
        let seq = 0;

        function show() {
            if (!term.value) return;
            const rel = (document.querySelector('#anatomyRelations input:checked') || {}).value || 'rdfs:subClassOf';
            const mine = ++seq;
            out.textContent = T('Loading…');
            fetch((window.sitePath || '') + '/api/ontology_expand.php?term=' + encodeURIComponent(term.value)
                  + '&rel=' + encodeURIComponent(rel))
                .then(r => r.json())
                .then(d => {
                    if (mine !== seq) return;
                    out.textContent = '';
                    const head = document.createElement('p');
                    head.className = 'mb-2';
                    const strong = document.createElement('strong');
                    strong.textContent = d.term.label || d.term.id;
                    head.append(strong, ' ' + (d.count
                        ? T('and %d terms related to it by "%s":', d.count, d.relation.label)
                        : T('only (no other term is related to it by "%s").', d.relation.label)));
                    out.appendChild(head);
                    if (!d.count) return;
                    const ul = document.createElement('ul');
                    ul.className = 'small mb-0';
                    ul.style.columns = '2 18rem';
                    d.terms.forEach(t => {
                        const li = document.createElement('li');
                        li.textContent = (t.label || t.id) + ' ';
                        const id = document.createElement('span');
                        id.className = 'text-muted font-monospace';
                        id.textContent = t.id;
                        li.appendChild(id);
                        ul.appendChild(li);
                    });
                    out.appendChild(ul);
                })
                .catch(() => { if (mine === seq) out.textContent = T('Could not load the related terms.'); });
        }
        box.addEventListener('onto:pick', show);
        document.querySelectorAll('#anatomyRelations input').forEach(r => r.addEventListener('change', show));
    });
})();
