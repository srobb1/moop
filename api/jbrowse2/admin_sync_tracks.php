<?php
/**
 * JBrowse Admin API: Sync Tracks from Google Sheet
 * 
 * Calls generate_tracks_from_sheet.php to sync track metadata.
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../admin/admin_access_check.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$syncMode = $_POST['syncMode'] ?? 'single';
$organism = $_POST['syncOrganism'] ?? '';
$assembly = $_POST['syncAssembly'] ?? '';
$forceRegenerate = isset($_POST['forceRegenerate']);
$dryRun = isset($_POST['dryRun']);

$config = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$site_path = $config->getPath('site_path');

$results = [];
$errors = [];

// Determine which assemblies to sync
$assembliesToSync = [];

if ($syncMode === 'all') {
    // Find all assemblies with registered sheets
    foreach (scandir($organism_data) as $org) {
        if ($org === '.' || $org === '..') continue;
        $orgPath = "$organism_data/$org";
        if (!is_dir($orgPath)) continue;
        
        foreach (scandir($orgPath) as $asm) {
            if ($asm === '.' || $asm === '..' || $asm === 'organism.sqlite') continue;
            $asmPath = "$orgPath/$asm";
            if (!is_dir($asmPath)) continue;
            
            $sheetFile = "$asmPath/jbrowse_tracks_sheet.txt";
            if (file_exists($sheetFile)) {
                $assembliesToSync[] = ['organism' => $org, 'assembly' => $asm];
            }
        }
    }
} elseif ($syncMode === 'single') {
    if (empty($organism) || empty($assembly)) {
        echo json_encode(['success' => false, 'error' => 'Organism and assembly required']);
        exit;
    }
    $assembliesToSync[] = ['organism' => $organism, 'assembly' => $assembly];
}

if (empty($assembliesToSync)) {
    echo json_encode(['success' => false, 'error' => 'No assemblies to sync']);
    exit;
}

// Sync each assembly
foreach ($assembliesToSync as $item) {
    $org = $item['organism'];
    $asm = $item['assembly'];
    
    $sheetFile = "$organism_data/$org/$asm/jbrowse_tracks_sheet.txt";
    
    if (!file_exists($sheetFile)) {
        $errors[] = "$org/$asm: No registered sheet found";
        continue;
    }
    
    // Read sheet configuration
    $sheetConfig = parse_ini_file($sheetFile);
    $sheetId = $sheetConfig['SHEET_ID'] ?? '';
    $gid = $sheetConfig['GID'] ?? '0';
    
    if (empty($sheetId)) {
        $errors[] = "$org/$asm: Invalid sheet configuration";
        continue;
    }
    
    // Build command
    $cmd = "php " . escapeshellarg("$site_path/tools/jbrowse/generate_tracks_from_sheet.php") . " ";
    $cmd .= escapeshellarg($sheetId) . " ";
    $cmd .= "--gid " . escapeshellarg($gid) . " ";
    $cmd .= "--organism " . escapeshellarg($org) . " ";
    $cmd .= "--assembly " . escapeshellarg($asm) . " ";
    
    if ($forceRegenerate) {
        $cmd .= "--force ";
    }
    
    if ($dryRun) {
        $cmd .= "--dry-run ";
    }
    
    $cmd .= "2>&1";
    
    // Execute
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    
    $outputText = implode("\n", $output);
    
    if ($returnCode === 0) {
        $results[] = [
            'organism' => $org,
            'assembly' => $asm,
            'success' => true,
            'output' => $outputText
        ];
    } else {
        $errors[] = "$org/$asm: Sync failed (exit code $returnCode)";
        $results[] = [
            'organism' => $org,
            'assembly' => $asm,
            'success' => false,
            'output' => $outputText
        ];
    }
}

// Prepare response
$allSuccess = empty($errors);
$summary = count($results) . " assembly(ies) processed";
if (!empty($errors)) {
    $summary .= ", " . count($errors) . " error(s)";
}

$output = "=== Track Sync Results ===\n\n";
$output .= "$summary\n\n";

foreach ($results as $result) {
    $status = $result['success'] ? '✓' : '✗';
    $output .= "$status {$result['organism']}/{$result['assembly']}\n";
    if (!empty($result['output'])) {
        $output .= "---\n";
        $output .= $result['output'] . "\n";
        $output .= "---\n\n";
    }
}

if (!empty($errors)) {
    $output .= "\n=== Errors ===\n";
    foreach ($errors as $error) {
        $output .= "✗ $error\n";
    }
}

echo json_encode([
    'success' => $allSuccess,
    'output' => $output,
    'results' => $results,
    'errors' => $errors
]);
