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

// Raw input IDs (resolved per-organism below)
$raw_input_ids = array_values(array_filter(array_map('trim', (array)($_POST['feature_ids'] ?? []))));

// Build base filters (no feature_ids — those are resolved per-organism)
$filters = [];
$types = array_filter($_POST['feature_types'] ?? []);
if (!empty($types))                             $filters['feature_types']        = array_values($types);
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

// Uniform reason string for non-ID filters (same for every matched row)
$global_filter_reason = buildMoopmartFilterReason($filters, $coord_filter);

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

    // Resolve input IDs for this organism and build per-gene reason map
    $id_reasons  = [];
    $org_filters = $filters;
    if (!empty($raw_input_ids)) {
        $id_reasons = moopmartResolveInputIds($raw_input_ids, $db, $gene_set_ids);
        if (empty($id_reasons)) {
            $by_org_counts[] = ['organism' => $org, 'count' => 0];
            continue;
        }
        $org_filters['feature_ids'] = array_keys($id_reasons);
    }

    $features = moopmartQueryFeatures($gene_set_ids, $db, $org_filters);
    if (empty($features)) {
        $by_org_counts[] = ['organism' => $org, 'count' => 0];
        continue;
    }

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
        $f['db_path']      = $db;
        // Build "why included" reason: ID resolution takes priority; fall back to filter description
        if (!empty($id_reasons)) {
            $reason = $id_reasons[$f['uniquename']] ?? '';
            if ($global_filter_reason) $reason .= ($reason ? ' + ' : '') . $global_filter_reason;
        } else {
            $reason = $global_filter_reason;
        }
        $f['match_reason'] = $reason;
        $all_features[]    = $f;
    }
    $by_org_counts[] = ['organism' => $org, 'count' => count($matched)];
}

// Expand gene rows to one row per mRNA, grouped by DB
$gene_count   = count($all_features);
$mrna_features = [];
$by_db_expand  = [];
foreach ($all_features as $f) {
    $by_db_expand[$f['db_path']][] = $f;
}
foreach ($by_db_expand as $db_path => $db_feats) {
    foreach (moopmartExpandToMrnaRows($db_feats, $db_path) as $row) {
        $mrna_features[] = $row;
    }
}

$total   = count($mrna_features);
$preview = array_slice($mrna_features, 0, 100);

// Annotation columns for preview (always wide — one mRNA row, annotations semicolon-joined)
$annotation_columns_selected = array_values(array_filter($_POST['annotation_columns'] ?? []));
$requested_ann = array_flip(array_values(array_filter($_POST['ann_columns'] ?? [])));
$ann_incl_id   = empty($requested_ann) || isset($requested_ann['ann_id']);
$ann_incl_desc = empty($requested_ann) || isset($requested_ann['ann_description']);
$clean_prev    = fn($s) => str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string)$s);

$ann_col_headers   = [];
$ann_by_uniquename = [];

if (!empty($annotation_columns_selected)) {
    foreach ($annotation_columns_selected as $src) {
        if ($ann_incl_id)   $ann_col_headers[] = 'ID:' . $src;
        if ($ann_incl_desc) $ann_col_headers[] = 'Description:' . $src;
    }

    // Deduplicate by gene feature_id before fetching annotations
    $seen_fids     = [];
    $preview_by_db = [];
    foreach ($preview as $f) {
        if (empty($f['db_path']) || isset($seen_fids[$f['feature_id']])) continue;
        $seen_fids[$f['feature_id']]         = true;
        $preview_by_db[$f['db_path']][]      = $f;
    }
    foreach ($preview_by_db as $db_path => $db_feats) {
        $chunk_anns = moopmartGetAnnotationsForFeatures(array_column($db_feats, 'feature_id'), $db_path);
        foreach ($db_feats as $f) {
            $ann_by_uniquename[$f['uniquename']] = $chunk_anns[$f['feature_id']] ?? [];
        }
    }
}

$rows = array_map(function ($f) use ($annotation_columns_selected, $ann_by_uniquename, $ann_incl_id, $ann_incl_desc, $clean_prev) {
    $row = [
        'uniquename'       => $f['uniquename'],
        'name'             => $f['name']          ?? '',
        'description'      => $f['description']   ?? '',
        'organism_dir'     => $f['organism_dir'],
        'genome_accession' => $f['genome_accession'],
        'gene_set_name'    => $f['gene_set_name'],
        'mrna_id'          => $f['mrna_id']       ?? '',
        'protein_id'       => $f['protein_id']    ?? '',
        'chr'              => $f['chr']            ?? '',
        'start'            => $f['start']          ?? '',
        'end'              => $f['end']            ?? '',
        'strand'           => $f['strand']         ?? '',
        'match_reason'     => $f['match_reason']   ?? '',
    ];
    foreach ($annotation_columns_selected as $src) {
        $entries = $ann_by_uniquename[$f['uniquename']][$src] ?? [];
        if ($ann_incl_id)   $row['ID:' . $src]          = implode('; ', array_map(fn($e) => $e['accession'], $entries));
        if ($ann_incl_desc) $row['Description:' . $src] = implode('; ', array_map(fn($e) => $clean_prev($e['description'] ?? ''), $entries));
    }
    return $row;
}, $preview);

echo json_encode([
    'count'           => $total,
    'gene_count'      => $gene_count,
    'rows'            => $rows,
    'ann_col_headers' => $ann_col_headers,
    'by_organism'     => $by_org_counts,
]);
