<?php
/**
 * ADMIN DASHBOARD - Wrapper
 * 
 * Handles admin access verification and renders admin dashboard
 * using clean architecture layout system.
 */

// Load admin initialization (handles auth, config, includes)
include_once __DIR__ . '/admin_init.php';

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Get config
$site = $config->getString('site');

// Configure display
$display_config = [
    'title' => 'Admin Tools - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/admin.php',
];

// Read organism cache metadata for dashboard status widget (no scanning)
$organism_data = $config->getPath('organism_data');
$cache_file    = moop_organism_cache_file();
$lock_file     = "$organism_data/.organism_cache_lock";
$cache_info    = ['generated' => null, 'organism_count' => 0, 'refreshing' => false];
$health_alerts = ['ungrouped' => 0, 'not_in_tree' => 0, 'stale_groups' => 0, 'new_gene_sets' => 0, 'orphaned_gene_sets' => 0, 'orphaned_assemblies' => 0, 'no_database' => 0];
$_raw_cache_data = [];
$cache_stale = false;      // true when live data fingerprints differ from the cache's
$cache_changed_orgs = [];  // organisms whose data changed since the cache was built
if (file_exists($cache_file)) {
    $raw = loadJsonFile($cache_file, []);
    if ($raw) {
        $cache_info['generated']      = $raw['generated'] ?? null;
        $cache_info['organism_count'] = count($raw['data'] ?? []);
        $_raw_cache_data = $raw['data'] ?? [];
        // Content-based staleness: compare the CURRENT data fingerprints against the ones
        // stored when the cache was built, so we flag "your data actually changed" (a DB
        // rebuilt/copied over, groups/taxonomy edited) instead of nagging by age. Cheap
        // (~3ms: organism.sqlite mtimes + two config files). This is what the drift/orphan
        // checks below depend on — a stale cache silently hides real problems.
        if (function_exists('buildPerOrganismFingerprints') && function_exists('buildConfigFingerprint')) {
            $_meta_path   = $config->getPath('metadata_path');
            $_cur_org_fps = buildPerOrganismFingerprints($organism_data);
            $_cur_cfg_fp  = buildConfigFingerprint("$_meta_path/taxonomy_tree_config.json", "$_meta_path/organism_assembly_groups.json");
            $_cached_org_fps = $raw['org_fingerprints'] ?? [];
            foreach ($_cur_org_fps as $_o => $_fp) {
                if (!isset($_cached_org_fps[$_o]) || $_cached_org_fps[$_o] !== $_fp) $cache_changed_orgs[] = $_o;
            }
            foreach ($_cached_org_fps as $_o => $_fp) {
                if (!isset($_cur_org_fps[$_o])) $cache_changed_orgs[] = $_o . ' (removed)';
            }
            sort($cache_changed_orgs);
            $_config_changed = isset($raw['config_fingerprint']) && $raw['config_fingerprint'] !== $_cur_cfg_fp;
            $cache_stale = !empty($cache_changed_orgs) || $_config_changed;
        }
    }
}
if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 600) {
    $cache_info['refreshing'] = true;
}
// Data health alerts — computed by a shared helper so the dashboard and the manage
// organisms page always show the SAME issues (single source of truth).
$_health = computeDataHealthAlerts($organism_data);
$health_alerts             = $_health['health_alerts'];
$_orphaned_gene_set_tuples = $_health['orphaned_gene_set_tuples'];
$_orphaned_assembly_tuples = $_health['orphaned_assembly_tuples'];
$_no_database_organisms    = $_health['no_database_organisms'];
$_new_gene_set_tuples      = $_health['new_gene_set_tuples'];
unset($_raw_cache_data, $_health);

// Prepare data for content file
// Site-data backup status comes from housekeeping (stored in session)
$data = [
    'config' => $config,
    'site' => $site,
    'site_data_backup' => $_SESSION['site_data_backup'] ?? null,
    'cache_info' => $cache_info,
    'cache_stale' => $cache_stale,
    'cache_changed_orgs' => $cache_changed_orgs,
    'health_alerts' => $health_alerts,
    'new_gene_set_tuples' => $_new_gene_set_tuples,
    'orphaned_gene_set_tuples' => $_orphaned_gene_set_tuples,
    'orphaned_assembly_tuples' => $_orphaned_assembly_tuples,
    'no_database_organisms' => $_no_database_organisms,
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        // Organism cache status widget for the dashboard. Polls the same GET status
        // endpoint used elsewhere (admin/api/refresh_organism_cache.php) and renders
        // an actual progress bar from its `progress: {current, total, step, organism}`
        // field, instead of a static "in progress" label with no detail. Also polls
        // on page load (not just after clicking the button) — a refresh can already
        // be running when the page loads because housekeeping launches it in the
        // background on its own schedule, not only in response to a button click.
        "function pollOrganismCacheStatus() {
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
          const tok = document.querySelector('meta[name=\"csrf-token\"]')?.content || '';
          if (btn) { btn.disabled = true; btn.innerHTML = '<i class=\"fa fa-spinner fa-spin\"></i> Starting…'; }
          fetch(ep, { method: 'POST', headers: { 'X-CSRF-Token': tok } })
            .then(r => r.json())
            .then(d => {
              if (d.error) {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class=\"fa fa-sync-alt\"></i> Update Cache'; }
                alert('Error: ' + d.error);
                return;
              }
              if (btn) btn.innerHTML = '<i class=\"fa fa-spinner fa-spin\"></i> Refreshing…';
              pollOrganismCacheStatus();
            })
            .catch(e => {
              if (btn) { btn.disabled = false; btn.innerHTML = '<i class=\"fa fa-sync-alt\"></i> Update Cache'; }
              alert('Failed: ' + e);
            });
        }
        " . ($cache_info['refreshing'] ? "document.addEventListener('DOMContentLoaded', pollOrganismCacheStatus);" : ""),
    ],
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
