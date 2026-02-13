#!/usr/bin/env php
<?php
/**
 * Generate Synteny Tracks from Google Sheet
 * 
 * Processes dual-assembly synteny tracks (PIF, MCScan, PAF) from a dedicated Google Sheet.
 * These tracks reference TWO assemblies and are stored/configured differently from single-assembly tracks.
 * 
 * Google Sheet Format:
 * 
 * Required columns:
 * - track_id: Unique identifier
 * - name: Display name
 * - track_path: Path to track file (.pif.gz, .anchors, .paf)
 * - organism1: First organism name
 * - assembly1: First assembly ID
 * - organism2: Second organism name  
 * - assembly2: Second assembly ID
 * 
 * Optional columns:
 * - category: Track category (default: Synteny)
 * - access_level: PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN (default: PUBLIC)
 * - bed1_path: BED file for organism1 (required for MCScan .anchors)
 * - bed2_path: BED file for organism2 (required for MCScan .anchors)
 * - description, technique, institute, etc.
 * 
 * Usage:
 *   php generate_synteny_tracks_from_sheet.php SHEET_ID --gid GID [OPTIONS]
 * 
 * Examples:
 *   # Generate all synteny tracks from sheet
 *   php generate_synteny_tracks_from_sheet.php 1AbC123... --gid 0
 * 
 *   # Dry run to preview
 *   php generate_synteny_tracks_from_sheet.php 1AbC123... --gid 0 --dry-run
 * 
 *   # Force regenerate specific tracks
 *   php generate_synteny_tracks_from_sheet.php 1AbC123... --gid 0 --force track1,track2
 * 
 * Track Types Supported:
 * - .pif.gz: Whole genome synteny (requires .tbi index)
 * - .anchors: MCScan orthologs (requires bed1_path and bed2_path)
 * - .paf: PAF alignments between two assemblies
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../lib/JBrowse/SyntenyGoogleSheetsParser.php';
require_once __DIR__ . '/../../lib/JBrowse/SyntenyTrackGenerator.php';

// Parse arguments
$options = parseArguments($argv);

if ($options['help']) {
    printHelp();
    exit(0);
}

// Validate required arguments
if (empty($options['sheet_id'])) {
    echo "Error: Sheet ID is required\n\n";
    printHelp();
    exit(1);
}

if (empty($options['gid'])) {
    echo "Error: GID is required (use --gid 0 for first sheet)\n\n";
    printHelp();
    exit(1);
}

// Initialize
$config = ConfigManager::getInstance();
$parser = new SyntenyGoogleSheetsParser();
$generator = new SyntenyTrackGenerator($config);

// Print configuration
if (!$options['quiet']) {
    echo "================================================================================\n";
    echo "SYNTENY TRACK GENERATOR (Dual-Assembly Tracks)\n";
    echo "================================================================================\n\n";
    echo "Configuration:\n";
    echo "  Sheet ID: {$options['sheet_id']}\n";
    echo "  GID: {$options['gid']}\n";
    if ($options['dry_run']) {
        echo "  Mode: DRY RUN (no changes will be made)\n";
    }
    if (!empty($options['force'])) {
        echo "  Force: " . implode(', ', $options['force']) . "\n";
    }
    echo "\n";
}

// Download and parse sheet
if (!$options['quiet']) {
    echo "Downloading and parsing Google Sheet...\n";
}

try {
    $content = $parser->download($options['sheet_id'], $options['gid']);
    $tracks = $parser->parseTracks($content);
    
    if (empty($tracks)) {
        echo "No synteny tracks found in sheet\n";
        exit(0);
    }
    
    if (!$options['quiet']) {
        echo "Found " . count($tracks) . " synteny tracks\n\n";
    }
    
    // Group tracks by assembly pair
    $assemblyPairs = [];
    foreach ($tracks as $track) {
        $pairInfo = $parser->getAssemblyPairName(
            $track['organism1'],
            $track['assembly1'],
            $track['organism2'],
            $track['assembly2']
        );
        $pairKey = $pairInfo['name'];
        
        if (!isset($assemblyPairs[$pairKey])) {
            $assemblyPairs[$pairKey] = [
                'assembly1' => $pairInfo['assembly1'],
                'assembly2' => $pairInfo['assembly2'],
                'tracks' => []
            ];
        }
        
        $assemblyPairs[$pairKey]['tracks'][] = $track;
    }
    
    if (!$options['quiet']) {
        echo "Assembly pairs found:\n";
        foreach ($assemblyPairs as $pairKey => $pairData) {
            echo "  • $pairKey (" . count($pairData['tracks']) . " tracks)\n";
        }
        echo "\n";
    }
    
    // Process each assembly pair
    $totalProcessed = 0;
    $totalCreated = 0;
    $totalSkipped = 0;
    $totalFailed = 0;
    
    foreach ($assemblyPairs as $pairKey => $pairData) {
        if (!$options['quiet']) {
            echo "Processing assembly pair: $pairKey\n";
            echo str_repeat("=", 80) . "\n";
        }
        
        foreach ($pairData['tracks'] as $track) {
            $totalProcessed++;
            
            // Determine track type from extension
            $trackType = $generator->determineTrackType($track['track_path']);
            
            if (!$trackType) {
                if (!$options['quiet']) {
                    echo "  ✗ {$track['track_id']}: Unknown track type\n";
                }
                $totalFailed++;
                continue;
            }
            
            // Get track handler
            $handler = $generator->getTrackTypeHandler($trackType);
            
            if (!$handler) {
                if (!$options['quiet']) {
                    echo "  ✗ {$track['track_id']}: No handler for type '$trackType'\n";
                }
                $totalFailed++;
                continue;
            }
            
            // Check if track exists
            $trackExists = trackExists($track['organism1'], $track['assembly1'], $track['track_id'], $trackType);
            
            // Skip if exists and not forcing
            if ($trackExists && !in_array($track['track_id'], $options['force'])) {
                if (!$options['quiet']) {
                    echo "  ⊘ {$track['track_id']}: Already exists (use --force to regenerate)\n";
                }
                $totalSkipped++;
                continue;
            }
            
            // Validate track
            $validation = $handler->validate($track);
            
            if (!$validation['valid']) {
                if (!$options['quiet']) {
                    echo "  ✗ {$track['track_id']}: Validation failed\n";
                    foreach ($validation['errors'] as $error) {
                        echo "      - $error\n";
                    }
                }
                $totalFailed++;
                continue;
            }
            
            // Generate track
            if (!$options['quiet']) {
                echo "  → {$track['track_id']}: {$track['name']}\n";
                echo "      Type: $trackType\n";
                echo "      Assembly 1: {$track['organism1']} {$track['assembly1']}\n";
                echo "      Assembly 2: {$track['organism2']} {$track['assembly2']}\n";
            }
            
            $success = $handler->generate(
                $track,
                $track['organism1'],  // Primary organism
                $track['assembly1'],  // Primary assembly
                array_merge($options, ['dry_run' => $options['dry_run']])
            );
            
            if ($success) {
                if (!$options['quiet']) {
                    echo "      ✓ Generated successfully\n";
                }
                $totalCreated++;
            } else {
                if (!$options['quiet']) {
                    echo "      ✗ Generation failed\n";
                }
                $totalFailed++;
            }
        }
        
        if (!$options['quiet']) {
            echo "\n";
        }
    }
    
    // Print summary
    if (!$options['quiet']) {
        echo "============================================================\n";
        echo "RESULTS\n";
        echo "============================================================\n\n";
        echo "Total tracks processed: $totalProcessed\n";
        echo "  ✓ Created: $totalCreated\n";
        echo "  ⊘ Skipped: $totalSkipped\n";
        echo "  ✗ Failed: $totalFailed\n\n";
        
        if (!$options['dry_run']) {
            echo "Done!\n\n";
            echo "Next step: Generate JBrowse browser configs\n";
            echo "  php tools/jbrowse/generate-jbrowse-configs.php\n";
        } else {
            echo "DRY RUN - No changes were made\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Check if track exists
 */
function trackExists($organism, $assembly, $trackId, $trackType) {
    global $config;
    
    $metadataDir = $config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
    $trackDir = "$metadataDir/synteny/{$organism}_{$assembly}/$trackType";
    $trackFile = "$trackDir/$trackId.json";
    
    return file_exists($trackFile);
}

/**
 * Parse command line arguments
 */
function parseArguments($argv) {
    $options = [
        'sheet_id' => '',
        'gid' => '',
        'force' => [],
        'dry_run' => false,
        'quiet' => false,
        'help' => false
    ];
    
    // First argument is sheet ID
    if (isset($argv[1]) && !str_starts_with($argv[1], '--')) {
        $options['sheet_id'] = $argv[1];
    }
    
    // Parse flags
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
        } elseif ($arg === '--gid') {
            $options['gid'] = $argv[++$i] ?? '';
        } elseif ($arg === '--force') {
            $forceList = $argv[++$i] ?? '';
            $options['force'] = array_map('trim', explode(',', $forceList));
        } elseif ($arg === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($arg === '--quiet' || $arg === '-q') {
            $options['quiet'] = true;
        }
    }
    
    return $options;
}

/**
 * Print help message
 */
function printHelp() {
    echo <<<HELP
Synteny Track Generator - Dual-Assembly Tracks from Google Sheet

Usage:
  php generate_synteny_tracks_from_sheet.php SHEET_ID --gid GID [OPTIONS]

Required:
  SHEET_ID                Google Sheet ID
  --gid GID              Sheet GID (use 0 for first sheet)

Options:
  --force TRACK_IDS      Force regenerate specific tracks (comma-separated)
  --dry-run              Preview without making changes
  --quiet, -q            Minimal output
  --help, -h             Show this help

Google Sheet Format:
  Required columns:
    track_id, name, track_path, organism1, assembly1, organism2, assembly2

  Optional columns:
    category, access_level, bed1_path, bed2_path, description, etc.

Track Types:
  .pif.gz               Whole genome synteny (requires .tbi index)
  .anchors              MCScan orthologs (requires bed1_path, bed2_path)
  .paf                  PAF alignments between assemblies

Examples:
  # Generate all tracks
  php generate_synteny_tracks_from_sheet.php 1AbC123... --gid 0

  # Dry run
  php generate_synteny_tracks_from_sheet.php 1AbC123... --gid 0 --dry-run

  # Force specific tracks
  php generate_synteny_tracks_from_sheet.php 1AbC123... --gid 0 --force track1,track2

After generating tracks, run:
  php tools/jbrowse/generate-jbrowse-configs.php

HELP;
}
