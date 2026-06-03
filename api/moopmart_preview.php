<?php
/**
 * MOOPmart Preview — Return feature count + first 100 rows for current filters.
 *
 * POST parameters: same as moopmart_export.php (sources[], feature_types[],
 * feature_id, gene_name, gene_description,
 * ann_criteria_src[], ann_criteria_acc[], ann_criteria_kw[],
 * coord_chr, coord_start, coord_end)
 *
 * Returns JSON: {
 *   count: N,
 *   rows:  [{uniquename, name, description, type, organism_dir,
 *            genome_accession, gene_set_name, chr, start, end, strand}, ...],
 *   by_organism: [{organism, count}, ...]
 * }
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/functions_database.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';

header('Content-Type: application/json');
csrf_protect();

$selected_raw = $_POST['sources'] ?? [];
if (!is_array($selected_raw)) $selected_raw = [$selected_raw];

$all_accessible    = flattenSourcesList(getAccessibleAssemblies());
$accessible_by_key = [];
foreach ($all_accessible as $src) {
    $key = $src['organism'] . '|' . $src['assembly'] . '|' . ($src['gene_set'] ?? '');
    $accessible_by_key[$key] = $src;
}

$selected = empty($selected_raw)
    ? $all_accessible
    : array_values(array_filter(array_map(fn($k) => $accessible_by_key[$k] ?? null, $selected_raw)));

if (empty($selected)) {
    echo json_encode(['count' => 0, 'rows' => [], 'by_organism' => []]);
    exit;
}

// Build filters
$filters = [];
$types = array_filter($_POST['feature_types'] ?? []);
if (!empty($types))                             $filters['feature_types']        = array_values($types);
if (!empty($_POST['feature_id']))               $filters['feature_id']           = trim($_POST['feature_id']);
if (!empty($_POST['gene_name']))                $filters['gene_name']            = trim($_POST['gene_name']);
if (!empty($_POST['gene_description']))         $filters['gene_description']     = trim($_POST['gene_description']);
$_crit_srcs = $_POST['ann_criteria_src'] ?? [];
$_crit_accs = $_POST['ann_criteria_acc'] ?? [];
$_crit_kws  = $_POST['ann_criteria_kw']  ?? [];
$_criteria  = [];
foreach (array_keys((array)$_crit_srcs) as $_i) {
    $src = trim($_crit_srcs[$_i] ?? '');
    $acc = trim($_crit_accs[$_i] ?? '');
    $kw  = trim($_crit_kws[$_i]  ?? '');
    if ($src !== '' || $acc !== '' || $kw !== '') {
        $_criteria[] = ['src' => $src, 'acc' => $acc, 'kw' => $kw];
    }
}
if (!empty($_criteria)) $filters['annotation_criteria'] = $_criteria;

$coord_filter = [];
if (!empty($_POST['coord_chr']))   $coord_filter['chr']   = trim($_POST['coord_chr']);
if (!empty($_POST['coord_start'])) $coord_filter['start'] = (int)$_POST['coord_start'];
if (!empty($_POST['coord_end']))   $coord_filter['end']   = (int)$_POST['coord_end'];

$organism_data = $config->getPath('organism_data');

// Group selected sources by organism
$by_organism = [];
foreach ($selected as $src) {
    $org = $src['organism'];
    $by_organism[$org]['db_path'] = "$organism_data/$org/organism.sqlite";
    $by_organism[$org]['sources'][] = $src;
}

// Collect all matching features with coordinates (same logic as export)
$all_features = [];
$by_org_counts = [];

foreach ($by_organism as $org => $org_data) {
    $db = $org_data['db_path'];
    if (!file_exists($db)) continue;
    $gene_set_ids = array_values(array_filter(array_column($org_data['sources'], 'gene_set_id')));
    if (empty($gene_set_ids)) continue;

    $features = moopmartQueryFeatures($gene_set_ids, $db, $filters);
    if (empty($features)) {
        $by_org_counts[] = ['organism' => $org, 'count' => 0];
        continue;
    }

    // Index queried uniquenames by gene_set_id so coord loading is targeted
    // (avoids loading the entire TSV; also picks up mRNA-level coords when
    //  the query returns mRNA features, e.g. when searching by annotation).
    $uniquenames_by_gs = [];
    foreach ($features as $f) {
        $uniquenames_by_gs[$f['gene_set_id']][] = $f['uniquename'];
    }

    $coords_by_gs = [];
    foreach ($org_data['sources'] as $src) {
        $gs_id = $src['gene_set_id'];
        if ($gs_id && !isset($coords_by_gs[$gs_id])) {
            $coords_by_gs[$gs_id] = moopmartLoadGeneCoords($src['path'], $uniquenames_by_gs[$gs_id] ?? []);
        }
    }

    $matched = moopmartAttachCoords($features, $coords_by_gs, $coord_filter);
    foreach ($matched as $f) {
        $f['organism_dir'] = $org;
        $all_features[]    = $f;
    }
    $by_org_counts[] = ['organism' => $org, 'count' => count($matched)];
}

$total   = count($all_features);
$preview = array_slice($all_features, 0, 100);

$rows = array_map(fn($f) => [
    'uniquename'       => $f['uniquename'],
    'name'             => $f['name']             ?? '',
    'description'      => $f['description']      ?? '',
    'type'             => $f['type'],
    'organism_dir'     => $f['organism_dir'],
    'genome_accession' => $f['genome_accession'],
    'gene_set_name'    => $f['gene_set_name'],
    'chr'              => $f['chr']    ?? '',
    'start'            => $f['start']  ?? '',
    'end'              => $f['end']    ?? '',
    'strand'           => $f['strand'] ?? '',
], $preview);

echo json_encode([
    'count'       => $total,
    'rows'        => $rows,
    'by_organism' => $by_org_counts,
]);
