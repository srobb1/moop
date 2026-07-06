<?php
/**
 * MANAGE JBROWSE - Admin Controller
 * 
 * Manages JBrowse2 track configurations and Google Sheets integration.
 * Provides two views:
 * 1. Setup View - If JBrowse2 not installed, guides user to installation
 * 2. Dashboard View - If installed, shows full management interface
 */

// Load admin initialization (handles auth, config, includes)
include_once __DIR__ . '/admin_init.php';
require_once __DIR__ . '/../lib/functions_data.php';

// Load layout system
include_once __DIR__ . '/../includes/layout.php';

// Get config
$site = $config->getString('site');
$site_path = $config->getPath('site_path');

// Check if JBrowse2 is installed
function isJBrowse2Installed($site_path) {
    $jbrowse_dir = $site_path . '/jbrowse2';
    
    // Check for key JBrowse2 files that indicate a working installation
    $required_items = [
        $jbrowse_dir . '/index.html',
        // Either @jbrowse directory (dev build) OR static directory (production build)
        // Check for at least one of these
    ];
    
    // Must have index.html
    if (!file_exists($jbrowse_dir . '/index.html')) {
        return false;
    }
    
    // Must have either @jbrowse (dev) or static (production)
    if (!is_dir($jbrowse_dir . '/@jbrowse') && !is_dir($jbrowse_dir . '/static')) {
        return false;
    }
    
    return true;
}

$jbrowse_installed = isJBrowse2Installed($site_path);

// If JBrowse is not installed, show setup view
if (!$jbrowse_installed) {
    $display_config = [
        'title' => 'JBrowse2 Setup Required - ' . $config->getString('siteTitle'),
        'content_file' => __DIR__ . '/pages/jbrowse_setup.php',
    ];
    
    $data = [
        'config' => $config,
        'site' => $site,
        'site_path' => $site_path,
    ];
    
    echo render_display_page(
        $display_config['content_file'],
        $data,
        $display_config['title']
    );
    exit;
}

// JBrowse is installed - continue with dashboard view
// Configure display
$display_config = [
    'title' => 'JBrowse Management - ' . $config->getString('siteTitle'),
    'content_file' => __DIR__ . '/pages/manage_jbrowse.php',
];

// Get all organisms for dropdowns
$organisms_dir = $config->getPath('organism_data');
$organisms = getOrganismsWithAssemblies($organisms_dir);

// Get registered assemblies (those with JSON in metadata/jbrowse2-configs/assemblies/)
$assemblies_meta_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/assemblies';
$registered_assemblies = [];
$assembly_display_names = [];  // [organism][assemblyId] = displayName
if (is_dir($assemblies_meta_dir)) {
    foreach (glob($assemblies_meta_dir . '/*.json') as $file) {
        $asm_data = json_decode(file_get_contents($file), true);
        if ($asm_data && isset($asm_data['organism'], $asm_data['assemblyId'])) {
            $org = $asm_data['organism'];
            $aid = $asm_data['assemblyId'];
            $registered_assemblies[$org][] = $aid;
            if (!empty($asm_data['displayName']) && $asm_data['displayName'] !== $aid) {
                $assembly_display_names[$org][$aid] = $asm_data['displayName'];
            }
        }
    }
}

// Compute assemblies that exist on disk but aren't registered yet
$unregistered_assemblies = [];
foreach ($organisms as $org => $assemblies) {
    foreach ($assemblies as $asm) {
        $registered = isset($registered_assemblies[$org]) && in_array($asm, $registered_assemblies[$org]);
        if (!$registered) {
            $has_genome = file_exists($config->getPath('organism_data') . "/$org/$asm/genome.fa");
            $unregistered_assemblies[] = [
                'organism' => $org,
                'assembly' => $asm,
                'has_genome' => $has_genome,
            ];
        }
    }
}

$registered_count = array_sum(array_map('count', $registered_assemblies));

// For each registered assembly, find gene sets on disk and their JBrowse status
$tracks_meta_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
$site_path       = $config->getPath('site_path');
$gene_sets_info  = [];
foreach ($registered_assemblies as $org => $asms) {
    foreach ($asms as $asm) {
        $asm_dir = "$organisms_dir/$org/$asm";
        if (!is_dir($asm_dir)) continue;
        foreach (glob("$asm_dir/*/" . genes_gff_filename()) ?: [] as $gff_path) {
            $gs        = basename(dirname($gff_path));
            $track_json = "$tracks_meta_dir/$org/$asm/gff/{$gs}_genes.json";
            $gz_path    = "$site_path/data/genomes/$org/$asm/$gs/annotations.gff3.gz";
            $gene_sets_info[] = [
                'organism'      => $org,
                'assembly'      => $asm,
                'gene_set'      => $gs,
                'gff_size'      => filesize($gff_path),
                'gff_prepped'   => file_exists($gz_path) && (file_exists("$gz_path.tbi") || file_exists("$gz_path.csi")),
                'is_registered' => file_exists($track_json),
            ];
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
    $track_files = glob($tracks_dir . '/*/*/*/*json') ?: [];
    $track_stats['total'] = count($track_files);

    $stats_cache_file = dirname($tracks_dir) . '/track_stats_cache.json';
    $newest_mtime = $track_files ? max(array_map('filemtime', $track_files)) : 0;
    $cached = is_file($stats_cache_file) ? json_decode(file_get_contents($stats_cache_file), true) : null;

    if ($cached && ($cached['mtime'] ?? 0) === $newest_mtime && ($cached['total'] ?? -1) === $track_stats['total']) {
        $track_stats = $cached['stats'];
        $track_stats['total'] = count($track_files);
    } else {
        foreach ($track_files as $file) {
            $track = json_decode(file_get_contents($file), true);
            if (!$track) continue;

            $type = $track['type'] ?? 'Unknown';
            $track_stats['by_type'][$type] = ($track_stats['by_type'][$type] ?? 0) + 1;

            $access = $track['metadata']['access_level'] ?? 'PUBLIC';
            $track_stats['by_access'][$access] = ($track_stats['by_access'][$access] ?? 0) + 1;

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
        file_put_contents($stats_cache_file, json_encode(['mtime' => $newest_mtime, 'total' => $track_stats['total'], 'stats' => $track_stats]));
    }
}

// Prepare data for content file
$data = [
    'config' => $config,
    'site' => $site,
    'organisms' => $organisms,
    'track_stats' => $track_stats,
    'registered_assemblies' => $registered_assemblies,
    'registered_count' => $registered_count,
    'unregistered_assemblies' => $unregistered_assemblies,
    'gene_sets_info' => $gene_sets_info,
    'inline_scripts' => [
        'const jbrowseOrganisms = '        . json_encode($organisms)               . ';',
        'const registeredOrganisms = '    . json_encode($registered_assemblies)   . ';',
        'const jbrowseAssemblyNames = '   . json_encode($assembly_display_names)  . ';',
        'const sitePath = "'              . $site                               . '";',
        'const unregisteredAssemblies = ' . json_encode($unregistered_assemblies) . ';',
        'const geneSetsInfo = '           . json_encode($gene_sets_info)        . ';',
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
