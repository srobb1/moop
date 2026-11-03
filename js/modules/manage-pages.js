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

    document.querySelectorAll('textarea.mp-body').forEach(function (area) {
        const out = document.getElementById(area.dataset.preview);
        if (!out) return;
        let timer = null, seq = 0;
        const render = function () {
            const mine = ++seq;
            const body = new URLSearchParams({ markdown: area.value });
            fetch(endpoint, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                .then(function (data) {
                    if (mine !== seq) return;           // a newer request is on its way
                    out.innerHTML = area.value.trim() === ''
                        ? '<span class="text-muted small">Start typing to see the page.</span>'
                        : data.html;                    // server-rendered, Parsedown safe mode
                })
                .catch(function (err) {
                    if (mine !== seq) return;
                    out.innerHTML = '<div class="alert alert-warning small mb-0">The preview could not be made ('
                        + String(err.message).replace(/[<>&]/g, '') + '). Your text is not lost — Save still works.</div>';
                });
        };
        area.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(render, 350); });
        if (area.value.trim() !== '') render();
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
})();
