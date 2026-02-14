<?php
/**
 * JBrowse2 Configuration API - PRIMARY ENDPOINT (STANDARD)
 * 
 * THIS IS THE PRIMARY ENDPOINT CURRENTLY USED BY ALL ASSEMBLIES
 * 
 * Purpose:
 * --------
 * Generates JBrowse2 configurations dynamically from metadata files.
 * Embeds all track configs in a single response with gzip compression.
 * 
 * When to Use:
 * ------------
 * - Assemblies with < 1000 tracks (current: all assemblies)
 * - When config load time is acceptable (< 2 seconds)
 * - When gzipped config size is manageable (< 500KB)
 * 
 * Performance:
 * ------------
 * - < 50 tracks:    ~200ms, <100KB
 * - 50-500 tracks:  ~500ms, 500KB → 50KB (gzipped)
 * - 500-1000:       ~1s, 2MB → 200KB (gzipped)
 * 
 * For assemblies > 1000 tracks, consider using config-optimized.php
 * 
 * Endpoints:
 * ----------
 * GET /api/jbrowse2/config.php
 *     Returns: List of assemblies user can access
 *     Example: { assemblies: [...], plugins: {...}, userAccessLevel: "PUBLIC" }
 * 
 * GET /api/jbrowse2/config.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
 *     Returns: Full JBrowse2 config with all tracks embedded
 *     - Assembly definition
 *     - All accessible tracks (filtered by user permissions)
 *     - JWT tokens added to track URLs (if not whitelisted IP)
 *     - Plugin configurations
 * 
 * Security:
 * ---------
 * - All configs are permission-filtered at generation time
 * - Access levels: PUBLIC < COLLABORATOR < IP_IN_RANGE < ADMIN
 * - All track URLs include JWT tokens (except whitelisted IPs)
 * - COLLABORATOR users verified against their specific assembly access
 * - No caching for security (configs generated fresh each request)
 * 
 * Example JavaScript Usage:
 * -------------------------
 * // Fetch assembly list
 * fetch('/moop/api/jbrowse2/config.php')
 *   .then(r => r.json())
 *   .then(data => {
 *     console.log('Assemblies:', data.assemblies);
 *     console.log('User access:', data.userAccessLevel);
 *   });
 * 
 * // Fetch full config for specific assembly
 * fetch('/moop/api/jbrowse2/config.php?organism=X&assembly=Y')
 *   .then(r => r.json())
 *   .then(config => {
 *     // All tracks are embedded in config.tracks[]
 *     console.log('Loaded tracks:', config.tracks.length);
 *     initJBrowse2(config);
 *   });
 * 
 * Alternative Endpoint:
 * ---------------------
 * For assemblies with > 1000 tracks, use api/jbrowse2/config-optimized.php
 * which implements lazy-loading via track URIs for better performance.
 * 
 * Current Status: PRIMARY ENDPOINT (actively used by all assemblies)
 * Last Updated: 2026-02-14
 */

// Enable gzip compression for large configs (handles 500+ tracks efficiently)
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Initialize session and access control
session_start();
require_once __DIR__ . '/../../includes/access_control.php';
require_once __DIR__ . '/../../lib/functions_access.php';
require_once __DIR__ . '/../../lib/jbrowse/track_token.php';
require_once __DIR__ . '/../../lib/jbrowse/config_functions.php';
require_once __DIR__ . '/../../lib/JBrowse/PluginLoader.php';

// Get user's access level
$user_access_level = get_access_level();
$organism = $_GET['organism'] ?? null;
$assembly = $_GET['assembly'] ?? null;
$assembly1 = $_GET['assembly1'] ?? null;
$assembly2 = $_GET['assembly2'] ?? null;

// Route to appropriate handler
if ($assembly1 && $assembly2) {
    // Dual-assembly config with synteny tracks
    generateDualAssemblyConfig($assembly1, $assembly2, $user_access_level);
} elseif ($organism && $assembly) {
    // Single assembly config with tracks
    generateAssemblyConfig($organism, $assembly, $user_access_level);
} else {
    // Assembly list only
    generateAssemblyList($user_access_level);
}

/**
 * Generate list of accessible assemblies
 * 
 * Returns a filtered list of assemblies based on user's access level.
 * Called when no organism/assembly parameters are provided.
 * 
 * @param string $user_access_level - User's access level (PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN)
 * @return void - Outputs JSON and exits
 */
function generateAssemblyList($user_access_level) {
    $metadata_path = __DIR__ . '/../../metadata/jbrowse2-configs/assemblies';
    
    if (!is_dir($metadata_path)) {
        http_response_code(500);
        echo json_encode(['error' => "Assembly metadata directory not found"]);
        exit;
    }
    
    $config = [
        'assemblies' => [],
        'plugins' => getJBrowse2PluginConfiguration(),
        'configuration' => [],
        'connections' => [],
        'defaultSession' => ['name' => 'New Session'],
        'tracks' => [],
        'userAccessLevel' => $user_access_level
    ];
    
    $assembly_files = glob("$metadata_path/*.json");
    
    foreach ($assembly_files as $file) {
        $assembly_def = json_decode(file_get_contents($file), true);
        
        if (!$assembly_def) continue;
        
        $assembly_access_level = $assembly_def['defaultAccessLevel'] ?? 'PUBLIC';
        
        // Check if user can access this assembly
        if (!canUserAccessAssembly($user_access_level, $assembly_access_level, 
                                   $assembly_def['organism'] ?? null, 
                                   $assembly_def['assemblyId'] ?? null)) {
            continue;
        }
        
        $config['assemblies'][] = [
            'name' => $assembly_def['name'],
            'displayName' => $assembly_def['displayName'] ?? $assembly_def['name'],
            'aliases' => $assembly_def['aliases'] ?? [],
            'accessLevel' => $assembly_access_level,
            'sequence' => $assembly_def['sequence'] ?? null
        ];
    }
    
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Generate full assembly configuration with tracks
 * 
 * Generates a complete JBrowse2 config with all accessible tracks embedded.
 * All tracks are included in the response (no lazy-loading).
 * 
 * Process:
 * 1. Validate user has access to assembly
 * 2. Load assembly definition from metadata
 * 3. Build base config with plugins
 * 4. Load and filter all tracks by user permissions
 * 5. Add JWT tokens to track URLs (if not whitelisted)
 * 6. Return complete config with gzip compression
 * 
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level
 * @return void - Outputs JSON with gzip compression and exits
 */
function generateAssemblyConfig($organism, $assembly, $user_access_level) {
    // 1. VALIDATE PERMISSIONS
    $accessible = getAccessibleAssemblies($organism, $assembly);
    if (empty($accessible)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this assembly']);
        exit;
    }
    
    // 2. LOAD ASSEMBLY DEFINITION
    $metadata_path = __DIR__ . '/../../metadata';
    $assemblies_dir = "$metadata_path/jbrowse2-configs/assemblies";
    $assembly_def_file = "$assemblies_dir/{$organism}_{$assembly}.json";
    
    if (!file_exists($assembly_def_file)) {
        http_response_code(404);
        echo json_encode(['error' => "Assembly definition not found: {$organism}_{$assembly}"]);
        exit;
    }
    
    $assembly_definition = json_decode(file_get_contents($assembly_def_file), true);
    
    if (!$assembly_definition) {
        http_response_code(500);
        echo json_encode(['error' => "Invalid assembly definition JSON"]);
        exit;
    }
    
    // 3. BUILD BASE CONFIG WITH PLUGINS AT TOP LEVEL
    $config = [
        'assemblies' => [
            [
                'name' => $assembly_definition['name'],
                'displayName' => $assembly_definition['displayName'] ?? "{$organism} ({$assembly})",
                'aliases' => $assembly_definition['aliases'] ?? [$assembly],
                'sequence' => $assembly_definition['sequence']
            ]
        ],
        'plugins' => getJBrowse2PluginConfiguration(),
        'configuration' => [],
        'tracks' => [],
        'defaultSession' => [
            'name' => "{$organism} {$assembly}",
            'view' => [
                'id' => 'linearGenomeView',
                'type' => 'LinearGenomeView',
                'tracks' => []
            ]
        ]
    ];
    
    // 4. LOAD AND FILTER TRACKS
    // All tracks are embedded in the response (no lazy-loading)
    // For assemblies > 1000 tracks, consider using config-optimized.php
    $tracks = loadFilteredTracks($organism, $assembly, $user_access_level);
    $config['tracks'] = $tracks;
    
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Load tracks filtered by user permissions
 * 
 * Loads all track metadata files and filters them based on:
 * - User's access level (PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN)
 * - Track's access level requirement
 * - For COLLABORATOR users: specific assembly access verification
 * 
 * All track configs are fully loaded and returned (embedded mode).
 * JWT tokens are added to track URLs for non-whitelisted users.
 * 
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level
 * @return array - Array of full track configurations with JWT tokens
 */
function loadFilteredTracks($organism, $assembly, $user_access_level) {
    $tracks_dir = __DIR__ . "/../../metadata/jbrowse2-configs/tracks";
    
    // Load tracks from hierarchical structure: tracks/organism/assembly/type/*.json
    $track_files = glob("$tracks_dir/$organism/$assembly/*/*.json");
    
    if (empty($track_files)) {
        // Fallback to flat structure (legacy compatibility)
        $track_files = glob("$tracks_dir/*.json");
    }
    
    $filtered_tracks = [];
    $is_whitelisted = isWhitelistedIP();
    
    // Access level hierarchy (higher number = more access)
    // PUBLIC (1) < COLLABORATOR (2) < IP_IN_RANGE (3) < ADMIN (4)
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
        
        // Get track access level from metadata
        $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
        $track_level_value = $access_hierarchy[$track_access_level] ?? 1;
        
        // PERMISSION CHECK: User must have sufficient access level
        // Example: PUBLIC user (1) cannot see COLLABORATOR tracks (2)
        if ($user_level_value < $track_level_value) {
            continue;
        }
        
        // COLLABORATOR-SPECIFIC CHECK: Must have explicit assembly access
        // COLLABORATOR users have access.php defining which assemblies they can see
        if ($user_access_level === 'COLLABORATOR' && $track_level_value >= 2) {
            $user_access = $_SESSION['access'] ?? [];
            if (!isset($user_access[$organism]) || !in_array($assembly, (array)$user_access[$organism])) {
                continue;
            }
        }
        
        // SECURITY: Generate JWT token for track URLs (non-whitelisted users only)
        $track_with_tokens = addTokensToTrack($track_def, $organism, $assembly, $user_access_level, $is_whitelisted);
        
        if ($track_with_tokens) {
            $filtered_tracks[] = $track_with_tokens;
        }
    }
    
    return $filtered_tracks;
}

/**
 * Add JWT tokens to track adapter URLs
 * 
 * Security layer that adds JWT authentication tokens to all track file URLs.
 * Tokens are only added for non-whitelisted IPs.
 * 
 * Process:
 * - Generate JWT token containing user_id, access_level, organism, assembly
 * - Recursively traverse adapter config to find all URIs
 * - Append token as query parameter to each URI
 * 
 * @param array $track_def - Track definition from metadata
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level
 * @param bool $is_whitelisted - Whether user's IP is whitelisted
 * @return array|null - Track config with tokens, or null if token generation fails
 */
function addTokensToTrack($track_def, $organism, $assembly, $user_access_level, $is_whitelisted) {
    // Generate token if not whitelisted IP
    // Whitelisted IPs can access track files directly without authentication
    $token = null;
    if (!$is_whitelisted) {
        try {
            $token = generateTrackToken($organism, $assembly, $user_access_level);
        } catch (Exception $e) {
            error_log("Failed to generate token for track {$track_def['trackId']}: " . $e->getMessage());
            return null; // Skip this track if token generation fails
        }
    }
    
    // Add token to adapter URLs
    if (isset($track_def['adapter'])) {
        $track_def['adapter'] = addTokenToAdapterUrls($track_def['adapter'], $token);
    }
    
    return $track_def;
}

/**
 * Recursively add token parameter to all URIs in adapter config
 * 
 * Traverses adapter configuration recursively to find all URIs and adds JWT tokens.
 * Handles special routing for /moop/data/tracks/ paths through tracks.php.
 * 
 * @param array $adapter - Adapter configuration (may contain nested arrays)
 * @param string|null $token - JWT token to add (null for whitelisted IPs)
 * @return array - Adapter config with tokens added to all URIs
 */
function addTokenToAdapterUrls($adapter, $token) {
    foreach ($adapter as $key => &$value) {
        if (is_array($value)) {
            if (isset($value['uri']) && !empty($value['uri'])) {
                $uri = $value['uri'];
                
                // Convert /moop/data/tracks/ paths to go through tracks.php
                if (preg_match('#^/moop/data/tracks/(.+)$#', $uri, $matches)) {
                    $file_path = $matches[1]; // e.g., "Nematostella_vectensis/GCA_033964005.1/maf/test.maf.gz"
                    $value['uri'] = '/moop/api/jbrowse2/tracks.php?file=' . urlencode($file_path);
                    
                    // Add token only if provided (whitelisted IPs don't need tokens)
                    if ($token) {
                        $value['uri'] .= '&token=' . urlencode($token);
                    }
                } elseif ($token) {
                    // For other URIs, just add token if provided
                    $separator = strpos($uri, '?') !== false ? '&' : '?';
                    $value['uri'] .= $separator . 'token=' . urlencode($token);
                }
            } else {
                // Recurse into nested arrays
                $value = addTokenToAdapterUrls($value, $token);
            }
        }
    }
    
    return $adapter;
}

/**
 * Check if user can access assembly
 */
function canUserAccessAssembly($user_level, $assembly_level, $organism, $assembly_id) {
    // Admin and IP_IN_RANGE see everything
    if ($user_level === 'ADMIN' || $user_level === 'IP_IN_RANGE') {
        return true;
    }
    
    // Public assemblies visible to all
    if ($assembly_level === 'PUBLIC') {
        return true;
    }
    
    // Collaborator needs specific assembly access
    if ($user_level === 'COLLABORATOR') {
        if ($organism && $assembly_id) {
            $user_access = $_SESSION['access'] ?? [];
            return isset($user_access[$organism]) && in_array($assembly_id, (array)$user_access[$organism]);
        }
        return false;
    }
    
    return false;
}
?>
