/**
 * The "Who can see this" dialog (admin/pages/_access_rule_dialog.php): one editor for every access
 * rule. A button with class js-access-edit opens it, filled from its data-kind, data-key,
 * data-title and data-rule (the stored rule as JSON). The data-set list is used only for
 * "Same as a data set". Saving is a plain form post to the page, which saves via lib/access_rules.php.
 * data-kind "analysis": no "Everyone", no data-set choice (it is always its own data set), and
 * data-default marks the setting it has with no stored rule.
 */
(function () {
    'use strict';
    const modalEl = document.getElementById('accessRuleModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const form = modalEl.querySelector('form');
    const select = form.querySelector('select[name="dataset"]');
    let isAnalysis = false;
    const sync = function () {
        const picked = form.querySelector('input[name="visible_to"]:checked');
        select.disabled = isAnalysis || !picked || picked.value !== 'dataset';
        select.required = !select.disabled;
        select.classList.toggle('d-none', isAnalysis);
    };
    form.querySelectorAll('input[name="visible_to"]').forEach(r => r.addEventListener('change', sync));

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-access-edit');
        if (!btn) return;
        e.preventDefault();
        let rule = {};
        try { rule = JSON.parse(btn.dataset.rule || '{}'); } catch (err) { rule = {}; }
        form.elements.kind.value = btn.dataset.kind || '';
        isAnalysis = btn.dataset.kind === 'analysis';
        modalEl.querySelectorAll('.js-ar-for-page').forEach(el => el.classList.toggle('d-none', isAnalysis));
        modalEl.querySelectorAll('.js-ar-for-analysis').forEach(el => el.classList.toggle('d-none', !isAnalysis));
        const everyone = modalEl.querySelector('[data-setting="everyone"]');
        if (everyone) everyone.classList.toggle('d-none', isAnalysis);
        modalEl.querySelectorAll('[data-setting]').forEach(function (row) {
            const badge = row.querySelector('.js-ar-default');
            if (badge) badge.classList.toggle('d-none', !(isAnalysis && row.dataset.setting === (btn.dataset.default || 'dataset')));
        });
        form.elements.key.value = btn.dataset.key || '';
        modalEl.querySelector('.js-access-title').textContent = btn.dataset.title || '';
        const to = rule.visible_to || (isAnalysis ? 'dataset' : 'everyone');
        const radio = form.querySelector('input[name="visible_to"][value="' + to + '"]');
        if (radio) radio.checked = true;
        select.value = to === 'dataset' ? [rule.organism, rule.assembly, rule.gene_set || ''].join('|') : '';
        sync();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
})();
