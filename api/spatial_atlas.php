<?php
/**
 * Spatial atlas data for the viewer (js/spatial-atlas.js): the tool page and the gene-page card.
 * Read-only; access-checked (lib/spatial_atlas.php moop_atlas_visible(): the gene set must be visible
 * AND the atlas's own rule, kind 'atlas'). A private atlas and a missing one both answer 404.
 *
 *   GET api/spatial_atlas.php?organism=O&assembly=A&gene_set=G&atlas=N&what=info
 *       what=mesh                      the surface + overlay, base64 little-endian (cache: per build)
 *       what=gene&gene=ID              one gene's values (base64, meta.value_encoding), similar genes, picture
 *       what=search&q=TEXT             genes for the picker, by name or ID
 *       what=asset&name=logo|figure    the atlas's own pictures
 * Plan: notes/SPATIAL_ATLAS_PLAN.md.
 */
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/spatial_atlas.php';

function atlas_api_fail(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: application/json');
    exit(json_encode(['error' => $msg]));
}

[$org, $asm, $gs, $name] = [(string) ($_GET['organism'] ?? ''), (string) ($_GET['assembly'] ?? ''),
                            (string) ($_GET['gene_set'] ?? ''), (string) ($_GET['atlas'] ?? '')];
$what = (string) ($_GET['what'] ?? 'info');
if (!preg_match('/^[a-z0-9_-]+$/', $name) || !moop_atlas_visible($org, $asm, $gs, $name)) atlas_api_fail(404, 'Not found');
$file = moop_atlas_file($org, $asm, $gs, $name);
$db   = moop_atlas_open($file);
if (!$db) atlas_api_fail(404, 'Not found');
// Access is decided: release the session lock so the mesh and the gene load in parallel.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
$meta = moop_atlas_meta($db);
$etag = '"' . md5($file . '|' . ($meta['built_at'] ?? '') . '|' . $what . '|' . ($_GET['gene'] ?? '') . '|' . ($_GET['name'] ?? '')) . '"';
// private: a shared cache must never hand a restricted atlas to someone else.
header('Cache-Control: private, max-age=3600');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if ($what !== 'search' && trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }

if ($what === 'asset') {
    $a = moop_atlas_asset($db, (string) ($_GET['name'] ?? ''));
    if (!$a) atlas_api_fail(404, 'Not found');
    header('Content-Type: ' . $a['mime']);
    header('Content-Length: ' . strlen($a['data']));
    echo $a['data'];
    exit;
}

header('Content-Type: application/json');
$b64 = fn($blob) => $blob === null ? null : base64_encode($blob);

if ($what === 'info') {
    $keep = ['name', 'title', 'paper_title', 'description', 'abstract', 'citation', 'pmid', 'data_url', 'value_label',
             'image_label', 'axis_map', 'camera', 'aspect', 'colorscale', 'value_encoding', 'vertex_count', 'gene_count',
             'genes_with_values', 'default_gene'];
    $out = array_intersect_key($meta, array_flip($keep));
    $out += ['organism' => $org, 'assembly' => $asm, 'gene_set' => $gs];
    $out['assets'] = $db->query('SELECT name FROM asset')->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($what === 'mesh') {
    $m = moop_atlas_mesh($db);
    if (!$m) atlas_api_fail(404, 'Not found');
    echo json_encode(['vertex_count' => strlen($m['vertices']) / 12, 'vertices' => $b64($m['vertices']),
                      'faces' => $b64($m['faces']), 'overlay' => $b64($m['overlay'])]);
    exit;
}

if ($what === 'search') {
    echo json_encode(['genes' => moop_atlas_search($db, (string) ($_GET['q'] ?? ''), 20)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($what === 'gene') {
    $gene = trim((string) ($_GET['gene'] ?? ''));
    if ($gene === '' || strlen($gene) > 200) atlas_api_fail(400, 'No gene');
    $row = moop_atlas_gene($db, moop_atlas_lookup_ids($org, $asm, $gs, $gene));
    if (!$row) { echo json_encode(['gene_id' => $gene, 'status' => 'not_in_study']); exit; }
    $out = ['gene_id' => $row['gene_id'], 'name' => $row['name'], 'status' => $row['vals'] === null ? 'not_expressed' : 'expressed',
            'vmin' => $row['vmin'] === null ? null : (float) $row['vmin'], 'vmax' => $row['vmax'] === null ? null : (float) $row['vmax'],
            'encoding' => $meta['value_encoding'] ?? 'uint8', 'vals' => $b64($row['vals'])];
    $out['similar'] = array_map(fn($c) => ['gene_id' => $c['other_id'], 'name' => $c['name'], 'r' => (float) $c['r']],
                                moop_atlas_correlated($db, $row['gene_id']));
    // The picture: a gene image of the analysis the atlas names (lib/gene_images.php does the access check).
    $out['image'] = null;
    $an = moop_atlas_image_analysis($meta);
    if ($an !== '') {
        require_once __DIR__ . '/../lib/gene_images.php';
        foreach (moop_images_open($org) ? moop_images_for_accessions($org, [$row['gene_id']], 20) : [] as $img) {
            if ((string) $img['analysis'] !== $an || in_array($img['kind'], MOOP_IMAGE_GALLERY_ONLY, true)) continue;
            $u = moop_url('api/gene_image.php') . '?organism=' . rawurlencode($org) . '&file=' . rawurlencode($img['file']);
            $out['image'] = ['url' => $u, 'caption' => (string) ($img['caption'] ?? '')];
            break;
        }
    }
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

atlas_api_fail(400, 'Unknown request');
