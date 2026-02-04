<?php
/**
 * Galaxy Integration Test - Search Results to MAFFT Alignment
 * 
 * This test simulates:
 * 1. Getting sequences from a search result (stored in variable)
 * 2. Loading Galaxy config from site_config.php
 * 3. Generating a MAFFT alignment request
 * 4. Submitting to Galaxy
 * 5. Returning history URL for results
 */

require_once __DIR__ . '/includes/config_init.php';

$config = ConfigManager::getInstance();

// ============================================
// STEP 1: Simulate search results sequences
// ============================================
// In real usage, these would come from the search results page
$sequences = [
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000619.1',
        'name' => 'NTNG1 netrin G1',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFC' . "\n" . 'GM'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000620.1',
        'name' => 'NTNG1 netrin G1',
        'sequence' => 'MCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIELTDNIVITFESGRPDLMILEKSLD' . "\n" . 'YGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFEIKDRFALFAGARLHNMASLYGQL' . "\n" . 'DTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGS'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000621.1',
        'name' => 'NTNG1 netrin G1',
        'sequence' => 'MVTLVVSTGKDNQSTPRNPAVCKCNLHATGCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANI'
    ],
    [
        'id' => 'Bradypodion_pumilum_JAWDJD010000004.1_000622.1',
        'name' => 'NTNG1 netrin G1',
        'sequence' => 'MDCECFGHSNRCSYIDVLSTFICVSCKHNTRGQNCELCRLGYYRNTSAKLDDENVCIECNCSRTGSVRDRCNEKGICECK' . "\n" . 'QGTTGPKCDKCLRGYYWHNQGCQ'
    ],
    [
        'id' => 'CCA3t017421001.1',
        'name' => 'NTNG1 netrin G1',
        'sequence' => 'MYLSRFLSLHTLWVTVSSVIQHYPSVWGHYDVCKTQIYTDEGKFWDYTACQPEAVDMMKYVKVTLDPPDITCGNPPENFC' . "\n" . 'GMGRYDTKMALVALVDDLCGNPYMCNECDATIDELAHPPELMFDAEGRHPSTFWQSTTWKEYPKPLQVNITFYWNKTIEL' . "\n" . 'TDNIVITFESGRPDLMILEKSLDYGRTWQPYQYYATDCLNAFNMEPKTVRDLTQQTVLEIICTEEYSTGYMANSKILHFE' . "\n" . 'IKDRFALFAGARLHNMASLYGQLDTTKNLRDFFTVTDLRIRLLRPATGEIYVDPQHLTRYFYAISDVKVVGRCKCNLHAT' . "\n" . 'GCREENKRLLCECEHNTTGPDCGKCKKNYQGRPWTPGSYLPIPRGTANIYCECFSHSNRCSYIDNVIVAALAQSVIAVMK' . "\n" . 'KEFVSVSREQQGLSVISVCEDITGTTRVVNDKRL'
    ],
    [
        'id' => 'Q9Y2I2',
        'name' => 'NTNG1_HUMAN',
        'sequence' => 'MYLSRFLSIHALWVTVSSVMQPYPLVWGHYDLCKTQIYTEEGKVWDYMACQPESTDMTKYLKVKLDPPDITCGDPPETFCAMGNPYMCNNECDASTPELAHPPELMFDFEGRHPSTFWQSATWKEYPKPLQVNITLSWSKTIELTDNIVITFESGRPDQMILEKSLDYGRTWQPYQYYATDCLDAFHMDPKSVKDLSQHTVLEIICTEEYSTGYTTNSKIIHFEIKDRFAFFAGPRLRNMASLYGQLDTTKKLRDFFTVTDLRIRLLRPAVGEIFVDELHLARYFYAISDIKVRGRCKCNLHATVCVYDNSKLTCECEHNTTGPDCGKCKKNYQGRPWSPGSYLPIPKGTANTCIPSISSIGNCECFGHSNRCSYIDLLNTVICVSCKHNTRGQHCELCRLGYFRNASAQLDDENVCIECYCNPLGSIHDRCNGSGFCECKTGTTGPKCDECLPGNSWHYGCQPNVCDNELLHCQNGGTCHNNVRCLCPAAYTGILCEKLRCEEAGSCGSDSGQGAPPHGSPALLLLTTLLGTASPLVF'
    ]
];

// ============================================
// STEP 2: Build FASTA format from sequences
// ============================================
$fasta_content = '';
foreach ($sequences as $seq) {
    $fasta_content .= '>' . $seq['id'] . ' ' . $seq['name'] . "\n";
    $fasta_content .= $seq['sequence'] . "\n";
}

echo "=== Galaxy Search Integration Test ===\n\n";
echo "✓ Loaded " . count($sequences) . " sequences from search results\n";

$galaxy_settings = $config->getArray('galaxy_settings', []);
$galaxy_url = $galaxy_settings['url'] ?? '';
$galaxy_api_key = $galaxy_settings['api_key'] ?? '';

echo "✓ Galaxy URL: " . ($galaxy_url ?: 'NOT SET') . "\n";
echo "✓ Galaxy API Key: " . (isset($galaxy_api_key) && !empty($galaxy_api_key) ? '***' . substr($galaxy_api_key, -4) : 'NOT SET') . "\n";

if (empty($galaxy_api_key) || empty($galaxy_url)) {
    echo "\n❌ Error: Galaxy is not properly configured\n";
    exit(1);
}

// ============================================
// STEP 3: Generate analysis request
// ============================================
echo "\n--- Building MAFFT Request ---\n";

$history_name = 'MOOP_Search_Alignment_' . date('Y-m-d_H-i-s');

$request = [
    'action' => 'align',
    'tool' => 'mafft',
    'history_name' => $history_name,
    'sequences' => $sequences,
    'parameters' => [
        'flavor' => 'mafft-fftns',
        'matrix' => 'BLOSUM62',
        'output_format' => 'fasta'
    ]
];

echo "✓ History Name: $history_name\n";
echo "✓ Sequence Count: " . count($request['sequences']) . "\n";
echo "✓ Tool: " . $request['tool'] . "\n";
echo "✓ FASTA Size: " . strlen($fasta_content) . " bytes\n";

// ============================================
// STEP 4: Submit to Galaxy via shell script
// ============================================
echo "\n--- Submitting to Galaxy ---\n";

// Write temporary FASTA file
$temp_file = '/tmp/moop_align_' . time() . '.fasta';
file_put_contents($temp_file, $fasta_content);
echo "✓ Created temporary FASTA: $temp_file\n";

// Build and execute command
$shell_script = __DIR__ . '/galaxy_mafft.sh';
$api_key = $galaxy_api_key;

if (!file_exists($shell_script)) {
    echo "\n❌ Error: Shell script not found: $shell_script\n";
    unlink($temp_file);
    exit(1);
}

$command = "$shell_script $temp_file $api_key 2>&1";
echo "✓ Running: $shell_script <temp_fasta> <api_key>\n";

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// Parse output
$history_id = null;
$alignment_file = null;

foreach ($output as $line) {
    echo "  > $line\n";
    
    // Extract history ID from output
    if (preg_match('/History: ([a-f0-9]+)/', $line, $matches)) {
        $history_id = $matches[1];
    }
    
    // Extract result file
    if (preg_match('/Results: (.+\.fasta)/', $line, $matches)) {
        $alignment_file = $matches[1];
    }
}

// Cleanup temp file
unlink($temp_file);

// ============================================
// STEP 5: Results
// ============================================
echo "\n--- Results ---\n";

if ($return_var === 0) {
    echo "\n✓ Alignment Complete!\n";
    
    if ($alignment_file && file_exists($alignment_file)) {
        echo "\n📄 Alignment saved locally: $alignment_file\n";
        echo "📏 File size: " . filesize($alignment_file) . " bytes\n";
        
        // Show first few lines
        $lines = file($alignment_file);
        echo "\nFirst aligned sequences:\n";
        for ($i = 0; $i < min(10, count($lines)); $i++) {
            echo "  " . rtrim($lines[$i]) . "\n";
        }
    }
    
    if ($history_id) {
        $viz_url = "$galaxy_url/visualizations/display?visualization=alignmentviewer&dataset_id=$history_id";
        echo "\n🔗 View alignment visualization:\n";
        echo "   $viz_url\n";
        echo "\n🔗 View full Galaxy history:\n";
        echo "   $galaxy_url/histories/view?id=$history_id\n";
    }
    
    // Return JSON response for API usage
    $response = [
        'success' => true,
        'message' => 'Alignment completed',
        'history_id' => $history_id,
        'alignment_file' => $alignment_file,
        'visualization_url' => $viz_url ?? null,
        'galaxy_url' => "$galaxy_url/histories/view?id=$history_id"
    ];
    
    echo "\n--- JSON Response ---\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "\n❌ Error: Galaxy submission failed\n";
    exit(1);
}
