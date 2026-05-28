<?php
/**
 * MOOPmart Export — Stream TSV or FASTA download for a filtered feature set.
 *
 * POST parameters:
 *   sources[]            - "organism|assembly|gene_set" strings (empty = all accessible)
 *   feature_types[]      - feature types to include (empty = all)
 *   annotation_source    - require annotation from this source name
 *   annotation_accession - require this exact accession
 *   annotation_keyword   - LIKE match on annotation description
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

$output_format = in_array($_POST['output_format'] ?? '', ['tsv', 'fasta']) ? $_POST['output_format'] : 'tsv';
$ann_format    = ($_POST['ann_format'] ?? 'wide') === 'long' ? 'long' : 'wide';
$fasta_mode    = $_POST['fasta_mode'] ?? 'gene';
$flank_bp      = max(1, min(100000, (int)($_POST['flank_bp'] ?? 500)));

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

// --- Build filters ---
$filters = [];
$types = array_filter($_POST['feature_types'] ?? []);
if (!empty($types))                             $filters['feature_types']        = array_values($types);
if (!empty($_POST['feature_id']))               $filters['feature_id']           = trim($_POST['feature_id']);
if (!empty($_POST['gene_name']))                $filters['gene_name']            = trim($_POST['gene_name']);
if (!empty($_POST['gene_description']))         $filters['gene_description']     = trim($_POST['gene_description']);
if (!empty($_POST['annotation_source']))        $filters['annotation_source']    = trim($_POST['annotation_source']);
if (!empty($_POST['annotation_accession']))     $filters['annotation_accession'] = trim($_POST['annotation_accession']);
if (!empty($_POST['annotation_keyword']))       $filters['annotation_keyword']   = trim($_POST['annotation_keyword']);

$coord_filter = [];
if (!empty($_POST['coord_chr']))   $coord_filter['chr']   = trim($_POST['coord_chr']);
if (!empty($_POST['coord_start'])) $coord_filter['start'] = (int)$_POST['coord_start'];
if (!empty($_POST['coord_end']))   $coord_filter['end']   = (int)$_POST['coord_end'];

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

    $features = moopmartQueryFeatures($gene_set_ids, $db, $filters);
    if (empty($features)) continue;

    $uniquenames_by_gs = [];
    foreach ($features as $f) {
        $uniquenames_by_gs[$f['gene_set_id']][] = $f['uniquename'];
    }

    $coords_by_gs = [];
    foreach ($sources as $src) {
        $gs_id = $src['gene_set_id'];
        if ($gs_id && !isset($coords_by_gs[$gs_id])) {
            $coords_by_gs[$gs_id]     = moopmartLoadGeneCoords($src['path'], $uniquenames_by_gs[$gs_id] ?? []);
            $sources_by_gs_id[$gs_id] = $src;
        }
    }

    foreach (moopmartAttachCoords($features, $coords_by_gs, $coord_filter) as $f) {
        $f['organism_dir'] = $org;
        $f['db_path']      = $db;
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

    // Header row
    $feature_headers = ['organism', 'assembly', 'gene_set', 'gene_id', 'gene_name',
                        'description', 'type', 'chr', 'start', 'end', 'strand'];
    if ($ann_format === 'long') {
        $headers = array_merge($feature_headers, ['annotation_source', 'annotation_id', 'annotation_description']);
    } else {
        $headers = $feature_headers;
        foreach ($source_cols as $s) {
            $headers[] = 'ID:' . $s;
            $headers[] = 'Description:' . $s;
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
            $fids       = array_column($chunk, 'feature_id');
            $chunk_anns = moopmartGetAnnotationsForFeatures($fids, $db_path);

            foreach ($chunk as $f) {
                $base = [
                    $f['organism_dir'],
                    $f['genome_accession'],
                    $f['gene_set_name'],
                    $f['uniquename'],
                    $clean($f['name']        ?? ''),
                    $clean($f['description'] ?? ''),
                    $f['type'],
                    $f['chr']    ?? '',
                    $f['start']  ?? '',
                    $f['end']    ?? '',
                    $f['strand'] ?? '',
                ];
                $fid_anns = $chunk_anns[$f['feature_id']] ?? [];

                if ($ann_format === 'long') {
                    $emitted = false;
                    foreach ($source_cols as $src_name) {
                        foreach ($fid_anns[$src_name] ?? [] as $entry) {
                            fputcsv($out, array_merge($base, [
                                $src_name,
                                $entry['accession'],
                                $clean($entry['description'] ?? ''),
                            ]), "\t");
                            $emitted = true;
                        }
                    }
                    // Emit one row even if no annotations matched
                    if (!$emitted) {
                        fputcsv($out, array_merge($base, ['', '', '']), "\t");
                    }
                } else {
                    $row = $base;
                    foreach ($source_cols as $src_name) {
                        $entries      = $fid_anns[$src_name] ?? [];
                        $accessions   = array_map(fn($e) => $e['accession'], $entries);
                        $descriptions = array_map(fn($e) => $clean($e['description'] ?? ''), $entries);
                        $row[] = implode('; ', $accessions);
                        $row[] = implode('; ', $descriptions);
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
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: no-cache, no-store');

    $out = fopen('php://output', 'w');

    // Group features by gene_set_id for per-file operations
    $by_gs = [];
    foreach ($all_features as $f) {
        $by_gs[$f['gene_set_id']][] = $f;
    }

    $genomic_modes = ['gene', 'upstream', 'downstream', 'exons'];

    foreach ($by_gs as $gs_id => $gs_features) {
        $src = $sources_by_gs_id[$gs_id] ?? null;
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
            $typed_ids = buildTypedIdsForGenes($gene_uniquenames, (int)$gs_id, $db_path);
            $extract_result = extractSequencesForAllTypes($gs_path, $typed_ids, $sequence_types, $org, $assembly);
            if (isset($extract_result['content'][$fasta_mode])) {
                fwrite($out, $extract_result['content'][$fasta_mode]);
            }
        }
    }
    fclose($out);
}
