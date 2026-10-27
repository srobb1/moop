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
 *   annotation_columns[] - annotation source names to include as TSV columns (empty = none;
 *                          the page has the user pick them)
 */

include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/functions_database.php';
include_once __DIR__ . '/../lib/extract_search_helpers.php';
include_once __DIR__ . '/../lib/blast_functions.php';
include_once __DIR__ . '/../lib/moopmart_functions.php';
require_once __DIR__ . '/../lib/download_filename.php';

csrf_protect();

// --- Input validation ---
$selected_raw = $_POST['sources'] ?? [];
if (!is_array($selected_raw)) $selected_raw = [$selected_raw];

$output_format  = in_array($_POST['output_format'] ?? '', ['tsv', 'fasta']) ? $_POST['output_format'] : 'tsv';
$fasta_mode     = $_POST['fasta_mode'] ?? 'gene';
$flank_bp       = max(1, min(100000, (int)($_POST['flank_bp'] ?? 500)));
$fasta_preview  = !empty($_POST['fasta_preview']);

$valid_fasta_modes = ['gene', 'upstream', 'downstream', 'exons', 'protein', 'transcript', 'cds'];
if (!in_array($fasta_mode, $valid_fasta_modes)) $fasta_mode = 'gene';

// --- Resolve accessible sources ---
$all_accessible    = flattenSourcesList(getAccessibleGeneSets());
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

// Filters, IDs, coordinates and annotation columns: parsed by the SAME function the previews use
// (moopmartParsePreviewRequest). This file used to repeat that parsing inline, so a fix to one --
// the criterion source name is matched exactly, never trimmed -- silently missed the other.
$req                         = moopmartParsePreviewRequest($_POST);
$raw_input_ids               = $req['raw_input_ids'];
$filters                     = $req['filters'];
$coord_filter                = $req['coord_filter'];
$global_filter_reason        = $req['global_filter_reason'];
$annotation_columns_selected = $req['annotation_columns'];
$ann_format                  = $req['ann_format'];   // long | wide -- the same parse the preview uses

$organism_data  = $config->getPath('organism_data');
$sequence_types = $config->getSequenceTypes();

// --- Group selected sources by organism ---
$by_organism = [];
foreach ($selected as $src) {
    $org = $src['organism'];
    $by_organism[$org]['db_path'] = organism_db_path($org);
    $by_organism[$org]['sources'][] = $src;
}

// Genes per streaming chunk. A chunk's annotation map is the dominant cost of the export
// (~47 KB per gene, so 250 genes ≈ 12 MB); keeping it small is what lets a whole-organism
// export finish inside PHP's 128M limit.
const MOOPMART_EXPORT_GENE_CHUNK = 250;

$sources_by_gs_id = [];

/**
 * Yield one organism's matched gene rows in chunks, with coordinates attached.
 *
 * A generator rather than a returned array on purpose: collecting every matched row first
 * cost ~108 MB for a 28k-gene genome — attaching coordinates rewrites each row into a new
 * array — and the export then died fetching the first chunk's annotations, before writing
 * a single data row. Streaming keeps peak memory flat however many organisms are selected.
 */
$organismChunks = function (string $org, array $org_data) use (
    $filters, $raw_input_ids, $coord_filter, $global_filter_reason, &$sources_by_gs_id
): Generator {
    $db = $org_data['db_path'];
    if (!file_exists($db)) return;
    $sources      = $org_data['sources'];
    $gene_set_ids = array_values(array_filter(array_column($sources, 'gene_set_id')));
    if (empty($gene_set_ids)) return;

    $id_reasons  = [];
    $org_filters = $filters;
    if (!empty($raw_input_ids)) {
        $id_reasons = moopmartResolveInputIds($raw_input_ids, $db, $gene_set_ids);
        if (empty($id_reasons)) return;
        $org_filters['feature_ids'] = array_keys($id_reasons);
    }

    $features = moopmartQueryFeatures($gene_set_ids, $db, $org_filters);
    if (empty($features)) return;

    // Coordinates come from a per-gene-set TSV, so load each one once for the whole gene
    // set rather than re-reading it for every chunk.
    $uniquenames_by_gs = [];
    foreach ($features as $f) {
        $uniquenames_by_gs[$f['gene_set_id']][] = $f['uniquename'];
    }

    $coords_by_gs = [];
    foreach ($sources as $src) {
        $gs_id  = $src['gene_set_id'];
        $gs_key = $db . '|' . $gs_id;
        if ($gs_id && !isset($coords_by_gs[$gs_id])) {
            $coords_by_gs[$gs_id]      = moop_feature_coords_by_gene($src['path'], $uniquenames_by_gs[$gs_id] ?? []);
            $sources_by_gs_id[$gs_key] = $src;
        }
    }
    unset($uniquenames_by_gs);

    foreach (array_chunk($features, MOOPMART_EXPORT_GENE_CHUNK) as $chunk) {
        $rows = [];
        // The coordinate filter can drop rows, so a chunk may come back empty.
        foreach (moopmartAttachCoords($chunk, $coords_by_gs, $coord_filter) as $f) {
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
            $rows[]            = $f;
        }
        if ($rows) yield $db => $rows;
    }
};

$ext      = $output_format === 'fasta' ? 'fa' : 'tsv';
// A MOOPmart export can span several organisms, so naming it after one would be a lie and
// naming it after none loses the provenance entirely. moop_download_scope_label() gives the
// organism when there is exactly one and "N-organisms" when there are more. The old name --
// moopmart_tsv_20260730_224455 -- said neither, and used the only Ymd_His date in the app.
$filename = moop_download_filename(
    'moopmart',
    [moop_download_scope_label(array_keys($by_organism)), $output_format],
    $ext
);

set_time_limit(0);
while (ob_get_level()) ob_end_clean();

// =============================================================
// TSV EXPORT
// =============================================================
if ($output_format === 'tsv') {

    // Annotation source columns, in the order chosen -- the preview's order, so the preview is
    // the file. (This used to sort() them, so wide headers came out in a different order.)
    // Empty selection = no annotation columns.
    $source_cols = array_values($annotation_columns_selected);

    // Strip embedded newlines/tabs that would break TSV parsing in Excel
    $clean = fn($s) => str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string)$s);
    $out   = null;

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

    // Annotation sub-columns: the same parse the preview uses (moopmartParsePreviewRequest)
    $ann_incl_id      = $req['ann_incl_id'];
    $ann_incl_desc    = $req['ann_incl_desc'];
    $ann_incl_src     = $req['ann_incl_src'];

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
    // Emit HTTP headers and the header row on the first surviving chunk. Until then nothing
    // has been written, so a run that matches nothing can still answer 404 instead of
    // returning a 200 with a lone header row.
    $beginOutput = function () use (&$out, $headers, $filename) {
        // Send headers immediately so nginx doesn't time out waiting for the first byte
        header('Content-Type: text/tab-separated-values; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');  // disable nginx proxy buffering

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers, "\t");
        flush();
    };

    // One organism at a time, one chunk at a time: expand to mRNA rows, fetch only that
    // chunk's annotations, write, free. Flush per chunk so the browser receives data
    // incrementally instead of waiting on the whole export.
    foreach ($by_organism as $org => $org_data) {
        foreach ($organismChunks($org, $org_data) as $db_path => $chunk) {
            if ($out === null) $beginOutput();

            // Expand gene rows to one row per mRNA before streaming
            $expanded   = moopmartExpandToMrnaRows($chunk, $db_path);
            $chunk_anns = moopmartGetAnnotationsForGenes(array_column($chunk, 'feature_id'), $db_path);

            foreach ($expanded as $f) {
                $base     = array_map(fn($k) => ($feat_col_map[$k][1])($f), $active_feat);
                $fid_anns = moopmartRowAnnotations($chunk_anns, $f);   // this row's own, never a sibling isoform's

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
            unset($expanded, $chunk_anns);
            flush();
        }
    }

    if ($out === null) {
        http_response_code(404);
        die('No features matched the selected filters.');
    }
    fclose($out);

// =============================================================
// FASTA EXPORT
// =============================================================
} else {

    $out = null;
    $beginOutput = function () use (&$out, $filename, $fasta_preview) {
        header('Content-Type: text/plain; charset=UTF-8');
        if (!$fasta_preview) {
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }
        header('Cache-Control: no-cache, no-store');
        $out = fopen('php://output', 'w');
    };

    $genomic_modes    = ['gene', 'upstream', 'downstream', 'exons'];
    $skipped_datasets = [];
    $genomic_notes    = [];   // per gene set: why some genes got no genomic sequence
    $collect_limit    = $fasta_preview ? 10 : PHP_INT_MAX;
    $collected        = 0;
    $reached_limit    = false;

    foreach ($by_organism as $org_name => $org_data) {
        if ($reached_limit) break;

        // Group this organism's genes by gene set: the exon writer makes a single GFF pass over
        // a whole gene set, so it needs one intact. Keep only the fields the FASTA writers read
        // — carrying descriptions and match reasons here costs several times the memory for
        // nothing, and this is the one array the export still holds in full.
        $by_gs = [];
        foreach ($organismChunks($org_name, $org_data) as $chunk) {
            foreach ($chunk as $f) {
                $by_gs[$f['gs_key']][] = [
                    'uniquename'  => $f['uniquename'],
                    'chr'         => $f['chr']    ?? '',
                    'start'       => $f['start']  ?? 0,
                    'end'         => $f['end']    ?? 0,
                    'strand'      => $f['strand'] ?? '',
                    'gene_set_id' => $f['gene_set_id'],
                ];
                if (++$collected >= $collect_limit) { $reached_limit = true; break 2; }
            }
        }
        if (empty($by_gs)) continue;
        if ($out === null) $beginOutput();

        foreach ($by_gs as $gs_key => $gs_features) {
            $src = $sources_by_gs_id[$gs_key] ?? null;
            if (!$src) continue;

            $org          = $src['organism'];
            $assembly     = $src['assembly'];
            $gs_name      = $src['gene_set'] ?? '';
            $assembly_dir = "$organism_data/$org/$assembly";
            $gs_path      = "$assembly_dir/$gs_name";
            $gff_path     = "$gs_path/" . genes_gff_filename();
            $gs_label     = $gs_name ? "$org / $assembly / $gs_name" : "$org / $assembly";

            if (in_array($fasta_mode, $genomic_modes)) {
                $genome_file = genome_fasta_filename();
                $fasta = "$assembly_dir/$genome_file";
                $fai   = "$assembly_dir/$genome_file.fai";
                if (!file_exists($fasta) || !file_exists($fai)) {
                    $missing = !file_exists($fasta) ? $genome_file : "$genome_file.fai";
                    $skipped_datasets[] = "$gs_label (missing $missing)";
                    continue;
                }
                $skips = moopmartStreamGenomicFasta($gs_features, $assembly_dir, $gff_path, $fasta_mode, $flank_bp, $out);
                foreach (moop_genomic_skip_notes($skips) as $note) $genomic_notes[] = "$gs_label: $note";
            } else {
                // Pre-built FASTA files: protein, transcript, cds. The genes are expanded to
                // their isoforms/CDS/proteins and written in batches, this one type only (it
                // used to extract all three types for the whole gene set: 969 MB on Myotis).
                // The gene_set_id is the access filter, so the expansion cannot leave this gene set.
                $gs_id_int = (int)($gs_features[0]['gene_set_id'] ?? 0);
                $streamed  = moop_stream_sequences($out, $gs_path, array_column($gs_features, 'uniquename'), $fasta_mode, [
                    'db_path'        => organism_db_path($org),
                    'sequence_types' => $sequence_types,
                    'assembly'       => $assembly,
                    'organism'       => $org,
                    'gene_set_ids'   => $gs_id_int ? [$gs_id_int] : [],
                ]);
                if (!$streamed['has_file']) {
                    $skipped_datasets[] = "$gs_label (missing $fasta_mode FASTA file)";
                }
            }
        }
        unset($by_gs);
    }

    if ($out === null) {
        http_response_code(404);
        die('No features matched the selected filters.');
    }

    foreach ($genomic_notes as $note) {
        fwrite($out, "# WARNING: $note\n");
    }
    if (!empty($skipped_datasets)) {
        fwrite($out, "\n# WARNING: The following gene sets were skipped because required files are missing:\n");
        foreach ($skipped_datasets as $ds) {
            fwrite($out, "#   - $ds\n");
        }
    }

    fclose($out);
}
