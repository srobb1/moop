<?php
/**
 * JBrowse Admin API: Delete Track
 *
 * Deletes one track config file, identified by where it actually lives:
 * {organism}/{assembly}/{type}/{fileId}.json.
 *
 * It used to look for {type}/{trackId}.json, but only combo files are named after their
 * JBrowse trackId — every other track is named after its sheet track id — so Delete
 * answered "Track not found" for 1,251 of 1,259 tracks. That same bug was the only thing
 * stopping the listing from deleting a gene annotation track, so that is now refused
 * explicitly: a gene track belongs to gene-set registration, and deleting it takes the
 * gene models out of the browser for that assembly.
 */

// admin_init.php rather than admin_access_check.php: it performs the same admin-role
// check AND verifies the CSRF token on POST. Using the bare access check left this
// endpoint authenticated but forgeable.
require_once __DIR__ . '/../admin_init.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$organism = $_POST['organism'] ?? '';
$assembly = $_POST['assembly'] ?? '';
$type     = $_POST['type'] ?? '';
$fileId   = $_POST['fileId'] ?? '';

// All four become path components.
foreach ([$organism, $assembly, $type, $fileId] as $part) {
    if ($part === '' || $part[0] === '.' || !preg_match('/^[A-Za-z0-9._-]+$/', $part)) {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters']);
        exit;
    }
}

$config    = ConfigManager::getInstance();
$trackFile = $config->getPath('metadata_path') . "/jbrowse2-configs/tracks/$organism/$assembly/$type/$fileId.json";

if (!is_file($trackFile)) {
    echo json_encode(['success' => false, 'error' => "Track not found: $fileId"]);
    exit;
}

$track = loadJsonFile($trackFile, []);
if (isset($track['metadata']['gene_set']) || !empty($track['metadata']['is_primary_gene_track'])) {
    echo json_encode([
        'success' => false,
        'error'   => "$fileId is a gene annotation track, created by gene-set registration. "
                   . 'It is not deleted from the track listing — archive the gene set instead.',
    ]);
    exit;
}

// Delete the file
if (unlink($trackFile)) {
    echo json_encode(['success' => true, 'message' => "Track deleted: $fileId"]);
} else {
    echo json_encode(['success' => false, 'error' => "Failed to delete track file $trackFile — check that the web server can write to its directory"]);
}
