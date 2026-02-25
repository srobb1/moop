<?php
/**
 * Integration Test: URL Whitelist Token Strategy
 * 
 * Tests 4 scenarios to validate the URL whitelist implementation:
 * 1. Public track on your server (internal path)
 * 2. Private track on your server (internal path)
 * 3. UCSC reference genome (external public)
 * 4. Misconfigured track (external private - should warn)
 * 
 * Run: php tests/integration_url_whitelist_test.php
 */

require_once __DIR__ . '/../includes/ConfigManager.php';
require_once __DIR__ . '/../lib/jbrowse/track_token.php';
require_once __DIR__ . '/../api/jbrowse2/config.php';

// Initialize
$config = ConfigManager::getInstance();
$config->initialize(
    __DIR__ . '/../config/site_config.php',
    __DIR__ . '/../config/tools_config.php'
);

echo "\n";
echo "================================================================================\n";
echo "  URL Whitelist Token Strategy - Integration Tests\n";
echo "================================================================================\n\n";

// Generate test token
$test_organism = "Nematostella_vectensis";
$test_assembly = "GCA_033964005.1";
$token = generateTrackToken($test_organism, $test_assembly);

echo "Test Setup:\n";
echo "  Organism: $test_organism\n";
echo "  Assembly: $test_assembly\n";
echo "  Token generated: " . substr($token, 0, 40) . "...\n\n";

$all_passed = true;

// ============================================================================
// SCENARIO 1: Public Track on Your Server (Internal Path)
// ============================================================================
echo "--------------------------------------------------------------------------------\n";
echo "SCENARIO 1: Public Track on Your Server (Internal Path)\n";
echo "--------------------------------------------------------------------------------\n";
echo "Expected: Token added, routed through tracks.php\n";
echo "Reason: .htaccess blocks direct access, even for PUBLIC tracks\n\n";

$track1 = [
    "type" => "QuantitativeTrack",
    "adapter" => [
        "type" => "BigWigAdapter",
        "bigWigLocation" => [
            "uri" => "/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/test.bw",
            "locationType" => "UriLocation"
        ]
    ],
    "metadata" => [
        "access_level" => "PUBLIC"
    ]
];

$result1 = addTokensToTrack($track1, $test_organism, $test_assembly, "PUBLIC", false);
$output_uri = $result1["adapter"]["bigWigLocation"]["uri"];
$has_token = strpos($output_uri, "token=") !== false;
$routed_tracks_php = strpos($output_uri, "/api/jbrowse2/tracks.php") !== false;

echo "Input URI:  " . $track1["adapter"]["bigWigLocation"]["uri"] . "\n";
echo "Output URI: " . $output_uri . "\n\n";

echo "Validations:\n";
echo "  [" . ($has_token ? "✓" : "✗") . "] Token added: " . ($has_token ? "YES" : "NO") . "\n";
echo "  [" . ($routed_tracks_php ? "✓" : "✗") . "] Routed through tracks.php: " . ($routed_tracks_php ? "YES" : "NO") . "\n";

if (!$has_token || !$routed_tracks_php) {
    echo "\n❌ SCENARIO 1 FAILED\n";
    $all_passed = false;
} else {
    echo "\n✓ SCENARIO 1 PASSED\n";
}

// ============================================================================
// SCENARIO 2: Private Track on Your Server (Internal Path)
// ============================================================================
echo "\n--------------------------------------------------------------------------------\n";
echo "SCENARIO 2: Private Track on Your Server (Internal Path)\n";
echo "--------------------------------------------------------------------------------\n";
echo "Expected: Token added, routed through tracks.php\n";
echo "Reason: COLLABORATOR tracks on your server need JWT for access control\n\n";

$track2 = [
    "type" => "QuantitativeTrack",
    "adapter" => [
        "type" => "BigWigAdapter",
        "bigWigLocation" => [
            "uri" => "/moop/data/tracks/Nematostella_vectensis/GCA_033964005.1/bigwig/collab.bw",
            "locationType" => "UriLocation"
        ]
    ],
    "metadata" => [
        "access_level" => "COLLABORATOR"
    ]
];

$result2 = addTokensToTrack($track2, $test_organism, $test_assembly, "COLLABORATOR", false);
$output_uri2 = $result2["adapter"]["bigWigLocation"]["uri"];
$has_token2 = strpos($output_uri2, "token=") !== false;
$routed_tracks_php2 = strpos($output_uri2, "/api/jbrowse2/tracks.php") !== false;

echo "Input URI:  " . $track2["adapter"]["bigWigLocation"]["uri"] . "\n";
echo "Output URI: " . $output_uri2 . "\n\n";

echo "Validations:\n";
echo "  [" . ($has_token2 ? "✓" : "✗") . "] Token added: " . ($has_token2 ? "YES" : "NO") . "\n";
echo "  [" . ($routed_tracks_php2 ? "✓" : "✗") . "] Routed through tracks.php: " . ($routed_tracks_php2 ? "YES" : "NO") . "\n";

if (!$has_token2 || !$routed_tracks_php2) {
    echo "\n❌ SCENARIO 2 FAILED\n";
    $all_passed = false;
} else {
    echo "\n✓ SCENARIO 2 PASSED\n";
}

// ============================================================================
// SCENARIO 3: UCSC Reference Genome (External Public)
// ============================================================================
echo "\n--------------------------------------------------------------------------------\n";
echo "SCENARIO 3: UCSC Reference Genome (External Public)\n";
echo "--------------------------------------------------------------------------------\n";
echo "Expected: No token added, URL unchanged\n";
echo "Reason: External public server, doesn't validate our JWTs\n\n";

$track3 = [
    "type" => "QuantitativeTrack",
    "adapter" => [
        "type" => "BigWigAdapter",
        "bigWigLocation" => [
            "uri" => "https://hgdownload.soe.ucsc.edu/goldenPath/hg38/phyloP470way/hg38.phyloP470way.bw",
            "locationType" => "UriLocation"
        ]
    ],
    "metadata" => [
        "access_level" => "PUBLIC"
    ]
];

$result3 = addTokensToTrack($track3, $test_organism, $test_assembly, "PUBLIC", false);
$output_uri3 = $result3["adapter"]["bigWigLocation"]["uri"];
$has_token3 = strpos($output_uri3, "token=") !== false;
$unchanged3 = ($output_uri3 === $track3["adapter"]["bigWigLocation"]["uri"]);

echo "Input URI:  " . $track3["adapter"]["bigWigLocation"]["uri"] . "\n";
echo "Output URI: " . $output_uri3 . "\n\n";

echo "Validations:\n";
echo "  [" . (!$has_token3 ? "✓" : "✗") . "] No token added: " . (!$has_token3 ? "YES" : "NO") . "\n";
echo "  [" . ($unchanged3 ? "✓" : "✗") . "] URL unchanged: " . ($unchanged3 ? "YES" : "NO") . "\n";

if ($has_token3 || !$unchanged3) {
    echo "\n❌ SCENARIO 3 FAILED\n";
    $all_passed = false;
} else {
    echo "\n✓ SCENARIO 3 PASSED\n";
}

// ============================================================================
// SCENARIO 4: Misconfigured Track (External Private)
// ============================================================================
echo "\n--------------------------------------------------------------------------------\n";
echo "SCENARIO 4: Misconfigured Track (External Private - Should Warn)\n";
echo "--------------------------------------------------------------------------------\n";
echo "Expected: No token added, warning logged\n";
echo "Reason: Can't enforce authentication on external server\n\n";

// Capture error log
$error_log_file = tempnam(sys_get_temp_dir(), 'test_log_');
ini_set('error_log', $error_log_file);

$track4 = [
    "type" => "QuantitativeTrack",
    "adapter" => [
        "type" => "BigWigAdapter",
        "bigWigLocation" => [
            "uri" => "https://someserver.com/data/sensitive_data.bw",
            "locationType" => "UriLocation"
        ]
    ],
    "metadata" => [
        "access_level" => "COLLABORATOR"
    ]
];

$result4 = addTokensToTrack($track4, $test_organism, $test_assembly, "COLLABORATOR", false);
$output_uri4 = $result4["adapter"]["bigWigLocation"]["uri"];
$has_token4 = strpos($output_uri4, "token=") !== false;
$unchanged4 = ($output_uri4 === $track4["adapter"]["bigWigLocation"]["uri"]);

// Check if warning was logged
$log_contents = file_get_contents($error_log_file);
$warning_logged = strpos($log_contents, "WARNING: Track has external URL") !== false;

echo "Input URI:  " . $track4["adapter"]["bigWigLocation"]["uri"] . "\n";
echo "Output URI: " . $output_uri4 . "\n\n";

echo "Validations:\n";
echo "  [" . (!$has_token4 ? "✓" : "✗") . "] No token added: " . (!$has_token4 ? "YES" : "NO") . "\n";
echo "  [" . ($unchanged4 ? "✓" : "✗") . "] URL unchanged: " . ($unchanged4 ? "YES" : "NO") . "\n";
echo "  [" . ($warning_logged ? "✓" : "✗") . "] Warning logged: " . ($warning_logged ? "YES" : "NO") . "\n";

if ($warning_logged) {
    echo "\nWarning message:\n";
    echo "  " . trim(substr($log_contents, strrpos($log_contents, "WARNING:"))) . "\n";
}

if ($has_token4 || !$unchanged4 || !$warning_logged) {
    echo "\n❌ SCENARIO 4 FAILED\n";
    $all_passed = false;
} else {
    echo "\n✓ SCENARIO 4 PASSED\n";
}

// Cleanup
unlink($error_log_file);

// ============================================================================
// FINAL RESULTS
// ============================================================================
echo "\n";
echo "================================================================================\n";
if ($all_passed) {
    echo "  ✓✓✓ ALL TESTS PASSED! ✓✓✓\n";
    echo "  URL Whitelist Token Strategy is working correctly.\n";
} else {
    echo "  ❌ SOME TESTS FAILED\n";
    echo "  Please review the failures above and fix the issues.\n";
}
echo "================================================================================\n\n";

exit($all_passed ? 0 : 1);
