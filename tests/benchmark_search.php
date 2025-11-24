<?php
/**
 * Benchmark: Search Performance Test
 * 
 * Tests search performance on annotation data using LIKE + REGEXP approach
 * 
 * Run: php tests/benchmark_search.php <database_path> <search_term>
 * Example: php tests/benchmark_search.php /var/www/html/moop/organisms/Anoura_caudifer/organism.sqlite kinase 5
 */

if ($argc < 3) {
    echo "Usage: php benchmark_search.php <database_path> <search_term> [num_runs]\n";
    echo "Example: php benchmark_search.php /var/www/html/moop/organisms/Anoura_caudifer/organism.sqlite kinase 5\n";
    exit(1);
}

$dbFile = $argv[1];
$searchTerm = $argv[2];
$numRuns = isset($argv[3]) ? (int)$argv[3] : 3;

if (!file_exists($dbFile)) {
    echo "Error: Database file not found: $dbFile\n";
    exit(1);
}

// Include search functions
require_once __DIR__ . '/../lib/functions_database.php';
require_once __DIR__ . '/../lib/database_queries.php';

echo "════════════════════════════════════════════════════════════════════════════════\n";
echo "BENCHMARK: Search Performance (LIKE + REGEXP)\n";
echo "════════════════════════════════════════════════════════════════════════════════\n\n";

echo "Database: " . basename(dirname($dbFile)) . "\n";
echo "Search term: \"$searchTerm\"\n";
echo "Runs per test: $numRuns\n\n";

// ============================================================================
// Test: Current LIKE + REGEXP approach
// ============================================================================
echo "TEST: Current Approach (LIKE + REGEXP Ranking)\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";

$likeRuntimes = [];
for ($i = 0; $i < $numRuns; $i++) {
    $start = microtime(true);
    
    // Use current search function
    $results = searchFeaturesAndAnnotations($searchTerm, false, $dbFile);
    
    $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
    $likeRuntimes[] = $elapsed;
    
    printf("  Run %d: %.2f ms (%d results)\n", $i + 1, $elapsed, count($results['results'] ?? []));
}

$avgLike = array_sum($likeRuntimes) / count($likeRuntimes);
$minLike = min($likeRuntimes);
$maxLike = max($likeRuntimes);

echo "\nRESULTS:\n";
printf("  Average: %.2f ms\n", $avgLike);
printf("  Min:     %.2f ms\n", $minLike);
printf("  Max:     %.2f ms\n", $maxLike);

echo "\n════════════════════════════════════════════════════════════════════════════════\n";


?>
