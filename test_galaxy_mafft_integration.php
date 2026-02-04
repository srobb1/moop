<?php
/**
 * MOOP Galaxy Integration Test
 * 
 * Demonstrates the complete workflow:
 * 1. Simulate search results with sequences from multiple species
 * 2. Build an analysis request with selected sequences
 * 3. Submit to Galaxy for MAFFT alignment
 * 4. Monitor job completion
 * 5. Retrieve and display results
 * 
 * Usage: php test_galaxy_mafft_integration.php
 */

// Load configuration
require_once __DIR__ . '/config/site_config.php';
$config = require __DIR__ . '/config/site_config.php';

// Load Galaxy client
require_once __DIR__ . '/lib/galaxy/client.php';

echo "\n=== MOOP Galaxy Integration Test ===\n";
echo "Purpose: Test complete workflow from search results to analysis\n\n";

// ============================================================
// STEP 1: Simulate search results with sequences from multiple species
// ============================================================
echo "[STEP 1] Simulating search results from multiple species...\n";

// These would normally come from a search results page
$searchResults = [
    [
        'species' => 'Bradypodion pumilum',
        'feature_id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000619.1',
        'name' => 'NTNG1 netrin G1',
        'sequence_type' => 'protein',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFC' . "\nGM"
    ],
    [
        'species' => 'Bradypodion pumilum',
        'feature_id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000620.1',
        'name' => 'NTNG1 netrin G1',
        'sequence_type' => 'protein',
        'sequence' => 'MCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLD' . "\n" . 'YGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQL' . "\n" . 'DTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGS'
    ],
    [
        'species' => 'Bradypodion pumilum',
        'feature_id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000621.1',
        'name' => 'NTNG1 netrin G1',
        'sequence_type' => 'protein',
        'sequence' => 'MVTLVVSTGKDNQSTPRNPAVCKCNLHATGCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANI'
    ],
    [
        'species' => 'Bradypodion pumilum',
        'feature_id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000622.1',
        'name' => 'NTNG1 netrin G1',
        'sequence_type' => 'protein',
        'sequence' => 'MDCECFGHSNRCSYIDVLSTFICVSCKHNTRGQNCELCRLGYYRNTSAKLDDENVCIECNCSRTGSVRDRCNEKGICECK' . "\n" . 'QGTTGPKCDKCLRGYYWHNQGCQ'
    ],
    [
        'species' => 'Carya cathayensis',
        'feature_id' => 'CCA3t017421001.1',
        'name' => 'NTNG1 netrin G1',
        'sequence_type' => 'protein',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFC' . "\n" . 'GMGRYDTKMALVALVDDLCGNPYMCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIEL' . "\n" . 'TDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFE' . "\n" . 'IKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGRCKCNLHAT' . "\n" . 'GCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANIYCECFSHSNRCSYIDNVIVAALAQSVIAVMK' . "\n" . 'KEFVSVSREQQGLSVISVCEDITGTTRVVNDKRL'
    ],
    [
        'species' => 'Homo sapiens',
        'feature_id' => 'Q9Y2I2',
        'name' => 'NTNG1_HUMAN',
        'sequence_type' => 'protein',
        'sequence' => 'MYLSRFLSIHALWVTVSSVMQPYPLVWGHYDLCKTQIYTEEGKVWDYMACQPESTDMTKYLKVKLDPPDITCGDPPETFCAMGNPYMCNNECDASTPELAHPPELMFDFEGRHPSTFWQSATWKEYPKPLQVNITLSWSKTIELTDNIVITFESGRPDQMILEKSLDYGRTWQPYQYYATDCLDAFHMDPKSVKDLSQHTVLEIICTEEYSTGYTTNSKIIHFEIKDRFAFFAGPRLRNMASLYGQLDTTKKLRDFFTVTDLRIRLLRPAVGEIFVDELHLARYFYAISDIKVRGRCKCNLHATVCVYDNSKLTCECEHNTTGPDCGKCKKNYQGRPWSPGSYLPIPKGTANTCIPSISSIGNCECFGHSNRCSYIDLLNTVICVSCKHNTRGQHCELCRLGYFRNASAQLDDENVCIECYCNPLGSIHDRCNGSGFCECKTGTTGPKCDECLPGNSWHYGCQPNVCDNELLHCQNGGTCHNNVRCLCPAAYTGILCEKLRCEEAGSCGSDSGQGAPPHGSPALLLLTTLLGTASPLVF'
    ]
];

echo "Found " . count($searchResults) . " sequences:\n";
foreach ($searchResults as $i => $result) {
    echo "  " . ($i+1) . ". " . $result['species'] . " - " . $result['feature_id'] . " (" . strlen($result['sequence']) . " aa)\n";
}

// ============================================================
// STEP 2: Build an analysis request
// ============================================================
echo "\n[STEP 2] Building analysis request...\n";

$analysisRequest = [
    'tool' => 'mafft',
    'user_id' => 1,  // Would come from logged-in user
    'name' => 'NTNG1 Homolog Alignment',
    'description' => 'Multi-species alignment of NTNG1 protein',
    'sequence_type' => 'protein',
    'sequences' => $searchResults,
    'options' => [
        'method' => 'auto',
        'flavour' => 'nofft',
        'maxiterate' => '0'
    ],
    'created_at' => date('Y-m-d H:i:s')
];

echo "Analysis Request:\n";
echo "  Tool: " . $analysisRequest['tool'] . "\n";
echo "  Name: " . $analysisRequest['name'] . "\n";
echo "  Sequences: " . count($analysisRequest['sequences']) . "\n";
echo "  Type: " . $analysisRequest['sequence_type'] . "\n";

// ============================================================
// STEP 3: Create FASTA file from sequences
// ============================================================
echo "\n[STEP 3] Creating FASTA file from sequences...\n";

$fastaContent = "";
foreach ($analysisRequest['sequences'] as $seq) {
    $fastaContent .= ">" . $seq['feature_id'] . " " . $seq['name'] . " [" . $seq['species'] . "]\n";
    $fastaContent .= wordwrap($seq['sequence'], 80, "\n", true) . "\n";
}

$fastaFile = '/tmp/moop_alignment_' . time() . '.fasta';
file_put_contents($fastaFile, $fastaContent);

echo "FASTA file created: " . basename($fastaFile) . "\n";
echo "File size: " . filesize($fastaFile) . " bytes\n";
echo "Preview:\n";
echo substr($fastaContent, 0, 200) . "...\n";

// ============================================================
// STEP 4: Connect to Galaxy and submit job
// ============================================================
echo "\n[STEP 4] Connecting to Galaxy...\n";

if (!$config['galaxy_settings']['api_key']) {
    echo "❌ ERROR: Galaxy API key not configured\n";
    echo "   Please add your API key to config/secrets.php\n";
    exit(1);
}

try {
    $galaxy = new GalaxyClient(
        $config['galaxy_settings']['url'],
        $config['galaxy_settings']['api_key'],
        $config['galaxy_settings']['mode']
    );
    
    // Test connection
    echo "Testing Galaxy connection...\n";
    $connTest = $galaxy->testConnection();
    
    if (!$connTest['success']) {
        echo "❌ ERROR: " . $connTest['message'] . "\n";
        exit(1);
    }
    echo "✓ Connected to Galaxy\n";
    
    // Create history
    echo "\nCreating Galaxy history...\n";
    $historyId = $galaxy->createHistory(
        $analysisRequest['user_id'],
        'MAFFT'
    );
    echo "✓ History created: $historyId\n";
    
    // Upload sequences
    echo "\nUploading sequences to Galaxy...\n";
    $datasetId = $galaxy->uploadFile(
        $historyId,
        $fastaFile,
        'fasta'
    );
    echo "✓ Sequences uploaded: $datasetId\n";
    
    // Wait for upload to complete
    echo "\nWaiting for upload to complete...\n";
    $maxWait = 30;
    $waited = 0;
    while ($waited < $maxWait) {
        $info = $galaxy->getDatasetInfo($datasetId);
        if ($info['state'] === 'ok') {
            echo "✓ Upload complete. Dataset ready.\n";
            break;
        } else if ($info['state'] === 'error') {
            echo "❌ Upload failed: " . json_encode($info) . "\n";
            exit(1);
        }
        echo "  Status: " . $info['state'] . "...\n";
        sleep(2);
        $waited += 2;
    }
    
    if ($waited >= $maxWait) {
        echo "⚠ Upload timeout, proceeding anyway...\n";
    }
    
    // Run MAFFT tool
    echo "\nStarting MAFFT alignment...\n";
    $toolId = $config['galaxy_settings']['tools']['mafft']['id'];
    
    $toolInputs = [
        'inputSequences' => [
            'src' => 'hda',
            'id' => $datasetId
        ],
        'outputFormat' => 'fasta',
    ];
    
    $result = $galaxy->runTool($historyId, $toolId, $toolInputs);
    $jobId = $result['job_id'];
    $outputId = $result['output_id'];
    
    echo "✓ MAFFT job started\n";
    echo "  Job ID: $jobId\n";
    echo "  Output Dataset ID: $outputId\n";
    
    // ============================================================
    // STEP 5: Monitor job and retrieve results
    // ============================================================
    echo "\n[STEP 5] Monitoring job progress...\n";
    
    $completion = $galaxy->waitForCompletion($jobId, 600); // 10 minute timeout
    
    if ($completion['success']) {
        echo "✓ Job completed successfully!\n";
        
        // Download results
        echo "\nDownloading alignment results...\n";
        $alignmentContent = $galaxy->getDatasetContent($outputId);
        
        // Save to file
        $outputFile = '/data/moop/mafft_alignment_' . date('YmdHis') . '.fasta';
        file_put_contents($outputFile, $alignmentContent);
        
        echo "✓ Results saved: " . basename($outputFile) . "\n";
        echo "  File size: " . filesize($outputFile) . " bytes\n";
        
        // Show preview
        echo "\nAlignment Preview (first 500 chars):\n";
        echo substr($alignmentContent, 0, 500) . "...\n";
        
        // Galaxy visualization URL
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "RESULTS AVAILABLE:\n";
        echo "  Download: " . $outputFile . "\n";
        echo "  View in Galaxy: " . $config['galaxy_settings']['url'] . 
             "/histories/view?id=" . urlencode($historyId) . "\n";
        echo "  Visualize: " . $config['galaxy_settings']['url'] . 
             "/visualizations/display?visualization=alignmentviewer&dataset_id=" . 
             urlencode($outputId) . "\n";
        echo str_repeat("=", 60) . "\n";
        
    } else {
        echo "❌ Job failed or timed out\n";
        echo "Status: " . json_encode($completion['status']) . "\n";
        exit(1);
    }
    
    // Cleanup
    echo "\nCleaning up temporary files...\n";
    @unlink($fastaFile);
    echo "✓ Done\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    @unlink($fastaFile);
    exit(1);
}

echo "\n=== Test Complete ===\n";
?>
