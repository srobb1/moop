/**
 * Retire / Restore on Manage Organisms (admin/api/retire_organism.php, lib/retire.php).
 *
 * Retire: the dialog first asks the server what it WOULD do (action=preview, nothing changes)
 * and lists it, then the admin types the organism's name to enable the button. Afterwards the
 * organism list is refreshed with the page's own Refresh Cache (rescanOrganisms()), which also
 * reloads the page when it finishes. The CSRF token rides along automatically (js/modules/csrf.js
 * wraps fetch).
 */
(function () {
    'use strict';

    const api = (typeof sitePath !== 'undefined' ? sitePath : '/moop') + '/admin/api/retire_organism.php';

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

    let organism = null;
    const modalEl = () => document.getElementById('retireModal');
    const body    = () => document.getElementById('retireBody');
    const go      = () => document.getElementById('retireGo');
    const input   = () => document.getElementById('retireConfirm');

    function openRetire(name) {
        organism = name;
        document.getElementById('retireOrgName').textContent = name.replace(/_/g, ' ');
        document.getElementById('retireTypeName').textContent = name;
        document.getElementById('retireConfirmWrap').style.display = 'none';
        input().value = '';
        go().disabled = true;
        go().style.display = '';
        body().innerHTML = '<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin me-1"></i> Checking what retiring it involves…</p>';
        bootstrap.Modal.getOrCreateInstance(modalEl()).show();

        post({ action: 'preview', organism: name }).then(p => {
            let html = '<p class="small mb-2">This will:</p>' + list(p.steps || [], 'angle-right', 'text-muted');
            html += '<p class="small text-muted mt-2 mb-0">It can be undone: <strong>Restore</strong> in the Retired list below. '
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
        post({ action: 'retire', organism, confirm: input().value }).then(r => {
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
        const name = btn.getAttribute('data-organism') || '';
        if (!window.confirm('Restore ' + name.replace(/_/g, ' ') + '? Its data, groups and JBrowse settings go back; '
                + 'register it again in Manage JBrowse afterwards.')) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Restoring…';
        post({ action: 'restore', bundle: btn.getAttribute('data-bundle') }).then(r => {
            window.alert((r.ok ? 'Restored.\n\n' : 'Restore stopped: ' + (r.error || '') + '\n\n') + (r.done || []).map(t => '• ' + t).join('\n'));
            if (r.ok) refreshList(); else { btn.disabled = false; btn.innerHTML = '<i class="fa fa-undo"></i> Restore'; }
        });
    }

    document.addEventListener('click', e => {
        const r = e.target.closest('.js-retire');
        if (r) { openRetire(r.getAttribute('data-organism')); return; }
        const s = e.target.closest('.js-restore');
        if (s) { doRestore(s); return; }
        if (e.target.closest('#retireGo')) doRetire();
    });
    document.addEventListener('input', e => {
        if (e.target.id === 'retireConfirm') go().disabled = e.target.value !== organism;
    });
})();
