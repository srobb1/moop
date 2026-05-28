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
$health_alerts = ['ungrouped' => 0, 'not_in_tree' => 0, 'stale_groups' => 0];
$_raw_cache_data = [];
if (file_exists($cache_file)) {
    $raw = json_decode(file_get_contents($cache_file), true);
    if ($raw) {
        $cache_info['generated']      = $raw['generated'] ?? null;
        $cache_info['organism_count'] = count($raw['data'] ?? []);
        $_raw_cache_data = $raw['data'] ?? [];
        // not_in_tree is stable between group edits — reading from cache is fine
        foreach ($_raw_cache_data as $_org_data) {
            $_checks = $_org_data['overall_status']['checks'] ?? [];
            if (isset($_checks['in_taxonomy_tree']) && !$_checks['in_taxonomy_tree']) {
                $health_alerts['not_in_tree']++;
            }
        }
    }
}
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
unset($_gd, $_ge, $_gs, $_gs_path, $_groups_file, $_org_data, $_org_name, $_asm, $_checks, $_grouped_pairs, $_raw_cache_data);

// Prepare data for content file
// Site-data backup status comes from housekeeping (stored in session)
$data = [
    'config' => $config,
    'site' => $site,
    'site_data_backup' => $_SESSION['site_data_backup'] ?? null,
    'cache_info' => $cache_info,
    'health_alerts' => $health_alerts,
    'inline_scripts' => [
        "const sitePath = '/" . $config->getString('site') . "';",
        // Inline refresh function for the dashboard — organism-management.js is not loaded here
        "function refreshOrganismCache(btn,statusEl){
          const ep=sitePath+'/admin/api/refresh_organism_cache.php';
          const tok=document.querySelector('meta[name=\"csrf-token\"]')?.content||'';
          if(btn){btn.disabled=true;btn.innerHTML='<i class=\"fa fa-spinner fa-spin\"></i> Refreshing…';}
          if(statusEl){statusEl.textContent='Starting…';statusEl.style.display='';}
          fetch(ep,{method:'POST',headers:{'X-CSRF-Token':tok}})
            .then(r=>r.json()).then(d=>{
              if(d.error){if(btn){btn.disabled=false;btn.innerHTML='<i class=\"fa fa-sync-alt\"></i> Update Cache';}if(statusEl)statusEl.textContent='Error: '+d.error;return;}
              const t0=Date.now();
              const p=setInterval(()=>{fetch(ep+'?status=1').then(r=>r.json()).then(s=>{
                const el=Math.round((Date.now()-t0)/1000);
                if(statusEl)statusEl.textContent='Scanning… '+el+'s';
                if(s.status==='idle'&&el>=1){clearInterval(p);if(statusEl)statusEl.textContent='Done — reloading…';window.location.reload();}
              }).catch(()=>clearInterval(p));},2000);
            }).catch(e=>{if(btn){btn.disabled=false;btn.innerHTML='<i class=\"fa fa-sync-alt\"></i> Update Cache';}if(statusEl)statusEl.textContent='Failed: '+e;});
        }"
    ],
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
