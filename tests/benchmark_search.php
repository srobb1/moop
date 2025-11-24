<?php
/**
 * Benchmark: LIKE vs FTS5 Search Performance
 * 
 * Tests search performance on annotation data using:
 * 1. Current LIKE + REGEXP approach
 * 2. FTS5 virtual table approach
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
echo "BENCHMARK: LIKE vs FTS5 Search Performance\n";
echo "════════════════════════════════════════════════════════════════════════════════\n\n";

echo "Database: " . basename(dirname($dbFile)) . "\n";
echo "Search term: \"$searchTerm\"\n";
echo "Runs per test: $numRuns\n\n";

// ============================================================================
// Test 1: Current LIKE + REGEXP approach
// ============================================================================
echo "TEST 1: Current Approach (LIKE + REGEXP Ranking)\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";

$likeRuntimes = [];
for ($i = 0; $i < $numRuns; $i++) {
    $start = microtime(true);
    
    // Use current search function
    $results = searchFeaturesAndAnnotations($searchTerm, false, $dbFile);
    
    $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
    $likeRuntimes[] = $elapsed;
    
    printf("  Run %d: %.2f ms (%d results)\n", $i + 1, $elapsed, count($results));
}

$avgLike = array_sum($likeRuntimes) / count($likeRuntimes);
$minLike = min($likeRuntimes);
$maxLike = max($likeRuntimes);

echo "\nRESULTS:\n";
printf("  Average: %.2f ms\n", $avgLike);
printf("  Min:     %.2f ms\n", $minLike);
printf("  Max:     %.2f ms\n", $maxLike);

// ============================================================================
// Test 2: FTS5 approach
// ============================================================================
echo "\n\nTEST 2: New Approach (FTS5 Full-Text Search)\n";
echo "───────────────────────────────────────────────────────────────────────────────\n";

$fts5Runtimes = [];
for ($i = 0; $i < $numRuns; $i++) {
    $start = microtime(true);
    
    // Use FTS5 search function
    $results = searchFeaturesAndAnnotationsFTS5($searchTerm, false, $dbFile);
    
    $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
    $fts5Runtimes[] = $elapsed;
    
    printf("  Run %d: %.2f ms (%d results)\n", $i + 1, $elapsed, count($results));
}

$avgFts5 = array_sum($fts5Runtimes) / count($fts5Runtimes);
$minFts5 = min($fts5Runtimes);
$maxFts5 = max($fts5Runtimes);

echo "\nRESULTS:\n";
printf("  Average: %.2f ms\n", $avgFts5);
printf("  Min:     %.2f ms\n", $minFts5);
printf("  Max:     %.2f ms\n", $maxFts5);

// ============================================================================
// Comparison
// ============================================================================
echo "\n\nCOMPARISON\n";
echo "════════════════════════════════════════════════════════════════════════════════\n";

$improvement = ((($avgLike - $avgFts5) / $avgLike) * 100);
$speedup = $avgLike / $avgFts5;

printf("LIKE + REGEXP Average:  %.2f ms\n", $avgLike);
printf("FTS5 Average:           %.2f ms\n", $avgFts5);
printf("\n");
printf("Speed improvement:      %s%.1f%% faster\n", ($improvement > 0 ? '+' : ''), $improvement);
printf("Speedup ratio:          %.1fx\n", $speedup);

if ($improvement > 0) {
    echo "\n✅ FTS5 is FASTER\n";
} else {
    echo "\n⚠️  LIKE + REGEXP is faster (may have small result set)\n";
}

echo "\n════════════════════════════════════════════════════════════════════════════════\n";

?>
