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
 * Measured 2026-10-13, the largest assembly here (Nematostella Nvec200, 1,171 tracks):
 * ~1.5 s, 3.2 MB -> 426 KB gzipped. See "Large track sets" below before changing this.
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
 * Large track sets -- what is already on demand, and what is not:
 * -------------------------------------------------------------------
 * This endpoint sends the TRACK LIST for one assembly: only the tracks this user may
 * see, each with a token bound to its one data file. The track DATA is already loaded
 * on demand: JBrowse fetches only the region on screen, through tracks.php, which
 * checks that token on every request. Only this list is sent all at once.
 *
 * If the list ever gets slow, two approaches JBrowse 2 actually supports:
 *   - a track-hub connection (JB2TrackHubConnection): a second config.json of tracks,
 *     loaded when the user connects to it -- e.g. one per category, served by this
 *     endpoint with the same filtering and tokens;
 *   - splitting the list by category and adding categories as the user asks for them
 *     (sessionTracks).
 * Do NOT use a track entry of {trackId, uri, assemblyNames} pointing back at PHP: in
 * JBrowse 2 that is shorthand for a DATA FILE, whose adapter is chosen by the file
 * extension. api/jbrowse2/config-optimized.php did exactly that for "lazy loading";
 * it was never switched on, would have broken every track over its 50-track threshold,
 * and duplicated this file's token code (already drifting). Deleted 2026-10-13.
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

// Initialize session and access control. Must go through moop_session_start() --
// session_set_cookie_params() and session_name() have no effect once the session
// has begun, so a direct session_start() here silently opts this endpoint out.
require_once __DIR__ . '/../../includes/session_init.php';
moop_session_start();
require_once __DIR__ . '/../../includes/access_control.php';
require_once __DIR__ . '/../../lib/functions_access.php';
require_once __DIR__ . '/../../lib/jbrowse/track_token.php';
require_once __DIR__ . '/../../lib/jbrowse/config_functions.php';
require_once __DIR__ . '/../../lib/jbrowse/PluginLoader.php';

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
        $assembly_def = loadJsonFile($file, []);

        if (!$assembly_def) continue;
        
        // Only assemblies with a gene set this viewer may open (the site's rules, not the
        // JSON's own defaultAccessLevel -- see moop_jbrowse_viewer_gene_sets()).
        if (!moop_jbrowse_can_open_assembly($assembly_def['organism'] ?? null, $assembly_def['assemblyId'] ?? null)) {
            continue;
        }
        
        $config['assemblies'][] = [
            'name' => $assembly_def['name'],
            'displayName' => $assembly_def['displayName'] ?? $assembly_def['name'],
            'aliases' => $assembly_def['aliases'] ?? [],
            'sequence' => moop_jbrowse_tokenize_sequence($assembly_def['sequence'] ?? null,
                                                         $assembly_def['organism'], $assembly_def['assemblyId'])
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
    // 1. PERMISSION FIRST: the viewer must be able to open a gene set of this assembly -- the same
    // rule as every other page (moop_jbrowse_viewer_gene_sets()). Before the file lookup, and the
    // same 404 as an unknown assembly, so the answer never says that a restricted one exists
    // (and $organism/$assembly reach the paths below only once they are known names).
    if (!moop_jbrowse_can_open_assembly($organism, $assembly)) {
        http_response_code(404);
        echo json_encode(['error' => "Assembly definition not found: {$organism}_{$assembly}"]);
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

    $assembly_definition = loadJsonFile($assembly_def_file, []);

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
                'sequence' => moop_jbrowse_tokenize_sequence($assembly_definition['sequence'] ?? null, $organism, $assembly)
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
    // All permitted tracks are listed in the response; their DATA loads on demand
    // through tracks.php. For very large lists see "Large track sets" in the header.
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
    // (A "flat structure" fallback used to load tracks/*.json -- every assembly's -- when this
    // assembly had none. No flat files exist, and it would have handed one assembly another's
    // tracks. Removed 2026-10-20.)
    $track_files = glob("$tracks_dir/$organism/$assembly/*/*.json") ?: [];
    
    $filtered_tracks = [];

    // Access level hierarchy (higher number = more access)
    // PUBLIC (1) < COLLABORATOR (2) < IP_IN_RANGE (3) < ADMIN (4)
    // Compared case-insensitively: track JSONs store 'Public' (11 of 12 TrackTypes default
    // to that literal) while the ladder is keyed 'PUBLIC'. Unknown levels fail CLOSED.
    // Single source of truth: lib/functions_access.php.
    $user_level_value = granted_access_level_value($user_access_level);

    foreach ($track_files as $track_file) {
        $track_def = loadJsonFile($track_file, []);

        if (!$track_def) continue;

        // A gene set's own track (gene models): visible exactly when the gene set is -- the
        // site's rule. Its stored access_level is written 'PUBLIC' at registration and is not
        // a decision anyone made, so it is not consulted for these.
        $track_gene_set = (string)($track_def['metadata']['gene_set'] ?? '');
        if ($track_gene_set !== '') {
            if (!moop_jbrowse_can_open_gene_set($organism, $assembly, $track_gene_set)) continue;
            $track_with_tokens = addTokensToTrack($track_def, $organism, $assembly);
            if ($track_with_tokens) $filtered_tracks[] = $track_with_tokens;
            continue;
        }

        // Other tracks (RNA-seq, etc. -- curated per track in the track sheets): the assembly is
        // already known to be open to this viewer; the track's own access_level narrows further.
        // No level set = admins only (fails closed); nothing is PUBLIC unless it says so.
        $track_access_level = $track_def['metadata']['access_level'] ?? null;
        $track_level_value = required_access_level_value($track_access_level);

        // PERMISSION CHECK: User must have sufficient access level
        // Example: PUBLIC user (1) cannot see COLLABORATOR tracks (2)
        if ($user_level_value < $track_level_value) {
            continue;
        }

        // COLLABORATOR-SPECIFIC CHECK: Must have explicit assembly access
        if (normalize_access_level($user_access_level) === 'COLLABORATOR' && $track_level_value >= 2) {
            $user_access = $_SESSION['access'] ?? [];
            if (!isset($user_access[$organism][$assembly])) {
                continue;
            }
        }
        
        $track_with_tokens = addTokensToTrack($track_def, $organism, $assembly);

        if ($track_with_tokens) {
            $filtered_tracks[] = $track_with_tokens;
        }
    }

    // What the browser sees of each track's metadata: the sheet's descriptive fields, flat,
    // so JBrowse's faceted selector can filter and search them (moop_jbrowse_browser_metadata()).
    foreach ($filtered_tracks as &$t) {
        if (isset($t['metadata']) && is_array($t['metadata'])) {
            $t['metadata'] = moop_jbrowse_browser_metadata($t['metadata']);
        }
    }
    unset($t);

    // Sort so Gene Models appear first, then other categories in a natural order
    $category_order = [
        'Gene Models'   => 0,
        'Gene Expression' => 1,
    ];
    $filtered_tracks = moop_jbrowse_order_tracks(moop_jbrowse_split_individual_tracks($filtered_tracks), $category_order);

    return $filtered_tracks;
}

/**
 * Add JWT tokens to track adapter configuration
 *
 * Trusted servers (configured in trusted_tracks_servers) always get tokens.
 * External servers (UCSC, Ensembl, etc.) never get tokens.
 * access_level controls who SEES the track; the URL whitelist controls which servers GET tokens.
 *
 * @param array $track_def - Track definition from metadata
 * @param string $organism - Organism name
 * @param string $assembly - Assembly ID
 * @return array|null - Track config with tokens, or null if token generation fails
 */
/**
 * An assembly's sequence block with its file URLs tokenised, like a track's. The genome FASTA
 * is fetched by URL from data/genomes/ exactly as a gene track is, but this block is built
 * apart from the tracks and until 2026-10-20 went out with no token at all.
 */
function moop_jbrowse_tokenize_sequence($sequence, $organism, $assembly) {
    if (!is_array($sequence) || !isset($sequence['adapter'])) return $sequence;
    $sequence['adapter'] = addTokenToAdapterUrls($sequence['adapter'], $organism, $assembly, 'PUBLIC');
    return $sequence;
}

function addTokensToTrack($track_def, $organism, $assembly) {
    // Tokens are minted per-file inside addTokenToAdapterUrls (each bound to the exact
    // file it authorizes — audit #17), so nothing is pre-minted here.
    $track_access_level = (string)($track_def['metadata']['access_level'] ?? '');

    if (isset($track_def['adapter'])) {
        try {
            $track_def['adapter'] = addTokenToAdapterUrls($track_def['adapter'], $organism, $assembly, $track_access_level);
            // A text-search (trix) index is fetched by URL like the track's own files.
            if (isset($track_def['textSearching']['textSearchAdapter'])) {
                $track_def['textSearching']['textSearchAdapter'] = addTokenToAdapterUrls(
                    $track_def['textSearching']['textSearchAdapter'], $organism, $assembly, $track_access_level);
            }
        } catch (Exception $e) {
            error_log("Failed to generate token(s) for track {$track_def['trackId']}: " . $e->getMessage());
            return null;
        }
    }

    return $track_def;
}

/**
 * Recursively add token parameter to adapter URIs
 * 
 * TOKEN STRATEGY (Updated 2026-02-25 - URL Whitelist):
 * 
 * IMPORTANT: With .htaccess blocking direct file access, ALL requests to
 * our tracks servers MUST have JWT tokens, regardless of access_level.
 * 
 * Strategy:
 * 1. Trusted servers (in trusted_tracks_servers config)
 *    → ALWAYS add token (your servers that validate JWTs)
 *    → This includes PUBLIC tracks on your servers!
 * 
 * 2. External servers (not in whitelist, e.g., UCSC, Ensembl)
 *    → NEVER add token (they don't validate our JWTs)
 * 
 * 3. MOOP internal paths
 *    → ALWAYS add token (routed through tracks.php)
 * 
 * Key Point: access_level controls FILTERING (who sees track in config)
 *            URL whitelist controls TOKENS (which servers get tokens)
 * 
 * Examples:
 * - PUBLIC track on your server → Token added (for .htaccess bypass)
 * - PUBLIC track on UCSC → No token (external public server)
 * - COLLABORATOR track on your server → Token added (authenticated access)
 * - COLLABORATOR track on UCSC → No token + warning logged (misconfigured)
 * 
 * Each tracks.php-routed file gets its OWN token, bound to that exact file (audit #17),
 * so a token cannot be replayed against a different file on the same assembly.
 *
 * @param array $adapter - Adapter configuration (may contain nested arrays)
 * @param string $organism - Organism (token context)
 * @param string $assembly - Assembly (token context)
 * @param string $track_access_level - Track's access level (for validation warnings)
 * @return array - Adapter config with per-file tokens added appropriately
 */
function addTokenToAdapterUrls($adapter, $organism, $assembly, $track_access_level = 'PUBLIC') {
    $config = ConfigManager::getInstance();
    $warn_on_external_private = $config->getBoolean('jbrowse2.warn_on_external_private_tracks', true);
    $site = $config->getString('site', 'moop');

    // Auto-include configured tracks server as trusted
    $tracks_server_cfg = $config->get('tracks_server', []);
    $tracks_server_enabled = !empty($tracks_server_cfg['enabled']);
    $tracks_server_url = isset($tracks_server_cfg['url']) ? rtrim($tracks_server_cfg['url'], '/') : '';

    foreach ($adapter as $key => &$value) {
        if (is_array($value)) {
            if (isset($value['uri']) && !empty($value['uri'])) {
                $uri = $value['uri'];

                // Detect external URLs (http://, https://, ftp://)
                $is_external = preg_match('#^(https?|ftp)://#i', $uri);

                if ($is_external) {
                    // Check if this is a trusted server (explicit list OR configured tracks server)
                    $is_trusted = $config->isTrustedTracksServer($uri) ||
                                  ($tracks_server_enabled && $tracks_server_url &&
                                   ($uri === $tracks_server_url || strpos($uri, $tracks_server_url . '/') === 0));

                    if ($is_trusted) {
                        // CASE 1: Trusted external server → ALWAYS add token and route through tracks.php
                        // If the URL contains /data/tracks/, extract the base and rewrite to tracks.php.
                        // This works even if tracks_server.url is not configured — the base is derived
                        // from the URL itself (e.g. https://tracks.example.com/moop/data/tracks/org/asm/f.bw
                        // becomes https://tracks.example.com/moop/api/jbrowse2/tracks.php?file=org/asm/f.bw).
                        $tracks_marker = '/data/tracks/';
                        $marker_pos = strpos($uri, $tracks_marker);
                        if ($marker_pos !== false) {
                            $remote_base = substr($uri, 0, $marker_pos);
                            $file_path   = substr($uri, $marker_pos + strlen($tracks_marker));
                            if (($q = strpos($file_path, '?')) !== false) {
                                $file_path = substr($file_path, 0, $q);
                            }
                            // Token bound to THIS file — it authorizes only $file_path.
                            $token = generateTrackToken($organism, $assembly, $file_path);
                            $value['uri'] = $remote_base . '/api/jbrowse2/tracks.php?file=' . urlencode($file_path) . '&token=' . urlencode($token);
                        } else {
                            // Trusted external, non-tracks URL: token is decorative (no ?file to bind).
                            $token = generateTrackToken($organism, $assembly, $uri);
                            $separator = strpos($uri, '?') !== false ? '&' : '?';
                            $value['uri'] .= $separator . 'token=' . urlencode($token);
                        }

                    } else {
                        // CASE 2: Untrusted external server → NEVER add token
                        // This is a public resource (UCSC, Ensembl, NCBI) that:
                        // - Doesn't validate our JWT tokens
                        // - Is truly public (open access)
                        // - Not protected by our .htaccess

                        // Validation: Warn if marked as private (can't enforce auth)
                        if ($warn_on_external_private && normalize_access_level($track_access_level) !== 'PUBLIC') {
                            error_log(
                                "WARNING: Track has external URL with access_level='{$track_access_level}' " .
                                "but server is not in trusted_tracks_servers list. " .
                                "Cannot enforce authentication. URL: $uri"
                            );
                        }

                        // Leave URL unchanged (no token leakage to external servers)
                        continue;
                    }

                } else {
                    // CASE 3: MOOP internal paths → Route through local tracks.php with token
                    // Local filesystem paths always stay local — remote files are registered
                    // with their https:// URL directly and are handled by CASE 1.
                    if (preg_match("#^/{$site}/data/tracks/(.+)$#", $uri, $matches)) {
                        $file_path = $matches[1];
                        if (($q = strpos($file_path, '?')) !== false) {
                            $file_path = substr($file_path, 0, $q);
                        }
                        // Token bound to THIS file.
                        $token = generateTrackToken($organism, $assembly, $file_path);
                        $value['uri'] = "/{$site}/api/jbrowse2/tracks.php?file=" . urlencode($file_path) . '&token=' . urlencode($token);

                    } elseif (preg_match("#^/{$site}/data/genomes/(.+)$#", $uri, $matches)) {
                        // CASE 3b: genome data (sequence, gene track, trix index) -> genome_file.php,
                        // which checks this token. nginx no longer serves data/genomes/ directly;
                        // until 2026-10-20 it did, and the token appended here was decorative.
                        $file_path = $matches[1];
                        if (($q = strpos($file_path, '?')) !== false) {
                            $file_path = substr($file_path, 0, $q);
                        }
                        $token = generateTrackToken($organism, $assembly, 'genomes/' . $file_path);
                        $value['uri'] = "/{$site}/api/jbrowse2/genome_file.php?file=" . urlencode($file_path) . '&token=' . urlencode($token);

                    } elseif (preg_match("#^/{$site}/#", $uri)) {
                        // CASE 4: Other MOOP paths served directly by MOOP.
                        // Token is decorative here (not routed through tracks.php); bind to the path.
                        $token = generateTrackToken($organism, $assembly, $uri);
                        $separator = strpos($uri, '?') !== false ? '&' : '?';
                        $value['uri'] .= $separator . 'token=' . urlencode($token);
                    }
                    // CASE 5: Other paths (relative, absolute) → Leave unchanged
                }

            } else {
                // Recurse into nested adapter structures
                $value = addTokenToAdapterUrls($value, $organism, $assembly, $track_access_level);
            }
        }
    }
    return $adapter;
}

?>
