/**
 * Retire / Restore on Manage Organisms (admin/api/retire.php, lib/retire.php).
 *
 * A .js-retire button names what to retire: data-level (organism | assembly | gene_set, default
 * organism), data-organism, data-assembly, data-gene-set. Organism buttons sit on the table rows;
 * assembly and gene-set buttons in the assembly dialog (admin/pages/organism_modals/asm.php).
 *
 * Retire: the dialog first asks the server what it WOULD do (action=preview, nothing changes)
 * and lists it, then the admin types the name (of the organism, assembly or gene set) to enable
 * the button. Afterwards the
 * organism list is refreshed with the page's own Refresh Cache (rescanOrganisms()), which also
 * reloads the page when it finishes. The CSRF token rides along automatically (js/modules/csrf.js
 * wraps fetch).
 */
(function () {
    'use strict';

    const api = (typeof sitePath !== 'undefined' ? sitePath : '/moop') + '/admin/api/retire.php';

    function post(fields) {
        const body = new FormData();
        Object.keys(fields).forEach(k => body.append(k, fields[k]));
        return fetch(api, { method: 'POST', body, credentials: 'same-origin' })
            .then(r => r.json())
            .catch(() => ({ ok: false, error: 'The server did not answer — nothing may have changed. Reload the page to see.' }));
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function list(items, icon, cls) {
        return '<ul class="list-unstyled small mb-0">' + items.map(t =>
            `<li class="mb-1"><i class="fa fa-${icon} ${cls} me-2"></i>${esc(t)}</li>`).join('') + '</ul>';
    }

    function refreshList() {
        // Manage Organisms' own Refresh Cache: rescans what changed, reports progress, reloads.
        if (typeof rescanOrganisms === 'function') rescanOrganisms(document.getElementById('rescanBtn'));
        else window.location.reload();
    }

    let target = null;   // {level, organism, assembly, gene_set}
    const modalEl = () => document.getElementById('retireModal');
    const body    = () => document.getElementById('retireBody');
    const go      = () => document.getElementById('retireGo');
    const input   = () => document.getElementById('retireConfirm');
    const nameOf  = t => ({ organism: t.organism, assembly: t.assembly, gene_set: t.gene_set })[t.level];

    function openRetire(btn) {
        target = {
            level:    btn.getAttribute('data-level') || 'organism',
            organism: btn.getAttribute('data-organism') || '',
            assembly: btn.getAttribute('data-assembly') || '',
            gene_set: btn.getAttribute('data-gene-set') || '',
        };
        const org = '<em class="sci-name">' + esc(target.organism.replace(/_/g, ' ')) + '</em>';
        document.getElementById('retireWhat').innerHTML =
              target.level === 'assembly' ? 'assembly <code>' + esc(target.assembly) + '</code> of ' + org
            : target.level === 'gene_set' ? 'gene set <code>' + esc(target.gene_set) + '</code> (' + esc(target.assembly) + ')'
            : org;
        document.getElementById('retireTypeName').textContent = nameOf(target);
        document.getElementById('retireConfirmWrap').style.display = 'none';
        input().value = '';
        go().disabled = true;
        go().style.display = '';
        body().innerHTML = '<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin me-1"></i> Checking what retiring it involves…</p>';
        // Bootstrap does not stack modals: an assembly or gene-set button lives in the organism
        // dialog, so close that first.
        const open = btn.closest('.modal');
        if (open && open !== modalEl()) bootstrap.Modal.getOrCreateInstance(open).hide();
        bootstrap.Modal.getOrCreateInstance(modalEl()).show();

        post(Object.assign({ action: 'preview' }, target)).then(p => {
            let html = '<p class="small mb-2">This will:</p>' + list(p.steps || [], 'angle-right', 'text-muted');
            (p.notes || []).forEach(n => { html += `<div class="alert alert-info small mt-3 mb-0">${esc(n)}</div>`; });
            html += '<p class="small text-muted mt-2 mb-0">It can be undone: <strong>Restore</strong> in the Retired list on this page. '
                  + 'People\'s access in Manage Users is kept.</p>';
            if (!p.ok) {
                html += `<div class="alert alert-danger small mt-3 mb-0">${esc(p.error || 'It cannot be retired.')}</div>`;
                go().style.display = 'none';
            } else {
                document.getElementById('retireConfirmWrap').style.display = '';
                setTimeout(() => input().focus(), 150);
            }
            body().innerHTML = html;
        });
    }

    function doRetire() {
        go().disabled = true;
        input().disabled = true;
        body().innerHTML = '<p class="small mb-0"><i class="fa fa-spinner fa-spin me-1"></i> Retiring — moving the data, unregistering, removing its groups…</p>';
        post(Object.assign({ action: 'retire', confirm: input().value }, target)).then(r => {
            input().disabled = false;
            document.getElementById('retireConfirmWrap').style.display = 'none';
            go().style.display = 'none';
            let html = (r.done && r.done.length) ? list(r.done, 'check', 'text-success') : '';
            if (r.ok) {
                html += '<p class="small mt-2 mb-0"><i class="fa fa-sync-alt fa-spin me-1"></i> Refreshing the organism list — the page reloads when it is done.</p>';
                body().innerHTML = html;
                setTimeout(() => { bootstrap.Modal.getOrCreateInstance(modalEl()).hide(); refreshList(); }, 1800);
            } else {
                html += `<div class="alert alert-danger small mt-2 mb-0">${esc(r.error || 'Retire failed.')}`
                      + (r.bundle ? ' What was done is recorded; <strong>Restore</strong> in the Retired list undoes it.' : '') + '</div>';
                body().innerHTML = html;
            }
        });
    }

    function doRestore(btn) {
        const name = (btn.getAttribute('data-name') || '').replace(/_/g, ' ');
        const settingsOnly = btn.getAttribute('data-settings-only') === '1';
        const label = btn.innerHTML;
        if (!window.confirm(settingsOnly
                ? name + ' is back on the site. Put back the groups and JBrowse settings it had when retired? The old data stays in the bundle.'
                : 'Restore ' + name + '? Its data, groups and JBrowse settings go back; the next steps are listed when it is done.')) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Working…';
        post({ action: 'restore', bundle: btn.getAttribute('data-bundle'), settings_only: settingsOnly ? '1' : '' }).then(r => {
            window.alert((r.ok ? 'Done.\n\n' : 'Stopped: ' + (r.error || '') + '\n\n') + (r.done || []).map(t => '• ' + t).join('\n'));
            if (r.ok) refreshList(); else { btn.disabled = false; btn.innerHTML = label; }
        });
    }

    document.addEventListener('click', e => {
        const r = e.target.closest('.js-retire');
        if (r) { openRetire(r); return; }
        const s = e.target.closest('.js-restore');
        if (s) { doRestore(s); return; }
        if (e.target.closest('#retireGo')) doRetire();
    });
    document.addEventListener('input', e => {
        if (e.target.id === 'retireConfirm') go().disabled = !target || e.target.value !== nameOf(target);
    });
})();
