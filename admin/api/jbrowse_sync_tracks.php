<?php
/**
 * JBrowse Admin API: Sync Tracks from Google Sheet
 * 
 * Calls generate_tracks_from_sheet.php to sync track metadata.
 */

// admin_init.php rather than admin_access_check.php: it performs the same admin-role
// check AND verifies the CSRF token on POST. Using the bare access check left this
// endpoint authenticated but forgeable.
require_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/functions_data.php';

header('Content-Type: application/json');
set_time_limit(0);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$syncMode = $_POST['syncMode'] ?? 'single';
$organism = $_POST['syncOrganism'] ?? '';
$assembly = $_POST['syncAssembly'] ?? '';
$forceRegenerate = isset($_POST['forceRegenerate']);
$removeMissing = isset($_POST['removeMissing']);
$dryRun = isset($_POST['dryRun']);

$config        = ConfigManager::getInstance();
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$sheets_base   = "$metadata_path/jbrowse2-configs/sheets";
$site_path     = $config->getPath('site_path');

$results = [];
$errors = [];

// Determine which assemblies to sync
$assembliesToSync = [];

if ($syncMode === 'all') {
    // Find all assemblies with registered sheets
    if (is_dir($sheets_base)) {
        foreach (scandir($sheets_base) as $org) {
            if ($org === '.' || $org === '..') continue;
            $orgPath = "$sheets_base/$org";
            if (!is_dir($orgPath)) continue;
            foreach (scandir($orgPath) as $asm) {
                if ($asm === '.' || $asm === '..') continue;
                if (file_exists("$orgPath/$asm/jbrowse_tracks_sheet.txt")) {
                    $assembliesToSync[] = ['organism' => $org, 'assembly' => $asm];
                }
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
    
    if (!isJBrowseAssemblyRegistered($org, $asm)) {
        $errors[] = "$org/$asm: not registered in JBrowse — register the assembly first";
        continue;
    }
    
    $sheetFile = "$sheets_base/$org/$asm/jbrowse_tracks_sheet.txt";
    
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
    $cmd = "php " . escapeshellarg("$site_path/scripts/generate_tracks_from_sheet.php") . " ";
    $cmd .= escapeshellarg($sheetId) . " ";
    $cmd .= "--gid " . escapeshellarg($gid) . " ";
    $cmd .= "--organism " . escapeshellarg($org) . " ";
    $cmd .= "--assembly " . escapeshellarg($asm) . " ";
    
    // Two separate choices. They used to be one checkbox, ticked by default, so every
    // ordinary sync also deleted whatever was not in the sheet.
    if ($forceRegenerate) {
        $cmd .= "--force ";
    }
    
    if ($removeMissing) {
        $cmd .= "--clean ";
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
        // Record when the tracks were last brought in line with the sheet. Checked, not
        // assumed: an unwritable sheet file would otherwise leave the table showing an old
        // date after a sync that worked.
        if (!$dryRun) {
            $sheetCfg = parse_ini_file($sheetFile, false, INI_SCANNER_RAW) ?: [];
            unset($sheetCfg['AUTO_SYNC']);   // never read by anything
            $sheetCfg['LAST_SYNC'] = date('Y-m-d H:i:s');
            if (!writeJBrowseSheetConfig($org, $asm, $sheetCfg)) {
                $errors[] = "$org/$asm: tracks synced, but the sync date could not be saved to $sheetFile";
            }
        }
        $results[] = [
            'organism' => $org,
            'assembly' => $asm,
            'success' => true,
            'output' => $outputText,
            'sheet' => getJBrowseSheetStatus($org, $asm)
        ];
    } else {
        $errors[] = "$org/$asm: Sync failed (exit code $returnCode)";
        $results[] = [
            'organism' => $org,
            'assembly' => $asm,
            'success' => false,
            'output' => $outputText,
            'sheet' => getJBrowseSheetStatus($org, $asm)
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
