<?php
/**
 * Galaxy MAFFT Alignment API
 * 
 * Accepts sequences and submits them to Galaxy for MAFFT alignment
 * POST /api/galaxy_mafft_align.php
 * 
 * Expected JSON input:
 * {
 *   "sequences": [
 *     {"id": "seq1", "header": "Description", "seq": "ATCG..."},
 *     {"id": "seq2", "header": "Description", "seq": "ATCG..."}
 *   ]
 * }
 * 
 * Returns:
 * {
 *   "success": true/false,
 *   "history_id": "...",
 *   "dataset_id": "...",
 *   "galaxy_url": "...",
 *   "visualization_url": "...",
 *   "error": "error message if failed"
 * }
 */

header('Content-Type: application/json');

// Include configuration
require_once __DIR__ . '/../config/config.php';

// Check if we have Galaxy configured
if (empty($site_config['galaxy_api_key']) || empty($site_config['galaxy_url'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Galaxy not configured. Please set galaxy_api_key and galaxy_url in site config.'
    ]);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['sequences']) || !is_array($input['sequences'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid input. Expected "sequences" array.'
    ]);
    exit;
}

// Build FASTA content from sequences
$fasta = '';
foreach ($input['sequences'] as $seq) {
    if (empty($seq['id']) || empty($seq['seq'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Each sequence must have "id" and "seq" fields.'
        ]);
        exit;
    }
    
    $header = !empty($seq['header']) ? $seq['header'] : $seq['id'];
    $fasta .= ">" . $seq['id'] . " " . $header . "\n";
    $fasta .= $seq['seq'] . "\n";
}

try {
    // Step 1: Create a new history
    $historyName = 'MAFFT_alignment_' . date('YmdHis');
    $historyData = json_encode(['name' => $historyName]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $site_config['galaxy_url'] . '/api/histories',
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $site_config['galaxy_api_key'],
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $historyData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to create history: HTTP $httpCode");
    }
    
    $historyResp = json_decode($response, true);
    if (empty($historyResp['id'])) {
        throw new Exception("No history ID in response");
    }
    
    $historyId = $historyResp['id'];
    
    // Step 2: Upload FASTA file using paste
    $uploadData = json_encode([
        'history_id' => $historyId,
        'tool_id' => 'upload1',
        'inputs' => [
            'files_0|url_paste' => $fasta,
            'files_0|type' => 'upload_dataset',
            'files_0|NAME' => 'sequences.fasta',
            'dbkey' => '?',
            'file_type' => 'fasta'
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $site_config['galaxy_url'] . '/api/tools',
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $site_config['galaxy_api_key'],
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $uploadData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to upload file: HTTP $httpCode");
    }
    
    $uploadResp = json_decode($response, true);
    if (empty($uploadResp['outputs'][0]['id'])) {
        throw new Exception("No dataset ID in upload response");
    }
    
    $datasetId = $uploadResp['outputs'][0]['id'];
    
    // Step 3: Wait for upload to complete
    $maxWait = 30;
    $waited = 0;
    while ($waited < $maxWait) {
        sleep(2);
        $waited += 2;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $site_config['galaxy_url'] . '/api/datasets/' . $datasetId,
            CURLOPT_HTTPHEADER => ['x-api-key: ' . $site_config['galaxy_api_key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $datasetResp = json_decode($response, true);
        if (!empty($datasetResp['state'])) {
            if ($datasetResp['state'] === 'ok') {
                break;
            } elseif ($datasetResp['state'] === 'error') {
                throw new Exception("Upload failed: state = error");
            }
        }
    }
    
    // Step 4: Run MAFFT
    $mafftData = json_encode([
        'history_id' => $historyId,
        'tool_id' => 'toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.3',
        'inputs' => [
            'inputSequences' => [
                'src' => 'hda',
                'id' => $datasetId
            ],
            'outputFormat' => 'fasta',
            'matrix_condition|matrix' => 'BLOSUM62',
            'flavour' => 'mafft-fftns'
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $site_config['galaxy_url'] . '/api/tools',
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $site_config['galaxy_api_key'],
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $mafftData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to run MAFFT: HTTP $httpCode");
    }
    
    $mafftResp = json_decode($response, true);
    if (empty($mafftResp['outputs'][0]['id'])) {
        throw new Exception("No output dataset ID from MAFFT");
    }
    
    $alignmentId = $mafftResp['outputs'][0]['id'];
    
    // Return success with Galaxy links
    echo json_encode([
        'success' => true,
        'history_id' => $historyId,
        'dataset_id' => $alignmentId,
        'galaxy_url' => $site_config['galaxy_url'],
        'history_url' => $site_config['galaxy_url'] . '/histories/view?id=' . $historyId,
        'visualization_url' => $site_config['galaxy_url'] . '/visualizations/display?visualization=alignmentviewer&dataset_id=' . $alignmentId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
