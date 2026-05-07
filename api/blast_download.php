<?php
/**
 * BLAST Results Download
 *
 * Accepts a POST with the result content and echoes it back as a file download.
 * Used because Chrome blocks data: URI downloads over HTTP.
 *
 * POST parameters:
 *   content  - The file content to download
 *   filename - Desired filename (basename only; path components are stripped)
 *   type     - 'txt' | 'tsv' | 'xml'
 */

include_once __DIR__ . '/../tools/tool_init.php';

$type    = $_POST['type']    ?? 'txt';
$content = $_POST['content'] ?? '';
$raw_fn  = $_POST['filename'] ?? 'blast_results.txt';
$filename = basename(preg_replace('/[^A-Za-z0-9._\-]/', '_', $raw_fn));

$mime_map = [
    'txt' => 'text/plain',
    'tsv' => 'text/tab-separated-values',
    'xml' => 'application/xml',
];
$mime = $mime_map[$type] ?? 'text/plain';

header('Content-Type: ' . $mime . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($content));
echo $content;
