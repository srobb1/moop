/**
 * Dashboard "Run housekeeping now" button.
 *
 * Housekeeping results are precomputed on an hourly interval (HOUSEKEEPING_MIN_INTERVAL),
 * so a health card can keep reporting something already fixed until the next sweep. That
 * caching is correct — the permission scan and organism-tree walk are far too expensive
 * to run on every dashboard load — but there was no way to say "recheck now" short of
 * waiting or deleting marker files by hand. This is that way.
 *
 * Reports per-task results rather than a bare spinner: the sweep is several seconds and
 * runs several distinct tasks, so showing which one is finishing (and what it cost) beats
 * an opaque wait. Requires `sitePath` (set via inline_scripts in admin.php).
 *
 * CSRF: js/modules/csrf.js attaches the token to jQuery AJAX automatically.
 */
(function () {
    'use strict';

    const btn = document.getElementById('rerunHousekeepingBtn');
    if (!btn) return;

    const out = document.getElementById('rerunHousekeepingResult');

    // The task names come from the server (data-tasks, rendered from
    // housekeeping_task_registry()) so this list cannot drift. It was a hardcoded copy of
    // eight names and had silently lost the two JBrowse tasks.
    let STEPS = [];
    try { STEPS = JSON.parse(btn.dataset.tasks || '[]'); } catch (e) { STEPS = []; }

    function render(html, cls) {
        if (!out) return;
        out.className = 'small mt-2 ' + (cls || 'text-muted');
        out.innerHTML = html;
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Running…';

        // The request is synchronous server-side, so we cannot stream real progress.
        // Walk the expected step list on a timer instead — honest about being an
        // estimate, and it still tells you which task is the slow one.
        let i = 0;
        render('Starting ' + STEPS.length + ' tasks…');
        const ticker = setInterval(function () {
            if (i < STEPS.length) {
                render('Running <code>' + escapeHtml(STEPS[i]) + '</code>… (' +
                       (i + 1) + '/' + STEPS.length + ')');
                i++;
            }
        }, 700);

        function finish(html, cls) {
            clearInterval(ticker);
            btn.disabled = false;
            btn.innerHTML = original;
            render(html, cls);
        }

        $.ajax({
            url: sitePath + '/admin/api/rerun_housekeeping.php',
            method: 'POST',
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                finish('⚠ ' + escapeHtml((res && res.message) || 'Housekeeping did not run.'),
                       'text-warning');
                return;
            }

            const rows = (res.tasks || []).map(function (t) {
                const icon = t.ok ? '✓' : '✗';
                const cls  = t.ok ? 'text-success' : 'text-danger';
                const err  = t.error ? ' — ' + escapeHtml(t.error) : '';
                return '<div><span class="' + cls + '">' + icon + '</span> <code>' +
                       escapeHtml(t.name) + '</code> <span class="text-muted">' +
                       t.ms + ' ms</span>' + err + '</div>';
            }).join('');

            // jbrowse_indexes only COUNTS in this request -- rebuilding runs in the
            // background child. Say so, or "✓ 3 ms" reads as "rebuilt".
            let indexNote = '';
            const ir = res.index_rebuild;
            if (ir) {
                indexNote = ir.needed === 0
                    ? '<div class="mt-1">Gene-track indexes: all current.</div>'
                    : '<div class="mt-1"><strong>' + ir.needed + ' gene-track ' +
                      (ir.needed === 1 ? 'index needs' : 'indexes need') + ' rebuilding</strong>' +
                      (ir.broken ? ' (' + ir.broken + ' broken)' : '') + ' — ' +
                      (ir.started
                          ? 'rebuild started in the background (up to about five minutes per run). Reload later to see the result.'
                          : 'could not start the background rebuild; it will run with the next hourly housekeeping.') +
                      '</div>';
            }

            const secs = (res.elapsed_ms / 1000).toFixed(1);
            finish('<strong>' + escapeHtml(res.message) + '</strong> in ' + secs + 's' +
                   '<div class="mt-1">' + rows + '</div>' + indexNote +
                   '<div class="mt-2"><a href="">Reload the page</a> to see the updated cards.</div>',
                   res.failed ? 'text-warning' : 'text-muted');
        }).fail(function (xhr) {
            finish('⚠ Request failed (HTTP ' + xhr.status + '). Check logs/error.log.',
                   'text-danger');
        });
    });
})();

/**
 * Permission card "Re-check now" — re-runs the permission scan ALONE.
 *
 * Deliberately a separate IIFE from the full-sweep button above. That one returns early
 * when its button is absent, and this card is conditional (it does not render at all once
 * the findings are clean), so sharing a scope would tie each button's wiring to the
 * other's presence.
 *
 * Why a targeted re-run and not the full sweep: the card is a cached snapshot, so it keeps
 * reporting a problem you have already fixed until the next interval. The full sweep
 * answers that but also re-walks the organism tree and can kick off an organism-cache
 * rebuild — disproportionate for refreshing one card, and slow enough that you would not
 * fire it automatically after a fix. One task is seconds.
 */
(function () {
    'use strict';

    const btn = document.getElementById('recheckPermissionsBtn');
    if (!btn) return;

    const out = document.getElementById('recheckPermissionsResult');

    function render(html, cls) {
        if (!out) return;
        out.style.display = '';
        out.className = 'small mb-4 ' + (cls || 'text-muted');
        out.innerHTML = html;
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking…';
        render('Walking the filesystem — this is the slow check, a few seconds.');

        function restore() {
            btn.disabled = false;
            btn.innerHTML = original;
        }

        $.ajax({
            url: sitePath + '/admin/api/rerun_housekeeping.php',
            method: 'POST',
            data: { task: btn.dataset.task || 'permission_check' },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                restore();
                render('⚠ ' + escapeHtml((res && res.message) || 'The check did not run.'),
                       'text-warning');
                return;
            }

            // Say what the answer IS, not merely that it re-ran — the whole point is
            // finding out whether the fix worked.
            const ps = res.perm_summary || {};
            const n  = ps.finding_count || 0;
            const secs = (res.elapsed_ms / 1000).toFixed(1);

            if (n === 0) {
                render('✓ No permission issues found (' + secs + 's) — reloading…',
                       'text-success');
            } else {
                const parts = [];
                if (ps.high)   parts.push(ps.high + ' high');
                if (ps.medium) parts.push(ps.medium + ' medium');
                if (ps.low)    parts.push(ps.low + ' low');
                render('Still ' + n + ' ' + (n === 1 ? 'area' : 'areas') +
                       (parts.length ? ' (' + escapeHtml(parts.join(', ')) + ')' : '') +
                       ' — ' + secs + 's, reloading…', 'text-warning');
            }
            // Reload so the card re-renders from the freshly written summary — including
            // vanishing entirely when there is nothing left to report.
            setTimeout(function () { window.location.reload(); }, 1200);
        }).fail(function (xhr) {
            restore();
            render('⚠ Request failed (HTTP ' + xhr.status + '). Check logs/error.log.',
                   'text-danger');
        });
    });
})();

/**
 * Gene-track index card "Rebuild now" — starts the jbrowse_indexes task in the BACKGROUND
 * CLI child and returns at once. Re-compressing GFFs takes minutes and is never done inside
 * a page request, so the button cannot wait for the answer. Instead it polls
 * rerun_housekeeping.php status=1, which reads the progress file the rebuild writes as it goes
 * (housekeeping_jbrowse_progress_write()), and shows which gene set it is on, step by step.
 * Opening the dashboard while a run is in flight picks the progress up too.
 */
(function () {
    'use strict';

    const btn = document.getElementById('rebuildGeneIndexesBtn');
    if (!btn) return;

    const out      = document.getElementById('rebuildGeneIndexesResult');
    const api      = sitePath + '/admin/api/rerun_housekeeping.php';
    const original = btn.innerHTML;
    const POLL_MS  = 3000;
    let since = null;   // server time the run was asked for; older progress is a previous run's

    function render(html, cls) {
        if (!out) return;
        out.style.display = '';
        out.className = 'small mb-4 ' + (cls || 'text-muted');
        out.innerHTML = html;
    }

    const label = key => escapeHtml(String(key).split('/').map((p, i) => i === 0 ? p.replace(/_/g, ' ') : p).join(' / '));
    const clock = t => new Date(t * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    function busy(text) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + text;
    }

    function listOf(p) {
        let html = '';
        (p.done || []).forEach(k => { html += '<div><span class="text-success">✓</span> ' + label(k) + '</div>'; });
        (p.failed || []).forEach(f => { html += '<div><span class="text-danger">✗</span> ' + escapeHtml(f) + '</div>'; });
        return html;
    }

    function poll() {
        $.ajax({ url: api, method: 'POST', data: { status: 1 }, dataType: 'json' }).done(function (res) {
            const p     = res && res.progress;
            const fresh = p && since !== null && p.started_at >= since - 5;
            if (res && res.running) {
                if (fresh && !p.finished) {
                    busy('Rebuilding ' + p.index + ' of ' + p.total + '…');
                    render('<div><span class="spinner-border spinner-border-sm text-secondary me-1"></span> '
                         + 'Rebuilding <strong>' + p.index + ' of ' + p.total + '</strong>: ' + label(p.current)
                         + ' <span class="text-muted">(started ' + clock(p.started_at) + ')</span></div>' + listOf(p));
                } else {
                    busy('Starting…');
                    render('<span class="spinner-border spinner-border-sm text-secondary me-1"></span> '
                         + 'Housekeeping is running in the background; the gene-track rebuild starts when it reaches it. '
                         + 'This page updates by itself.');
                }
                setTimeout(poll, POLL_MS);
                return;
            }
            // Not running any more: report what this run did, then reload so the card is current.
            if (fresh && p.finished) {
                const bad = (p.failed || []).length;
                render('<div class="' + (bad ? 'text-warning' : 'text-success') + ' fw-semibold">'
                     + (p.total === 0 ? 'Nothing needed rebuilding.'
                        : 'Rebuilt ' + (p.done || []).length + ' of ' + p.total + (bad ? ', ' + bad + ' failed' : '')
                          + (p.left ? ', ' + p.left + ' left for the next run' : '') + ' in ' + p.seconds + ' s.')
                     + '</div>' + listOf(p) + '<div class="text-muted mt-1">Refreshing the card…</div>');
            } else {
                render('Housekeeping finished. Refreshing the card…');
            }
            btn.innerHTML = original;
            setTimeout(() => window.location.reload(), 2500);
        }).fail(function (xhr) {
            btn.disabled = false;
            btn.innerHTML = original;
            render('⚠ Lost touch with the server (HTTP ' + xhr.status + '). The rebuild may still be running — reload in a minute.', 'text-danger');
        });
    }

    btn.addEventListener('click', function () {
        busy('Starting…');
        render('<span class="spinner-border spinner-border-sm text-secondary me-1"></span> Starting the rebuild in the background…');
        $.ajax({
            url: api,
            method: 'POST',
            data: { task: btn.dataset.task || 'jbrowse_indexes', background: 1 },
            dataType: 'json'
        }).done(function (res) {
            if (res && (res.success || res.reason === 'already_running')) {
                since = res.now || Math.floor(Date.now() / 1000);
                setTimeout(poll, 1000);
                return;
            }
            btn.disabled = false;
            btn.innerHTML = original;
            render(escapeHtml((res && res.message) || 'No answer from the server.'), 'text-warning');
        }).fail(function (xhr) {
            btn.disabled = false;
            btn.innerHTML = original;
            render('⚠ Request failed (HTTP ' + xhr.status + '). Check logs/error.log.', 'text-danger');
        });
    });

    // A run already in flight (started by the hourly sweep, another admin, or before a reload):
    // show it rather than a button that looks idle.
    $.ajax({ url: api, method: 'POST', data: { status: 1 }, dataType: 'json' }).done(function (res) {
        if (!(res && res.running)) return;
        // A rebuild under way now is this run's; a finished one in the file is a previous run's.
        const p = res.progress;
        since = (p && !p.finished) ? p.started_at : res.now;
        poll();
    });
})();
