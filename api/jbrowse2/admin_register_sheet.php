<?php
/**
 * JBrowse Admin API: Register/Test Google Sheet
 * 
 * Validates and saves Google Sheet configuration for track syncing.
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../admin/admin_access_check.php';
require_once __DIR__ . '/../../lib/JBrowse/GoogleSheetsParser.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? 'test';
$organism = $_POST['organism'] ?? '';
$assembly = $_POST['assembly'] ?? '';
$sheetUrl = $_POST['sheetUrl'] ?? '';
$gid = $_POST['gid'] ?? '0';
$autoSync = isset($_POST['autoSync']);

// Validate inputs
if (empty($organism) || empty($assembly) || empty($sheetUrl)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Extract sheet ID from URL or use as-is
$sheetId = $sheetUrl;
if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $sheetUrl, $matches)) {
    $sheetId = $matches[1];
}

// Extract GID from URL if present
if (preg_match('/[#&]gid=(\d+)/', $sheetUrl, $matches)) {
    $gid = $matches[1];
}

try {
    $parser = new GoogleSheetsParser();
    
    // Download and parse sheet
    $content = $parser->download($sheetId, $gid);
    $lines = explode("\n", $content);
    
    if (empty($lines)) {
        throw new RuntimeException('Sheet is empty');
    }
    
    // Parse header
    $header = str_getcsv(trim($lines[0]), "\t");
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // Remove BOM
    $header = array_map('strtolower', $header);
    
    // Check required columns
    $requiredColumns = ['track_id', 'name', 'track_path'];
    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $header)) {
            $missingColumns[] = $col;
        }
    }
    
    if (!empty($missingColumns)) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing required columns: ' . implode(', ', $missingColumns),
            'foundColumns' => $header
        ]);
        exit;
    }
    
    // Count tracks (non-empty, non-comment rows)
    $trackCount = 0;
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (!empty($line) && !preg_match('/^#/', $line)) {
            $trackCount++;
        }
    }
    
    // If just testing, return validation results
    if ($action === 'test') {
        echo json_encode([
            'success' => true,
            'columns' => $requiredColumns,
            'trackCount' => $trackCount,
            'message' => 'Sheet validated successfully'
        ]);
        exit;
    }
    
    // Register: Save sheet configuration
    if ($action === 'register') {
        $config = ConfigManager::getInstance();
        $organism_data = $config->getPath('organism_data');
        $sheetConfigPath = "$organism_data/$organism/$assembly/jbrowse_tracks_sheet.txt";
        
        // Ensure directory exists
        $dir = dirname($sheetConfigPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        
        // Create config file
        $configContent = "SHEET_ID=$sheetId\n";
        $configContent .= "GID=$gid\n";
        $configContent .= "REGISTERED_DATE=" . date('Y-m-d H:i:s') . "\n";
        $configContent .= "AUTO_SYNC=" . ($autoSync ? 'true' : 'false') . "\n";
        
        if (file_put_contents($sheetConfigPath, $configContent) === false) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to write configuration file'
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Sheet registered for $organism/$assembly with $trackCount tracks",
            'trackCount' => $trackCount
        ]);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
