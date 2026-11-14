/**
 * The "Who can see this" dialog (admin/pages/_access_rule_dialog.php): one editor for every access
 * rule. A button with class js-access-edit opens it, filled from its data-kind, data-key,
 * data-title and data-rule (the stored rule as JSON). The data-set list is used only for
 * "Same as a data set". Saving is a plain form post to the page, which saves via lib/access_rules.php.
 */
(function () {
    'use strict';
    const modalEl = document.getElementById('accessRuleModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const form = modalEl.querySelector('form');
    const select = form.querySelector('select[name="dataset"]');
    const sync = function () {
        const picked = form.querySelector('input[name="visible_to"]:checked');
        select.disabled = !picked || picked.value !== 'dataset';
        select.required = !select.disabled;
    };
    form.querySelectorAll('input[name="visible_to"]').forEach(r => r.addEventListener('change', sync));

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-access-edit');
        if (!btn) return;
        e.preventDefault();
        let rule = {};
        try { rule = JSON.parse(btn.dataset.rule || '{}'); } catch (err) { rule = {}; }
        form.elements.kind.value = btn.dataset.kind || '';
        form.elements.key.value = btn.dataset.key || '';
        modalEl.querySelector('.js-access-title').textContent = btn.dataset.title || '';
        const to = rule.visible_to || 'everyone';
        const radio = form.querySelector('input[name="visible_to"][value="' + to + '"]');
        if (radio) radio.checked = true;
        select.value = to === 'dataset' ? [rule.organism, rule.assembly, rule.gene_set || ''].join('|') : '';
        sync();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
})();
