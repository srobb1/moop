<?php
/**
 * JBrowse2 Config Generation Functions
 * 
 * Shared functions for generating JBrowse2 configurations dynamically.
 * Used by config.php and other config endpoints.
 */

/**
 * Parse assembly name into organism and assembly components
 * 
 * Format: Organism_Assembly (e.g., Nematostella_vectensis_GCA_033964005.1)
 * Returns: [organism, assembly]
 * 
 * @param string $full_name - Full assembly name
 * @return array - [organism, assembly] or [null, null] if invalid
 */
function parseAssemblyName($full_name)
{
    // Assembly IDs typically start with GCA_ or GCF_
    if (preg_match('/^(.+?)_(GC[AF]_\d+\.\d+)$/', $full_name, $matches)) {
        return [$matches[1], $matches[2]];
    }
    
    // Fallback: split on last underscore
    $parts = explode('_', $full_name);
    if (count($parts) < 2) {
        return [null, null];
    }
    
    $assembly = array_pop($parts);
    $organism = implode('_', $parts);
    
    return [$organism, $assembly];
}

/**
 * Load synteny tracks connecting two assemblies
 * 
 * Searches for synteny tracks in both possible directory orderings and
 * filters them based on user access level.
 * 
 * @param string $assembly1 - First assembly name (format: Organism_Assembly)
 * @param string $assembly2 - Second assembly name (format: Organism_Assembly)
 * @param string $user_access_level - User's access level (PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN)
 * @return array - Array of synteny track configurations
 */
function loadSyntenyTracks($assembly1, $assembly2, $user_access_level)
{
    $tracks_dir = __DIR__ . '/../../metadata/jbrowse2-configs/tracks/synteny';
    
    if (!is_dir($tracks_dir)) {
        return []; // No synteny tracks exist yet
    }
    
    // Synteny tracks can be stored in either order
    // Try both: assembly1_assembly2 and assembly2_assembly1
    $pattern1 = "{$assembly1}_{$assembly2}";
    $pattern2 = "{$assembly2}_{$assembly1}";
    
    $track_files = [];
    
    // Check first pattern
    if (is_dir("$tracks_dir/$pattern1")) {
        $files = glob("$tracks_dir/$pattern1/*/*.json");
        if ($files) {
            $track_files = array_merge($track_files, $files);
        }
    }
    
    // Check second pattern (reversed)
    if (is_dir("$tracks_dir/$pattern2")) {
        $files = glob("$tracks_dir/$pattern2/*/*.json");
        if ($files) {
            $track_files = array_merge($track_files, $files);
        }
    }
    
    if (empty($track_files)) {
        return [];
    }
    
    $filtered_tracks = [];
    $is_whitelisted = isWhitelistedIP();
    
    $access_hierarchy = [
        'ADMIN' => 4,
        'IP_IN_RANGE' => 3,
        'COLLABORATOR' => 2,
        'PUBLIC' => 1
    ];
    
    $user_level_value = $access_hierarchy[$user_access_level] ?? 0;
    
    foreach ($track_files as $track_file) {
        $track_def = json_decode(file_get_contents($track_file), true);
        
        if (!$track_def) continue;
        
        // Get track access level
        $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
        $track_level_value = $access_hierarchy[$track_access_level] ?? 1;
        
        // Check if user has access
        if ($user_level_value < $track_level_value) {
            continue;
        }
        
        // COLLABORATOR check: Must have access to at least one of the assemblies
        if ($user_access_level === 'COLLABORATOR' && $track_level_value >= 2) {
            $user_access = $_SESSION['access'] ?? [];
            
            // Parse assembly names
            list($org1, $asm1) = parseAssemblyName($assembly1);
            list($org2, $asm2) = parseAssemblyName($assembly2);
            
            $has_access = false;
            
            // Check if user has access to assembly1
            if (isset($user_access[$org1]) && in_array($asm1, (array)$user_access[$org1])) {
                $has_access = true;
            }
            
            // Check if user has access to assembly2
            if (isset($user_access[$org2]) && in_array($asm2, (array)$user_access[$org2])) {
                $has_access = true;
            }
            
            if (!$has_access) {
                continue;
            }
        }
        
        // Add JWT tokens to track URLs
        $track_with_tokens = addTokensToTrack($track_def, null, null, $user_access_level, $is_whitelisted);
        
        if ($track_with_tokens) {
            $filtered_tracks[] = $track_with_tokens;
        }
    }
    
    return $filtered_tracks;
}

/**
 * Generate dual-assembly configuration with synteny tracks
 * 
 * Generates JBrowse2 config for comparing two assemblies side-by-side.
 * Includes both assembly definitions, all tracks for both assemblies,
 * and synteny tracks connecting them.
 * 
 * @param string $assembly1 - First assembly (format: Organism_Assembly)
 * @param string $assembly2 - Second assembly (format: Organism_Assembly)
 * @param string $user_access_level - User's access level
 * @return void - Outputs JSON and exits
 */
function generateDualAssemblyConfig($assembly1, $assembly2, $user_access_level)
{
    // Parse assembly names
    list($organism1, $asm1) = parseAssemblyName($assembly1);
    list($organism2, $asm2) = parseAssemblyName($assembly2);
    
    if (!$organism1 || !$asm1 || !$organism2 || !$asm2) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid assembly name format. Use: Organism_Assembly']);
        exit;
    }
    
    // Load assembly definitions
    $metadata_path = __DIR__ . '/../../metadata/jbrowse2-configs/assemblies';
    $assembly1_file = "$metadata_path/{$organism1}_{$asm1}.json";
    $assembly2_file = "$metadata_path/{$organism2}_{$asm2}.json";
    
    if (!file_exists($assembly1_file)) {
        http_response_code(404);
        echo json_encode(['error' => "Assembly not found: $assembly1"]);
        exit;
    }
    
    if (!file_exists($assembly2_file)) {
        http_response_code(404);
        echo json_encode(['error' => "Assembly not found: $assembly2"]);
        exit;
    }
    
    $assembly1_def = json_decode(file_get_contents($assembly1_file), true);
    $assembly2_def = json_decode(file_get_contents($assembly2_file), true);
    
    if (!$assembly1_def || !$assembly2_def) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load assembly definitions']);
        exit;
    }
    
    // Check user has access to both assemblies
    $assembly1_access = $assembly1_def['defaultAccessLevel'] ?? 'PUBLIC';
    $assembly2_access = $assembly2_def['defaultAccessLevel'] ?? 'PUBLIC';
    
    if (!canUserAccessAssembly($user_access_level, $assembly1_access, $organism1, $asm1)) {
        http_response_code(403);
        echo json_encode(['error' => "Access denied to assembly: $assembly1"]);
        exit;
    }
    
    if (!canUserAccessAssembly($user_access_level, $assembly2_access, $organism2, $asm2)) {
        http_response_code(403);
        echo json_encode(['error' => "Access denied to assembly: $assembly2"]);
        exit;
    }
    
    // Build dual-assembly config
    $config = [
        'assemblies' => [
            [
                'name' => $assembly1_def['name'],
                'displayName' => $assembly1_def['displayName'] ?? "{$organism1} ({$asm1})",
                'aliases' => $assembly1_def['aliases'] ?? [$asm1],
                'sequence' => $assembly1_def['sequence']
            ],
            [
                'name' => $assembly2_def['name'],
                'displayName' => $assembly2_def['displayName'] ?? "{$organism2} ({$asm2})",
                'aliases' => $assembly2_def['aliases'] ?? [$asm2],
                'sequence' => $assembly2_def['sequence']
            ]
        ],
        'plugins' => getJBrowse2PluginConfiguration(),
        'configuration' => [],
        'tracks' => [],
        'defaultSession' => [
            'name' => "{$assembly1} vs {$assembly2} Comparison",
            'views' => [
                [
                    'type' => 'LinearSyntenyView',
                    'views' => [
                        [
                            'type' => 'LinearGenomeView',
                            'assemblyNames' => [$assembly1_def['name']],
                            'tracks' => []
                        ],
                        [
                            'type' => 'LinearGenomeView',
                            'assemblyNames' => [$assembly2_def['name']],
                            'tracks' => []
                        ]
                    ],
                    'tracks' => []
                ]
            ]
        ]
    ];
    
    // Load tracks for both assemblies
    $tracks1 = loadFilteredTracks($organism1, $asm1, $user_access_level);
    $tracks2 = loadFilteredTracks($organism2, $asm2, $user_access_level);
    
    // Load synteny tracks connecting these assemblies
    $synteny_tracks = loadSyntenyTracks($assembly1, $assembly2, $user_access_level);
    
    // Combine all tracks
    $config['tracks'] = array_merge($tracks1, $tracks2, $synteny_tracks);
    
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
