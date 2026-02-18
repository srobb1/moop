<?php
/**
 * JBROWSE MANAGEMENT - Wrapper
 * 
 * Admin interface for managing JBrowse assemblies, tracks, and configurations.
 * Provides Google Sheets registration, track listing, URL validation, and config generation.
 */

// Load admin initialization (handles auth, config, includes)
include_once __DIR__ . '/admin_init.php';

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Get config
$site = $config->getString('site');

// Configure display
$display_config = [
    'title' => 'JBrowse Management - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/manage_jbrowse.php',
];

// Get all organisms for dropdowns
$organisms_dir = $config->getPath('organism_data');
$organisms = [];
if (is_dir($organisms_dir)) {
    foreach (scandir($organisms_dir) as $org) {
        if ($org === '.' || $org === '..') continue;
        $org_path = $organisms_dir . '/' . $org;
        if (is_dir($org_path)) {
            $assemblies = [];
            foreach (scandir($org_path) as $asm) {
                if ($asm === '.' || $asm === '..' || $asm === 'organism.sqlite') continue;
                if (is_dir($org_path . '/' . $asm)) {
                    $assemblies[] = $asm;
                }
            }
            if (!empty($assemblies)) {
                $organisms[$org] = $assemblies;
            }
        }
    }
}

// Get track statistics
$tracks_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
$track_stats = [
    'total' => 0,
    'by_type' => [],
    'by_access' => [],
    'by_organism' => [],
    'warnings' => 0
];

if (is_dir($tracks_dir)) {
    $track_files = glob($tracks_dir . '/*/*/*/*json');
    $track_stats['total'] = count($track_files);
    
    foreach ($track_files as $file) {
        $track = json_decode(file_get_contents($file), true);
        if (!$track) continue;
        
        // Track type
        $type = $track['type'] ?? 'Unknown';
        $track_stats['by_type'][$type] = ($track_stats['by_type'][$type] ?? 0) + 1;
        
        // Access level
        $access = $track['metadata']['access_level'] ?? 'PUBLIC';
        $track_stats['by_access'][$access] = ($track_stats['by_access'][$access] ?? 0) + 1;
        
        // Check for warnings (public sources with non-public access)
        if (isset($track['adapter']['gffGzLocation']['uri']) || 
            isset($track['adapter']['bigWigLocation']['uri']) ||
            isset($track['adapter']['bamLocation']['uri'])) {
            
            $uri = $track['adapter']['gffGzLocation']['uri'] ?? 
                   $track['adapter']['bigWigLocation']['uri'] ?? 
                   $track['adapter']['bamLocation']['uri'] ?? '';
            
            $publicBases = ['ucsc.edu', 'ensembl.org', 'ncbi.nlm.nih.gov'];
            foreach ($publicBases as $base) {
                if (strpos($uri, $base) !== false && $access !== 'PUBLIC') {
                    $track_stats['warnings']++;
                    break;
                }
            }
        }
    }
}

// Prepare data for content file
$data = [
    'config' => $config,
    'site' => $site,
    'organisms' => $organisms,
    'track_stats' => $track_stats,
    'inline_scripts' => [
        'const jbrowseOrganisms = ' . json_encode($organisms) . ';',
        'const sitePath = "' . $site . '";'
    ],
    'page_script' => [
        '/' . $config->getString('site') . '/js/admin-utilities.js',
        '/' . $config->getString('site') . '/js/jbrowse-admin.js',
    ],
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);
?>
