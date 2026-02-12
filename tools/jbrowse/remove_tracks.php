#!/usr/bin/env php
<?php
/**
 * Remove JBrowse Tracks CLI
 * 
 * Command-line tool to remove tracks, assemblies, or organisms.
 * Replaces remove_jbrowse_data.sh with pure PHP implementation.
 * 
 * Usage:
 *   php remove_tracks.php --organism Organism --assembly Assembly [options]
 *   php remove_tracks.php --organism Organism [options]
 *   php remove_tracks.php --track TRACK_ID --organism Organism --assembly Assembly [options]
 * 
 * @package MOOP
 * @subpackage JBrowse
 */

// Bootstrap
require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../lib/JBrowse/PathResolver.php';
require_once __DIR__ . '/../../lib/JBrowse/TrackManager.php';

// Parse command-line arguments
$options = parseArguments($argv);

// Validate
if (empty($options['organism'])) {
    showUsage();
    exit(1);
}

// Help
if (!empty($options['help'])) {
    showUsage();
    exit(0);
}

// Initialize components
try {
    $config = ConfigManager::getInstance();
    $pathResolver = new PathResolver($config);
    $trackManager = new TrackManager($config, $pathResolver);
} catch (Exception $e) {
    echo "Error: Failed to initialize: " . $e->getMessage() . "\n";
    exit(1);
}

// Determine scope
$organism = $options['organism'];
$assembly = $options['assembly'] ?? null;
$trackId = $options['track'] ?? null;

if ($trackId) {
    $scope = 'track';
    if (!$assembly) {
        echo "Error: --assembly required when removing a track\n";
        exit(1);
    }
} elseif ($assembly) {
    $scope = 'assembly';
} else {
    $scope = 'organism';
}

// Display header
echo "\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "    Remove JBrowse Tracks\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

if (!empty($options['dry_run'])) {
    echo "⚠  DRY RUN MODE - No changes will be made\n";
    echo "\n";
}

// Show what will be removed
echo "Scope: ";
switch ($scope) {
    case 'track':
        echo "Remove single track\n";
        echo "  Track ID: {$trackId}\n";
        echo "  Organism: {$organism}\n";
        echo "  Assembly: {$assembly}\n";
        break;
    case 'assembly':
        echo "Remove all tracks for assembly\n";
        echo "  Organism: {$organism}\n";
        echo "  Assembly: {$assembly}\n";
        break;
    case 'organism':
        echo "Remove all assemblies for organism\n";
        echo "  Organism: {$organism}\n";
        break;
}

echo "\n";

// Show what will be affected
echo "What will be removed:\n";
echo "────────────────────────────────────────────────────────────────\n";

if ($scope === 'track') {
    echo "  - Track metadata: {$trackId}.json\n";
    if (!empty($options['remove_data'])) {
        echo "  - Track data file [DATA]\n";
    }
} elseif ($scope === 'assembly') {
    $trackCount = count($trackManager->listTracks($organism, $assembly));
    echo "  - Track metadata ({$trackCount} tracks)\n";
    echo "  - Assembly metadata\n";
    echo "  - Cached configs\n";
    if (!empty($options['remove_data'])) {
        echo "  - Genome data [DATA]\n";
        echo "  - Track data [DATA]\n";
    }
} else {
    $assemblies = $trackManager->listAssemblies($organism);
    echo "  Found " . count($assemblies) . " assemblies for {$organism}\n\n";
    foreach ($assemblies as $asm) {
        $trackCount = count($trackManager->listTracks($organism, $asm));
        echo "  Assembly: {$asm}\n";
        echo "    - Track metadata ({$trackCount} tracks)\n";
        echo "    - Assembly metadata\n";
        echo "    - Cached configs\n";
        if (!empty($options['remove_data'])) {
            echo "    - Genome data [DATA]\n";
            echo "    - Track data [DATA]\n";
        }
        echo "\n";
    }
}

echo "────────────────────────────────────────────────────────────────\n";
echo "\n";

if (!empty($options['remove_data'])) {
    echo "ℹ  Data files will be deleted (--remove-data specified)\n";
} else {
    echo "ℹ  Data files will be preserved (use --remove-data to delete)\n";
}
echo "\n";

// Confirmation
if (empty($options['dry_run']) && empty($options['yes'])) {
    echo "⚠  Continue? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes') {
        echo "ℹ  Cancelled\n";
        exit(0);
    }
}

echo "\n";

// Execute removal
$result = null;

try {
    $managerOptions = [
        'dry_run' => !empty($options['dry_run']),
        'remove_data' => !empty($options['remove_data'])
    ];
    
    switch ($scope) {
        case 'track':
            echo "Removing track...\n";
            $result = $trackManager->removeTrack($trackId, $organism, $assembly, $managerOptions);
            break;
            
        case 'assembly':
            echo "Removing assembly...\n";
            $result = $trackManager->removeAssembly($organism, $assembly, $managerOptions);
            break;
            
        case 'organism':
            echo "Removing organism...\n";
            $result = $trackManager->removeOrganism($organism, $managerOptions);
            break;
    }
    
    // Display results
    echo "\n";
    
    if (!empty($result['errors'])) {
        echo "✗ Errors occurred:\n";
        foreach ($result['errors'] as $error) {
            echo "  - {$error}\n";
        }
        echo "\n";
    }
    
    if (!empty($result['items_removed'])) {
        echo "Items removed:\n";
        foreach ($result['items_removed'] as $item) {
            echo "  ✓ {$item}\n";
        }
        echo "\n";
    }
    
    if ($result['success']) {
        echo "✓ Removal completed successfully\n";
        
        if (!empty($options['dry_run'])) {
            echo "  [DRY RUN] No changes were actually made\n";
        }
    } else {
        echo "✗ Removal failed\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
echo "════════════════════════════════════════════════════════════════\n";

if (!empty($options['dry_run'])) {
    echo "  [DRY RUN] No changes were made\n";
} else {
    echo "✓ Complete\n";
    if (empty($options['remove_data'])) {
        echo "\n";
        echo "Data files preserved. Regenerate tracks with:\n";
        echo "  php tools/jbrowse/generate_tracks_from_sheet.php ...\n";
    }
}

echo "════════════════════════════════════════════════════════════════\n";
echo "\n";

exit(0);

/**
 * Parse command-line arguments
 */
function parseArguments($argv)
{
    $options = [
        'organism' => null,
        'assembly' => null,
        'track' => null,
        'dry_run' => false,
        'remove_data' => false,
        'yes' => false,
        'help' => false
    ];
    
    array_shift($argv); // Remove script name
    
    for ($i = 0; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        switch ($arg) {
            case '--organism':
                $options['organism'] = $argv[++$i] ?? null;
                break;
                
            case '--assembly':
                $options['assembly'] = $argv[++$i] ?? null;
                break;
                
            case '--track':
                $options['track'] = $argv[++$i] ?? null;
                break;
                
            case '--dry-run':
                $options['dry_run'] = true;
                break;
                
            case '--remove-data':
                $options['remove_data'] = true;
                break;
                
            case '--yes':
            case '-y':
                $options['yes'] = true;
                break;
                
            case '--help':
            case '-h':
                $options['help'] = true;
                break;
                
            default:
                echo "Unknown option: {$arg}\n";
                showUsage();
                exit(1);
        }
    }
    
    return $options;
}

/**
 * Show usage information
 */
function showUsage()
{
    echo "Remove JBrowse Tracks\n\n";
    echo "USAGE:\n";
    echo "  php remove_tracks.php --organism ORGANISM [OPTIONS]\n\n";
    echo "REQUIRED:\n";
    echo "  --organism ORGANISM       Organism name\n\n";
    echo "OPTIONS:\n";
    echo "  --assembly ASSEMBLY       Assembly ID (remove specific assembly)\n";
    echo "  --track TRACK_ID          Track ID (requires --assembly)\n";
    echo "  --remove-data             Also delete data files (default: keep)\n";
    echo "  --dry-run                 Show what would be removed\n";
    echo "  --yes, -y                 Skip confirmation prompt\n";
    echo "  --help, -h                Show this help\n\n";
    echo "EXAMPLES:\n";
    echo "  # Remove single track (metadata only)\n";
    echo "  php remove_tracks.php \\\n";
    echo "    --track my_track_id \\\n";
    echo "    --organism Nematostella_vectensis \\\n";
    echo "    --assembly GCA_033964005.1\n\n";
    echo "  # Remove assembly (metadata only)\n";
    echo "  php remove_tracks.php \\\n";
    echo "    --organism Nematostella_vectensis \\\n";
    echo "    --assembly GCA_033964005.1\n\n";
    echo "  # Remove organism (all assemblies, metadata only)\n";
    echo "  php remove_tracks.php \\\n";
    echo "    --organism Nematostella_vectensis\n\n";
    echo "  # Remove with data files\n";
    echo "  php remove_tracks.php \\\n";
    echo "    --organism Nematostella_vectensis \\\n";
    echo "    --assembly GCA_033964005.1 \\\n";
    echo "    --remove-data\n\n";
    echo "  # Dry run (preview)\n";
    echo "  php remove_tracks.php \\\n";
    echo "    --organism Nematostella_vectensis \\\n";
    echo "    --dry-run\n";
}
