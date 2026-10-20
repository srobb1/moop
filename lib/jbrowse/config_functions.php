<?php
/**
 * JBrowse2 Config Generation Functions
 * 
 * Shared functions for generating JBrowse2 configurations dynamically.
 * Used by config.php and other config endpoints.
 */

// Access-level ladder + case-insensitive normalisation used by loadFilteredTracks().
// Required explicitly rather than relying on the caller having loaded it first.
require_once __DIR__ . '/../functions_access.php';
require_once __DIR__ . '/../extract_search_helpers.php';   // flattenSourcesList()

/**
 * The gene sets this viewer may open, for the genome browser: [organism][assembly] => [gene_set => true].
 * An assembly appears under its accession AND its directory name (they differ for a few).
 *
 * The ONE access decision for JBrowse, from the same rules every other page uses
 * (getAccessibleGeneSets() -> has_gene_set_access(): organism_assembly_groups.json + users.json).
 * Until 2026-10-20 api/jbrowse2/config.php decided from its own fields instead -- an assembly
 * JSON's defaultAccessLevel and a gene track's metadata.access_level, both written as 'PUBLIC'
 * at registration, a missing value also PUBLIC -- so an anonymous request for any organism's
 * config got its genome and gene track, while the jbrowse2.php picker (which used these rules)
 * hid it. Those fields are no longer read for access.
 */
function moop_jbrowse_viewer_gene_sets(): array {
    static $memo = null;
    if ($memo !== null) return $memo;
    $memo = [];
    foreach (flattenSourcesList(getAccessibleGeneSets()) as $src) {
        foreach (array_unique(array_filter([$src['genome_accession'] ?? '', $src['assembly'] ?? ''])) as $asm) {
            $memo[$src['organism']][$asm][$src['gene_set']] = true;
        }
    }
    return $memo;
}

/** May this viewer open this assembly in the genome browser: at least one of its gene sets. */
function moop_jbrowse_can_open_assembly(?string $organism, ?string $assembly): bool {
    if ((string)$organism === '' || (string)$assembly === '') return false;
    return !empty(moop_jbrowse_viewer_gene_sets()[$organism][$assembly]);
}

/** May this viewer see this gene set's gene track. */
function moop_jbrowse_can_open_gene_set(string $organism, string $assembly, string $gene_set): bool {
    return !empty(moop_jbrowse_viewer_gene_sets()[$organism][$assembly][$gene_set]);
}

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

    // Levels are compared case-insensitively — track JSONs store 'Public', the ladder is
    // keyed 'PUBLIC'. See required_/granted_access_level_value() in lib/functions_access.php.
    $user_level_value = granted_access_level_value($user_access_level);

    foreach ($track_files as $track_file) {
        $track_def = loadJsonFile($track_file, []);

        if (!$track_def) continue;

        // Get track access level
        $track_access_level = $track_def['metadata']['access_level'] ?? null;   // none set = admins only
        $track_level_value = required_access_level_value($track_access_level);

        // Check if user has access
        if ($user_level_value < $track_level_value) {
            continue;
        }

        // COLLABORATOR check: Must have access to at least one of the assemblies
        if (normalize_access_level($user_access_level) === 'COLLABORATOR' && $track_level_value >= 2) {
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
        
        $track_with_tokens = addTokensToTrack($track_def, null, null);
        
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
    
    // Access first, answered as "not found", so a restricted assembly's existence is not revealed.
    foreach ([[$organism1, $asm1, $assembly1], [$organism2, $asm2, $assembly2]] as [$check_org, $check_asm, $check_name]) {
        if (!moop_jbrowse_can_open_assembly($check_org, $check_asm)) {
            http_response_code(404);
            echo json_encode(['error' => "Assembly not found: $check_name"]);
            exit;
        }
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
    
    $assembly1_def = loadJsonFile($assembly1_file, []);
    $assembly2_def = loadJsonFile($assembly2_file, []);
    
    if (!$assembly1_def || !$assembly2_def) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load assembly definitions']);
        exit;
    }
    
    // Build dual-assembly config
    $config = [
        'assemblies' => [
            [
                'name' => $assembly1_def['name'],
                'displayName' => $assembly1_def['displayName'] ?? "{$organism1} ({$asm1})",
                'aliases' => $assembly1_def['aliases'] ?? [$asm1],
                'sequence' => moop_jbrowse_tokenize_sequence($assembly1_def['sequence'] ?? null, $organism1, $asm1)
            ],
            [
                'name' => $assembly2_def['name'],
                'displayName' => $assembly2_def['displayName'] ?? "{$organism2} ({$asm2})",
                'aliases' => $assembly2_def['aliases'] ?? [$asm2],
                'sequence' => moop_jbrowse_tokenize_sequence($assembly2_def['sequence'] ?? null, $organism2, $asm2)
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
