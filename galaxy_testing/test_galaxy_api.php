<?php
/**
 * Galaxy MAFFT API Test with Stored Configuration
 * 
 * Tests the Galaxy integration by:
 * 1. Loading Galaxy configuration from site_config
 * 2. Sending sequences to /api/galaxy/mafft.php
 * 3. Polling /api/galaxy/results.php for job status
 * 4. Retrieving and displaying results
 */

// Load configuration
require_once __DIR__ . '/config/site_config.php';
require_once __DIR__ . '/includes/ConfigManager.php';

$config = ConfigManager::getInstance();
$galaxyConfig = $config->get('galaxy');

// Validate Galaxy configuration
if (!$galaxyConfig || !$galaxyConfig['enabled']) {
    echo "❌ Galaxy integration is not enabled in configuration\n";
    exit(1);
}

if (!$galaxyConfig['api_key']) {
    echo "❌ Galaxy API key is not configured\n";
    exit(1);
}

echo "✓ Galaxy configuration loaded\n";
echo "  - Instance: " . $galaxyConfig['instance_url'] . "\n";
echo "  - API Key: " . substr($galaxyConfig['api_key'], 0, 8) . "...\n\n";

// Test sequences (NTNG1 protein from search results)
$sequences = [
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000619.1',
        'header' => 'NTNG1 netrin G1',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFCGM'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000620.1',
        'header' => 'NTNG1 netrin G1',
        'sequence' => 'MCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGS'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000621.1',
        'header' => 'NTNG1 netrin G1',
        'sequence' => 'MVTLVVSTGKDNQSTPRNPAVCKCNLHATGCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANI'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000622.1',
        'header' => 'NTNG1 netrin G1',
        'sequence' => 'MDCECFGHSNRCSYIDVLSTFICVSCKHNTRGQNCELCRLGYYRNTSAKLDDENVCIECNCSRTGSVRDRCNEKGICECKQGTTGPKCDKCLRGYYWHNGCQ'
    ],
    [
        'id' => 'CCA3t017421001.1',
        'header' => 'NTNG1 netrin G1',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFCGMGRYDTKMALVALVDDLCGNPYMCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGSCKCNLHATGCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANIYCECFSHSNRCSYIDNVIVAALAQSVIAVMKKEFVSVSREQQGLSVISVCEDITGTTRVVNDKRL'
    ]
];

echo "════════════════════════════════════════════════════════════════\n";
echo "  Galaxy MAFFT API Test\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// Step 1: Test sequence submission
echo "Step 1: Submitting " . count($sequences) . " sequences to Galaxy MAFFT...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$requestPayload = [
    'sequences' => $sequences,
    'options' => [
        'flavor' => 'default'
    ]
];

echo "Request payload:\n";
echo json_encode($requestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Make the request to our Galaxy endpoint
$ch = curl_init('http://localhost/moop/api/galaxy/mafft.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
    exit(1);
}

echo "HTTP Status: $httpCode\n\n";

$result = json_decode($response, true);

if ($httpCode !== 200) {
    echo "❌ API returned error:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

if (!$result['success']) {
    echo "❌ API error: " . ($result['error'] ?? 'Unknown error') . "\n";
    exit(1);
}

echo "✅ Sequences submitted successfully!\n\n";
echo "Response:\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Extract job info
$jobId = $result['job_id'] ?? null;
$outputId = $result['output_id'] ?? null;
$historyId = $result['history_id'] ?? null;

if (!$jobId || !$outputId) {
    echo "❌ Missing job_id or output_id in response\n";
    exit(1);
}

echo "Job ID: $jobId\n";
echo "Output ID: $outputId\n";
echo "History ID: $historyId\n\n";

// Step 2: Poll for completion
echo "Step 2: Polling for job completion...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$maxWaitTime = 300; // 5 minutes
$pollInterval = 5;  // 5 seconds
$elapsed = 0;

while ($elapsed < $maxWaitTime) {
    echo "Checking status (elapsed: ${elapsed}s)...\n";
    
    $ch = curl_init("http://localhost/moop/api/galaxy/results.php?action=status&job_id=$jobId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $statusResult = json_decode($response, true);
    
    if ($httpCode === 200 && $statusResult['success']) {
        $status = $statusResult['status'];
        echo "  Status: " . json_encode($status, JSON_PRETTY_PRINT) . "\n";
        
        if ($status['state'] === 'ok') {
            echo "\n✅ Job completed successfully!\n\n";
            break;
        } elseif ($status['state'] === 'error') {
            echo "\n❌ Job failed: " . ($status['stderr'] ?? 'Unknown error') . "\n";
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

// Step 3: Retrieve results
echo "Step 3: Retrieving alignment results...\n";
echo "───────────────────────────────────────────────────────────────\n\n";

$ch = curl_init("http://localhost/moop/api/galaxy/results.php?action=results&output_id=$outputId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resultsData = json_decode($response, true);

if ($httpCode !== 200 || !$resultsData['success']) {
    echo "❌ Failed to retrieve results\n";
    echo json_encode($resultsData, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "✅ Results retrieved!\n\n";

// Display alignment
if (isset($resultsData['alignment'])) {
    echo "Alignment Output:\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo $resultsData['alignment'] . "\n";
} else {
    echo "Raw Results:\n";
    echo json_encode($resultsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n════════════════════════════════════════════════════════════════\n";
echo "  ✅ Test Complete!\n";
echo "════════════════════════════════════════════════════════════════\n";
?>
