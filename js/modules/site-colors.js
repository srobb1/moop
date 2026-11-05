/**
 * Site Configuration -> Site Colors (admin/pages/manage_site_config.php).
 *
 * Keeps each color picker and its hex box in step, and previews the palette: the fields go to
 * admin/api/theme_preview.php, which works the palette out (lib/theme_colors.php -- the only place
 * that arithmetic lives), and the answer is applied as CSS variables to the PREVIEW BOX only, so
 * the page itself does not change until the form is saved. CSRF: js/modules/csrf.js adds the token
 * to every fetch.
 */
(function () {
    'use strict';
    const root = document.getElementById('siteColors');
    const preview = document.getElementById('tcPreview');
    if (!root || !preview) return;
    const warning = document.getElementById('tcWarning');
    const swatches = document.getElementById('tcSwatches');
    const HEX = /^#[0-9a-fA-F]{6}$/;
    const NAMES = {
        '--moop-tool-header': 'main', '--moop-tool-hover': 'hover', '--moop-organism-header': 'organism',
        '--moop-main-dark': 'dark text', '--moop-main-tint': 'tint', '--moop-main-lightest': 'lightest',
        '--moop-assembly-header': 'assembly', '--moop-action': 'action',
    };
    const field = id => document.getElementById(id);

    let timer = null;
    function refresh() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const body = new URLSearchParams();
            ['main', 'assembly', 'action'].forEach(k => {
                const v = field('tc-' + k).value.trim();
                body.append(k, HEX.test(v) ? v : '');
            });
            fetch(window.sitePath + '/admin/api/theme_preview.php', { method: 'POST', body })
                .then(r => r.json())
                .then(show)
                .catch(() => { warning.textContent = 'The preview could not be loaded.'; warning.classList.remove('d-none'); });
        }, 150);
    }

    function show(data) {
        const p = data.palette || {};
        Object.entries(p).forEach(([k, v]) => preview.style.setProperty(k, v));
        swatches.textContent = '';
        Object.entries(NAMES).forEach(([k, label]) => {
            if (!p[k]) return;
            const chip = document.createElement('span');
            chip.className = 'badge border text-dark';
            chip.style.background = '#fff';
            const dot = document.createElement('span');
            dot.style.cssText = 'display:inline-block;width:.8em;height:.8em;border-radius:2px;margin-right:.3em;vertical-align:-1px;background:' + p[k];
            chip.appendChild(dot);
            chip.appendChild(document.createTextNode(label + ' ' + p[k]));
            swatches.appendChild(chip);
        });
        const w = data.warnings || {};
        const keys = Object.keys(w);
        warning.textContent = '';
        if (!keys.length) { warning.classList.add('d-none'); return; }
        const names = keys.map(k => (NAMES[k] || k) + ' ' + w[k] + ':1').join(', ');
        warning.appendChild(document.createTextNode('White text on these is harder to read than the usual 4.5:1 ('
            + names + '). Headers are large and bold, where 3:1 is the floor. '));
        if (data.suggestion) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm btn-outline-dark ms-1';
            b.textContent = 'Use ' + data.suggestion + ' for the main color';
            b.addEventListener('click', () => { setColor('tc-main', data.suggestion); });
            warning.appendChild(b);
        }
        warning.classList.remove('d-none');
    }

    function setColor(id, value) {
        field(id).value = value;
        const picker = root.querySelector('.tc-picker[data-for="' + id + '"]');
        if (picker && HEX.test(value)) picker.value = value;
        field(id).dispatchEvent(new Event('input', { bubbles: true }));
    }

    root.querySelectorAll('.tc-picker').forEach(picker => picker.addEventListener('input', () => {
        field(picker.dataset.for).value = picker.value;
        field(picker.dataset.for).dispatchEvent(new Event('input', { bubbles: true }));
    }));
    root.querySelectorAll('.tc-hex').forEach(hex => hex.addEventListener('input', () => {
        const picker = root.querySelector('.tc-picker[data-for="' + hex.id + '"]');
        if (picker && HEX.test(hex.value.trim())) picker.value = hex.value.trim();
        refresh();
    }));
    root.querySelectorAll('.tc-clear').forEach(b => b.addEventListener('click', () => setColor(b.dataset.for, '')));

    refresh();
})();
