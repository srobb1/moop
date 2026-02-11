<?php
/**
 * Generate JBrowse2 config.json files from modular assembly definitions
 * 
 * Creates web-accessible config.json files for each assembly
 * that JBrowse2 can load without URL parameters
 * 
 * Also generates cached configs per access level:
 * - PUBLIC.json (public tracks only)
 * - COLLABORATOR.json (public + collaborator tracks)
 * - ADMIN.json (all tracks)
 * - IP_IN_RANGE.json (all tracks)
 */

// Define paths (from tools/jbrowse/ to project root)
$PROJECT_ROOT = __DIR__ . '/../../';

$METADATA_ASSEMBLIES_DIR = $PROJECT_ROOT . 'metadata/jbrowse2-configs/assemblies';
$JBROWSE_CONFIGS_DIR = $PROJECT_ROOT . 'jbrowse2/configs';
$JBROWSE_TRACKS_DIR = $PROJECT_ROOT . 'metadata/jbrowse2-configs/tracks';

// Create configs directory if it doesn't exist
if (!is_dir($JBROWSE_CONFIGS_DIR)) {
    mkdir($JBROWSE_CONFIGS_DIR, 0775, true);
}

/**
 * Generate a JBrowse2 config.json for a specific assembly
 */
function generateAssemblyConfig($assemblyDef, $jbrowseTracksDir) {
    $assemblyName = $assemblyDef['name'];
    
    // Start with base config structure
    $config = [
        'assemblies' => [$assemblyDef],
        'configuration' => [],
        'connections' => [],
        'defaultSession' => [
            'name' => 'New Session',
            'view' => [
                'id' => 'linearGenomeView',
                'type' => 'LinearGenomeView',
                'offsetPx' => 0,
                'bpPerPx' => 1,
                'displayedRegions' => []
            ]
        ],
        'tracks' => []
    ];
    
    // Load all individual track files for this assembly
    $trackFiles = glob($jbrowseTracksDir . '/*.json');
    foreach ($trackFiles as $trackFile) {
        $trackData = json_decode(file_get_contents($trackFile), true);
        if ($trackData && isset($trackData['assemblyNames'])) {
            // Check if this track belongs to this assembly
            if (in_array($assemblyName, $trackData['assemblyNames'])) {
                $config['tracks'][] = $trackData;
            }
        }
    }
    
    return $config;
}

/**
 * Generate cached configs per access level
 * Filters tracks based on access_level metadata
 */
function generateCachedConfigs($assemblyDef, $jbrowseTracksDir, $assemblyDir) {
    $assemblyName = $assemblyDef['name'];
    
    // Define access hierarchy
    // ADMIN is highest (can see test/unreleased tracks)
    // IP_IN_RANGE is below ADMIN but above COLLABORATOR
    $accessLevels = ['PUBLIC', 'COLLABORATOR', 'IP_IN_RANGE', 'ADMIN'];
    $accessHierarchy = [
        'PUBLIC' => 1,
        'COLLABORATOR' => 2,
        'IP_IN_RANGE' => 3,
        'ADMIN' => 4
    ];
    
    // Base config structure
    $baseConfig = [
        'assemblies' => [$assemblyDef],
        'configuration' => [],
        'connections' => [],
        'defaultSession' => [
            'name' => 'New Session',
            'view' => [
                'id' => 'linearGenomeView',
                'type' => 'LinearGenomeView',
                'offsetPx' => 0,
                'bpPerPx' => 1,
                'displayedRegions' => []
            ]
        ],
        'tracks' => []
    ];
    
    // Load all tracks
    $allTracks = [];
    $trackFiles = glob($jbrowseTracksDir . '/*.json');
    foreach ($trackFiles as $trackFile) {
        $trackData = json_decode(file_get_contents($trackFile), true);
        if ($trackData && isset($trackData['assemblyNames']) && in_array($assemblyName, $trackData['assemblyNames'])) {
            $allTracks[] = $trackData;
        }
    }
    
    // Generate config for each access level
    foreach ($accessLevels as $level) {
        $config = $baseConfig;
        $userLevel = $accessHierarchy[$level];
        
        // Filter tracks based on access level
        foreach ($allTracks as $track) {
            $trackAccessLevel = $track['metadata']['access_level'] ?? 'PUBLIC';
            $requiredLevel = $accessHierarchy[$trackAccessLevel] ?? 1;
            
            // User can see track if their level >= required level
            if ($userLevel >= $requiredLevel) {
                $config['tracks'][] = $track;
            }
        }
        
        // Write cached config
        $cacheFile = $assemblyDir . '/' . $level . '.json';
        file_put_contents($cacheFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($cacheFile, 0664);
        
        echo "  ✓ Generated {$level}.json (" . count($config['tracks']) . " tracks)\n";
    }
}

/**
 * Process all assemblies and generate config files
 */
function processAssemblies($metadataDir, $jbrowseDir, $tracksDir) {
    $count = 0;
    $errors = [];
    
    if (!is_dir($metadataDir)) {
        echo "Error: Metadata directory not found: $metadataDir\n";
        return ['count' => 0, 'errors' => ["Directory not found: $metadataDir"]];
    }
    
    $files = glob($metadataDir . '/*.json');
    
    foreach ($files as $file) {
        $assemblyName = basename($file, '.json');
        
        try {
            // Read assembly definition
            $assemblyDef = json_decode(file_get_contents($file), true);
            if (!$assemblyDef) {
                $errors[] = "Invalid JSON in $file";
                continue;
            }
            
            // Generate config
            $config = generateAssemblyConfig($assemblyDef, $tracksDir);
            
            // Create assembly-specific directory
            $assemblyDir = $jbrowseDir . '/' . $assemblyName;
            if (!is_dir($assemblyDir)) {
                mkdir($assemblyDir, 0775, true);
            }
            
            // Write config.json
            $configFile = $assemblyDir . '/config.json';
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            chmod($configFile, 0664);
            
            echo "✓ Generated config for: $assemblyName\n";
            
            // Generate cached configs per access level
            generateCachedConfigs($assemblyDef, $tracksDir, $assemblyDir);
            
            $count++;
            
        } catch (Exception $e) {
            $errors[] = "Error processing $assemblyName: " . $e->getMessage();
        }
    }
    
    return ['count' => $count, 'errors' => $errors];
}

// Run generation
echo "Generating JBrowse2 config files...\n";
echo "Metadata dir: $METADATA_ASSEMBLIES_DIR\n";
echo "Output dir: $JBROWSE_CONFIGS_DIR\n";
echo "---\n";

$result = processAssemblies($METADATA_ASSEMBLIES_DIR, $JBROWSE_CONFIGS_DIR, $JBROWSE_TRACKS_DIR);

echo "---\n";
echo "Generated {$result['count']} config files\n";

if (!empty($result['errors'])) {
    echo "\nErrors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}
?>
