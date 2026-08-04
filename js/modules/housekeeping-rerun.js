/**
 * Dashboard "Run housekeeping now" button.
 *
 * Housekeeping results are precomputed on a 4-hour interval (HOUSEKEEPING_MIN_INTERVAL),
 * so a health card can keep reporting something already fixed until the next sweep. That
 * caching is correct — the permission scan and organism-tree walk are far too expensive
 * to run on every dashboard load — but there was no way to say "recheck now" short of
 * waiting or deleting marker files by hand. This is that way.
 *
 * Reports per-task results rather than a bare spinner: the sweep is several seconds and
 * runs eight distinct tasks, so showing which one is finishing (and what it cost) beats
 * an opaque wait. Requires `sitePath` (set via inline_scripts in admin.php).
 *
 * CSRF: js/modules/csrf.js attaches the token to jQuery AJAX automatically.
 */
(function () {
    'use strict';

    const btn = document.getElementById('rerunHousekeepingBtn');
    if (!btn) return;

    const out = document.getElementById('rerunHousekeepingResult');

    // Rough per-task expectations so the wait is not silent. The permission scan walks
    // the whole organism tree and dominates the total; naming it is the difference
    // between "it hung" and "it is doing the slow one".
    const STEPS = [
        'clean_temp_files',
        'ensure_cache_dir',
        'snapshot_site_data',
        'environment_check',
        'permission_check',
        'refresh_annotation_caches',
        'refresh_organism_cache',
        'ncbi_taxonomy_update'
    ];

    function render(html, cls) {
        if (!out) return;
        out.className = 'small mt-2 ' + (cls || 'text-muted');
        out.innerHTML = html;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
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

            const secs = (res.elapsed_ms / 1000).toFixed(1);
            finish('<strong>' + escapeHtml(res.message) + '</strong> in ' + secs + 's' +
                   '<div class="mt-1">' + rows + '</div>' +
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

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
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
