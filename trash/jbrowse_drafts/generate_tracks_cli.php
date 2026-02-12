#!/usr/bin/env php
<?php
/**
 * JBrowse Track Generator - CLI Interface
 * 
 * Generates JBrowse2 track configurations from Google Sheets metadata.
 * 
 * Usage:
 *   php generate_tracks_cli.php --sheet-id=SHEET_ID --gid=GID --organism=ORGANISM --assembly=ASSEMBLY [OPTIONS]
 * 
 * Options:
 *   --sheet-id     Google Sheet ID (required)
 *   --gid          Sheet GID/tab ID (required)
 *   --organism     Organism name (required)
 *   --assembly     Assembly ID (required)
 *   --dry-run      Preview changes without writing files
 *   --force        Force regeneration of existing tracks
 *   --verbose      Show detailed output
 * 
 * Example:
 *   php generate_tracks_cli.php \
 *     --sheet-id=1Md23wIYo08cjtsOZMBy5UIsNaaxTxT8ijG-QLC3CjIo \
 *     --gid=1977809640 \
 *     --organism=Nematostella_vectensis \
 *     --assembly=GCA_033964005.1 \
 *     --dry-run
 */

// Load MOOP environment
require_once __DIR__ . '/../../config/site_config.php';
require_once __DIR__ . '/../../lib/JBrowse/GoogleSheetsParser.php';
require_once __DIR__ . '/../../lib/JBrowse/TrackGenerator.php';

// Parse command line arguments
$options = getopt('', [
    'sheet-id:',
    'gid:',
    'organism:',
    'assembly:',
    'dry-run',
    'force',
    'verbose',
    'help'
]);

// Show help
if (isset($options['help']) || empty($options)) {
    echo file_get_contents(__FILE__);
    exit(0);
}

// Validate required arguments
$required = ['sheet-id', 'gid', 'organism', 'assembly'];
$missing = [];
foreach ($required as $arg) {
    if (!isset($options[$arg])) {
        $missing[] = "--$arg";
    }
}

if (!empty($missing)) {
    echo "Error: Missing required arguments: " . implode(', ', $missing) . "\n\n";
    echo "Run with --help to see usage.\n";
    exit(1);
}

// Extract options
$sheetId = $options['sheet-id'];
$gid = $options['gid'];
$organism = $options['organism'];
$assembly = $options['assembly'];
$dryRun = isset($options['dry-run']);
$force = isset($options['force']);
$verbose = isset($options['verbose']);

// Initialize components
try {
    $config = new ConfigManager();
    $parser = new GoogleSheetsParser();
    $generator = new TrackGenerator($config);
    
    // Set options
    $generator->setDryRun($dryRun);
    $generator->setForce($force);
    $generator->setVerbose($verbose);
    
    // Download and parse sheet
    if ($verbose) {
        echo "Downloading sheet...\n";
        echo "  Sheet ID: $sheetId\n";
        echo "  GID: $gid\n";
    }
    
    $content = $parser->download($sheetId, $gid);
    
    if ($verbose) {
        echo "Parsing tracks...\n";
    }
    
    $tracks = $parser->parseTracks($content, $organism, $assembly);
    
    // Show statistics
    $stats = $parser->getStatistics($tracks);
    echo "\nParsed tracks:\n";
    echo "  Regular tracks: {$stats['regular_tracks']}\n";
    echo "  Combo tracks: {$stats['combo_tracks']}\n";
    echo "  Total: {$stats['total']}\n\n";
    
    if ($dryRun) {
        echo "DRY RUN MODE - No files will be written\n\n";
    }
    
    // Generate regular tracks
    if (!empty($tracks['regular'])) {
        echo "Generating regular tracks...\n";
        
        foreach ($tracks['regular'] as $track) {
            try {
                $result = $generator->generateTrack($track);
                
                if ($result['success']) {
                    if ($verbose) {
                        echo "  ✓ {$track['name']} ({$track['track_id']})\n";
                        if ($dryRun) {
                            echo "    Would write: {$result['json_path']}\n";
                        } else {
                            echo "    Written: {$result['json_path']}\n";
                        }
                    } else {
                        echo "  ✓ {$track['name']}\n";
                    }
                } else {
                    echo "  ✗ {$track['name']}: {$result['error']}\n";
                }
                
            } catch (Exception $e) {
                echo "  ✗ {$track['name']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    // Generate combo tracks
    if (!empty($tracks['combo'])) {
        echo "Generating combo tracks...\n";
        
        foreach ($tracks['combo'] as $combo) {
            try {
                $result = $generator->generateComboTrack($combo);
                
                if ($result['success']) {
                    if ($verbose) {
                        echo "  ✓ {$combo['name']} ({$combo['track_id']})\n";
                        echo "    Groups: " . count($combo['groups']) . "\n";
                        if ($dryRun) {
                            echo "    Would write: {$result['json_path']}\n";
                        } else {
                            echo "    Written: {$result['json_path']}\n";
                        }
                    } else {
                        echo "  ✓ {$combo['name']}\n";
                    }
                } else {
                    echo "  ✗ {$combo['name']}: {$result['error']}\n";
                }
                
            } catch (Exception $e) {
                echo "  ✗ {$combo['name']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    // Summary
    echo "Done!\n";
    if ($dryRun) {
        echo "No files were written (dry run mode)\n";
    }
    
    exit(0);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($verbose) {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}
