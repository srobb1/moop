/**
 * Manage Menu (admin/pages/manage_menu.php): edit the main navigation bar.
 *
 * The menu is kept as DATA (window.mmData.menu, the same shape ConfigManager::mainMenu() returns)
 * and the editor is redrawn from it after every change; the whole menu is written into the
 * hidden #mm-json field just before the form is sent, and the server checks it
 * (ConfigManager::mainMenuProblem()) before saving. Nothing here builds HTML from text the admin
 * typed: labels and addresses go into .value / .textContent only.
 */
(function () {
    'use strict';
    const data = window.mmData;
    const root = document.getElementById('mm-editor');
    if (!data || !root) return;

    const menu  = JSON.parse(JSON.stringify(data.menu || []));
    const pages = data.pages || [];
    // The site's active languages, English first (Admin -> Manage Languages). A label in a retired
    // language is not shown here but stays in the item, so it is saved back untouched.
    const languages = data.languages && data.languages.length ? data.languages : [{ code: 'en', name: 'English' }];

    // ---- small DOM helpers (no innerHTML with admin text) ----------------------------------
    function el(tag, attrs, children) {
        const n = document.createElement(tag);
        Object.entries(attrs || {}).forEach(([k, v]) => {
            if (k === 'text') n.textContent = v;
            else if (k === 'class') n.className = v;
            else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
            else n.setAttribute(k, v);
        });
        (children || []).forEach(c => c && n.appendChild(c));
        return n;
    }
    function iconBtn(icon, title, onclick, cls) {
        return el('button', { type: 'button', class: 'btn btn-sm ' + (cls || 'btn-outline-secondary'), title: title,
                              'aria-label': title, onclick: onclick }, [el('i', { class: 'fa ' + icon })]);
    }
    function moveBtns(list, i) {
        return el('div', { class: 'btn-group btn-group-sm me-2' }, [
            iconBtn('fa-arrow-up', 'Move up', () => { if (i > 0) { [list[i - 1], list[i]] = [list[i], list[i - 1]]; draw(); } }),
            iconBtn('fa-arrow-down', 'Move down', () => { if (i < list.length - 1) { [list[i + 1], list[i]] = [list[i], list[i + 1]]; draw(); } }),
        ]);
    }
    function removeBtn(list, i) {
        return iconBtn('fa-times', 'Remove', () => { list.splice(i, 1); draw(); }, 'btn-outline-danger ms-2');
    }
    function labelInputs(item, placeholder) {
        item.label = item.label && typeof item.label === 'object' ? item.label : { en: String(item.label || '') };
        const mk = (lang, ph) => {
            const input = el('input', { type: 'text', class: 'form-control form-control-sm', maxlength: '60',
                                        placeholder: ph, 'aria-label': ph });
            input.value = item.label[lang] || '';
            input.addEventListener('input', () => { item.label[lang] = input.value; });
            return input;
        };
        return el('div', { class: 'd-flex gap-1 flex-grow-1', style: 'min-width: 16rem;' },
                  languages.map((l, i) => mk(l.code, placeholder + ' (' + l.name + (i ? ', optional' : '') + ')')));
    }
    function targetInputs(item) {
        item.to = item.to || { builtin: 'about' };
        const sel = el('select', { class: 'form-select form-select-sm', style: 'max-width: 16rem;', 'aria-label': 'Goes to' });
        const opts = [['builtin:about', 'About'], ['builtin:help', 'Help'], ['builtin:home', 'Home page']]
            .concat(pages.map(p => ['page:' + p.slug, 'Page: ' + p.title]))
            .concat([['url', 'An address…']]);
        const now = item.to.builtin ? 'builtin:' + item.to.builtin : item.to.page ? 'page:' + item.to.page : 'url';
        opts.forEach(([v, t]) => { const o = el('option', { value: v, text: t }); if (v === now) o.selected = true; sel.appendChild(o); });
        if (item.to.page && !pages.some(p => p.slug === item.to.page)) {
            const o = el('option', { value: 'page:' + item.to.page, text: 'Page: ' + item.to.page + ' (deleted — not shown)' });
            o.selected = true; sel.appendChild(o);
        }
        const url = el('input', { type: 'text', class: 'form-control form-control-sm' + (now === 'url' ? '' : ' d-none'),
                                  placeholder: '/moop/… or https://…', 'aria-label': 'Address', style: 'max-width: 20rem;' });
        url.value = item.to.url || '';
        sel.addEventListener('change', () => {
            const v = sel.value;
            if (v === 'url') { item.to = { url: url.value }; url.classList.remove('d-none'); }
            else if (v.startsWith('page:')) { item.to = { page: v.slice(5) }; url.classList.add('d-none'); }
            else { item.to = { builtin: v.slice(8) }; url.classList.add('d-none'); }
        });
        url.addEventListener('input', () => { item.to = { url: url.value }; });
        return [sel, url];
    }
    function keyFor(label) {
        const base = String(label || 'menu').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'menu';
        let key = base, n = 2;
        while (menu.some(m => m.type === 'dropdown' && m.key === key)) key = base + '-' + (n++);
        return key;
    }

    // ---- drawing ---------------------------------------------------------------------------
    function draw() {
        root.textContent = '';
        if (!menu.length) root.appendChild(el('p', { class: 'text-muted small', text: 'The menu bar is empty (only the site name shows).' }));
        menu.forEach((item, i) => {
            const row = el('div', { class: 'border rounded p-2 mb-2 bg-white' });
            const head = el('div', { class: 'd-flex align-items-center flex-wrap gap-2' }, [moveBtns(menu, i)]);
            if (item.type === 'tools') {
                head.appendChild(el('span', {}, [el('i', { class: 'fa fa-toolbox me-1' }),
                    el('strong', { text: 'Tools' }), el('span', { class: 'text-muted small ms-2', text: 'drop-down — arranged on Manage Tools' })]));
            } else if (item.type === 'link') {
                head.appendChild(el('span', { class: 'badge bg-light text-dark', text: 'Link' }));
                head.appendChild(labelInputs(item, 'Label'));
                targetInputs(item).forEach(n => head.appendChild(n));
            } else {
                head.appendChild(el('span', { class: 'badge bg-info text-dark', text: 'Drop-down' }));
                head.appendChild(labelInputs(item, 'Name'));
            }
            head.appendChild(removeBtn(menu, i));
            row.appendChild(head);

            if (item.type === 'dropdown') {
                item.items = item.items || [];
                const box = el('div', { class: 'ms-4 mt-2 ps-3 border-start' });
                if (!item.items.length) box.appendChild(el('p', { class: 'small text-muted mb-1', text: 'Empty — an empty drop-down is not shown.' }));
                item.items.forEach((child, j) => {
                    const line = el('div', { class: 'd-flex align-items-center flex-wrap gap-2 mb-1' }, [moveBtns(item.items, j)]);
                    if (child.type === 'divider') {
                        line.appendChild(el('span', { class: 'text-muted small', text: '— divider line —' }));
                    } else {
                        line.appendChild(labelInputs(child, 'Label'));
                        targetInputs(child).forEach(n => line.appendChild(n));
                    }
                    line.appendChild(removeBtn(item.items, j));
                    box.appendChild(line);
                });
                box.appendChild(el('div', { class: 'd-flex gap-2 mt-1' }, [
                    el('button', { type: 'button', class: 'btn btn-sm btn-outline-primary', text: 'Add a link here',
                                   onclick: () => { item.items.push({ type: 'link', label: { en: '' }, to: { builtin: 'about' } }); draw(); } }),
                    el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Add a divider',
                                   onclick: () => { item.items.push({ type: 'divider' }); draw(); } }),
                ]));
                row.appendChild(box);
            }
            root.appendChild(row);
        });
        const addTools = document.getElementById('mm-add-tools');
        if (addTools) addTools.classList.toggle('d-none', menu.some(m => m.type === 'tools'));
    }

    document.querySelectorAll('[data-mm-add]').forEach(btn => btn.addEventListener('click', () => {
        const kind = btn.dataset.mmAdd;
        if (kind === 'link') menu.push({ type: 'link', label: { en: '' }, to: { builtin: 'about' } });
        if (kind === 'dropdown') menu.push({ type: 'dropdown', key: '', label: { en: '' }, items: [] });
        if (kind === 'tools' && !menu.some(m => m.type === 'tools')) menu.unshift({ type: 'tools' });
        draw();
    }));

    document.getElementById('mm-form').addEventListener('submit', () => {
        // A new drop-down gets its key from its name, once; an existing key never changes,
        // because pages refer to the drop-down by it.
        menu.forEach(m => { if (m.type === 'dropdown' && !m.key) m.key = keyFor(m.label && m.label.en); });
        document.getElementById('mm-json').value = JSON.stringify(menu);
    });

    draw();
})();
