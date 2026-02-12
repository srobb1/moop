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

// Load ConfigManager
require_once __DIR__ . '/../../includes/config_init.php';
$config = ConfigManager::getInstance();

// Get paths from config
$SITE_PATH = $config->getPath('site_path');
$METADATA_ASSEMBLIES_DIR = $config->getPath('metadata_path') . '/jbrowse2-configs/assemblies';
$JBROWSE_CONFIGS_DIR = $SITE_PATH . '/jbrowse2/configs';
$JBROWSE_TRACKS_DIR = $config->getPath('metadata_path') . '/jbrowse2-configs/tracks';
$GENOMES_DIR = $SITE_PATH . '/data/genomes';

// Create configs directory if it doesn't exist
if (!is_dir($JBROWSE_CONFIGS_DIR)) {
    mkdir($JBROWSE_CONFIGS_DIR, 0775, true);
}

/**
 * Generate a JBrowse2 config.json for a specific assembly
 */
function generateAssemblyConfig($assemblyDef, $jbrowseTracksDir) {
    $assemblyName = $assemblyDef['name'];
    
    // Extract organism and assembly from assemblyDef
    $organism = $assemblyDef['organism'];
    $assembly = $assemblyDef['assemblyId'];
    
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
    // Support both hierarchical and flat structures
    $trackFiles = [];
    
    // Try hierarchical structure first: tracks/organism/assembly/*/*.json
    $hierarchicalPattern = $jbrowseTracksDir . '/' . $organism . '/' . $assembly . '/*/*.json';
    $hierarchicalFiles = glob($hierarchicalPattern);
    if ($hierarchicalFiles) {
        $trackFiles = $hierarchicalFiles;
    } else {
        // Fall back to flat structure
        $trackFiles = glob($jbrowseTracksDir . '/*.json');
    }
    
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
function generateCachedConfigs($assemblyDef, $jbrowseTracksDir, $assemblyDir, $genomesDir) {
    $assemblyName = $assemblyDef['name'];
    
    // Extract organism and assembly from assemblyDef
    $organism = $assemblyDef['organism'];
    $assembly = $assemblyDef['assemblyId'];
    
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
    
    // Support both hierarchical and flat structures
    $trackFiles = [];
    $hierarchicalPattern = $jbrowseTracksDir . '/' . $organism . '/' . $assembly . '/*/*.json';
    $hierarchicalFiles = glob($hierarchicalPattern);
    if ($hierarchicalFiles) {
        $trackFiles = $hierarchicalFiles;
    } else {
        // Fall back to flat structure
        $trackFiles = glob($jbrowseTracksDir . '/*.json');
    }
    
    foreach ($trackFiles as $trackFile) {
        $trackData = json_decode(file_get_contents($trackFile), true);
        if ($trackData && isset($trackData['assemblyNames']) && in_array($assemblyName, $trackData['assemblyNames'])) {
            $allTracks[] = $trackData;
        }
    }
    
    // Sort tracks to prioritize GFF/annotation tracks first
    // This ensures proper display order in JBrowse2 track selector
    usort($allTracks, function($a, $b) {
        // Extract track type from trackId or adapter type
        $aType = '';
        $bType = '';
        
        // Check adapter type
        if (isset($a['adapter']['type'])) {
            if (strpos($a['adapter']['type'], 'Gff3') !== false) $aType = 'gff';
            elseif (strpos($a['adapter']['type'], 'Gtf') !== false) $aType = 'gtf';
        }
        if (isset($b['adapter']['type'])) {
            if (strpos($b['adapter']['type'], 'Gff3') !== false) $bType = 'gff';
            elseif (strpos($b['adapter']['type'], 'Gtf') !== false) $bType = 'gtf';
        }
        
        // Priority: gff/gtf first, then everything else
        $priority = ['gff' => 0, 'gtf' => 1];
        $aPriority = $priority[$aType] ?? 999;
        $bPriority = $priority[$bType] ?? 999;
        
        return $aPriority - $bPriority;
    });
    
    // Generate config for each access level
    foreach ($accessLevels as $level) {
        $config = $baseConfig;
        $userLevel = $accessHierarchy[$level];
        
        // DISABLED 2026-02-12: Now using metadata-driven track system via Google Sheets
        // Tracks are loaded from metadata/jbrowse2-configs/tracks/ instead
        // This prevents duplicate GFF tracks and ensures proper track ordering
        /*
        // Add annotations track if GFF file exists (PUBLIC access for all)
        // Extract organism and assembly from assembly definition, not from name parsing
        $organism = $assemblyDef['organism'] ?? null;
        $assemblyId = $assemblyDef['assemblyId'] ?? null;
        
        if ($organism && $assemblyId) {
            $annotationsFile = $genomesDir . "/{$organism}/{$assemblyId}/annotations.gff3.gz";
            if (file_exists($annotationsFile)) {
                $trackId = "{$assemblyName}-genes";
                
                // Build base track config
                $annotationTrack = [
                    'type' => 'FeatureTrack',
                    'trackId' => $trackId,
                    'name' => 'Gene Annotations',
                    'assemblyNames' => [$assemblyName],
                    'category' => ['Annotation'],
                    'adapter' => [
                        'type' => 'Gff3TabixAdapter',
                        'gffGzLocation' => [
                            'uri' => "/moop/data/genomes/{$organism}/{$assemblyId}/annotations.gff3.gz",
                            'locationType' => 'UriLocation'
                        ],
                        'index' => [
                            'location' => [
                                'uri' => "/moop/data/genomes/{$organism}/{$assemblyId}/annotations.gff3.gz.tbi",
                                'locationType' => 'UriLocation'
                            ],
                            'indexType' => 'TBI'
                        ]
                    ]
                ];
                
                // Check if text search index exists
                $trixBaseDir = dirname($genomesDir) . '/tracks/trix';  // Use dirname for proper path
                $ixFile = "{$trixBaseDir}/{$trackId}.ix";
                
                if (file_exists($ixFile)) {
                    // Add text search configuration
                    $annotationTrack['textSearching'] = [
                        'textSearchAdapter' => [
                            'type' => 'TrixTextSearchAdapter',
                            'textSearchAdapterId' => "{$trackId}-index",
                            'ixFilePath' => [
                                'uri' => "/moop/data/tracks/trix/{$trackId}.ix",
                                'locationType' => 'UriLocation'
                            ],
                            'ixxFilePath' => [
                                'uri' => "/moop/data/tracks/trix/{$trackId}.ixx",
                                'locationType' => 'UriLocation'
                            ],
                            'metaFilePath' => [
                                'uri' => "/moop/data/tracks/trix/{$trackId}_meta.json",
                                'locationType' => 'UriLocation'
                            ],
                            'assemblyNames' => [$assemblyName]
                        ]
                    ];
                }
                
                $config['tracks'][] = $annotationTrack;
            }
        }
        */
        
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
function processAssemblies($metadataDir, $jbrowseDir, $tracksDir, $genomesDir) {
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
            
            // Generate cached configs per access level (with genomes dir)
            generateCachedConfigs($assemblyDef, $tracksDir, $assemblyDir, $genomesDir);
            
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

$result = processAssemblies($METADATA_ASSEMBLIES_DIR, $JBROWSE_CONFIGS_DIR, $JBROWSE_TRACKS_DIR, $GENOMES_DIR);

echo "---\n";
echo "Generated {$result['count']} config files\n";

if (!empty($result['errors'])) {
    echo "\nErrors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}
?>
