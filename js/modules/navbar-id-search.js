/**
 * Navbar gene ID lookup — the box in the menu bar on every page.
 *
 * The same lookup as the index page's ID search (window.MoopFeatureSearch, from
 * js/modules/feature-search.js): one endpoint, one row layout, one "no exact match →
 * search annotations" fallback. The results open in a panel under the box, so a lookup
 * never takes you off the page you are on until you pick a result.
 *
 * Runs on Enter / the button, not as you type: each lookup fans out across every
 * database the viewer can open.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const form  = document.getElementById('navIdSearch');
        const input = document.getElementById('navIdInput');
        const panel = document.getElementById('navIdPanel');
        if (!form || !input || !panel || !window.MoopFeatureSearch) return;

        let seq = 0;            // a reply to an older lookup must not overwrite a newer one
        let hasResults = false; // reopen what was there when the box is focused again

        const open  = () => { panel.hidden = false; };
        const close = () => { panel.hidden = true; };

        function show(headHtml) {
            panel.innerHTML =
                `<div class="navbar-id-panel-head">
                   <span>${headHtml}</span>
                   <button type="button" class="btn-close btn-sm" aria-label="${moopTh('Close')}"></button>
                 </div>
                 <div class="navbar-id-panel-body"></div>`;
            panel.querySelector('.btn-close').addEventListener('click', () => { seq++; close(); input.focus(); });
            open();
            return panel.querySelector('.navbar-id-panel-body');
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const q = input.value.trim();
            if (!q) { close(); return; }

            const mine = ++seq;
            hasResults = false;
            show('<i class="fa fa-spinner fa-spin me-1"></i> ' + moopTm('Looking up %s…', `<strong>${escapeHtml(q)}</strong>`));

            MoopFeatureSearch.lookup(q)
                .then(results => {
                    if (mine !== seq) return;
                    const n = results.length;
                    const body = show(n
                        ? (n === 1 ? moopTm('%d match for %s', n, `<strong>${escapeHtml(q)}</strong>`) : moopTm('%d matches for %s', n, `<strong>${escapeHtml(q)}</strong>`))
                        : `<strong>${escapeHtml(q)}</strong>`);
                    MoopFeatureSearch.renderInto(body, results, q, {});
                    hasResults = true;
                })
                .catch(err => {
                    if (mine !== seq) return;
                    show(`<span class="text-danger">${escapeHtml(err.message)}</span>`);
                });
        });

        // Back into the box (focus, or a click while it already has focus after Esc).
        ['focus', 'click'].forEach(ev => input.addEventListener(ev, () => { if (hasResults) open(); }));

        // Esc from the box or the panel closes it; a click anywhere outside the form does too.
        form.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) { close(); input.focus(); }
        });
        document.addEventListener('click', function (e) {
            if (!panel.hidden && !form.contains(e.target)) close();
        });
    });
})();
