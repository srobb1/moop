#!/usr/bin/env php
<?php
/**
 * JBrowse Track Generator - CLI Interface
 * 
 * Generate JBrowse tracks from Google Sheets metadata.
 * 
 * Usage:
 *   php generate_tracks_from_sheet.php SHEET_ID \
 *     --gid 1234567 \
 *     --organism Nematostella_vectensis \
 *     --assembly GCA_033964005.1 \
 *     [--force TRACK_ID...] \
 *     [--dry-run] \
 *     [--clean]
 * 
 * @package MOOP\JBrowse
 */

// Bootstrap
require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../lib/JBrowse/TrackGenerator.php';

// Parse command line arguments
$options = parseArguments($argv);

// Validate required arguments
if (empty($options['sheet_id'])) {
    showUsage();
    exit(1);
}

if (empty($options['gid']) || empty($options['organism']) || empty($options['assembly'])) {
    echo "Error: Missing required arguments\n\n";
    showUsage();
    exit(1);
}

// Initialize
$config = ConfigManager::getInstance();
$generator = new TrackGenerator($config);

echo "JBrowse Track Generator\n";
echo "=======================\n\n";

// Display configuration
echo "Configuration:\n";
echo "  Sheet ID: " . $options['sheet_id'] . "\n";
echo "  GID: " . $options['gid'] . "\n";
echo "  Organism: " . $options['organism'] . "\n";
echo "  Assembly: " . $options['assembly'] . "\n";

if (!empty($options['force'])) {
    if (empty($options['force_ids'])) {
        echo "  Force: ALL tracks\n";
    } else {
        echo "  Force: " . implode(', ', $options['force_ids']) . "\n";
    }
}

if ($options['dry_run']) {
    echo "  Mode: DRY RUN (no changes will be made)\n";
}

if ($options['clean']) {
    echo "  Clean: Remove orphaned tracks\n";
}

echo "\n";

try {
    // Load tracks from Google Sheet
    echo "Downloading and parsing Google Sheet...\n";
    $tracks = $generator->loadFromSheet(
        $options['sheet_id'],
        $options['gid'],
        $options['organism'],
        $options['assembly']
    );
    
    $regularCount = count($tracks['regular']);
    $comboCount = count($tracks['combo']);
    
    echo "✓ Found $regularCount regular tracks and $comboCount combo tracks\n\n";
    
    // Show track summary
    if ($regularCount > 0) {
        echo "Regular Tracks:\n";
        $shown = 0;
        foreach ($tracks['regular'] as $track) {
            echo "  - " . $track['track_id'] . " (" . $track['name'] . ")\n";
            $shown++;
            if ($shown >= 5 && $regularCount > 5) {
                echo "  ... and " . ($regularCount - 5) . " more\n";
                break;
            }
        }
        echo "\n";
    }
    
    if ($comboCount > 0) {
        echo "Combo Tracks:\n";
        foreach ($tracks['combo'] as $combo) {
            $totalFiles = 0;
            foreach ($combo['groups'] as $group) {
                $totalFiles += count($group['tracks']);
            }
            echo "  - " . $combo['track_id'] . " (" . $combo['name'] . ") - $totalFiles files\n";
        }
        echo "\n";
    }
    
    // Generate tracks
    echo "Generating tracks...\n";
    echo str_repeat('-', 60) . "\n\n";
    
    $results = $generator->generateTracks($tracks, [
        'force' => $options['force'] ? $options['force_ids'] : null,
        'dry_run' => $options['dry_run']
    ]);
    
    // Show results
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "RESULTS\n";
    echo str_repeat('=', 60) . "\n\n";
    
    echo "Total tracks processed: " . $results['stats']['total'] . "\n";
    echo "  ✓ Created: " . $results['stats']['created'] . "\n";
    echo "  ⊘ Skipped: " . $results['stats']['skipped'] . "\n";
    echo "  ✗ Failed: " . $results['stats']['failed'] . "\n";
    
    if (!empty($results['failed'])) {
        echo "\nFailed Tracks:\n";
        foreach ($results['failed'] as $failed) {
            echo "  ✗ " . $failed['track_id'] . " - " . ($failed['error'] ?? 'Unknown error') . "\n";
        }
    }
    
    // Clean orphaned tracks
    if ($options['clean']) {
        echo "\nCleaning orphaned tracks...\n";
        $sheetTrackIds = array_column($tracks['regular'], 'track_id');
        $removed = $generator->cleanOrphanedTracks($sheetTrackIds, $options['organism'], $options['assembly']);
        echo "✓ Removed $removed orphaned tracks\n";
    }
    
    echo "\n";
    
    if ($options['dry_run']) {
        echo "DRY RUN MODE - No changes were made\n";
    } else {
        echo "Done!\n";
        
        // Generate JBrowse browser config files
        echo "\nGenerating JBrowse browser configs...\n";
        $configScript = __DIR__ . '/generate-jbrowse-configs.php';
        exec("php " . escapeshellarg($configScript) . " 2>&1", $configOutput, $configReturn);
        
        if ($configReturn === 0) {
            echo "✓ Browser configs generated successfully\n";
        } else {
            echo "⚠ Warning: Config generation had issues:\n";
            echo implode("\n", $configOutput) . "\n";
        }
    }
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Parse command line arguments
 */
function parseArguments($argv)
{
    $options = [
        'sheet_id' => null,
        'gid' => null,
        'organism' => null,
        'assembly' => null,
        'force' => false,
        'force_ids' => [],
        'dry_run' => false,
        'clean' => false
    ];
    
    // First argument is sheet ID
    if (isset($argv[1]) && strpos($argv[1], '--') !== 0) {
        $options['sheet_id'] = $argv[1];
        array_shift($argv);
    }
    array_shift($argv);
    
    // Parse flags
    for ($i = 0; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        switch ($arg) {
            case '--gid':
                $options['gid'] = $argv[++$i] ?? null;
                break;
                
            case '--organism':
                $options['organism'] = $argv[++$i] ?? null;
                break;
                
            case '--assembly':
                $options['assembly'] = $argv[++$i] ?? null;
                break;
                
            case '--force':
                $options['force'] = true;
                // Collect track IDs until next flag or end
                while (isset($argv[$i + 1]) && strpos($argv[$i + 1], '--') !== 0) {
                    $options['force_ids'][] = $argv[++$i];
                }
                break;
                
            case '--dry-run':
                $options['dry_run'] = true;
                break;
                
            case '--clean':
                $options['clean'] = true;
                break;
                
            case '--help':
            case '-h':
                showUsage();
                exit(0);
        }
    }
    
    return $options;
}

/**
 * Show usage information
 */
function showUsage()
{
    echo "JBrowse Track Generator - Generate tracks from Google Sheets\n\n";
    echo "USAGE:\n";
    echo "  php generate_tracks_from_sheet.php SHEET_ID [OPTIONS]\n\n";
    echo "REQUIRED ARGUMENTS:\n";
    echo "  SHEET_ID                  Google Sheet ID\n";
    echo "  --gid GID                 Sheet GID (tab identifier)\n";
    echo "  --organism ORGANISM       Organism name\n";
    echo "  --assembly ASSEMBLY       Assembly ID\n\n";
    echo "OPTIONS:\n";
    echo "  --force [TRACK_IDS...]    Force regenerate tracks (all if no IDs given)\n";
    echo "  --dry-run                 Show what would be done without making changes\n";
    echo "  --clean                   Remove tracks not in sheet\n";
    echo "  --help, -h                Show this help message\n\n";
    echo "EXAMPLES:\n";
    echo "  # Generate all new tracks\n";
    echo "  php generate_tracks_from_sheet.php 1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo \\\n";
    echo "    --gid 1977809640 \\\n";
    echo "    --organism Nematostella_vectensis \\\n";
    echo "    --assembly GCA_033964005.1\n\n";
    echo "  # Force regenerate all tracks\n";
    echo "  php generate_tracks_from_sheet.php SHEET_ID \\\n";
    echo "    --gid 1234567 \\\n";
    echo "    --organism Organism \\\n";
    echo "    --assembly Assembly \\\n";
    echo "    --force\n\n";
    echo "  # Force regenerate specific tracks\n";
    echo "  php generate_tracks_from_sheet.php SHEET_ID \\\n";
    echo "    --gid 1234567 \\\n";
    echo "    --organism Organism \\\n";
    echo "    --assembly Assembly \\\n";
    echo "    --force track_id_1 track_id_2\n\n";
    echo "  # Dry run to preview changes\n";
    echo "  php generate_tracks_from_sheet.php SHEET_ID \\\n";
    echo "    --gid 1234567 \\\n";
    echo "    --organism Organism \\\n";
    echo "    --assembly Assembly \\\n";
    echo "    --dry-run\n\n";
    echo "  # Clean orphaned tracks\n";
    echo "  php generate_tracks_from_sheet.php SHEET_ID \\\n";
    echo "    --gid 1234567 \\\n";
    echo "    --organism Organism \\\n";
    echo "    --assembly Assembly \\\n";
    echo "    --clean\n";
}
