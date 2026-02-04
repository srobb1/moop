<?php
/**
 * Direct Galaxy API Test (Hardcoded)
 * 
 * This test bypasses the GalaxyClient class and talks directly to Galaxy
 * to debug the integration without dependency issues.
 */

// Hardcoded Galaxy configuration
$GALAXY_URL = 'https://usegalaxy.org';
$GALAXY_API_KEY = '572d0be92e585b105efea8879b73fe86';

// Test sequences
$sequences = [
    [
        'id' => 'seq1',
        'header' => 'Bradypodion_pumilum_JAWDJD010000004.1_000619.1',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFCGM'
    ],
    [
        'id' => 'seq2',
        'header' => 'Bradypodion_pumilum_JAWDJD010000004.1_000620.1',
        'sequence' => 'MCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGS'
    ],
    [
        'id' => 'seq3',
        'header' => 'CCA3t017421001.1',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFCGMGRYDTKMALVALVDDLCGNPYMCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGSCKCNLHATGCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANIYCECFSHSNRCSYIDNVIVAALAQSVIAVMKKEFVSVSREQQGLSVISVCEDITGTTRVVNDKRL'
    ]
];

echo "════════════════════════════════════════════════════════════════\n";
echo "  Direct Galaxy API Test (Hardcoded)\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// Step 1: Create a history
echo "Step 1: Creating Galaxy history...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$ch = curl_init("$GALAXY_URL/api/histories?key=$GALAXY_API_KEY");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => 'MOOP MAFFT Test']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
    exit(1);
}

echo "HTTP Status: $httpCode\n";
$historyData = json_decode($response, true);

if ($httpCode !== 200) {
    echo "❌ Failed to create history\n";
    echo json_encode($historyData, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$historyId = $historyData['id'];
echo "✅ History created: $historyId\n\n";

// Step 2: Create FASTA file and upload it
echo "Step 2: Creating and uploading FASTA file...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

// Create FASTA content
$fasta = '';
foreach ($sequences as $seq) {
    $fasta .= '>' . $seq['header'] . "\n";
    $fasta .= wordwrap($seq['sequence'], 80, "\n", true) . "\n";
}

echo "FASTA content:\n";
echo $fasta . "\n";

// Save to temp file
$tempFile = tempnam(sys_get_temp_dir(), 'mafft_');
file_put_contents($tempFile, $fasta);

// Upload file using cURL file upload - simpler approach using url_paste
$ch = curl_init("$GALAXY_URL/api/histories/$historyId/contents?key=$GALAXY_API_KEY");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

// Read file content
$fileContent = file_get_contents($tempFile);

// Use JSON body for upload
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'file_type' => 'fasta',
    'dbkey' => '?',
    'upload_option' => 'url_paste',
    'files' => [
        'url_paste' => $fileContent
    ]
]));

curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ cURL Error uploading file: $curlError\n";
    unlink($tempFile);
    exit(1);
}

echo "HTTP Status: $httpCode\n";
$uploadData = json_decode($response, true);

if ($httpCode !== 200 && $httpCode !== 201) {
    echo "❌ Failed to upload file\n";
    echo json_encode($uploadData, JSON_PRETTY_PRINT) . "\n";
    unlink($tempFile);
    exit(1);
}

$datasetId = is_array($uploadData) ? ($uploadData[0]['id'] ?? $uploadData['id'] ?? null) : null;

if (!$datasetId) {
    echo "❌ Could not extract dataset ID from upload response\n";
    echo json_encode($uploadData, JSON_PRETTY_PRINT) . "\n";
    unlink($tempFile);
    exit(1);
}

echo "✅ File uploaded with dataset ID: $datasetId\n\n";
unlink($tempFile);

// Step 3: Run MAFFT tool
echo "Step 3: Running MAFFT...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$toolInputs = [
    'input' => [
        'src' => 'hda',
        'id' => $datasetId
    ]
];

echo "Tool inputs:\n";
echo json_encode($toolInputs, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init("$GALAXY_URL/api/tools/toolshed.g2.bx.psu.edu/repos/rnateam/mafft/mafft/7.305/invoke?key=$GALAXY_API_KEY");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'history_id' => $historyId,
    'inputs' => $toolInputs
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
    exit(1);
}

echo "HTTP Status: $httpCode\n";
$toolData = json_decode($response, true);

if ($httpCode !== 200) {
    echo "❌ Failed to run tool\n";
    echo json_encode($toolData, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "✅ Tool invoked!\n";
echo json_encode($toolData, JSON_PRETTY_PRINT) . "\n\n";

$jobId = $toolData['jobs'][0]['id'] ?? null;
$outputId = $toolData['outputs'][0]['id'] ?? null;

if (!$jobId) {
    echo "❌ Could not extract job ID\n";
    exit(1);
}

echo "Job ID: $jobId\n";
echo "Output ID: $outputId\n\n";

// Step 4: Poll for completion
echo "Step 4: Polling for job completion...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$maxWaitTime = 300;
$pollInterval = 5;
$elapsed = 0;

while ($elapsed < $maxWaitTime) {
    echo "Checking status (elapsed: ${elapsed}s)...\n";
    
    $ch = curl_init("$GALAXY_URL/api/jobs/$jobId?key=$GALAXY_API_KEY");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $jobStatus = json_decode($response, true);
    
    if ($httpCode === 200) {
        $state = $jobStatus['state'];
        echo "  State: $state\n";
        
        if ($state === 'ok') {
            echo "\n✅ Job completed!\n\n";
            break;
        } elseif ($state === 'error') {
            echo "\n❌ Job failed\n";
            echo json_encode($jobStatus, JSON_PRETTY_PRINT) . "\n";
            exit(1);
        }
    }
    
    sleep($pollInterval);
    $elapsed += $pollInterval;
}

if ($elapsed >= $maxWaitTime) {
    echo "❌ Timeout waiting for job completion\n";
    exit(1);
}

// Step 5: Get results
echo "Step 5: Retrieving alignment results...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$ch = curl_init("$GALAXY_URL/api/datasets/$outputId/display?key=$GALAXY_API_KEY");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Failed to get results\n";
    exit(1);
}

echo "✅ Results retrieved!\n\n";
echo "Alignment:\n";
echo "───────────────────────────────────────────────────────────────\n";
echo $response . "\n";

echo "\n════════════════════════════════════════════════════════════════\n";
echo "  ✅ Test Complete!\n";
echo "════════════════════════════════════════════════════════════════\n";
?>
