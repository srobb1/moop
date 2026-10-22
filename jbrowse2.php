<?php
/**
 * Genome Browser — JBrowse2
 *
 * Selector page for choosing an organism/assembly to view in JBrowse2.
 * If organism + assembly are supplied as GET parameters the selector is
 * skipped and the browser is launched directly (deep-link from gene pages,
 * BLAST results, assembly pages, etc.).
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';
include_once __DIR__ . '/lib/moop_functions.php';
include_once __DIR__ . '/lib/extract_search_helpers.php';
include_once __DIR__ . '/lib/feature_coords.php';

$config        = ConfigManager::getInstance();
$site          = $config->getString('site', 'moop');
$organism_data = $config->getPath('organism_data');

// Build organism → assemblies map from accessible sources
$raw_sources     = flattenSourcesList(getAccessibleGeneSets());
$scope_tree      = [];   // [organism] = [assembly, ...]
$organism_info   = [];   // [organism] = {genus, species, common_name}
$organism_groups = [];   // [organism] = [group, ...]
$all_groups      = [];   // distinct non-PUBLIC groups, sorted
$jb_sources      = [];   // the sources whose assembly is registered with the browser
$assembly_names  = [];   // [organism][assembly] = human-readable name (if set)

// Only assemblies registered with the genome browser: the picker used to offer every assembly
// the viewer may open, and 28 of 96 have no JBrowse definition, so choosing one ended in
// "HTTP 404 fetching .../config.php" (found 2026-10-20, sea lamprey GCF_010993605.1). Same file
// api/jbrowse2/config.php loads; register an assembly (Admin -> JBrowse) to make it appear.
$jb_assemblies_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/assemblies';

foreach ($raw_sources as $src) {
    $org = $src['organism'];
    $asm = $src['genome_accession'] ?: $src['assembly'];
    if (empty($asm)) continue;
    if (!is_file("$jb_assemblies_dir/{$org}_{$asm}.json")) continue;

    if (!isset($scope_tree[$org]))         $scope_tree[$org] = [];
    if (!in_array($asm, $scope_tree[$org])) $scope_tree[$org][] = $asm;

    if (!isset($organism_info[$org])) {
        $info = loadOrganismInfo($org, $organism_data) ?: [];
        $organism_info[$org] = [
            'genus'       => $info['genus']       ?? '',
            'species'     => $info['species']     ?? '',
            'common_name' => $info['common_name'] ?? '',
        ];
    }

    if (!isset($assembly_names[$org][$asm])) {
        $gn = $src['genome_name'] ?? '';
        $assembly_names[$org][$asm] = ($gn && $gn !== $asm) ? $gn : '';
    }

    $jb_sources[] = $src;
}
// The registered assemblies' groups: per organism through the one shared list (sorted, legacy
// PUBLIC skipped), and the distinct set for the filter chips from the same lists.
$organism_groups = moop_organism_groups_map($jb_sources);
$all_groups = array_values(array_unique(array_merge([], ...array_values($organism_groups ?: [[]]))));
ksort($scope_tree);
usort($all_groups, 'moop_group_name_order');

// Deep-link parameters (coming from gene page / BLAST / assembly page)
$dl_organism = '';
$dl_assembly = '';
if (!empty($_GET['organism']) && preg_match('/^[A-Za-z0-9_\-\.]+$/', $_GET['organism']))
    $dl_organism = $_GET['organism'];
if (!empty($_GET['assembly']) && preg_match('/^[A-Za-z0-9_\-\.]+$/', $_GET['assembly']))
    $dl_assembly = $_GET['assembly'];

// A deep link to an assembly the browser cannot show -- not registered with JBrowse, or not
// one this viewer may open (both already left out of $scope_tree) -- gets the site's not-found
// page, worded the same either way, instead of JBrowse's raw "HTTP 404 fetching config.php".
if ($dl_organism !== '' && $dl_assembly !== ''
        && !in_array($dl_assembly, $scope_tree[$dl_organism] ?? [], true)) {
    moop_not_found_page('There is no assembly "' . $dl_assembly . '" in the genome browser that you can open.');
}

$dl_loc = '';
if (!empty($_GET['loc']) && preg_match('/^[\w.\-:]+$/', $_GET['loc']))
    $dl_loc = $_GET['loc'];

$session_tracks_json = '';
if (!empty($_GET['sessionTracks'])) {
    $decoded = json_decode($_GET['sessionTracks'], true);
    if (is_array($decoded)) $session_tracks_json = json_encode($decoded);
}

$session_track_id = '';
// Comma-separated: a linkout may add more than one session track — e.g. the
// primer tool sends the boxes and the arc, which must be different tracks
// because a track shows one display at a time.
if (!empty($_GET['sessionTrackId']) && preg_match('/^[a-zA-Z0-9_,]+$/', $_GET['sessionTrackId']))
    $session_track_id = $_GET['sessionTrackId'];

// Build per-assembly metadata: gene tracks + first gene loc (or first scaffold fallback).
// Used by JS to pre-select gene tracks and set an initial loc on launch.
$asm_config_dir  = $config->getPath('metadata_path') . '/jbrowse2-configs/assemblies/';
$assembly_meta   = [];  // ["{org}_{asm}"] = ['geneTracks' => [...], 'firstLoc' => '...']
$primary_gene_tracks = [];

foreach ($scope_tree as $org => $assemblies) {
    foreach ($assemblies as $asm) {
        $json_path = $asm_config_dir . $org . '_' . $asm . '.json';
        if (!file_exists($json_path)) continue;

        $def         = loadJsonFile($json_path, []);
        $gene_tracks = $def['primaryGeneTracks'] ?? [];

        // First gene loc: the first feature of the first gene set that has coordinates
        $first_loc = '';
        $coords_files = glob($organism_data . '/' . $org . '/' . $asm . '/*/feature_coords.tsv');
        if ($coords_files) {
            sort($coords_files);
            $r = moop_feature_coords_first(dirname($coords_files[0]));
            if ($r) $first_loc = "{$r['chr']}:{$r['start']}..{$r['end']}";
        }

        // Fallback: first sequence name from the .fai index
        if (!$first_loc) {
            $fai_uri = $def['sequence']['adapter']['faiLocation']['uri'] ?? '';
            if ($fai_uri) {
                $fai_path = $_SERVER['DOCUMENT_ROOT'] . $fai_uri;
                if (file_exists($fai_path) && ($fh = fopen($fai_path, 'r'))) {
                    $line = fgets($fh);
                    fclose($fh);
                    if ($line) $first_loc = explode("\t", trim($line))[0];
                }
            }
        }

        $assembly_meta[$org . '_' . $asm] = [
            'geneTracks' => array_values($gene_tracks),
            'firstLoc'   => $first_loc,
        ];

        // Also populate deep-link gene tracks if this is the requested assembly
        if ($org === $dl_organism && $asm === $dl_assembly)
            $primary_gene_tracks = $gene_tracks;
    }
}

echo render_display_page(
    __DIR__ . '/tools/pages/jbrowse2.php',
    [
        'scope_tree'          => $scope_tree,
        'organism_info'       => $organism_info,
        'organism_groups'     => $organism_groups,
        'all_groups'          => $all_groups,
        'site'                => $site,
        'dl_organism'         => $dl_organism,
        'dl_assembly'         => $dl_assembly,
        'page_script'         => ["/$site/js/modules/jbrowse2-browser.js"],
        'page_styles'         => ["/$site/css/jbrowse2.css"],
        'inline_scripts'      => [
            "const moopSite        = " . json_encode('/' . $site)                        . ";",
            "const jbDlOrganism    = " . json_encode($dl_organism)                      . ";",
            "const jbDlAssembly    = " . json_encode($dl_assembly)                      . ";",
            "const jbDlLoc         = " . json_encode($dl_loc)                           . ";",
            "const jbGeneTracks    = " . json_encode(array_values($primary_gene_tracks)) . ";",
            "const jbSessionTracks = " . json_encode($session_tracks_json)              . ";",
            "const jbSessionTrackId= " . json_encode($session_track_id)                 . ";",
            "const jbAssemblyMeta  = " . json_encode($assembly_meta)                    . ";",
            "const jbAssemblyNames = " . json_encode($assembly_names)                  . ";",
        ],
    ],
    'Genome Browser'
);
?>
