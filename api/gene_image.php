<?php
/**
 * One gene image (in situ, plot, movie) from organisms/<organism>/.images/, access-checked.
 *
 *   GET api/gene_image.php?organism=Schmidtea_mediterranea&file=davies_2017/SMED30001362_piwi-3_S3.png[&size=thumb]
 *
 * organisms/ is denied to the web by nginx, so this is the only way an image leaves the server.
 * The file must be LISTED in that organism's gene_images.sqlite (built from .images/images.tsv),
 * resolve inside images/, and belong to a data set the visitor may see (moop_dataset_visible():
 * organism + assembly + gene set, same rule as the gene set itself). A private image and a
 * missing one both answer 404, so a private image cannot be shown to exist.
 *
 * Range requests are honoured (movies: browsers seek with them). Read-only.
 * Docs: docs/ORGANISM_SIDE_FILES.md §2-3.
 */
include_once __DIR__ . '/../tools/tool_init.php';
include_once __DIR__ . '/../lib/gene_images.php';

function gene_image_404(): void {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit("Not found\n");
}

$organism = (string) ($_GET['organism'] ?? '');
$file     = (string) ($_GET['file'] ?? '');
$key      = (string) ($_GET['key'] ?? '');
if (!moop_plain_name($organism) || ($file === '' && $key === '') || strpos($file . $key, "\0") !== false) gene_image_404();

if ($key !== '') {
    // ?key=<analysis>: that analysis's key image (a cluster map) -- visible when one of its images is.
    $path = moop_image_key_path($organism, $key);
} else {
    $row  = moop_image_visible_row($organism, $file);   // null if unlisted OR not visible to this visitor
    // ?poster=1: the movie's still (its row's 'poster' file) -- same access check, same 404 when there is none.
    $path = $row ? moop_image_path($organism, isset($_GET['poster']) ? (string) $row['poster'] : $file) : null;
}
if (!$path) gene_image_404();
// Access is decided; release the session lock so a page's many images load in parallel.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// ?size=thumb: a small copy made once into the cache (moop_image_thumbnail); if it cannot be
// made (no gd, a movie, already small) the original is sent instead.
if (($_GET['size'] ?? '') === 'thumb' && ($thumb = moop_image_thumbnail($organism, $path))) $path = $thumb;

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$type = MOOP_IMAGE_EXTS[$ext] ?? null;
if (!$type) gene_image_404();
$size = filesize($path);
$mtime = filemtime($path);
$etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

header('Content-Type: ' . $type);
header('X-Content-Type-Options: nosniff');
// private: a shared cache must never hand a restricted image to someone else.
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Accept-Ranges: bytes');
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }

$start = 0; $end = $size - 1;
if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $m) && ($m[1] !== '' || $m[2] !== '')) {
    if ($m[1] === '') { $start = max(0, $size - (int) $m[2]); }                 // last N bytes
    else { $start = (int) $m[1]; if ($m[2] !== '') $end = min((int) $m[2], $size - 1); }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;

$fh = fopen($path, 'rb');
if (!$fh) gene_image_404();
fseek($fh, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fh)) {
    $chunk = fread($fh, (int) min(65536, $left));
    if ($chunk === false) break;
    echo $chunk;
    $left -= strlen($chunk);
}
fclose($fh);
