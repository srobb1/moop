/**
 * Admin dashboard: the organism cache card (Update Cache button + progress bar).
 *
 * Was page logic inside admin/admin.php's inline_scripts until 2026-11-25 (CLAUDE.md §7; and the
 * CSP can only drop 'unsafe-inline' once no page logic is inline). The button carries
 * data-action="refresh-organism-cache"; the health card's copy (admin/pages/_data_health_card.php)
 * uses the same attribute, and on Manage Organisms it runs that page's rescanOrganisms() instead.
 * Globals from inline_scripts: sitePath, dashCacheRefreshing.
 *
 * Organism cache status widget for the dashboard. Polls the same GET status
 * endpoint used elsewhere (admin/api/refresh_organism_cache.php) and renders
 * an actual progress bar from its `progress: {current, total, step, organism}`
 * field, instead of a static "in progress" label with no detail. Also polls
 * on page load (not just after clicking the button) — a refresh can already
 * be running when the page loads because housekeeping launches it in the
 * background on its own schedule, not only in response to a button click.
 */
function pollOrganismCacheStatus() {
  const ep = sitePath + '/admin/api/refresh_organism_cache.php';
  const bar  = document.getElementById('dashCacheProgressBar');
  const text = document.getElementById('dashCacheProgressText');
  const wrap = document.getElementById('dashCacheProgressWrap');
  if (!bar || !text || !wrap) return;
  wrap.style.display = 'block';
  const poll = setInterval(() => {
    fetch(ep + '?status=1').then(r => r.json()).then(s => {
      if (s.progress) {
        const p = s.progress;
        const pct = p.total > 0 ? Math.round((p.current / p.total) * 100) : 0;
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        text.textContent = '[' + p.current + '/' + p.total + '] ' + p.step + ': ' + p.organism;
      } else {
        text.textContent = 'Starting…';
      }
      if (s.status === 'idle') {
        clearInterval(poll);
        bar.style.width = '100%';
        bar.textContent = '100%';
        text.textContent = 'Done — reloading…';
        setTimeout(() => window.location.reload(), 800);
      }
    }).catch(() => {
      clearInterval(poll);
      text.textContent = 'Could not check status — reload the page to check manually.';
    });
  }, 1500);
}
function startOrganismCacheRefresh(btn) {
  const ep = sitePath + '/admin/api/refresh_organism_cache.php';
  const tok = document.querySelector('meta[name="csrf-token"]')?.content || '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Starting…'; }
  fetch(ep, { method: 'POST', headers: { 'X-CSRF-Token': tok } })
    .then(r => r.json())
    .then(d => {
      if (d.error) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-sync-alt"></i> Update Cache'; }
        alert('Error: ' + d.error);
        return;
      }
      if (btn) btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Refreshing…';
      pollOrganismCacheStatus();
    })
    .catch(e => {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-sync-alt"></i> Update Cache'; }
      alert('Failed: ' + e);
    });
}

// The card's buttons (dashboard and health card). On Manage Organisms this file is not loaded;
// organism-management.js answers the same attribute with its own rescanOrganisms().
document.addEventListener('click', function (e) {
  const btn = e.target.closest && e.target.closest('[data-action="refresh-organism-cache"]');
  if (btn) startOrganismCacheRefresh(btn);
});

if (typeof dashCacheRefreshing !== 'undefined' && dashCacheRefreshing) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pollOrganismCacheStatus);
  else pollOrganismCacheStatus();
}
