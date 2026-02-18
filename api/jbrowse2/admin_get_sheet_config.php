<?php
/**
 * JBrowse Admin API: Get Sheet Config
 * 
 * Returns existing Google Sheet configuration for organism/assembly.
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../admin/admin_access_check.php';

header('Content-Type: application/json');

// Get parameters
$organism = $_GET['organism'] ?? '';
$assembly = $_GET['assembly'] ?? '';

if (empty($organism) || empty($assembly)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$config = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$sheetConfigPath = "$organism_data/$organism/$assembly/jbrowse_tracks_sheet.txt";

if (!file_exists($sheetConfigPath)) {
    echo json_encode(['success' => false, 'message' => 'No sheet registered']);
    exit;
}

// Read configuration
$sheetConfig = parse_ini_file($sheetConfigPath);

if ($sheetConfig === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to read config']);
    exit;
}

echo json_encode([
    'success' => true,
    'config' => $sheetConfig
]);
