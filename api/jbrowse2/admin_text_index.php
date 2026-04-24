<?php
/**
 * JBrowse Admin API: Text-Index a Track
 *
 * Runs `jbrowse text-index` on a local GFF or BED track to enable feature
 * name/ID search in JBrowse2.  Creates trix files in jbrowse2/{organism}/{assembly}/trix/
 * and updates the track JSON with a textSearching block.
 *
 * POST params:
 *   organism   - organism directory name  (e.g. Nematostella_vectensis)
 *   assembly   - assembly ID              (e.g. GCA_033964005.1)
 *   track_id   - JBrowse trackId          (e.g. track_e1f2d5134e)
 *   attributes - comma-separated GFF attributes to index  [default: Name,ID]
 */

require_once __DIR__ . '/../../admin/admin_init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$organism   = trim($_POST['organism']   ?? '');
$assembly   = trim($_POST['assembly']   ?? '');
$track_id   = trim($_POST['track_id']   ?? '');
$attributes = trim($_POST['attributes'] ?? 'Name,ID');

if (empty($organism) || empty($assembly) || empty($track_id)) {
    echo json_encode(['success' => false, 'error' => 'organism, assembly, and track_id are required']);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $organism) ||
    !preg_match('/^[A-Za-z0-9_\-\.]+$/', $assembly)  ||
    !preg_match('/^[A-Za-z0-9_\-\.]+$/', $track_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid characters in parameters']);
    exit;
}

// Attributes must be comma-separated identifiers only
if (!preg_match('/^[A-Za-z0-9_]+(,[A-Za-z0-9_]+)*$/', $attributes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid attributes. Use comma-separated names, e.g. Name,ID']);
    exit;
}

$config    = ConfigManager::getInstance();
$site_path = $config->getPath('site_path');
$site      = $config->getString('site', 'moop');

// Locate the track JSON
$track_dir  = "$site_path/metadata/jbrowse2-configs/tracks/$organism/$assembly";
$track_file = find_track_json($track_dir, $track_id);

if (!$track_file) {
    echo json_encode(['success' => false, 'error' => "Track JSON not found for: $track_id"]);
    exit;
}

$track_def = json_decode(file_get_contents($track_file), true);
if (!$track_def) {
    echo json_encode(['success' => false, 'error' => 'Could not parse track JSON']);
    exit;
}

// Only local tracks can be indexed (we can't read remote files to build an index)
if ($track_def['metadata']['is_remote'] ?? false) {
    echo json_encode(['success' => false, 'error' => 'Text indexing is only supported for local tracks']);
    exit;
}

$file_path = $track_def['metadata']['file_path'] ?? '';
if (empty($file_path) || !file_exists($file_path)) {
    echo json_encode(['success' => false, 'error' => "Track file not found: $file_path"]);
    exit;
}

// Check for jbrowse CLI
$jbrowse = find_jbrowse_cli();
if (!$jbrowse) {
    echo json_encode([
        'success'  => false,
        'error'    => 'jbrowse CLI not found. Install Node.js then run: npm install -g @jbrowse/cli',
        'no_cli'   => true,
    ]);
    exit;
}

// Prepare per-organism/assembly trix directory so indexes never collide across assemblies.
// jbrowse text-index names files after the input file basename (not --fileId), so two
// assemblies sharing the same source filename (e.g. annotations.gff3.gz) would overwrite
// each other if stored in a shared directory.
// Output layout: jbrowse2/{organism}/{assembly}/trix/{basename}.ix
$jbrowse2_dir = "$site_path/jbrowse2";
$trix_out_dir = "$jbrowse2_dir/$organism/$assembly";
if (!is_dir($trix_out_dir) && !mkdir($trix_out_dir, 0755, true)) {
    echo json_encode(['success' => false, 'error' => "Could not create trix directory: $trix_out_dir"]);
    exit;
}
$trix_dir = "$trix_out_dir/trix";

$log = [];

// Run jbrowse text-index
$cmd = escapeshellarg($jbrowse) . ' text-index'
     . ' --file '       . escapeshellarg($file_path)
     . ' --fileId '     . escapeshellarg($track_id)
     . ' --out '        . escapeshellarg($trix_out_dir)
     . ' --attributes ' . escapeshellarg($attributes)
     . ' --force'
     . ' 2>&1';

$log[] = 'jbrowse text-index --file ' . basename($file_path)
       . ' --fileId ' . $track_id
       . ' --attributes ' . $attributes;

$out = [];
exec($cmd, $out, $rc);
$log = array_merge($log, $out);

if ($rc !== 0) {
    echo json_encode([
        'success' => false,
        'error'   => 'jbrowse text-index failed (exit ' . $rc . ')',
        'log'     => $log,
    ]);
    exit;
}

// Verify the .ix file was created
// jbrowse text-index names trix files after the basename of --file, not --fileId
$gz_basename = basename($file_path);             // e.g. "annotations.gff3.gz"
$ix_file = "$trix_dir/{$gz_basename}.ix";
if (!file_exists($ix_file)) {
    echo json_encode([
        'success' => false,
        'error'   => 'text-index ran but .ix file was not created',
        'log'     => $log,
    ]);
    exit;
}
$log[] = 'Created: ' . basename($ix_file) . ' (' . number_format(filesize($ix_file)) . ' bytes)';

// Update track JSON with textSearching block
$assembly_name = $track_def['assemblyNames'][0] ?? "{$organism}_{$assembly}";
$track_def['textSearching'] = build_text_searching_block($track_id, $gz_basename, $organism, $assembly, $assembly_name, $site);

// Store indexing config in metadata for future reference
$track_def['metadata']['text_index_attributes'] = $attributes;
$track_def['metadata']['text_index_date']       = gmdate('Y-m-d\TH:i:s\Z');

$json = json_encode($track_def, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($track_file, $json) === false) {
    echo json_encode([
        'success' => false,
        'error'   => 'Index created but could not update track JSON',
        'log'     => $log,
    ]);
    exit;
}
$log[] = 'Updated track JSON with textSearching block';

echo json_encode([
    'success'    => true,
    'output'     => implode("\n", $log),
    'attributes' => $attributes,
]);

// ============================================================================
// Helpers
// ============================================================================

/**
 * Find the JSON file for a given trackId under any type subdirectory.
 */
function find_track_json(string $track_dir, string $track_id): ?string {
    foreach (['gff', 'bed', 'gtf', 'vcf', 'bam', 'bigwig', 'cram'] as $type) {
        $f = "$track_dir/$type/$track_id.json";
        if (file_exists($f)) return $f;
    }
    // Fallback: search all JSON files by trackId field
    foreach (glob("$track_dir/*/*.json") ?: [] as $f) {
        $def = json_decode(file_get_contents($f), true);
        if (($def['trackId'] ?? '') === $track_id) return $f;
    }
    return null;
}

/**
 * Locate the jbrowse CLI binary.
 */
function find_jbrowse_cli(): ?string {
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
    // Try PATH lookup
    $out = [];
    exec('command -v jbrowse 2>/dev/null', $out, $ret);
    if ($ret === 0 && !empty($out[0])) return trim($out[0]);
    return null;
}

/**
 * Build the textSearching block to embed in a track JSON.
 * Trix files live at jbrowse2/{organism}/{assembly}/trix/ to avoid cross-assembly collisions.
 */
function build_text_searching_block(string $track_id, string $gz_basename, string $organism, string $assembly, string $assembly_name, string $site): array {
    $base = "/$site/jbrowse2/$organism/$assembly/trix/$gz_basename";
    return [
        'textSearchAdapter' => [
            'type'                => 'TrixTextSearchAdapter',
            'textSearchAdapterId' => $track_id . '-index',
            'ixFilePath'          => ['uri' => "{$base}.ix",        'locationType' => 'UriLocation'],
            'ixxFilePath'         => ['uri' => "{$base}.ixx",       'locationType' => 'UriLocation'],
            'metaFilePath'        => ['uri' => "{$base}_meta.json", 'locationType' => 'UriLocation'],
            'assemblyNames'       => [$assembly_name],
        ],
    ];
}
