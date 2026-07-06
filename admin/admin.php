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
$cache_file    = "$organism_data/.organism_cache.json";
$lock_file     = "$organism_data/.organism_cache_lock";
$cache_info    = ['generated' => null, 'organism_count' => 0, 'refreshing' => false];
$health_alerts = ['ungrouped' => 0, 'not_in_tree' => 0, 'stale_groups' => 0, 'new_gene_sets' => 0, 'orphaned_gene_sets' => 0];
$_raw_cache_data = [];
$cache_stale = false;      // true when live data fingerprints differ from the cache's
$cache_changed_orgs = [];  // organisms whose data changed since the cache was built
if (file_exists($cache_file)) {
    $raw = json_decode(file_get_contents($cache_file), true);
    if ($raw) {
        $cache_info['generated']      = $raw['generated'] ?? null;
        $cache_info['organism_count'] = count($raw['data'] ?? []);
        $_raw_cache_data = $raw['data'] ?? [];
        // not_in_tree is stable between group edits — reading from cache is fine.
        foreach ($_raw_cache_data as $_org_data) {
            $_checks = $_org_data['overall_status']['checks'] ?? [];
            if (isset($_checks['in_taxonomy_tree']) && !$_checks['in_taxonomy_tree']) {
                $health_alerts['not_in_tree']++;
            }
        }
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
// Gene_set directories on disk with no matching row in their organism's DB — e.g.
// dropped upstream during a DB rebuild but never cleaned up here. Also cache-driven.
$_orphaned_gene_set_tuples = getOrphanedGeneSetTuples($organism_data);
$health_alerts['orphaned_gene_sets'] = count($_orphaned_gene_set_tuples);
if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 600) {
    $cache_info['refreshing'] = true;
}
// Count ungrouped organisms and stale group entries using the LIVE groups file so
// the dashboard stays accurate after group edits without requiring a cache refresh.
$_groups_file = $config->getPath('metadata_path') . '/organism_assembly_groups.json';
$_gd = file_exists($_groups_file) ? (json_decode(file_get_contents($_groups_file), true) ?? []) : [];
// Build set of (organism/assembly) pairs that have at least one group assigned
$_grouped_pairs = [];
foreach ($_gd as $_ge) {
    if (!empty($_ge['groups'])) {
        $_grouped_pairs[$_ge['organism'] . '/' . $_ge['assembly']] = true;
    }
}
// Count organisms where any assembly in the cache has no group entry
foreach ($_raw_cache_data as $_org_name => $_org_data) {
    foreach ($_org_data['assemblies'] ?? [] as $_asm) {
        if (!isset($_grouped_pairs[$_org_name . '/' . $_asm])) {
            $health_alerts['ungrouped']++;
            break;
        }
    }
}
// Count stale group entries (in JSON but directory no longer on disk)
foreach ($_gd as $_ge) {
    $_gs = $_ge['gene_set'] ?? 'v1';
    $_gs_path = $organism_data . '/' . $_ge['organism'] . '/' . $_ge['assembly'] . '/' . $_gs;
    if (!is_dir($_gs_path)) {
        $health_alerts['stale_groups']++;
    }
}
// Gene_set directories that exist on disk but have no groups.json entry at all —
// e.g. a newly-added gene set nobody has granted access to yet. Checked at
// gene_set granularity (not just organism/assembly) so adding a gene set to an
// already-grouped assembly doesn't silently hide the gap — see
// getUnrepresentedGeneSetTuples() for why the coarser check misses this.
$_all_organisms = getOrganismsWithAssemblies($organism_data);
$_new_gene_set_tuples = getUnrepresentedGeneSetTuples($_all_organisms, $organism_data, $_gd);
$health_alerts['new_gene_sets'] = count($_new_gene_set_tuples);
unset($_gd, $_ge, $_gs, $_gs_path, $_groups_file, $_org_data, $_org_name, $_asm, $_checks, $_grouped_pairs, $_raw_cache_data, $_all_organisms);

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
