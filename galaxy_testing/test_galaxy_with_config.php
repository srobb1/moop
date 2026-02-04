<?php
/**
 * TEST: Galaxy MAFFT Integration with Stored Config
 * 
 * This test:
 * 1. Loads stored Galaxy config from site_config.php
 * 2. Uses sequences that would come from search results
 * 3. Submits alignment request to Galaxy
 * 4. Monitors job completion
 * 5. Provides visualization link
 */

// Load configuration
$site_config = require __DIR__ . '/config/site_config.php';
$secrets = require __DIR__ . '/config/secrets.php';

// Get Galaxy settings from config
$galaxy_url = $site_config['galaxy_settings']['url'];
$api_key = $secrets['galaxy']['api_key'];
$mafft_tool_id = $site_config['galaxy_settings']['tools']['mafft']['id'];

echo "=== Galaxy MAFFT Test with Config ===\n";
echo "Galaxy URL: $galaxy_url\n";
echo "API Key: " . (strlen($api_key) > 10 ? substr($api_key, 0, 10) . "..." : "NOT SET") . "\n";
echo "MAFFT Tool ID: $mafft_tool_id\n\n";

if (!$api_key || $api_key === 'YOUR_API_KEY_HERE') {
    echo "ERROR: Galaxy API key not configured.\n";
    echo "Please:\n";
    echo "1. Log in to: $galaxy_url/user/api_key\n";
    echo "2. Copy your API key\n";
    echo "3. Paste it in config/secrets.php\n";
    exit(1);
}

// Simulate sequences from search results (as they would come from DB)
$sequences = [
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000619.1',
        'description' => 'NTNG1 netrin G1',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFCGM'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000620.1',
        'description' => 'NTNG1 netrin G1',
        'sequence' => 'MCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGS'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000621.1',
        'description' => 'NTNG1 netrin G1',
        'sequence' => 'MVTLVVSTGKDNQSTPRNPAVCKCNLHATGCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANI'
    ],
    [
        'id' => 'Q9Y2I2',
        'description' => 'NTNG1_HUMAN',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFCGMGRYDTKMALVALVDDLCGNPYMCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGSCKCNLHATGCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANIYCECFSHSNRCSYIDNVIVAALAQSVIAVMKKEFVSVSREQQGLSVISVCEDITGTTRVVNDKRL'
    ]
];

// Build FASTA content from sequences
$fasta_content = '';
foreach ($sequences as $seq) {
    $fasta_content .= ">" . $seq['id'] . " " . $seq['description'] . "\n";
    $fasta_content .= wordwrap($seq['sequence'], 80, "\n", true) . "\n";
}

echo "Input sequences: " . count($sequences) . "\n";
echo "FASTA content length: " . strlen($fasta_content) . " bytes\n\n";

// Step 1: Create a new history
echo "[1/5] Creating new history...\n";
$history_name = "MAFFT_from_MOOP_" . date('YmdHis');
$history_response = json_decode(shell_exec(sprintf(
    'curl -s -X POST %s/api/histories -H "x-api-key: %s" -H "Content-Type: application/json" -d %s',
    escapeshellarg($galaxy_url),
    escapeshellarg($api_key),
    escapeshellarg(json_encode(['name' => $history_name]))
)), true);

if (!isset($history_response['id'])) {
    echo "ERROR: Failed to create history\n";
    echo "Response: " . json_encode($history_response) . "\n";
    exit(1);
}
$history_id = $history_response['id'];
echo "✓ History created: $history_id\n\n";

// Step 2: Upload FASTA file
echo "[2/5] Uploading FASTA file...\n";
$upload_response = json_decode(shell_exec(sprintf(
    'curl -s -X POST %s/api/tools -H "x-api-key: %s" -H "Content-Type: application/json" -d %s',
    escapeshellarg($galaxy_url),
    escapeshellarg($api_key),
    escapeshellarg(json_encode([
        'history_id' => $history_id,
        'tool_id' => 'upload1',
        'inputs' => [
            'files_0|url_paste' => $fasta_content,
            'files_0|type' => 'upload_dataset',
            'dbkey' => '?',
            'file_type' => 'fasta'
        ]
    ]))
)), true);

if (!isset($upload_response['outputs'][0]['id'])) {
    echo "ERROR: Failed to upload file\n";
    echo "Response: " . json_encode($upload_response) . "\n";
    exit(1);
}
$dataset_id = $upload_response['outputs'][0]['id'];
echo "✓ File uploaded: $dataset_id\n\n";

// Step 3: Wait for upload to complete
echo "[3/5] Waiting for upload to complete...\n";
$max_wait = 30;
for ($i = 0; $i < $max_wait; $i++) {
    $state_response = json_decode(shell_exec(sprintf(
        'curl -s %s/api/datasets/%s -H "x-api-key: %s"',
        escapeshellarg($galaxy_url),
        escapeshellarg($dataset_id),
        escapeshellarg($api_key)
    )), true);
    
    $state = $state_response['state'] ?? 'unknown';
    echo "  Attempt " . ($i + 1) . "/$max_wait: $state\n";
    
    if ($state === 'ok') {
        echo "✓ Upload complete\n\n";
        break;
    } elseif ($state === 'error') {
        echo "ERROR: Upload failed\n";
        exit(1);
    }
    sleep(2);
}

// Step 4: Run MAFFT
echo "[4/5] Running MAFFT alignment...\n";
$mafft_response = json_decode(shell_exec(sprintf(
    'curl -s -X POST %s/api/tools -H "x-api-key: %s" -H "Content-Type: application/json" -d %s',
    escapeshellarg($galaxy_url),
    escapeshellarg($api_key),
    escapeshellarg(json_encode([
        'history_id' => $history_id,
        'tool_id' => $mafft_tool_id,
        'inputs' => [
            'inputSequences' => ['src' => 'hda', 'id' => $dataset_id],
            'outputFormat' => 'fasta',
            'matrix_condition|matrix' => 'BLOSUM62',
            'flavour' => 'mafft-fftns'
        ]
    ]))
)), true);

if (!isset($mafft_response['outputs'][0]['id'])) {
    echo "ERROR: Failed to start MAFFT\n";
    echo "Response: " . json_encode($mafft_response) . "\n";
    exit(1);
}
$mafft_job_id = $mafft_response['outputs'][0]['id'];
echo "✓ MAFFT job started: $mafft_job_id\n\n";

// Step 5: Monitor job and download results
echo "[5/5] Monitoring MAFFT job (this may take 2-5 minutes)...\n";
$max_iterations = 60;
for ($i = 0; $i < $max_iterations; $i++) {
    $job_response = json_decode(shell_exec(sprintf(
        'curl -s %s/api/datasets/%s -H "x-api-key: %s"',
        escapeshellarg($galaxy_url),
        escapeshellarg($mafft_job_id),
        escapeshellarg($api_key)
    )), true);
    
    $job_state = $job_response['state'] ?? 'unknown';
    echo "  Attempt " . ($i + 1) . "/$max_iterations: $job_state\n";
    
    if ($job_state === 'ok') {
        echo "\n✓ Alignment complete!\n\n";
        
        // Download results
        $output_file = "mafft_alignment_" . date('YmdHis') . ".fasta";
        shell_exec(sprintf(
            'curl -s %s/api/datasets/%s/display -H "x-api-key: %s" > %s',
            escapeshellarg($galaxy_url),
            escapeshellarg($mafft_job_id),
            escapeshellarg($api_key),
            escapeshellarg($output_file)
        ));
        
        echo "=== RESULTS ===\n";
        echo "Alignment saved to: $output_file\n";
        echo "File size: " . filesize($output_file) . " bytes\n";
        echo "\nGalaxy history: $galaxy_url/histories/view?id=$history_id\n";
        echo "Visualization: $galaxy_url/visualizations/display?visualization=alignmentviewer&dataset_id=$mafft_job_id\n";
        exit(0);
    } elseif ($job_state === 'error') {
        echo "\nERROR: MAFFT job failed\n";
        exit(1);
    }
    sleep(5);
}

echo "\nWARNING: Job monitoring timeout. Check Galaxy manually:\n";
echo "$galaxy_url/histories/view?id=$history_id\n";
?>
