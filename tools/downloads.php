<?php
/**
 * DOWNLOADS - File Download Tool
 *
 * Allows users to browse and download files (FASTA, GFF, and custom files)
 * for organisms/assemblies they have access to.
 * Context-aware: filters by organism, assembly, or group when provided.
 */

ob_start();
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';

$siteTitle = $config->getString('siteTitle');

ob_end_clean();

// Parse context parameters
$context          = parseContextParameters();
$context_organism = $context['organism'];
$context_assembly = $context['assembly'];
$context_group    = $context['group'];
$display_name     = $context['display_name'];

// Extensions excluded from listing (BLAST DB index files + internal system files)
$excluded_exts = array_flip([
    // BLAST nucleotide DB
    'ndb', 'nhr', 'nin', 'njs', 'nog', 'nos', 'not', 'nsq', 'ntf', 'nto',
    // BLAST protein DB
    'pdb', 'phr', 'pin', 'pjs', 'pog', 'pos', 'pot', 'psq', 'ptf', 'pto',
    // Internal system files
    'sqlite', 'json',
]);

function _downloads_format_size(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576,    1) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024,       1) . ' KB';
    return $bytes . ' B';
}

// Get accessible assemblies
$sources_by_group   = getAccessibleAssemblies();
$accessible_sources = flattenSourcesList($sources_by_group);

// Apply context filters
if (!empty($context_organism)) {
    $accessible_sources = array_values(array_filter(
        $accessible_sources, fn($s) => $s['organism'] === $context_organism
    ));
}
if (!empty($context_assembly)) {
    $accessible_sources = array_values(array_filter(
        $accessible_sources, fn($s) => $s['assembly'] === $context_assembly
    ));
}
if (!empty($context_group)) {
    $accessible_sources = array_values(array_filter(
        $accessible_sources, fn($s) => in_array($context_group, $s['groups'] ?? [])
    ));
}

// De-duplicate (same assembly can appear under multiple groups)
$seen           = [];
$unique_sources = [];
foreach ($accessible_sources as $source) {
    $key = $source['organism'] . "\0" . $source['assembly'];
    if (!isset($seen[$key])) {
        $seen[$key]       = true;
        $unique_sources[] = $source;
    }
}

// Sort: priority extensions first, then alphabetical within each assembly
$ext_priority = array_flip(['fa', 'fasta', 'faa', 'gff', 'gff3', 'gtf', 'bed', 'vcf']);

// Build download tree: $download_tree[organism][assembly] = ['files' => [...], 'file_count' => N, 'total_label' => '...']
$download_tree = [];

foreach ($unique_sources as $source) {
    $organism      = $source['organism'];
    $assembly      = $source['assembly'];
    $assembly_path = $source['path'];

    if (!is_dir($assembly_path)) continue;

    $files = [];
    foreach (scandir($assembly_path) as $fname) {
        if ($fname === '.' || $fname === '..') continue;
        $fpath = "$assembly_path/$fname";
        if (!is_file($fpath)) continue;
        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
        if (isset($excluded_exts[$ext])) continue;

        $size    = (int) filesize($fpath);
        $files[] = [
            'name'       => $fname,
            'size'       => $size,
            'size_label' => _downloads_format_size($size),
        ];
    }

    if (empty($files)) continue;

    usort($files, function ($a, $b) use ($ext_priority) {
        $ea = strtolower(pathinfo($a['name'], PATHINFO_EXTENSION));
        $eb = strtolower(pathinfo($b['name'], PATHINFO_EXTENSION));
        $pa = $ext_priority[$ea] ?? 999;
        $pb = $ext_priority[$eb] ?? 999;
        return $pa !== $pb ? $pa - $pb : strcmp($a['name'], $b['name']);
    });

    $total_size = array_sum(array_column($files, 'size'));

    if (!isset($download_tree[$organism])) {
        $download_tree[$organism] = [];
    }
    $download_tree[$organism][$assembly] = [
        'files'       => $files,
        'file_count'  => count($files),
        'total_label' => _downloads_format_size($total_size),
    ];
}

ksort($download_tree);

// Build page title
$page_title = 'Downloads';
if (!empty($display_name)) {
    $page_title .= ' — ' . $display_name;
} elseif (!empty($context_organism)) {
    $page_title .= ' — ' . str_replace('_', ' ', $context_organism);
} elseif (!empty($context_group)) {
    $page_title .= ' — ' . $context_group;
}

$display_config = [
    'title'          => $page_title . ' - ' . htmlspecialchars($siteTitle),
    'content_file'   => __DIR__ . '/pages/downloads.php',
    'page_script'    => ['/' . $site . '/js/modules/downloads.js'],
    'inline_scripts' => [
        "const sitePath = '/$site';",
    ],
];

$data = [
    'site'             => $site,
    'siteTitle'        => $siteTitle,
    'download_tree'    => $download_tree,
    'context_organism' => $context_organism,
    'context_assembly' => $context_assembly,
    'context_group'    => $context_group,
    'display_name'     => $display_name,
    'page_title'       => $page_title,
];

include_once __DIR__ . '/display-template.php';
