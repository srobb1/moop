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
if (file_exists($cache_file)) {
    $raw = json_decode(file_get_contents($cache_file), true);
    if ($raw) {
        $cache_info['generated']      = $raw['generated'] ?? null;
        $cache_info['organism_count'] = count($raw['data'] ?? []);
    }
}
if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 600) {
    $cache_info['refreshing'] = true;
}

// Prepare data for content file
// Site-data backup status comes from housekeeping (stored in session)
$data = [
    'config' => $config,
    'site' => $site,
    'site_data_backup' => $_SESSION['site_data_backup'] ?? null,
    'cache_info' => $cache_info,
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
