#!/usr/bin/env php
<?php
/**
 * Google Sheets to JBrowse2 Track Generator (PHP Implementation)
 * 
 * This script reads track metadata from a Google Sheet and automatically generates
 * JBrowse2 track configurations.
 * 
 * Features:
 * - Auto-detects track types from file extensions and categories
 * - Supports multi-BigWig tracks (combo tracks)
 * - 28 color schemes with cycling (blues, reds, purples, rainbow, etc.)
 * - Access level control (PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN)
 * - Checks for existing tracks to avoid duplicates
 * - AUTO track resolution for reference sequences and annotations
 * 
 * Usage:
 *   php generate_tracks_from_sheet.php SHEET_ID [OPTIONS]
 * 
 * Example:
 *   php generate_tracks_from_sheet.php \
 *     1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo \
 *     --gid 1977809640 \
 *     --organism Nematostella_vectensis \
 *     --assembly GCA_033964005.1
 * 
 * Required Columns in Google Sheet:
 *   - track_id: [REQUIRED] Unique track identifier (used as trackId in JBrowse2)
 *   - name: [REQUIRED] Display name (shown in JBrowse2 UI)
 *   - category: [REQUIRED] Track category (organizational, e.g., "Gene Expression")
 *   - TRACK_PATH: [REQUIRED] File path or URL to track file
 * 
 * TRACK_PATH Format:
 *   - Absolute path: /data/moop/data/tracks/sample.bw
 *   - Relative path: data/tracks/sample.bw (resolved via ConfigManager)
 *   - HTTP/HTTPS URL: https://server.edu/tracks/sample.bw (used as-is)
 *   - AUTO (for reference/annotations): Script auto-resolves to:
 *     * Reference: {genomes_dir}/{organism}/{assembly}/reference.fasta
 *     * Annotations: {genomes_dir}/{organism}/{assembly}/genomic.gff
 * 
 * Optional Columns:
 *   - access_level: PUBLIC, COLLABORATOR, IP_IN_RANGE, or ADMIN (default: PUBLIC)
 *       Hierarchy: PUBLIC < COLLABORATOR < IP_IN_RANGE < ADMIN
 *       Higher levels inherit tracks from lower levels
 *   - description: Track description
 *   - technique: Technique used (e.g., RNA-seq, ChIP-seq)
 *   - condition: Experimental condition
 *   - tissue: Tissue/organ type
 *   - #any_column: Columns starting with # are ignored
 *   - ...any other columns for your own metadata
 * 
 * Combo Tracks (Multi-BigWig):
 *   Denoted by special markers in the sheet:
 *   # Combo Track Name
 *   ## colorscheme: group name
 *   track1 data
 *   track2 data
 *   ## colorscheme: another group
 *   track3 data
 *   ### end
 * 
 * Color Schemes:
 *   - Regular: ## blues: Sample Group (cycles through 11 blues)
 *   - Exact: ## exact=OrangeRed: Group (all tracks use OrangeRed)
 *   - Indexed: ## reds3: Group (all tracks use 4th red, 0-indexed: Crimson)
 *   - See --list-colors for all 28 available schemes
 * 
 * Information Flags:
 *   --list-colors           List all available color schemes
 *   --suggest-colors N      Suggest best color schemes for N files
 *   --list-track-ids        List track IDs that would be created from sheet
 *   --list-existing         List existing tracks for organism/assembly
 * 
 * Track Generation Flags:
 *   --force [TRACK_IDS...]  Force regenerate tracks (all if no IDs given)
 *   --dry-run               Show what would be done without making changes
 *   --clean                 Remove tracks not in sheet
 * 
 * Exit Codes:
 *   0 - Success
 *   1 - Error (missing args, parsing failure, etc.)
 * 
 * Output:
 *   - Track metadata JSON files in: metadata/jbrowse2-configs/tracks/{organism}/{assembly}/{type}/
 *   - Assembly definition in: metadata/jbrowse2-configs/assemblies/{organism}_{assembly}.json
 *   - Browser config files in: jbrowse2/configs/{organism}_{assembly}/
 * 
 * @package MOOP\JBrowse
 * @author JBrowse Track Generator
 * @version 2.0 (PHP implementation)
 */

// Bootstrap
require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../lib/JBrowse/TrackGenerator.php';
require_once __DIR__ . '/../../lib/JBrowse/ColorSchemes.php';

// Parse command line arguments
$options = parseArguments($argv);

// Handle --list-colors flag
if (!empty($options['list_colors'])) {
    ColorSchemes::listSchemes();
    exit(0);
}

// Handle --suggest-colors flag
if (!empty($options['suggest_colors'])) {
    $numFiles = intval($options['suggest_colors']);
    if ($numFiles < 1) {
        echo "Error: --suggest-colors requires a positive number\n";
        exit(1);
    }
    ColorSchemes::displaySuggestions($numFiles);
    exit(0);
}

// Handle --list-existing flag (requires organism/assembly)
if (!empty($options['list_existing'])) {
    if (empty($options['organism']) || empty($options['assembly'])) {
        echo "Error: --list-existing requires --organism and --assembly\n";
        exit(1);
    }
    
    $config = ConfigManager::getInstance();
    $generator = new TrackGenerator($config);
    listExistingTracks($generator, $options['organism'], $options['assembly']);
    exit(0);
}

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

// Handle --list-track-ids flag (requires sheet to be loaded)
if (!empty($options['list_track_ids'])) {
    listTrackIdsFromSheet($generator, $options);
    exit(0);
}

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
        'clean' => false,
        'list_colors' => false,
        'suggest_colors' => null,
        'list_track_ids' => false,
        'list_existing' => false
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
                
            case '--list-colors':
                $options['list_colors'] = true;
                break;
                
            case '--suggest-colors':
                $options['suggest_colors'] = $argv[++$i] ?? null;
                break;
                
            case '--list-track-ids':
                $options['list_track_ids'] = true;
                break;
                
            case '--list-existing':
                $options['list_existing'] = true;
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
    echo "  --list-colors             List available color schemes for combo tracks\n";
    echo "  --suggest-colors N        Suggest best color schemes for N files\n";
    echo "  --list-track-ids          List track IDs from sheet and exit\n";
    echo "  --list-existing           List existing track IDs for organism/assembly and exit\n";
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

/**
 * List track IDs that would be created from Google Sheet
 */
function listTrackIdsFromSheet($generator, $options)
{
    try {
        // Load tracks from sheet
        $tracks = $generator->loadFromSheet(
            $options['sheet_id'],
            $options['gid'],
            $options['organism'],
            $options['assembly']
        );
        
        echo str_repeat('=', 80) . "\n";
        echo "TRACK IDs FROM GOOGLE SHEET\n";
        echo str_repeat('=', 80) . "\n\n";
        
        if (!empty($tracks['regular'])) {
            echo "Regular Tracks (" . count($tracks['regular']) . "):\n";
            echo str_repeat('-', 80) . "\n";
            foreach ($tracks['regular'] as $track) {
                $trackId = $track['track_id'];
                $name = $track['name'];
                // Determine type from path
                $trackType = $generator->determineTrackType($track['TRACK_PATH']);
                echo "  $trackId\n";
                echo "    → \"$name\" ($trackType)\n";
            }
            echo "\n";
        }
        
        if (!empty($tracks['combo'])) {
            echo "Combo Tracks (" . count($tracks['combo']) . "):\n";
            echo str_repeat('-', 80) . "\n";
            foreach ($tracks['combo'] as $comboTrack) {
                $trackId = $comboTrack['track_id'];
                $name = $comboTrack['name'];
                $subtrackCount = 0;
                foreach ($comboTrack['groups'] as $group) {
                    $subtrackCount += count($group['tracks']);
                }
                echo "  $trackId\n";
                echo "    → \"$name\" (multi-bigwig, $subtrackCount subtracks)\n";
            }
            echo "\n";
        }
        
        echo str_repeat('=', 80) . "\n";
        echo "USAGE:\n";
        echo "  # Create all tracks:\n";
        echo "  php generate_tracks_from_sheet.php {$options['sheet_id']} \\\n";
        echo "    --gid {$options['gid']} \\\n";
        echo "    --organism {$options['organism']} \\\n";
        echo "    --assembly {$options['assembly']}\n\n";
        echo "  # Force regenerate specific track:\n";
        echo "  php generate_tracks_from_sheet.php {$options['sheet_id']} --force TRACK_ID \\\n";
        echo "    --gid {$options['gid']} \\\n";
        echo "    --organism {$options['organism']} \\\n";
        echo "    --assembly {$options['assembly']}\n";
        echo str_repeat('=', 80) . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * List existing tracks for organism/assembly
 */
function listExistingTracks($generator, $organism, $assembly)
{
    echo str_repeat('=', 80) . "\n";
    echo "EXISTING TRACKS: $organism / $assembly\n";
    echo str_repeat('=', 80) . "\n\n";
    
    $tracks = $generator->getTrackStatus($organism, $assembly);
    
    if (empty($tracks)) {
        echo "No existing tracks found\n\n";
        echo "Track metadata location:\n";
        echo "  metadata/jbrowse2-configs/tracks/$organism/$assembly/\n";
        return;
    }
    
    // Group by type
    $byType = [];
    foreach ($tracks as $track) {
        $type = $track['type'];
        if (!isset($byType[$type])) {
            $byType[$type] = [];
        }
        $byType[$type][] = $track;
    }
    
    echo "Total: " . count($tracks) . " tracks\n\n";
    
    foreach ($byType as $type => $typeTracks) {
        echo ucfirst($type) . " Tracks (" . count($typeTracks) . "):\n";
        echo str_repeat('-', 80) . "\n";
        echo "  track_id → track display name (category)\n\n";
        foreach ($typeTracks as $track) {
            $category = is_array($track['category']) ? implode(', ', $track['category']) : $track['category'];
            // Single line format for easy grepping
            echo "  {$track['track_id']} → \"{$track['name']}\" ($category)\n";
        }
        echo "\n";
    }
    
    echo str_repeat('=', 80) . "\n";
}

