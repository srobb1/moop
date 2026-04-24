<?php
/**
 * JBrowse Admin API: Re-prep GFF
 *
 * Re-runs bgzip + tabix on an assembly's annotations.gff3 symlink even if
 * compressed files already exist.  Use this after replacing a source genomic.gff
 * with real data.
 *
 * POST params:
 *   organism          - organism directory name (e.g. Nematostella_vectensis)
 *   assembly          - assembly ID             (e.g. GCA_033964005.1)
 *   text_index        - 1 to also run jbrowse text-index after tabix  [optional]
 *   attributes        - comma-separated attributes for text-index      [default: Name,ID]
 */

require_once __DIR__ . '/../../admin/admin_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$organism   = trim($_POST['organism']   ?? '');
$assembly   = trim($_POST['assembly']   ?? '');
$do_index   = !empty($_POST['text_index']);
$attributes = trim($_POST['attributes'] ?? 'Name,ID');

if (empty($organism) || empty($assembly)) {
    echo json_encode(['success' => false, 'error' => 'Organism and assembly are required']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $organism) || !preg_match('/^[A-Za-z0-9_\-\.]+$/', $assembly)) {
    echo json_encode(['success' => false, 'error' => 'Invalid organism or assembly name']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]+(,[A-Za-z0-9_]+)*$/', $attributes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid attributes format']);
    exit;
}

$config        = ConfigManager::getInstance();
$organisms_dir = $config->getPath('organism_data');
$site_path     = $config->getPath('site_path');
$site          = $config->getString('site', 'moop');

$source_gff  = "$organisms_dir/$organism/$assembly/genomic.gff";
$genomes_dir = "$site_path/data/genomes/$organism/$assembly";
$target_gff  = "$genomes_dir/annotations.gff3";
$gz_file     = "$genomes_dir/annotations.gff3.gz";
$tbi_file    = "$gz_file.tbi";

$log = [];

// Validate source GFF exists and is non-empty
if (!file_exists($source_gff)) {
    echo json_encode(['success' => false, 'error' => "No genomic.gff found at $source_gff"]);
    exit;
}
$gff_size = filesize($source_gff);
if ($gff_size === 0) {
    echo json_encode(['success' => false, 'error' => "genomic.gff is empty (0 bytes). Replace it with real data first."]);
    exit;
}
$log[] = "Source: genomic.gff (" . number_format($gff_size) . " bytes)";

if (!is_dir($genomes_dir)) {
    echo json_encode(['success' => false, 'error' => "Genomes directory not found: $genomes_dir — register the assembly first"]);
    exit;
}

// Ensure symlink exists
if (!is_link($target_gff) && !file_exists($target_gff)) {
    if (!symlink($source_gff, $target_gff)) {
        echo json_encode(['success' => false, 'error' => "Failed to create symlink for annotations.gff3"]);
        exit;
    }
    $log[] = 'Created annotations.gff3 symlink';
} else {
    $log[] = 'annotations.gff3 symlink OK';
}

// Remove stale compressed file + index so they are always rebuilt fresh
foreach ([$tbi_file, $gz_file] as $f) {
    if (file_exists($f)) {
        unlink($f);
        $log[] = 'Removed old ' . basename($f);
    }
}

// Sort GFF before compressing — tabix requires positions sorted within each sequence.
// Use POSIX sort (original approach, no extra dependencies).
// Falls back to `jbrowse sort-gff` if POSIX sort fails.
$rc = 1; $out = [];
$sort_cmd = '(grep "^#" ' . escapeshellarg($target_gff)
         . '; grep -v "^#" ' . escapeshellarg($target_gff)
         . ' | sort -t"$(printf \'\\t\')" -k1,1 -k4,4n) | bgzip > ' . escapeshellarg($gz_file);
exec('/bin/bash -c ' . escapeshellarg($sort_cmd), $out, $rc);
if ($rc !== 0 || !file_exists($gz_file)) {
    // Fallback: jbrowse sort-gff
    $jbrowse_bin = find_jbrowse_cli_reprep();
    if ($jbrowse_bin) {
        $cmd = escapeshellarg($jbrowse_bin) . ' sort-gff ' . escapeshellarg($target_gff)
             . ' | bgzip > ' . escapeshellarg($gz_file);
        exec('/bin/bash -c ' . escapeshellarg($cmd), $out, $rc);
        $log[] = $rc === 0 ? 'sort+bgzip (jbrowse sort-gff fallback) OK' : 'jbrowse sort-gff also failed';
    }
}
if ($rc !== 0 || !file_exists($gz_file)) {
    echo json_encode(['success' => false, 'error' => 'sort+bgzip failed: ' . implode(' ', $out), 'log' => $log]);
    exit;
}
$log[] = 'bgzip OK (' . number_format(filesize($gz_file)) . ' bytes)';

// tabix
$out = [];
$cmd = 'tabix -p gff ' . escapeshellarg($gz_file) . ' 2>&1';
exec($cmd, $out, $rc);
if ($rc !== 0) {
    echo json_encode(['success' => false, 'error' => 'tabix failed: ' . implode(' ', $out), 'log' => $log]);
    exit;
}
$log[] = 'tabix OK (' . number_format(filesize($tbi_file)) . ' bytes)';

// ── Optional text-index step ─────────────────────────────────────────────────
$text_index_result = null;
if ($do_index) {
    $text_index_result = run_text_index($organism, $assembly, $gz_file, $attributes, $site_path, $site);
    if ($text_index_result['success']) {
        $log[] = 'text-index OK (attributes: ' . $attributes . ')';
    } else {
        $log[] = 'text-index skipped: ' . $text_index_result['error'];
    }
}

echo json_encode([
    'success'           => true,
    'output'            => implode("\n", $log),
    'text_index_result' => $text_index_result,
]);

// ============================================================================
// Helpers
// ============================================================================

/**
 * Run jbrowse text-index and update the track JSON with a textSearching block.
 * Trix files go in jbrowse2/trix/ (served as public static assets).
 * Returns ['success' => bool, ...].
 */
function run_text_index(
    string $organism,
    string $assembly,
    string $gz_file,
    string $attributes,
    string $site_path,
    string $site
): array {
    $jbrowse = find_jbrowse_cli_reprep();
    if (!$jbrowse) {
        return [
            'success' => false,
            'error'   => 'jbrowse CLI not found — install Node.js then: npm install -g @jbrowse/cli',
            'no_cli'  => true,
        ];
    }

    $jbrowse2_dir = "$site_path/jbrowse2";
    $trix_out_dir = "$jbrowse2_dir/$organism/$assembly";
    if (!is_dir($trix_out_dir) && !mkdir($trix_out_dir, 0755, true)) {
        return ['success' => false, 'error' => "Could not create trix directory: $trix_out_dir"];
    }
    $trix_dir = "$trix_out_dir/trix";

    // Find the track JSON that belongs to this assembly's annotations.gff3.gz
    $tracks_dir = "$site_path/metadata/jbrowse2-configs/tracks/$organism/$assembly/gff";
    $track_file = null;
    $track_def  = null;
    $track_id   = null;

    foreach (glob("$tracks_dir/*.json") ?: [] as $f) {
        $def = json_decode(file_get_contents($f), true);
        if (!$def) continue;
        if (($def['adapter']['type'] ?? '') !== 'Gff3TabixAdapter') continue;
        if ($def['metadata']['is_remote'] ?? false) continue;
        if (strpos(basename($def['metadata']['file_path'] ?? ''), 'annotations.gff3') !== false) {
            $track_file = $f;
            $track_def  = $def;
            $track_id   = $def['trackId'] ?? null;
            break;
        }
    }

    if (!$track_file || !$track_id) {
        return ['success' => false, 'error' => 'Could not find matching track JSON for annotations.gff3.gz'];
    }

    // Run jbrowse text-index using --file mode (no config.json needed)
    $cmd = escapeshellarg($jbrowse) . ' text-index'
         . ' --file '       . escapeshellarg($gz_file)
         . ' --fileId '     . escapeshellarg($track_id)
         . ' --out '        . escapeshellarg($trix_out_dir)
         . ' --attributes ' . escapeshellarg($attributes)
         . ' --force'
         . ' 2>&1';

    $out = [];
    exec($cmd, $out, $rc);

    if ($rc !== 0) {
        return ['success' => false, 'error' => 'exit ' . $rc . ': ' . implode(' ', $out)];
    }

    // jbrowse text-index names trix files after the basename of --file, not --fileId
    $gz_basename = basename($gz_file);           // e.g. "annotations.gff3.gz"
    $ix_file     = "$trix_dir/{$gz_basename}.ix";
    if (!file_exists($ix_file)) {
        return ['success' => false, 'error' => 'text-index ran but .ix file was not created'];
    }

    // Add textSearching block to the track JSON
    // Trix files live at jbrowse2/{organism}/{assembly}/trix/ to avoid cross-assembly collisions.
    $assembly_name  = $track_def['assemblyNames'][0] ?? "{$organism}_{$assembly}";
    $base           = "/$site/jbrowse2/$organism/$assembly/trix/$gz_basename";
    $track_def['textSearching'] = [
        'textSearchAdapter' => [
            'type'                => 'TrixTextSearchAdapter',
            'textSearchAdapterId' => $track_id . '-index',
            'ixFilePath'          => ['uri' => "{$base}.ix",        'locationType' => 'UriLocation'],
            'ixxFilePath'         => ['uri' => "{$base}.ixx",       'locationType' => 'UriLocation'],
            'metaFilePath'        => ['uri' => "{$base}_meta.json", 'locationType' => 'UriLocation'],
            'assemblyNames'       => [$assembly_name],
        ],
    ];
    $track_def['metadata']['text_index_attributes'] = $attributes;
    $track_def['metadata']['text_index_date']       = gmdate('Y-m-d\TH:i:s\Z');

    file_put_contents($track_file, json_encode($track_def, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return ['success' => true];
}

function find_jbrowse_cli_reprep(): ?string {
    $candidates = [
        // Project-local install (preferred — uses Node 20 wrapper)
        __DIR__ . '/../../tools/jbrowse-cli/jbrowse-run.sh',
        __DIR__ . '/../../tools/jbrowse-cli/bin/jbrowse',
        '/usr/local/bin/jbrowse',
        '/usr/bin/jbrowse',
        (getenv('HOME') ?: '') . '/.npm-global/bin/jbrowse',
        '/root/.npm-global/bin/jbrowse',
        '/usr/local/lib/node_modules/.bin/jbrowse',
        '/usr/lib/node_modules/@jbrowse/cli/bin/run',
    ];
    foreach ($candidates as $path) {
        if ($path && is_executable($path)) return $path;
    }
    $out = [];
    exec('command -v jbrowse 2>/dev/null', $out, $ret);
    if ($ret === 0 && !empty($out[0])) return trim($out[0]);
    return null;
}
