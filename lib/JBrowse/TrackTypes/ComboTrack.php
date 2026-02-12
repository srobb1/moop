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
     * Color scheme mappings - full arrays from Python script
     * Matches generate_tracks_from_sheet.py COLORS dictionary
     */
    private $colorSchemes = [
        // Original groups
        'blues' => ['Navy', 'Blue', 'RoyalBlue', 'SteelBlue', 'DodgerBlue', 'DeepSkyBlue', 
                    'CornflowerBlue', 'SkyBlue', 'LightSkyBlue', 'LightSteelBlue', 'LightBlue'],
        'purples' => ['Indigo', 'Purple', 'DarkViolet', 'DarkSlateBlue', 'DarkOrchid', 
                      'Fuchsia', 'SlateBlue', 'MediumSlateBlue', 'MediumOrchid', 
                      'MediumPurple', 'Orchid', 'Plum', 'Thistle', 'Lavender'],
        'yellows' => ['DarkKhaki', 'Gold', 'Khaki', 'PeachPuff', 'Yellow', 'PaleGoldenrod', 
                      'Moccasin', 'PapayaWhip', 'LightGoldenrodYellow', 'LemonChiffon', 'LightYellow'],
        'cyans' => ['Teal', 'LightSeaGreen', 'CadetBlue', 'DarkTurquoise', 'Turquoise', 
                    'Aqua', 'Aquamarine', 'PaleTurquoise', 'LightCyan'],
        'pinks' => ['MediumVioletRed', 'DeepPink', 'PaleVioletRed', 'HotPink', 'LightPink', 'Pink'],
        'greens' => ['DarkGreen', 'DarkOliveGreen', 'ForestGreen', 'SeaGreen', 'Olive', 
                     'OliveDrab', 'MediumSeaGreen', 'LimeGreen', 'Lime', 'MediumSpringGreen', 
                     'DarkSeaGreen', 'MediumAquamarine', 'YellowGreen', 'LawnGreen', 
                     'LightGreen', 'GreenYellow'],
        'reds' => ['DarkRed', 'Red', 'Firebrick', 'Crimson', 'IndianRed', 'LightCoral', 
                   'Salmon', 'DarkSalmon', 'LightSalmon'],
        'oranges' => ['OrangeRed', 'Tomato', 'DarkOrange', 'Coral', 'Orange'],
        'browns' => ['Maroon', 'Brown', 'SaddleBrown', 'Sienna', 'Chocolate', 'DarkGoldenrod', 
                     'Peru', 'RosyBrown', 'Goldenrod', 'SandyBrown', 'Tan', 'Burlywood', 
                     'Wheat', 'NavajoWhite', 'Bisque', 'BlanchedAlmond', 'Cornsilk'],
        'grays' => ['Gainsboro', 'LightGray', 'Silver', 'DarkGray', 'Gray', 'DimGray', 
                    'LightSlateGray', 'SlateGray', 'DarkSlateGray'],
        'diffs' => ['Turquoise', 'Coral', 'MediumVioletRed', 'Red', 'Gold', 'Sienna', 
                    'SeaGreen', 'SkyBlue', 'BlueViolet', 'MistyRose', 'LightSlateGray'],
        
        // Expanded groups
        'rainbow' => ['#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', 
                      '#46f0f0', '#f032e6', '#bcf60c', '#fabebe', '#008080', '#e6beff', 
                      '#9a6324', '#fffac8', '#800000', '#aaffc3', '#808000', '#ffd8b1', 
                      '#000075', '#808080'],
        'warm' => ['#8B0000', '#DC143C', '#FF0000', '#FF4500', '#FF6347', '#FF7F50', 
                   '#FFA500', '#FFD700', '#FFFF00', '#ADFF2F', '#7FFF00', '#00FF00'],
        'cool' => ['#00008B', '#0000CD', '#0000FF', '#1E90FF', '#00BFFF', '#00CED1', 
                   '#00FFFF', '#00FA9A', '#00FF7F', '#3CB371', '#2E8B57', '#006400'],
        'earth' => ['#8B4513', '#A0522D', '#D2691E', '#CD853F', '#DEB887', '#F4A460', 
                    '#D2B48C', '#BC8F8F', '#F5DEB3', '#FFE4C4', '#FFDEAD', '#FFE4B5', 
                    '#FAEBD7', '#FAF0E6', '#FFF8DC', '#FFFACD'],
        'pastels' => ['#FFB3BA', '#FFDFBA', '#FFFFBA', '#BAFFC9', '#BAE1FF', '#E0BBE4', 
                      '#FFDFD3', '#FEC8D8', '#D5AAFF', '#B4F8C8', '#A0C4FF', '#FDFFB6', 
                      '#FFD6A5', '#CAFFBF', '#FFC6FF', '#FFFFFC'],
        'vibrant' => ['#FF006E', '#FB5607', '#FFBE0B', '#8338EC', '#3A86FF', '#06FFA5', 
                      '#FF1654', '#247BA0', '#F72585', '#4361EE', '#4CC9F0', '#7209B7', 
                      '#F77F00', '#06D6A0', '#EF476F', '#FFD166'],
        'monoblues' => ['#03045E', '#023E8A', '#0077B6', '#0096C7', '#00B4D8', '#48CAE4', 
                        '#90E0EF', '#ADE8F4', '#CAF0F8'],
        'monogreens' => ['#004B23', '#006400', '#007200', '#008000', '#38B000', '#70E000', 
                         '#9EF01A', '#CCFF33'],
        'monoreds' => ['#641220', '#6E1423', '#85182A', '#A11D33', '#A71E34', '#C9184A', 
                       '#FF4D6D', '#FF758F', '#FF8FA3'],
        'monopurples' => ['#240046', '#3C096C', '#5A189A', '#7209B7', '#9D4EDD', '#C77DFF', 
                          '#E0AAFF', '#F0D9FF'],
        'neon' => ['#FF10F0', '#39FF14', '#FFFF00', '#FF3503', '#00F5FF', '#FE019A', 
                   '#BC13FE', '#FF073A', '#FF6600', '#00FFFF', '#FF00FF', '#CCFF00'],
        'sea' => ['#001F3F', '#003D5C', '#005A7A', '#007899', '#0095B7', '#00B3D5', 
                  '#33C1E3', '#66CFF0', '#99DDFC', '#CCEBFF'],
        'forest' => ['#013220', '#2D6A4F', '#40916C', '#52B788', '#74C69D', '#95D5B2', 
                     '#B7E4C7', '#D8F3DC'],
        'sunset' => ['#03071E', '#370617', '#6A040F', '#9D0208', '#D00000', '#DC2F02', 
                     '#E85D04', '#F48C06', '#FAA307', '#FFBA08'],
        'galaxy' => ['#0B0C10', '#1F2833', '#45A29E', '#66FCF1', '#C5C6C7', '#7B2CBF', 
                     '#9D4EDD', '#C77DFF', '#E0AAFF'],
        'contrast' => ['#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', 
                       '#FF00FF', '#00FFFF'],
        'grayscale' => ['#000000', '#1A1A1A', '#333333', '#4D4D4D', '#666666', '#808080', 
                        '#999999', '#B3B3B3', '#CCCCCC', '#E6E6E6', '#F2F2F2', '#FFFFFF'],
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
            $colorSchemeName = $group['color'];
            $colorScheme = $this->colorSchemes[$colorSchemeName] ?? ['DarkGray'];
            
            // Reset color index for each group (like Perl: my $colorCount = 0)
            $colorIndex = 0;
            
            foreach ($group['tracks'] as $track) {
                // Get color from scheme at current index (cycles through the array)
                $color = is_array($colorScheme) ? 
                    ($colorScheme[$colorIndex % count($colorScheme)] ?? 'DarkGray') : 
                    $colorScheme;
                
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
                $colorIndex++;  // Increment for next track in this group
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
