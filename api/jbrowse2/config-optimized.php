<?php
/**
 * JBrowse2 Configuration API - OPTIMIZED FOR LARGE TRACK SETS
 * 
 * THIS ENDPOINT IS AVAILABLE BUT NOT CURRENTLY USED
 * 
 * Purpose:
 * --------
 * For assemblies with > 1000 tracks, this endpoint uses lazy-loading to improve performance.
 * Instead of embedding all track configs in the response, it returns track URIs that
 * JBrowse2 fetches on-demand.
 * 
 * When to Use:
 * ------------
 * - Assembly has > 1000 tracks
 * - Standard config.php response time > 2 seconds
 * - Gzipped config size > 500KB
 * 
 * Decision Logic:
 * ---------------
 * - <= 50 tracks:  Embeds full track configs (fastest for small sets)
 * - > 50 tracks:   Returns track URIs for lazy loading
 * 
 * Performance:
 * ------------
 * Standard (config.php):  2MB config → 200KB gzipped, ~500ms for 500 tracks
 * Optimized (this file):  < 50KB config, ~200ms regardless of track count
 * 
 * Usage:
 * ------
 * GET /api/jbrowse2/config-optimized.php
 *     Returns: List of assemblies user can access
 *     Example response: { assemblies: [...], userAccessLevel: "PUBLIC" }
 * 
 * GET /api/jbrowse2/config-optimized.php?organism=Nematostella_vectensis&assembly=GCA_033964005.1
 *     Returns: Assembly config with tracks
 *     - If <= 50 tracks: Full configs embedded
 *     - If > 50 tracks: Track URIs for lazy loading
 * 
 * GET /api/jbrowse2/config-optimized.php?track=unique-track-id&organism=X&assembly=Y
 *     Returns: Individual track configuration
 *     Called by JBrowse2 when using lazy-loading mode
 * 
 * How to Activate:
 * ----------------
 * 1. Update js/jbrowse2-loader.js to call config-optimized.php instead of config.php
 * 2. Or implement smart routing based on track count:
 *    if (trackCount > 1000) {
 *        apiUrl = '/moop/api/jbrowse2/config-optimized.php';
 *    } else {
 *        apiUrl = '/moop/api/jbrowse2/config.php';
 *    }
 * 
 * Example JavaScript Usage:
 * -------------------------
 * // Fetch assembly config with optimized loading
 * fetch('/moop/api/jbrowse2/config-optimized.php?organism=X&assembly=Y')
 *   .then(r => r.json())
 *   .then(config => {
 *     // If track.uri is present, JBrowse2 will lazy-load it
 *     console.log('Tracks:', config.tracks.length);
 *     initJBrowse2(config);
 *   });
 * 
 * Current Status: AVAILABLE (not currently used, all assemblies < 1000 tracks)
 * Last Updated: 2026-02-14
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Initialize session and access control
session_start();
require_once __DIR__ . '/../../includes/access_control.php';
require_once __DIR__ . '/../../lib/functions_access.php';
require_once __DIR__ . '/../../lib/jbrowse/track_token.php';
require_once __DIR__ . '/../../lib/JBrowse/PluginLoader.php';

// Get user's access level
$user_access_level = get_access_level();
$organism = $_GET['organism'] ?? null;
$assembly = $_GET['assembly'] ?? null;
$track_id = $_GET['track'] ?? null;

// Route to appropriate handler
if ($track_id) {
    // Individual track config request
    serveSingleTrackConfig($track_id, $organism, $assembly, $user_access_level);
} elseif ($organism && $assembly) {
    // Full assembly config with track references
    generateOptimizedAssemblyConfig($organism, $assembly, $user_access_level);
} else {
    // Assembly list only
    generateAssemblyList($user_access_level);
}

/**
 * Generate list of accessible assemblies
 * 
 * Returns a list of assemblies the user has permission to view.
 * Used when no organism/assembly parameters are provided.
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
        'configuration' => getJBrowse2PluginConfiguration(),
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
 * Generate optimized assembly config with track URIs or embedded tracks
 * 
 * Decision Logic:
 * - <= 50 tracks: Embeds full track configs (one HTTP request)
 * - > 50 tracks:  Returns track URIs for lazy loading (reduces initial payload)
 * 
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level
 * @return void - Outputs JSON and exits
 */
function generateOptimizedAssemblyConfig($organism, $assembly, $user_access_level) {
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
    
    // 3. BUILD BASE CONFIG WITH PLUGINS
    $config = [
        'assemblies' => [
            [
                'name' => $assembly_definition['name'],
                'displayName' => $assembly_definition['displayName'] ?? "{$organism} ({$assembly})",
                'aliases' => $assembly_definition['aliases'] ?? [$assembly],
                'sequence' => $assembly_definition['sequence']
            ]
        ],
        'configuration' => getJBrowse2PluginConfiguration(),
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
    
    // 4. LOAD TRACK LIST (lightweight - just IDs and URIs)
    $track_references = getTrackReferences($organism, $assembly, $user_access_level);
    
    // OPTIMIZATION DECISION POINT:
    // For small track sets (<=50), embed full configs to avoid additional HTTP requests
    // For large track sets (>50), use URIs to reduce initial payload size
    // Threshold of 50 chosen based on testing: ~100KB embedded vs ~10KB with URIs
    if (count($track_references) <= 50) {
        // EMBEDDED MODE: All track configs in one response
        // Pros: Faster for small sets (one request)
        // Cons: Large payload for many tracks
        $config['tracks'] = loadFullTrackConfigs($track_references, $organism, $assembly, $user_access_level);
    } else {
        // LAZY-LOADING MODE: Track URIs only, JBrowse2 fetches on-demand
        // Pros: Small initial payload (<50KB), fast initial load
        // Cons: Additional requests as tracks are opened
        $config['tracks'] = convertToTrackUris($track_references, $organism, $assembly);
    }
    
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Get list of track references (just metadata, no full configs)
 * 
 * This function scans track metadata files and returns lightweight references.
 * Performs permission filtering based on user access level.
 * 
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level (PUBLIC, COLLABORATOR, IP_IN_RANGE, ADMIN)
 * @return array - Array of track references with trackId, name, type, category, file path
 */
function getTrackReferences($organism, $assembly, $user_access_level) {
    $tracks_dir = __DIR__ . "/../../metadata/jbrowse2-configs/tracks";
    $track_files = glob("$tracks_dir/$organism/$assembly/*/*.json");
    
    if (empty($track_files)) {
        // Fallback to flat structure (legacy compatibility)
        $track_files = glob("$tracks_dir/*.json");
    }
    
    // Access level hierarchy (higher number = more access)
    // PUBLIC (1) < COLLABORATOR (2) < IP_IN_RANGE (3) < ADMIN (4)
    $access_hierarchy = [
        'ADMIN' => 4,
        'IP_IN_RANGE' => 3,
        'COLLABORATOR' => 2,
        'PUBLIC' => 1
    ];
    
    $user_level_value = $access_hierarchy[$user_access_level] ?? 0;
    $track_refs = [];
    
    foreach ($track_files as $track_file) {
        $track_def = json_decode(file_get_contents($track_file), true);
        
        if (!$track_def) continue;
        
        // Get track access level
        $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
        $track_level_value = $access_hierarchy[$track_access_level] ?? 1;
        
        // Check if user has sufficient access
        if ($user_level_value < $track_level_value) {
            continue;
        }
        
        // Special check for COLLABORATOR
        if ($user_access_level === 'COLLABORATOR' && $track_level_value >= 2) {
            $user_access = $_SESSION['access'] ?? [];
            if (!isset($user_access[$organism]) || !in_array($assembly, (array)$user_access[$organism])) {
                continue;
            }
        }
        
        $track_refs[] = [
            'file' => $track_file,
            'trackId' => $track_def['trackId'],
            'name' => $track_def['name'] ?? 'Unknown',
            'type' => $track_def['type'] ?? 'FeatureTrack',
            'category' => $track_def['category'] ?? []
        ];
    }
    
    return $track_refs;
}

/**
 * Load full track configs (for small track sets <= 50)
 * 
 * When track count is small, it's more efficient to embed full configs
 * rather than forcing JBrowse2 to make additional HTTP requests.
 * 
 * @param array $track_refs - Array of track references from getTrackReferences()
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level
 * @return array - Array of full track configurations with JWT tokens
 */
function loadFullTrackConfigs($track_refs, $organism, $assembly, $user_access_level) {
    $tracks = [];
    $is_whitelisted = isWhitelistedIP();
    
    foreach ($track_refs as $ref) {
        $track_def = json_decode(file_get_contents($ref['file']), true);
        $track_with_tokens = addTokensToTrack($track_def, $organism, $assembly, $user_access_level, $is_whitelisted);
        
        if ($track_with_tokens) {
            $tracks[] = $track_with_tokens;
        }
    }
    
    return $tracks;
}

/**
 * Convert track references to URIs (for large track sets > 50)
 * 
 * Returns lightweight track stubs with URIs pointing back to this endpoint.
 * JBrowse2 will request individual tracks via:
 *   GET config-optimized.php?track=TRACK_ID&organism=X&assembly=Y
 * 
 * This reduces initial config size from MB to KB, improving load times.
 * 
 * @param array $track_refs - Array of track references from getTrackReferences()
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @return array - Array of track stubs with URIs for lazy loading
 */
function convertToTrackUris($track_refs, $organism, $assembly) {
    $tracks = [];
    
    foreach ($track_refs as $ref) {
        $tracks[] = [
            'type' => $ref['type'],
            'trackId' => $ref['trackId'],
            'name' => $ref['name'],
            'category' => $ref['category'],
            'assemblyNames' => ["{$organism}_{$assembly}"],
            'uri' => "/moop/api/jbrowse2/config-optimized.php?track=" . urlencode($ref['trackId']) . 
                     "&organism=" . urlencode($organism) . 
                     "&assembly=" . urlencode($assembly)
        ];
    }
    
    return $tracks;
}

/**
 * Serve individual track configuration
 * 
 * Called by JBrowse2 when using lazy-loading mode.
 * Returns a single track's full configuration with JWT tokens.
 * 
 * Security:
 * - Validates user has access to the assembly
 * - Validates user has access to the specific track (based on track access level)
 * - Adds JWT tokens for track file URLs
 * 
 * @param string $track_id - Track ID to retrieve
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @param string $user_access_level - User's access level
 * @return void - Outputs JSON and exits
 */
function serveSingleTrackConfig($track_id, $organism, $assembly, $user_access_level) {
    if (!$organism || !$assembly) {
        http_response_code(400);
        echo json_encode(['error' => 'organism and assembly parameters required']);
        exit;
    }
    
    // Validate permissions
    $accessible = getAccessibleAssemblies($organism, $assembly);
    if (empty($accessible)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Find track file
    $tracks_dir = __DIR__ . "/../../metadata/jbrowse2-configs/tracks";
    $track_files = glob("$tracks_dir/$organism/$assembly/*/*.json");
    
    foreach ($track_files as $track_file) {
        $track_def = json_decode(file_get_contents($track_file), true);
        
        if ($track_def && $track_def['trackId'] === $track_id) {
            // Check access
            $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
            // ... access check logic ...
            
            // Add tokens
            $is_whitelisted = isWhitelistedIP();
            $track_with_tokens = addTokensToTrack($track_def, $organism, $assembly, $user_access_level, $is_whitelisted);
            
            echo json_encode($track_with_tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Track not found']);
}

// Include helper functions from config.php
/**
 * Add JWT tokens to track adapter configuration
 * 
 * SECURITY UPDATE (2026-02-25):
 * - Uses URL whitelist from trusted_tracks_servers config
 * - Trusted servers ALWAYS get tokens (even for PUBLIC tracks)
 * - External servers NEVER get tokens (prevents token leakage)
 * - Logs warnings for misconfigured tracks
 * 
 * With .htaccess blocking direct file access, even PUBLIC tracks
 * on your servers need JWT tokens.
 */
function addTokensToTrack($track_def, $organism, $assembly, $user_access_level, $is_whitelisted) {
    // ALWAYS generate JWT tokens for all users
    try {
        $token = generateTrackToken($organism, $assembly);
    } catch (Exception $e) {
        error_log("Failed to generate token for track {$track_def['trackId']}: " . $e->getMessage());
        return null;
    }
    
    // Get track access level for validation warnings
    $track_access_level = $track_def['metadata']['access_level'] ?? 'PUBLIC';
    
    if (isset($track_def['adapter'])) {
        $track_def['adapter'] = addTokenToAdapterUrls($track_def['adapter'], $token, $track_access_level);
    }
    
    return $track_def;
}

/**
 * Recursively add token parameter to adapter URIs
 * 
 * TOKEN STRATEGY (2026-02-25 - URL Whitelist):
 * - Trusted servers (in whitelist) → ALWAYS add token
 * - External servers (not in whitelist) → NEVER add token
 * - MOOP internal paths → ALWAYS add token
 */
function addTokenToAdapterUrls($adapter, $token, $track_access_level = 'PUBLIC') {
    $config = ConfigManager::getInstance();
    $warn_on_external_private = $config->getBoolean('jbrowse2.warn_on_external_private_tracks', true);
    $site = $config->getString('site', 'moop');
    
    foreach ($adapter as $key => &$value) {
        if (is_array($value)) {
            if (isset($value['uri']) && !empty($value['uri'])) {
                $uri = $value['uri'];
                
                // Detect external URLs
                $is_external = preg_match('#^(https?|ftp)://#i', $uri);
                
                if ($is_external) {
                    // Check if this is a trusted server
                    if ($config->isTrustedTracksServer($uri)) {
                        // Trusted server → Add token
                        $separator = strpos($uri, '?') !== false ? '&' : '?';
                        $value['uri'] .= $separator . 'token=' . urlencode($token);
                    } else {
                        // External server → No token + validate
                        if ($warn_on_external_private && $track_access_level !== 'PUBLIC') {
                            error_log(
                                "WARNING: Track has external URL with access_level='{$track_access_level}' " .
                                "but server is not in trusted_tracks_servers list. URL: $uri"
                            );
                        }
                        continue;
                    }
                } else {
                    // Internal paths → Add token (use site variable from config)
                    if (preg_match("#^/{$site}/data/tracks/(.+)$#", $uri, $matches)) {
                        $file_path = $matches[1];
                        $value['uri'] = "/{$site}/api/jbrowse2/tracks.php?file=" . urlencode($file_path);
                        $value['uri'] .= '&token=' . urlencode($token);
                    } elseif (preg_match("#^/{$site}/#", $uri)) {
                        $separator = strpos($uri, '?') !== false ? '&' : '?';
                        $value['uri'] .= $separator . 'token=' . urlencode($token);
                    }
                }
            } else {
                $value = addTokenToAdapterUrls($value, $token, $track_access_level);
            }
        }
    }
    
    return $adapter;
}

function canUserAccessAssembly($user_level, $assembly_level, $organism, $assembly_id) {
    if ($user_level === 'ADMIN' || $user_level === 'IP_IN_RANGE') {
        return true;
    }
    
    if ($assembly_level === 'PUBLIC') {
        return true;
    }
    
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
