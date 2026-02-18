<?php
/**
 * JBrowse Admin API: Delete Track(s)
 * 
 * Deletes track metadata files.
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../admin/admin_access_check.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$trackId = $_POST['trackId'] ?? '';
$organism = $_POST['organism'] ?? '';
$assembly = $_POST['assembly'] ?? '';

if (empty($trackId) || empty($organism) || empty($assembly)) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$config = ConfigManager::getInstance();
$tracks_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/tracks';

// Find the track file
$trackTypes = ['bigwig', 'bam', 'vcf', 'gff', 'gtf', 'paf', 'bed', 'cram', 'combo'];
$trackFile = null;

foreach ($trackTypes as $type) {
    $path = "$tracks_dir/$organism/$assembly/$type/$trackId.json";
    if (file_exists($path)) {
        $trackFile = $path;
        break;
    }
}

if (!$trackFile) {
    echo json_encode([
        'success' => false,
        'error' => "Track not found: $trackId"
    ]);
    exit;
}

// Delete the file
if (unlink($trackFile)) {
    echo json_encode([
        'success' => true,
        'message' => "Track deleted: $trackId"
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => "Failed to delete track file"
    ]);
}
