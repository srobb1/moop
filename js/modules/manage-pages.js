/**
 * Manage Pages (admin/pages/manage_pages.php): live preview and a suggested address.
 *
 * The preview is rendered by the SERVER (admin/api/preview_page.php), with the same converter
 * page.php publishes with -- a browser-side Markdown library would be a second converter, and
 * the two would drift. Requests are debounced, and a reply that arrives after a newer one was
 * sent is dropped, so a slow answer can never overwrite a newer preview.
 */
(function () {
    'use strict';
    if (typeof sitePath === 'undefined') return;
    const endpoint = sitePath + '/admin/api/preview_page.php';
    const format = () => (document.querySelector('input[name="format"]:checked') || {}).value || 'markdown';
    const renders = [];

    document.querySelectorAll('textarea.mp-body').forEach(function (area) {
        const out = document.getElementById(area.dataset.preview);
        if (!out) return;
        const removedBox = document.getElementById('removed_' + area.id.replace(/^body_/, ''));
        let timer = null, seq = 0;
        const render = function () {
            const mine = ++seq;
            const body = new URLSearchParams({ markdown: area.value, format: format() });
            fetch(endpoint, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                .then(function (data) {
                    if (mine !== seq) return;           // a newer request is on its way
                    out.innerHTML = area.value.trim() === ''
                        ? '<span class="text-muted small">Start typing to see the page.</span>'
                        : data.html;                    // server-rendered and cleaned (moop_page_render)
                    // HTML: say what the cleaner took out, so a vanished tag is never a mystery.
                    if (removedBox) {
                        const removed = data.removed || [];
                        removedBox.classList.toggle('d-none', removed.length === 0);
                        removedBox.textContent = '';
                        if (removed.length) {
                            removedBox.appendChild(document.createTextNode('Removed, because it could run code: '));
                            const ul = document.createElement('ul');
                            ul.className = 'mb-0';
                            removed.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
                            removedBox.appendChild(ul);
                        }
                    }
                })
                .catch(function (err) {
                    if (mine !== seq) return;
                    out.innerHTML = '<div class="alert alert-warning small mb-0">The preview could not be made ('
                        + String(err.message).replace(/[<>&]/g, '') + '). Your text is not lost — Save still works.</div>';
                });
        };
        area.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(render, 350); });
        renders.push(render);
        if (area.value.trim() !== '') render();
    });

    // Switching Markdown <-> HTML redraws every preview and the note beside the switch.
    const note = document.getElementById('format-note');
    document.querySelectorAll('input[name="format"]').forEach(function (r) {
        r.addEventListener('change', function () {
            if (note) note.textContent = format() === 'html'
                ? 'Your own HTML — cards, grids, Bootstrap classes. Scripts and anything that can run are removed.'
                : 'Simple formatting with a few marks (see "Markdown in brief" below).';
            renders.forEach(fn => fn());
        });
    });

    // New page: suggest the address from the English title until the admin edits it.
    const slug = document.getElementById('slug'), title = document.getElementById('title_en');
    if (slug && title && !slug.readOnly) {
        let touched = slug.value !== '';
        slug.addEventListener('input', function () { touched = true; });
        title.addEventListener('input', function () {
            if (touched) return;
            slug.value = title.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
        });
    }

    // The pages list: a filter box (title, address, languages, who can see it), like the other admin tables.
    if (window.jQuery && jQuery.fn.DataTable && document.getElementById('pages-table')) {
        jQuery('#pages-table').DataTable({
            paging: false, info: false, order: [],
            columnDefs: [{ orderable: false, targets: -1 }],
            language: { search: 'Filter:', searchPlaceholder: 'title, address, language, who can see it' }
        });
    }

    // Changing who can see a page reloads this page: typed, unsaved text would be lost, so ask first.
    const pageForm = document.getElementById('page-form');
    const accessForm = document.querySelector('#accessRuleModal form');
    if (pageForm && accessForm) {
        let dirty = false;
        pageForm.addEventListener('input', function () { dirty = true; });
        accessForm.addEventListener('submit', function (e) {
            if (dirty && !window.confirm('This page has changes that are not saved yet. Saving who can see it reloads the page and loses them. Continue?')) e.preventDefault();
        });
    }
})();
