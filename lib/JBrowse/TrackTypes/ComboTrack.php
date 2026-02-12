<?php
/**
 * ComboTrack - Multi-wiggle (combo) track type for JBrowse
 * 
 * Generates MultiWiggle tracks that combine multiple BigWig files
 * into a single track with color-grouped subtracks.
 * 
 * Structure from GoogleSheetsParser:
 * [
 *   'track_id' => 'combo_id',
 *   'name' => 'Display Name',
 *   'organism' => 'Genus_species',
 *   'assembly' => 'GCA_xxx',
 *   'groups' => [
 *     'group_name' => [
 *       'color' => 'greens',
 *       'tracks' => [
 *         ['track_id' => 'id', 'name' => 'Name', 'TRACK_PATH' => 'file.bw'],
 *         ...
 *       ]
 *     ],
 *     ...
 *   ]
 * ]
 * 
 * @package MOOP\JBrowse\TrackTypes
 */

require_once __DIR__ . '/TrackTypeInterface.php';

class ComboTrack implements TrackTypeInterface
{
    private $pathResolver;
    private $config;
    
    /**
     * Color scheme mappings
     */
    private $colorSchemes = [
        'greens' => 'DarkGreen',
        'reds' => 'DarkRed',
        'blues' => 'DarkBlue',
        'purples' => 'DarkMagenta',
        'oranges' => 'DarkOrange',
        'grays' => 'DarkGray',
        'browns' => 'SaddleBrown',
        'pinks' => 'DeepPink',
        'teals' => 'DarkCyan',
        'yellows' => 'DarkGoldenrod'
    ];
    
    public function __construct($pathResolver, $config)
    {
        $this->pathResolver = $pathResolver;
        $this->config = $config;
    }
    
    /**
     * Validate combo track data
     */
    public function validate($trackData)
    {
        $errors = [];
        
        // Required fields
        if (empty($trackData['track_id'])) {
            $errors[] = "Missing track_id";
        }
        
        if (empty($trackData['name'])) {
            $errors[] = "Missing name";
        }
        
        if (empty($trackData['groups']) || !is_array($trackData['groups'])) {
            $errors[] = "Missing or invalid groups array";
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate each group
        $totalTracks = 0;
        foreach ($trackData['groups'] as $groupName => $group) {
            if (empty($group['tracks']) || !is_array($group['tracks'])) {
                $errors[] = "Group '$groupName' has no tracks";
                continue;
            }
            
            // Validate each track in group
            foreach ($group['tracks'] as $track) {
                if (empty($track['TRACK_PATH'])) {
                    $errors[] = "Track in group '$groupName' missing TRACK_PATH";
                    continue;
                }
                
                // Validate BigWig extension
                if (!preg_match('/\.(bw|bigwig)$/i', $track['TRACK_PATH'])) {
                    $errors[] = "Track '{$track['TRACK_PATH']}' is not a BigWig file";
                }
                
                $totalTracks++;
            }
        }
        
        if ($totalTracks === 0) {
            $errors[] = "No valid tracks found in any group";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'track_count' => $totalTracks
        ];
    }
    
    /**
     * Generate combo track configuration
     */
    public function generate($trackData, $organism, $assembly, $options = [])
    {
        $dryRun = $options['dry_run'] ?? false;
        
        echo "  Combo: " . $trackData['name'] . " (" . $trackData['track_id'] . ")\n";
        
        // Validate first
        $validation = $this->validate($trackData);
        if (!$validation['valid']) {
            echo "    ✗ Validation failed:\n";
            foreach ($validation['errors'] as $error) {
                echo "      - $error\n";
            }
            return false;
        }
        
        echo "    ✓ Contains " . $validation['track_count'] . " subtracks\n";
        
        // Build subadapters array
        $subadapters = [];
        $subtrackIndex = 0;
        
        foreach ($trackData['groups'] as $groupName => $group) {
            $color = $this->colorSchemes[$group['color']] ?? 'DarkGray';
            
            foreach ($group['tracks'] as $track) {
                // Resolve path - handle bare filenames
                $trackPath = $track['TRACK_PATH'];
                
                // If it's just a filename (no path separators), construct organism/assembly path
                if (basename($trackPath) === $trackPath) {
                    // Build full filesystem path
                    $filesystemPath = $this->config->getPath('site_path') . '/data/tracks/' . $organism . '/' . $assembly . '/bigwig/' . $trackPath;
                    $webUri = '/moop/data/tracks/' . $organism . '/' . $assembly . '/bigwig/' . $trackPath;
                } else {
                    // Full or relative path provided
                    $isRemote = $this->pathResolver->isRemote($trackPath);
                    if ($isRemote) {
                        $filesystemPath = null;
                        $webUri = $trackPath;
                    } else {
                        // Absolute path
                        $filesystemPath = $trackPath;
                        $webUri = $this->pathResolver->toWebUri($filesystemPath);
                    }
                }
                
                // Check if file exists (local paths only)
                $isRemote = $this->pathResolver->isRemote($trackPath);
                $exists = $isRemote || ($filesystemPath && file_exists($filesystemPath));
                
                if (!$exists) {
                    echo "      ✗ File not found: " . $filesystemPath . "\n";
                    continue;
                }
                
                $subtrackId = $trackData['track_id'] . "_sub" . $subtrackIndex;
                
                $subadapters[] = [
                    'type' => 'BigWigAdapter',
                    'bigWigLocation' => [
                        'uri' => $webUri,
                        'locationType' => 'UriLocation'
                    ],
                    'name' => $track['name'],
                    'color' => $color
                ];
                
                $subtrackIndex++;
            }
        }
        
        if (empty($subadapters)) {
            echo "    ✗ No valid subtracks found\n";
            return false;
        }
        
        // Build track configuration
        $trackConfig = [
            'type' => 'MultiQuantitativeTrack',
            'trackId' => $trackData['track_id'],
            'name' => $trackData['name'],
            'assemblyNames' => [$organism . '_' . $assembly],
            'category' => ['Combo Tracks'],
            'adapter' => [
                'type' => 'MultiWiggleAdapter',
                'subadapters' => $subadapters
            ],
            'displays' => [
                [
                    'type' => 'MultiLinearWiggleDisplay',
                    'displayId' => $trackData['track_id'] . '-MultiLinearWiggleDisplay'
                ]
            ]
        ];
        
        // Determine output path
        $configDir = $this->config->getPath('site_path') . '/metadata/jbrowse2-configs/tracks/' . $organism . '/' . $assembly . '/combo';
        $configFile = $configDir . '/' . strtolower($trackData['track_id']) . '.json';
        
        if ($dryRun) {
            echo "    [DRY RUN] Would write to: $configFile\n";
            echo "    [DRY RUN] Subtracks: " . count($subadapters) . "\n";
            return true;
        }
        
        // Create directory if needed
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0755, true)) {
                echo "    ✗ Failed to create directory: $configDir\n";
                return false;
            }
        }
        
        // Write configuration
        $json = json_encode($trackConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($configFile, $json) === false) {
            echo "    ✗ Failed to write configuration file\n";
            return false;
        }
        
        echo "    ✓ Generated combo track with " . count($subadapters) . " subtracks\n";
        echo "      → $configFile\n";
        
        return true;
    }
    
    public function getRequiredFields()
    {
        return ['track_id', 'name', 'groups'];
    }
    
    public function getType()
    {
        return 'combo';
    }
    
    public function getValidExtensions()
    {
        // Combo tracks don't have a single file extension
        // They contain multiple BigWig files
        return [];
    }
    
    public function requiresIndex()
    {
        return false;
    }
    
    public function getIndexExtensions()
    {
        return [];
    }
}
