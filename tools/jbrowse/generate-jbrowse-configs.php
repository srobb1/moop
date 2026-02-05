<?php
/**
 * Generate JBrowse2 config.json files from modular assembly definitions
 * 
 * Creates web-accessible config.json files for each assembly
 * that JBrowse2 can load without URL parameters
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
    
    // Load tracks for this assembly if they exist
    $tracksFile = $jbrowseTracksDir . '/' . $assemblyName . '.json';
    if (file_exists($tracksFile)) {
        $tracksData = json_decode(file_get_contents($tracksFile), true);
        if ($tracksData && isset($tracksData['tracks'])) {
            $config['tracks'] = $tracksData['tracks'];
        }
    }
    
    return $config;
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
