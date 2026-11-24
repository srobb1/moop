<?php
/**
 * BLAST results download: one saved file of a finished search, exactly as BLAST wrote it.
 *
 *   GET api/blast_download.php?job=<id>&format=txt|tsv|xml
 *
 * Used by the BLAST page's download buttons AND the links api/v1/blast.php hands an agent, so
 * the two give the same bytes (lib/blast_jobs.php). Files are kept 24 hours.
 *
 * Until 2026-11-24 this echoed back text the browser POSTed from hidden <div>s, which added
 * template indentation to the TXT and Windows line endings to all three.
 */

include_once __DIR__ . '/../tools/tool_init.php';
require_once __DIR__ . '/../lib/blast_jobs.php';

$file = blast_job_file((string) ($_GET['job'] ?? ''), (string) ($_GET['format'] ?? ''));
if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo t_plain('These BLAST results are no longer available. Result files are kept for 24 hours -- please run the search again.') . "\n";
    exit;
}

header('Content-Type: ' . $file['mime'] . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($file['path']));
readfile($file['path']);
