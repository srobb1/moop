<?php
/**
 * /api/jbrowse2/assembly.php
 * 
 * Generates complete JBrowse2 config for an organism-assembly
 * Reads modular assembly definitions from /metadata/jbrowse2-configs/assemblies/
 * Dynamically filters tracks based on user access level
 * 
 * GET /api/jbrowse2/assembly?organism={organism}&assembly={assembly}
 * 
 * Flow:
 * 1. Validate permissions via getAccessibleAssemblies()
 * 2. Load assembly definition from metadata/{organism}_{assembly}.json
 * 3. Load all available track definitions from metadata/tracks/
 * 4. Filter tracks by user access level
 * 5. Generate JWT tokens for tracks (if needed)
 * 6. Return complete JBrowse2 config
 */

require_once '../../includes/access_control.php';
require_once '../../lib/functions_access.php';
require_once '../../lib/jbrowse/track_token.php';

header('Content-Type: application/json');
header('Cache-Control: max-age=300, private');  // 5 min cache

// Get parameters
$organism = $_GET['organism'] ?? '';
$assembly = $_GET['assembly'] ?? '';

// Validate input
if (empty($organism) || empty($assembly)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing organism or assembly parameter']);
    exit;
}

// 1. VALIDATE PERMISSIONS
$accessible = getAccessibleAssemblies($organism, $assembly);
if (empty($accessible)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied to this assembly']);
    exit;
}

// Get user access level
$user_access_level = $_SESSION['access_level'] ?? 'Public';
if (!isset($_SESSION['access_level'])) {
    // Auto-detect based on session
    $user_access_level = isset($_SESSION['username']) && has_access('ALL') ? 'ALL' : 'Public';
}

// 2. LOAD ASSEMBLY DEFINITION FROM METADATA
$metadata_path = __DIR__ . '/../../metadata';
$assemblies_dir = "$metadata_path/jbrowse2-configs/assemblies";
$assembly_def_file = "$assemblies_dir/{$organism}_{$assembly}.json";

$assembly_definition = null;
if (file_exists($assembly_def_file)) {
    $assembly_def_content = file_get_contents($assembly_def_file);
    $assembly_definition = json_decode($assembly_def_content, true);
    
    if (!$assembly_definition) {
        http_response_code(500);
        echo json_encode(['error' => "Invalid assembly definition JSON: $assembly_def_file"]);
        exit;
    }
} else {
    // Fallback: generate basic config if definition not found
    error_log("Assembly definition not found: $assembly_def_file - using fallback");
    $assembly_definition = [
        'name' => "{$organism}_{$assembly}",
        'displayName' => "{$organism} ({$assembly})",
        'organism' => $organism,
        'assemblyId' => $assembly,
        'aliases' => [$assembly],
        'defaultAccessLevel' => 'Public',
        'sequence' => [
            'type' => 'ReferenceSequenceTrack',
            'trackId' => "{$organism}_{$assembly}-ReferenceSequenceTrack",
            'adapter' => [
                'type' => 'IndexedFastaAdapter',
                'fastaLocation' => [
                    'uri' => "/data/moop/data/genomes/{$organism}/{$assembly}/reference.fasta",
                    'locationType' => 'UriLocation'
                ],
                'faiLocation' => [
                    'uri' => "/data/moop/data/genomes/{$organism}/{$assembly}/reference.fasta.fai",
                    'locationType' => 'UriLocation'
                ]
            ]
        ]
    ];
}

// 2B. BUILD BASE ASSEMBLY CONFIG FROM DEFINITION
$assembly_config = [
    'organism' => $organism,
    'assembly' => $assembly,
    'assemblies' => [
        [
            'name' => $assembly_definition['name'],
            'displayName' => $assembly_definition['displayName'] ?? "{$organism} ({$assembly})",
            'aliases' => $assembly_definition['aliases'] ?? [$assembly],
            'sequence' => $assembly_definition['sequence']
        ]
    ],
    'tracks' => [],
    'defaultSession' => [
        'name' => 'default',
        'view' => [
            'type' => 'LinearGenomeView',
            'tracks' => []
        ]
    ]
];

// 3. LOAD ALL AVAILABLE TRACK CONFIGS
$metadata_path = __DIR__ . '/../../metadata';
$tracks_dir = "$metadata_path/jbrowse2-configs/tracks";
$track_files = glob("$tracks_dir/*.json");
$available_tracks = [];

if (!is_dir($tracks_dir)) {
    http_response_code(500);
    echo json_encode(['error' => "Track config directory not found: $tracks_dir"]);
    exit;
}

foreach ($track_files as $track_file) {
    $track_content = file_get_contents($track_file);
    $track = json_decode($track_content, true);
    if ($track) {
        $available_tracks[] = $track;
    }
}

// 4. FILTER TRACKS BY USER ACCESS LEVEL
$is_whitelisted = isWhitelistedIP();

foreach ($available_tracks as $track) {
    // Get track access levels
    $track_access_levels = $track['access_levels'] ?? ['Public'];
    
    // Determine if user can access this track
    $user_can_access = false;
    
    if ($user_access_level === 'ALL') {
        // Admin sees everything
        $user_can_access = true;
    } elseif (in_array('Public', $track_access_levels)) {
        // Public tracks visible to everyone
        $user_can_access = true;
    } elseif ($user_access_level === 'Collaborator' && in_array('Collaborator', $track_access_levels)) {
        // Check if collaborator has access to this specific assembly
        $user_access = $_SESSION['access'] ?? [];
        if (isset($user_access[$organism]) && in_array($assembly, (array)$user_access[$organism])) {
            $user_can_access = true;
        }
    }
    
    // Skip if user cannot access this track
    if (!$user_can_access) {
        continue;
    }
    
    // 5. BUILD TRACK URL WITH TOKEN IF NEEDED
    $file_template = $track['file_template'] ?? '';
    $file_name = str_replace(
        ['{organism}', '{assembly}'],
        [$organism, $assembly],
        $file_template
    );
    
    // Generate token for non-whitelisted users or always for security
    $track_url = "http://127.0.0.1:8888/tracks/{$track['format']}/{$file_name}";
    
    if (!$is_whitelisted) {
        // Generate JWT token for external/collaborator access
        try {
            $token = generateTrackToken($organism, $assembly, $user_access_level);
            $track_url .= "?token=" . urlencode($token);
        } catch (Exception $e) {
            error_log("Failed to generate token for track: " . $e->getMessage());
            continue;  // Skip this track if token generation fails
        }
    }
    
    // 6. ADD TRACK TO CONFIG
    $track_id = "{$organism}_{$assembly}_{$track['track_id']}";
    
    $track_config = [
        'name' => $track['name'],
        'trackId' => $track_id,
        'assemblyNames' => [$assembly],
        'type' => $track['type'],
        'adapter' => [
            'type' => $track['format'] === 'bam' ? 'BamAdapter' : 'BigWigAdapter',
        ],
        'displays' => [$track['display'] ?? []]
    ];
    
    // Add format-specific adapter config
    if ($track['format'] === 'bam') {
        $track_config['adapter']['bamLocation'] = ['uri' => $track_url];
        $track_config['adapter']['baiLocation'] = ['uri' => "$track_url.bai"];
    } else {
        $track_config['adapter']['bigWigLocation'] = ['uri' => $track_url];
    }
    
    // Remove empty display config
    if (empty($track_config['displays'][0])) {
        unset($track_config['displays']);
    }
    
    $assembly_config['tracks'][] = $track_config;
}

// 7. ADD REFERENCE ANNOTATIONS IF AVAILABLE
$annotations_file = __DIR__ . "/../../data/genomes/{$organism}/{$assembly}/annotations.gff3.gz";
if (file_exists($annotations_file)) {
    $assembly_config['tracks'][] = [
        'name' => 'Annotations',
        'trackId' => 'annotations',
        'assemblyNames' => [$assembly],
        'type' => 'FeatureTrack',
        'adapter' => [
            'type' => 'GffAdapter',
            'gffLocation' => [
                'uri' => "/jbrowse2/data/{$organism}/{$assembly}/annotations.gff3.gz"
            ],
            'index' => [
                'location' => [
                    'uri' => "/jbrowse2/data/{$organism}/{$assembly}/annotations.gff3.gz.tbi"
                ]
            ]
        ]
    ];
}

// 8. RETURN CONFIG
echo json_encode($assembly_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
