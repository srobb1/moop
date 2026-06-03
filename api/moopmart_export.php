<?php
/**
 * MOOPmart Export — Stream TSV or FASTA download for a filtered feature set.
 *
 * POST parameters:
 *   sources[]            - "organism|assembly|gene_set" strings (empty = all accessible)
 *   feature_types[]      - feature types to include (empty = all)
 *   ann_criteria_src[]   - annotation source name per criterion (empty = any)
 *   ann_criteria_acc[]   - exact accession per criterion (empty = skip)
 *   ann_criteria_kw[]    - keyword per criterion (empty = skip); criteria are AND'd
 *   coord_chr / coord_start / coord_end - coordinate range filter
 *   output_format        - 'tsv' | 'fasta'  (default: tsv)
 *   fasta_mode           - 'gene'|'upstream'|'downstream'|'exons'|'protein'|'transcript'|'cds'
 *   flank_bp             - int, 1–100000 (upstream/downstream only; default: 500)
 *   annotation_columns[] - annotation source names to include as TSV columns (empty = all)
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/functions_database.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';

csrf_protect();

// --- Input validation ---
$selected_raw = $_POST['sources'] ?? [];
if (!is_array($selected_raw)) $selected_raw = [$selected_raw];

$output_format  = in_array($_POST['output_format'] ?? '', ['tsv', 'fasta']) ? $_POST['output_format'] : 'tsv';
$ann_format     = ($_POST['ann_format'] ?? 'long') === 'wide' ? 'wide' : 'long';
$fasta_mode     = $_POST['fasta_mode'] ?? 'gene';
$flank_bp       = max(1, min(100000, (int)($_POST['flank_bp'] ?? 500)));
$fasta_preview  = !empty($_POST['fasta_preview']);

$valid_fasta_modes = ['gene', 'upstream', 'downstream', 'exons', 'protein', 'transcript', 'cds'];
if (!in_array($fasta_mode, $valid_fasta_modes)) $fasta_mode = 'gene';

// --- Resolve accessible sources ---
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
    http_response_code(400);
    die('No accessible sources selected.');
}

// Raw input IDs (resolved per-organism below)
$raw_input_ids = array_values(array_filter(array_map('trim', (array)($_POST['feature_ids'] ?? []))));

// --- Build base filters (no feature_ids — those are resolved per-organism) ---
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

$global_filter_reason        = buildMoopmartFilterReason($filters, $coord_filter);
$annotation_columns_selected = array_filter($_POST['annotation_columns'] ?? []);

$organism_data  = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();

// --- Group selected sources by organism ---
$by_organism = [];
foreach ($selected as $src) {
    $org = $src['organism'];
    $by_organism[$org]['db_path'] = "$organism_data/$org/organism.sqlite";
    $by_organism[$org]['sources'][] = $src;
}

// --- Collect all matching features with coordinates ---
$all_features     = [];
$sources_by_gs_id = [];

foreach ($by_organism as $org => $org_data) {
    $db = $org_data['db_path'];
    if (!file_exists($db)) continue;
    $sources      = $org_data['sources'];
    $gene_set_ids = array_values(array_filter(array_column($sources, 'gene_set_id')));
    if (empty($gene_set_ids)) continue;

    $id_reasons  = [];
    $org_filters = $filters;
    if (!empty($raw_input_ids)) {
        $id_reasons = moopmartResolveInputIds($raw_input_ids, $db, $gene_set_ids);
        if (empty($id_reasons)) continue;
        $org_filters['feature_ids'] = array_keys($id_reasons);
    }

    $features = moopmartQueryFeatures($gene_set_ids, $db, $org_filters);
    if (empty($features)) continue;

    $uniquenames_by_gs = [];
    foreach ($features as $f) {
        $uniquenames_by_gs[$f['gene_set_id']][] = $f['uniquename'];
    }

    $coords_by_gs = [];
    foreach ($sources as $src) {
        $gs_id  = $src['gene_set_id'];
        $gs_key = $db . '|' . $gs_id;
        if ($gs_id && !isset($coords_by_gs[$gs_id])) {
            $coords_by_gs[$gs_id]      = moopmartLoadGeneCoords($src['path'], $uniquenames_by_gs[$gs_id] ?? []);
            $sources_by_gs_id[$gs_key] = $src;
        }
    }

    foreach (moopmartAttachCoords($features, $coords_by_gs, $coord_filter) as $f) {
        $f['organism_dir'] = $org;
        $f['db_path']      = $db;
        $f['gs_key']       = $db . '|' . $f['gene_set_id'];
        if (!empty($id_reasons)) {
            $reason = $id_reasons[$f['uniquename']] ?? '';
            if ($global_filter_reason) $reason .= ($reason ? ' + ' : '') . $global_filter_reason;
        } else {
            $reason = $global_filter_reason;
        }
        $f['match_reason'] = $reason;
        $all_features[]    = $f;
    }
}

if (empty($all_features)) {
    http_response_code(404);
    die('No features matched the selected filters.');
}

$date     = date('Ymd_His');
$ext      = $output_format === 'fasta' ? 'fa' : 'tsv';
$filename = "moopmart_{$output_format}_{$date}.{$ext}";

set_time_limit(0);
while (ob_get_level()) ob_end_clean();

// =============================================================
// TSV EXPORT
// =============================================================
if ($output_format === 'tsv') {

    // Determine annotation source columns — empty selection = no annotation columns
    $source_cols = array_values($annotation_columns_selected);
    sort($source_cols);

    // Send headers immediately so nginx doesn't time out waiting for the first byte
    header('Content-Type: text/tab-separated-values; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no');  // disable nginx proxy buffering

    $out = fopen('php://output', 'w');

    // Strip embedded newlines/tabs that would break TSV parsing in Excel
    $clean = fn($s) => str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string)$s);

    // Feature column map: UI key → [header label, value extractor]
    $feat_col_map = [
        'organism'         => ['organism',         fn($f) => $f['organism_dir']],
        'assembly'         => ['assembly',         fn($f) => $f['genome_accession']],
        'gene_set'         => ['gene_set',         fn($f) => $f['gene_set_name']],
        'gene_id'          => ['gene_id',          fn($f) => $f['uniquename']],
        'gene_name'        => ['gene_name',        fn($f) => $clean($f['name'] ?? '')],
        'gene_description' => ['gene_description', fn($f) => $clean($f['description'] ?? '')],
        'mrna_id'          => ['mrna_id',          fn($f) => $f['mrna_id'] ?? ''],
        'protein_id'       => ['protein_id',       fn($f) => $f['protein_id'] ?? ''],
        'chr'              => ['chr',              fn($f) => $f['chr'] ?? ''],
        'start'            => ['start',            fn($f) => $f['start'] ?? ''],
        'stop'             => ['stop',             fn($f) => $f['end'] ?? ''],
        'strand'           => ['strand',           fn($f) => $f['strand'] ?? ''],
        'why_included'     => ['why_included',     fn($f) => $f['match_reason'] ?? ''],
    ];

    $requested_feat = array_values(array_filter($_POST['feature_columns'] ?? []));
    $active_feat    = empty($requested_feat)
        ? array_keys($feat_col_map)
        : array_values(array_filter($requested_feat, fn($k) => isset($feat_col_map[$k])));

    // Annotation sub-column selection (ann_id / ann_description; ann_type / ann_source = no data field)
    $requested_ann    = array_flip(array_values(array_filter($_POST['ann_columns'] ?? [])));
    $ann_incl_id      = empty($requested_ann) || isset($requested_ann['ann_id']);
    $ann_incl_desc    = empty($requested_ann) || isset($requested_ann['ann_description']);
    $ann_incl_src     = empty($requested_ann) || isset($requested_ann['ann_source']);

    // Build header row
    $feature_headers = array_map(fn($k) => $feat_col_map[$k][0], $active_feat);
    if ($ann_format === 'long') {
        $ann_sub_headers = [];
        if ($ann_incl_src)  $ann_sub_headers[] = 'annotation_source';
        if ($ann_incl_id)   $ann_sub_headers[] = 'annotation_id';
        if ($ann_incl_desc) $ann_sub_headers[] = 'annotation_description';
        $headers = array_merge($feature_headers, $ann_sub_headers);
    } else {
        $headers = $feature_headers;
        foreach ($source_cols as $s) {
            if ($ann_incl_id)   $headers[] = 'ID:' . $s;
            if ($ann_incl_desc) $headers[] = 'Description:' . $s;
        }
    }
    fputcsv($out, $headers, "\t");
    flush();

    // Group features by DB so annotation fetches hit one DB at a time.
    // Process in chunks of 500 to keep annotation results memory-bounded;
    // flush after each chunk so the browser receives data incrementally.
    $by_db = [];
    foreach ($all_features as $f) {
        $by_db[$f['db_path']][] = $f;
    }

    foreach ($by_db as $db_path => $db_features) {
        foreach (array_chunk($db_features, 500) as $chunk) {
            // Expand gene rows to one row per mRNA before streaming
            $expanded   = moopmartExpandToMrnaRows($chunk, $db_path);
            $fids       = array_column($chunk, 'feature_id'); // gene-level fids for annotation lookup
            $chunk_anns = moopmartGetAnnotationsForFeatures($fids, $db_path);

            foreach ($expanded as $f) {
                $base     = array_map(fn($k) => ($feat_col_map[$k][1])($f), $active_feat);
                $fid_anns = $chunk_anns[$f['feature_id']] ?? []; // feature_id is still the gene's

                if ($ann_format === 'long') {
                    $emitted = false;
                    foreach ($source_cols as $src_name) {
                        foreach ($fid_anns[$src_name] ?? [] as $entry) {
                            $ann_vals = [];
                            if ($ann_incl_src)  $ann_vals[] = $src_name;
                            if ($ann_incl_id)   $ann_vals[] = $entry['accession'];
                            if ($ann_incl_desc) $ann_vals[] = $clean($entry['description'] ?? '');
                            fputcsv($out, array_merge($base, $ann_vals), "\t");
                            $emitted = true;
                        }
                    }
                    // Emit one row even if no annotations matched
                    if (!$emitted) {
                        fputcsv($out, array_merge($base, array_fill(0, count($ann_sub_headers), '')), "\t");
                    }
                } else {
                    $row = $base;
                    foreach ($source_cols as $src_name) {
                        $entries = $fid_anns[$src_name] ?? [];
                        if ($ann_incl_id)   $row[] = implode('; ', array_map(fn($e) => $e['accession'], $entries));
                        if ($ann_incl_desc) $row[] = implode('; ', array_map(fn($e) => $clean($e['description'] ?? ''), $entries));
                    }
                    fputcsv($out, $row, "\t");
                }
            }
            flush();
        }
    }
    fclose($out);

// =============================================================
// FASTA EXPORT
// =============================================================
} else {

    header('Content-Type: text/plain; charset=UTF-8');
    if (!$fasta_preview) {
        header("Content-Disposition: attachment; filename=\"$filename\"");
    }
    header('Cache-Control: no-cache, no-store');

    $out = fopen('php://output', 'w');

    // Limit to first 10 for preview
    $fasta_features = $fasta_preview ? array_slice($all_features, 0, 10) : $all_features;

    // Group features by db+gene_set_id so each organism's features hit its own genome files
    $by_gs = [];
    foreach ($fasta_features as $f) {
        $by_gs[$f['gs_key']][] = $f;
    }

    $genomic_modes = ['gene', 'upstream', 'downstream', 'exons'];

    foreach ($by_gs as $gs_key => $gs_features) {
        $src = $sources_by_gs_id[$gs_key] ?? null;
        if (!$src) continue;

        $org          = $src['organism'];
        $assembly     = $src['assembly'];
        $gs_name      = $src['gene_set'] ?? '';
        $assembly_dir = "$organism_data/$org/$assembly";
        $gs_path      = "$assembly_dir/$gs_name";
        $gff_path     = "$gs_path/genomic.gff";

        if (in_array($fasta_mode, $genomic_modes)) {
            moopmartStreamGenomicFasta($gs_features, $assembly_dir, $gff_path, $fasta_mode, $flank_bp, $out);
        } else {
            // Pre-built FASTA files: protein, transcript, cds
            // $gs_features is gene-level; expand to mRNA/CDS/protein children for extraction
            $gene_uniquenames = array_column($gs_features, 'uniquename');
            $db_path  = "$organism_data/$org/organism.sqlite";
            $gs_id_int = (int)($gs_features[0]['gene_set_id'] ?? 0);
            $typed_ids = buildTypedIdsForGenes($gene_uniquenames, $gs_id_int, $db_path);
            $extract_result = extractSequencesForAllTypes($gs_path, $typed_ids, $sequence_types, $org, $assembly);
            if (isset($extract_result['content'][$fasta_mode])) {
                fwrite($out, $extract_result['content'][$fasta_mode]);
            }
        }
    }
    fclose($out);
}
