/**
 * ID Translator (tools/crosswalk.php).
 *  - The source list: groups with a tick-all box and "n of m ticked", a filter box, and the line of
 *    ticked sources with x to remove each. A new organism reloads the page (its own sources).
 *  - Results: sortable / paged with DataTables (already on every page).
 *  - Download TSV: EVERY row (all pages of the table, not just the visible one), as the cells' text.
 *    Built in the browser from the table the server already sent -- no second request.
 */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const T = (s, ...a) => (typeof moopT === 'function' ? moopT(s, ...a) : s.replace(/%[sd]/g, () => a.shift()));
        const list = document.getElementById('cwSources');
        if (list) {
            const boxes = () => Array.from(list.querySelectorAll('.cw-source input[type=checkbox]'));
            const picked = document.getElementById('cwPicked');
            // Every group's "n of m ticked", its tri-state box, and the line of ticked sources (x removes).
            const refresh = function () {
                list.querySelectorAll('details.cw-group').forEach(function (g) {
                    const own = Array.from(g.querySelectorAll('.cw-source input'));
                    const n = own.filter(b => b.checked).length;
                    const box = g.querySelector('.cw-group-box');
                    box.checked = n === own.length; box.indeterminate = n > 0 && n < own.length;
                    g.querySelector('.cw-group-count').textContent = T('%d of %d ticked', n, own.length);
                });
                if (!picked) return;
                picked.textContent = '';
                const on = boxes().filter(b => b.checked);
                if (!on.length) { picked.textContent = T('Nothing ticked: the table shows just the reference genes.'); picked.className = 'small mb-2 text-muted'; return; }
                picked.className = 'small mb-2 d-flex flex-wrap gap-1 align-items-center';
                const lead = document.createElement('span'); lead.textContent = T('Translating into:'); lead.className = 'me-1'; picked.appendChild(lead);
                on.forEach(function (b) {
                    const chip = document.createElement('span');
                    chip.className = 'badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1';
                    chip.title = ((b.labels && b.labels[0]) ? b.labels[0].textContent : b.value).replace(/\s+/g, ' ').trim();
                    chip.appendChild(document.createTextNode(b.value));
                    const x = document.createElement('button');
                    x.type = 'button'; x.className = 'btn-close'; x.style.fontSize = '0.55rem';
                    x.setAttribute('aria-label', T('Remove %s', b.value));
                    x.addEventListener('click', () => { b.checked = false; refresh(); });
                    chip.appendChild(x); picked.appendChild(chip);
                });
            };
            list.addEventListener('change', refresh);
            // The group box ticks or clears its whole group. It sits inside <summary>, so stop the click
            // from also opening / closing the group.
            list.querySelectorAll('.cw-group-box').forEach(function (gb) {
                gb.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const g = gb.closest('details');
                    const want = gb.checked;
                    g.querySelectorAll('.cw-source input').forEach(b => { b.checked = want; });
                    if (want) g.open = true;
                    refresh();
                });
                gb.closest('summary').addEventListener('click', function (e) { if (e.target === gb) e.preventDefault(); });
            });
            const none = document.getElementById('cwNone');
            if (none) none.addEventListener('click', () => { boxes().forEach(b => { b.checked = false; }); refresh(); });
            // Filter: show matching sources (label or name), open the groups that have a match.
            const filter = document.getElementById('cwFilter'), nomatch = document.getElementById('cwNoMatch');
            if (filter) filter.addEventListener('input', function () {
                const q = filter.value.trim().toLowerCase();
                let any = false;
                list.querySelectorAll('details.cw-group').forEach(function (g) {
                    let hit = 0;
                    g.querySelectorAll('.cw-source').forEach(function (row) {
                        const show = !q || row.textContent.toLowerCase().includes(q);
                        row.hidden = !show; if (show) hit++;
                    });
                    g.hidden = q !== '' && hit === 0;
                    if (q) g.open = hit > 0;
                    if (hit) any = true;
                });
                if (nomatch) nomatch.hidden = any || !q;
            });
            refresh();
        }
        // Another organism has its own sources: reload the form for it (ids kept, ticks dropped).
        const org = document.getElementById('cwOrganism');
        if (org) org.addEventListener('change', function () {
            const ids = document.getElementById('cwIds');
            location.href = location.pathname + '?organism=' + encodeURIComponent(org.value) + (ids && ids.value ? '&ids=' + encodeURIComponent(ids.value) : '');
        });

        const table = document.getElementById('cwResults');
        if (!table) return;
        if (window.jQuery && jQuery.fn.DataTable) jQuery(table).DataTable({ pageLength: 25, order: [], autoWidth: false });
        // Download TSV is a plain link: the server writes the file (tools/crosswalk.php ?download=tsv).
    });
})();
