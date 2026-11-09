/**
 * Ontology term search box -- type a name or synonym, pick a term.
 *
 *   <div class="onto-box" data-onto-box data-ontologies="">           (empty = every ontology the
 *     <input type="text" class="form-control" autocomplete="off">       site offers in the term box)
 *     <input type="hidden" name="term">                                 receives the chosen id
 *   </div>
 *
 * Suggestions come from api/ontology_terms.php, best first ("brain" -> cephalic ganglia, because
 * "brain" is its exact synonym -- and the list says so). Arrow keys move, Enter picks, Escape
 * closes. Picking fires a 'onto:pick' event on the box with {id, label} so the page can react.
 * All text is set with textContent -- never parsed as HTML.
 */
(function () {
    'use strict';
    const T = (s, ...a) => (typeof moopT === 'function' ? moopT(s, ...a) : s.replace(/%s/g, () => a.shift()));

    function attach(box) {
        const input  = box.querySelector('input[type=text]');
        const hidden = box.querySelector('input[type=hidden]');
        if (!input || !hidden || box.dataset.ontoReady) return;
        box.dataset.ontoReady = '1';
        box.style.position = 'relative';

        const list = document.createElement('div');
        list.className = 'list-group shadow-sm onto-box-list';
        list.setAttribute('role', 'listbox');
        list.style.cssText = 'position:absolute;z-index:1050;left:0;right:0;top:100%;max-height:22rem;overflow-y:auto;display:none;';
        box.appendChild(list);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        let items = [], active = -1, timer = null, seq = 0;

        function close() { list.style.display = 'none'; input.setAttribute('aria-expanded', 'false'); active = -1; }
        function highlight(i) {
            [...list.children].forEach((el, n) => el.classList.toggle('active', n === i));
            active = i;
            if (list.children[i]) list.children[i].scrollIntoView({ block: 'nearest' });
        }
        function pick(t) {
            input.value  = t.label;
            hidden.value = t.id;
            close();
            box.dispatchEvent(new CustomEvent('onto:pick', { bubbles: true, detail: { id: t.id, label: t.label } }));
        }
        function render(terms, q) {
            list.textContent = '';
            items = terms;
            if (!terms.length) {
                const none = document.createElement('div');
                none.className = 'list-group-item small text-muted';
                none.textContent = T('No term matches "%s"', q);
                list.appendChild(none);
            }
            terms.forEach((t, i) => {
                const a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action py-1';
                a.setAttribute('role', 'option');
                const name = document.createElement('span');
                name.className = 'fw-semibold';
                name.textContent = t.label;
                const id = document.createElement('span');
                id.className = 'text-muted small ms-2 font-monospace';
                id.textContent = t.id;
                a.append(name, id);
                if (t.matched) {
                    const why = document.createElement('div');
                    why.className = 'small text-muted';
                    why.textContent = (t.kind === 'exact' ? T('synonym: %s', t.matched)
                                     : t.kind === 'broad' ? T('broader term: %s', t.matched)
                                     : t.kind === 'narrow' ? T('narrower term: %s', t.matched)
                                     : T('related: %s', t.matched));
                    a.appendChild(why);
                }
                a.addEventListener('mousedown', e => { e.preventDefault(); pick(t); });
                a.addEventListener('mousemove', () => highlight(i));
                list.appendChild(a);
            });
            list.style.display = 'block';
            input.setAttribute('aria-expanded', 'true');
            highlight(terms.length ? 0 : -1);
        }
        function lookup() {
            const q = input.value.trim();
            hidden.value = '';
            if (q.length < 2) { close(); return; }
            const mine = ++seq;
            const o = box.dataset.ontologies ? '&o=' + encodeURIComponent(box.dataset.ontologies) : '';
            fetch((window.sitePath || '') + '/api/ontology_terms.php?q=' + encodeURIComponent(q) + o)
                .then(r => r.json())
                .then(d => { if (mine === seq) render(d.terms || [], q); })
                .catch(() => { if (mine === seq) close(); });
        }

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 180); });
        input.addEventListener('keydown', e => {
            if (list.style.display === 'none') return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, items.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
            else if (e.key === 'Enter' && items[active]) { e.preventDefault(); pick(items[active]); }
            else if (e.key === 'Escape') { close(); }
        });
        input.addEventListener('blur', () => setTimeout(close, 120));
    }

    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-onto-box]').forEach(attach));
    window.moopOntoBoxAttach = attach;
})();
