#!/usr/bin/env php
<?php
/**
 * Refresh an expression bundle's sample metadata from the track Google Sheet.
 *
 * Division of labour (notes/EXPRESSION_COUNT_TABLES_PLAN.md):
 *   - the SHEET owns descriptive metadata — stage, tissue, condition, accessions, citation —
 *     because that is where the organism's researchers curate it;
 *   - the bundle's samples.tsv owns GROUPING and ORDER (sample_id, group, replicate), which is a
 *     presentation decision the sheet has no column for.
 * This script copies the first into the second. Re-run it whenever the sheet changes.
 *
 * A sample is found by its track rows: sample_id "PRJNA200689.452" matches TRACK_ID
 * PRJNA200689.452.bw, .pos.bw and .neg.bw. Reading stops at the "##### Combo Tracks" header (user:
 * ignore everything after it).
 *
 * A track's row legitimately appears many times: every "# name … ### end" block is a JBrowse overlay,
 * and one track can belong to several overlays as well as being shown on its own. There is no
 * "standalone" copy to prefer — all copies are equal, and they all write the same JBrowse track file,
 * so copies that DISAGREE make JBrowse show whichever was processed last. Such a sample is refused
 * here rather than picking one; the fix belongs in the sheet.
 *
 * Usage:
 *   php scripts/expression_samples_from_sheet.php --bundle=DIR --sheet-id=ID --gid=GID [--dry-run]
 *   php scripts/expression_samples_from_sheet.php --bundle=DIR --sheet-tsv=FILE  [--dry-run]
 *
 * Writes into samples.tsv (other columns are kept as they are):
 *   developmental_stage, tissue, condition, run_accession, biosample, sheet_name, track_keys
 * and into experiment.json, when every sample agrees: citation, project_accession, lab.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$opts = getopt('', ['bundle:', 'sheet-id:', 'gid:', 'sheet-tsv:', 'dry-run', 'help']);
if (isset($opts['help']) || empty($opts['bundle']) || (empty($opts['sheet-tsv']) && (empty($opts['sheet-id']) || !isset($opts['gid'])))) {
    fwrite(STDERR, "Usage: php scripts/expression_samples_from_sheet.php --bundle=DIR (--sheet-id=ID --gid=GID | --sheet-tsv=FILE) [--dry-run]\n");
    exit(2);
}
$bundle  = rtrim($opts['bundle'], '/');
$dry_run = isset($opts['dry-run']);

require_once dirname(__DIR__) . '/lib/expression_functions.php';

// sheet column => samples.tsv column
const SHEET_FIELDS = [
    'developmental-stage' => 'stage',
    'tissue'              => 'tissue',
    'condition'           => 'condition',
    'accession'           => 'run_accession',
    'biosample'           => 'biosample',
];
// sheet column => experiment.json key, filled only when every sample agrees
const EXPERIMENT_FIELDS = ['citation' => 'citation', 'project' => 'project_accession', 'source' => 'lab'];

// --- the sheet -------------------------------------------------------------
if (!empty($opts['sheet-tsv'])) {
    $content = @file_get_contents($opts['sheet-tsv']);
    if ($content === false) { fwrite(STDERR, "Cannot read {$opts['sheet-tsv']}\n"); exit(1); }
} else {
    require_once dirname(__DIR__) . '/lib/jbrowse/GoogleSheetsParser.php';
    try {
        $content = (new GoogleSheetsParser())->download($opts['sheet-id'], $opts['gid']);
    } catch (Exception $e) {
        fwrite(STDERR, "Cannot download the sheet: " . $e->getMessage() . "\n");
        exit(1);
    }
}
$lines  = preg_split('/\r?\n/', $content);
$header = str_getcsv(array_shift($lines), "\t", '"', '');
$col    = array_flip($header);
foreach (array_merge(['TRACK_ID', 'NAME'], array_keys(SHEET_FIELDS), array_keys(EXPERIMENT_FIELDS)) as $need) {
    if (!isset($col[$need])) { fwrite(STDERR, "The sheet has no '$need' column\n"); exit(1); }
}
$by_track = [];
foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $cells = str_getcsv($line, "\t", '"', '');
    $id = trim($cells[$col['TRACK_ID']] ?? '');
    // "##### Combo Tracks: Updated …" starts a generated section that repeats tracks listed above
    // it. Everything after it is ignored (user, 2026-10-09).
    if (preg_match('/^#+\s*combo tracks\b/i', $id)) break;
    if ($id === '' || $id[0] === '#') continue;     // combo-track markers
    $row = [];
    foreach ($header as $i => $h) $row[$h] = trim($cells[$i] ?? '');
    $by_track[$id][] = $row;
}

// --- the bundle --------------------------------------------------------------
$samples_file = "$bundle/samples.tsv";
if (!is_file($samples_file)) { fwrite(STDERR, "No $samples_file\n"); exit(1); }
$s_lines  = array_values(array_filter(file($samples_file, FILE_IGNORE_NEW_LINES), fn($l) => trim($l) !== ''));
$s_header = explode("\t", array_shift($s_lines));
if (!in_array('sample_id', $s_header, true)) { fwrite(STDERR, "$samples_file has no sample_id column\n"); exit(1); }

$owned   = array_merge(array_values(SHEET_FIELDS), ['sheet_name', 'track_keys']);
$kept    = array_values(array_filter($s_header, fn($h) => !in_array($h, $owned, true)));
$out_hdr = array_merge($kept, $owned);

$problems = []; $missing = []; $exp_values = [];
$out = [implode("\t", $out_hdr)];
foreach ($s_lines as $line) {
    $cells = explode("\t", $line);
    $rec   = [];
    foreach ($s_header as $i => $h) $rec[$h] = $cells[$i] ?? '';
    $sid = $rec['sample_id'];

    $rows = []; $keys = [];
    foreach (["$sid.bw", "$sid.pos.bw", "$sid.neg.bw"] as $tid) {
        if (!isset($by_track[$tid])) continue;
        $keys[] = $tid;
        foreach ($by_track[$tid] as $r) $rows[] = $r;
    }
    if (!$rows) { $missing[] = $sid; $out[] = implode("\t", array_map(fn($h) => $rec[$h] ?? '', $out_hdr)); continue; }

    // Every field this script copies must agree across the sample's rows (pos, neg, duplicates).
    // NAME differs between strands only by the .pos/.neg suffix, so compare it without that.
    $vals = [];
    foreach (array_merge(array_keys(SHEET_FIELDS), array_keys(EXPERIMENT_FIELDS), ['NAME']) as $f) {
        $distinct = array_values(array_unique(array_map(
            fn($r) => $f === 'NAME' ? preg_replace('/\.(pos|neg)$/', '', $r[$f]) : $r[$f], $rows)));
        if (count($distinct) > 1) {
            $problems[] = "$sid: the sheet disagrees with itself on '$f': " . implode(' | ', $distinct);
        }
        $vals[$f] = $distinct[0];
    }
    foreach (SHEET_FIELDS as $f => $c) $rec[$c] = $vals[$f];
    $rec['sheet_name'] = $vals['NAME'];
    $rec['track_keys'] = implode(';', $keys);
    foreach (EXPERIMENT_FIELDS as $f => $k) $exp_values[$k][] = $vals[$f];
    $out[] = implode("\t", array_map(fn($h) => str_replace("\t", ' ', $rec[$h] ?? ''), $out_hdr));
}

printf("%s: %d samples, %d found in the sheet\n", basename($bundle), count($s_lines), count($s_lines) - count($missing));
if ($missing) echo "  not in the sheet (left as they were): " . implode(', ', $missing) . "\n";
if ($problems) {
    echo "  REFUSED — fix the sheet first:\n    " . implode("\n    ", $problems) . "\n";
    exit(1);
}

// experiment.json: only fields every sample agrees on
$exp_file = "$bundle/experiment.json";
$exp = is_file($exp_file) ? (json_decode(file_get_contents($exp_file), true) ?: []) : [];
foreach ($exp_values as $k => $vs) {
    $u = array_values(array_unique(array_filter($vs, fn($v) => $v !== '')));
    if (count($u) === 1 && ($exp[$k] ?? '') !== $u[0]) {
        echo "  experiment.json $k: '" . ($exp[$k] ?? '') . "' → '{$u[0]}'\n";
        $exp[$k] = $u[0];
    } elseif (count($u) > 1) {
        echo "  experiment.json $k: samples disagree (" . implode(' | ', $u) . ") — left as is\n";
    }
}

if ($dry_run) { echo "  dry run — nothing written\n"; exit(0); }
file_put_contents($samples_file, implode("\n", $out) . "\n");
file_put_contents($exp_file, json_encode($exp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "  wrote samples.tsv and experiment.json\n";
