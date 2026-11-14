/**
 * Access Overview filters (admin/pages/access_overview.php): text, kind or kind group, organism,
 * and "only restricted". Rows carry their own data-* values, so the filter reads each row and never
 * a column position. The chosen filters stay in the address (#…) so a reload or a save keeps them.
 */
(function () {
    'use strict';
    const table = document.getElementById('access-overview-table');
    if (!table) return;
    const text = document.querySelector('.js-ao-text');
    const kind = document.querySelector('.js-ao-kind');
    const org = document.querySelector('.js-ao-org');
    const restricted = document.querySelector('.js-ao-restricted');
    const shown = document.querySelector('.js-ao-shown');
    const none = document.querySelector('.js-ao-none');
    const rows = Array.from(table.tBodies[0].rows);

    const apply = function () {
        const words = text.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        const [kType, kVal] = kind.value ? kind.value.split(':') : ['', ''];
        let n = 0;
        rows.forEach(function (tr) {
            const hay = tr.textContent.toLowerCase();
            const ok = words.every(w => hay.includes(w))
                && (!kType || tr.dataset[kType] === kVal)
                && (!org.value || tr.dataset.organism === org.value)
                && (!restricted.checked || tr.dataset.restricted === '1');
            tr.classList.toggle('d-none', !ok);
            if (ok) n++;
        });
        shown.textContent = n;
        none.classList.toggle('d-none', n > 0);
        try {
            const p = new URLSearchParams();
            if (text.value) p.set('q', text.value);
            if (kind.value) p.set('kind', kind.value);
            if (org.value) p.set('org', org.value);
            if (restricted.checked) p.set('restricted', '1');
            history.replaceState(null, '', p.toString() ? '#' + p.toString() : location.pathname + location.search);
        } catch (e) { /* the filter works without remembering it */ }
    };

    try {
        const p = new URLSearchParams(location.hash.slice(1));
        text.value = p.get('q') || '';
        if (p.get('kind')) kind.value = p.get('kind');
        if (p.get('org')) org.value = p.get('org');
        restricted.checked = p.get('restricted') === '1';
    } catch (e) { /* no saved filter */ }

    [text, kind, org, restricted].forEach(el => el.addEventListener(el === text ? 'input' : 'change', apply));
    // A save redirects to the bare page: carry the filter in the form's action so it survives.
    const accessForm = document.querySelector('#accessRuleModal form');
    if (accessForm) accessForm.addEventListener('submit', function () { accessForm.action = 'access_overview.php' + location.hash; });
    apply();
})();
