<?php
/**
 * /api/jbrowse2/test-assembly.php
 * 
 * Test version of assembly API without full MOOP session dependency
 * Generates complete JBrowse2 config for an organism-assembly
 * Reads modular assembly definitions from /metadata/jbrowse2-configs/assemblies/
 * Dynamically filters tracks based on access level
 * 
 * GET /api/jbrowse2/test-assembly.php?organism={organism}&assembly={assembly}&access_level={level}
 * access_level can be: Public (default), Collaborator, ALL
 */

require_once '../../lib/jbrowse/track_token.php';

header('Content-Type: application/json');
header('Cache-Control: max-age=300, private');

// Get parameters
$organism = $_GET['organism'] ?? '';
$assembly = $_GET['assembly'] ?? '';
$access_level = $_GET['access_level'] ?? 'Public';

// Validate input
if (empty($organism) || empty($assembly)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing organism or assembly parameter']);
    exit;
}

// Validate organism-assembly by checking assembly definition exists
$metadata_path = __DIR__ . '/../../metadata';
$assemblies_dir = "$metadata_path/jbrowse2-configs/assemblies";
$assembly_def_file = "$assemblies_dir/{$organism}_{$assembly}.json";

if (!file_exists($assembly_def_file)) {
    http_response_code(404);
    echo json_encode(['error' => "Assembly not found or not configured: $organism / $assembly"]);
    exit;
}

// Load assembly definition
$assembly_def_content = file_get_contents($assembly_def_file);
$assembly_definition = json_decode($assembly_def_content, true);

if (!$assembly_definition) {
    http_response_code(500);
    echo json_encode(['error' => "Invalid assembly definition JSON: $assembly_def_file"]);
    exit;
}

// 2. BUILD BASE ASSEMBLY CONFIG FROM DEFINITION
$assembly_config = [
    'organism' => $organism,
    'assembly' => $assembly,
    'user_access_level' => $access_level,
    'test_mode' => true,
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
foreach ($available_tracks as $track) {
    // Get track access levels
    $track_access_levels = $track['access_levels'] ?? ['Public'];
    
    // Determine if user can access this track
    $user_can_access = false;
    
    if ($access_level === 'ADMIN' || $access_level === 'IP_IN_RANGE') {
        // Admin and IP_IN_RANGE see everything
        $user_can_access = true;
    } elseif (in_array('PUBLIC', $track_access_levels)) {
        // Public tracks visible to everyone
        $user_can_access = true;
    } elseif ($access_level === 'COLLABORATOR' && in_array('COLLABORATOR', $track_access_levels)) {
        // Collaborators see collaborator tracks (simplified for test)
        $user_can_access = true;
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
    
    // Generate token
    $track_url = "http://127.0.0.1:8888/api/jbrowse2/fake-tracks-server.php?file={$track['format']}/{$file_name}";
    
    try {
        $token = generateTrackToken($organism, $assembly, $access_level);
        $track_url .= "&token=" . urlencode($token);
    } catch (Exception $e) {
        error_log("Failed to generate token for track: " . $e->getMessage());
        continue;
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

// 7. RETURN CONFIG
echo json_encode($assembly_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
