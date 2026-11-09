<?php
/**
 * Site Configuration -> Ontologies -> "Rebuild now".
 *
 * Takes NO input: it leaves a request for housekeeping (lib/ontologies.php's marker) and starts
 * the 'ontologies' task in the background, which rebuilds every configured ontology from its
 * sources. Admin-only and CSRF-checked by admin_init.php. The page request itself never
 * downloads or builds anything -- that happens only in the background run.
 */
include_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/ontologies.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false]); exit; }

$dir = moop_ontology_dir();
if ((!is_dir($dir) && !moop_ensure_cache_dir($dir)) || @file_put_contents(moop_ontology_rebuild_marker(), date('c')) === false) {
    echo json_encode(['success' => false, 'message' => "Could not write to $dir -- check the cache folder's permissions (Admin -> Filesystem Permissions)."]);
    exit;
}
$started = housekeeping_spawn_background('ontologies');
echo json_encode(['success' => true, 'started' => $started,
                  'message' => $started ? 'Rebuilding in the background -- reload this page in a minute to see the result.'
                                        : 'Requested -- housekeeping is busy and will rebuild on its next run.']);
