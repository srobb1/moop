<?php
/**
 * /api/jbrowse2/get-config.php
 *
 * Returns JBrowse2 configuration dynamically based on user authentication
 * Filters assemblies by user's access level
 *
 * GET /api/jbrowse2/get-config.php                  - Returns all user-accessible assemblies
 * GET /api/jbrowse2/get-config.php?assembly=NAME   - Returns config for specific assembly
 *
 * Returns JSON config with only assemblies the user can access:
 * - Anonymous users: Public assemblies only
 * - Logged-in users: Public + Collaborator + user's level
 * - Admins: All assemblies
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Get current user and their access level
// This integrates with your existing auth system
session_start();

// Determine user access level
$user_access_level = 'Public'; // Default for anonymous

if (isset($_SESSION['user_id'])) {
    // User is logged in - get their access level from session or database
    $user_access_level = $_SESSION['access_level'] ?? 'Collaborator';
    
    // If user is admin, they see everything
    if ($_SESSION['is_admin'] ?? false) {
        $user_access_level = 'ALL';
    }
}

// Get optional assembly filter parameter
$requested_assembly = $_GET['assembly'] ?? null;

// Base configuration
$config = [
    'assemblies' => [],
    'configuration' => [],
    'connections' => [],
    'defaultSession' => [
        'name' => 'New Session'
    ],
    'tracks' => [],
    'userAccessLevel' => $user_access_level
];

// Load all assembly definitions from metadata
$metadata_path = '/data/moop/metadata/jbrowse2-configs/assemblies';

if (!is_dir($metadata_path)) {
    http_response_code(500);
    echo json_encode(['error' => "Assembly metadata directory not found: $metadata_path"]);
    exit;
}

$assembly_files = glob("$metadata_path/*.json");

foreach ($assembly_files as $file) {
    $assembly_def = json_decode(file_get_contents($file), true);
    
    if (!$assembly_def) {
        continue;
    }
    
    // If a specific assembly was requested, skip others
    if ($requested_assembly && $assembly_def['name'] !== $requested_assembly) {
        continue;
    }
    
    // Check if user can access this assembly
    $assembly_access_level = $assembly_def['defaultAccessLevel'] ?? 'Public';
    
    // Determine if user can access this assembly
    $user_can_access = false;
    
    if ($user_access_level === 'ALL') {
        // Admin sees everything
        $user_can_access = true;
    } elseif ($assembly_access_level === 'Public') {
        // Anyone can see public
        $user_can_access = true;
    } elseif ($user_access_level === 'Collaborator' && $assembly_access_level === 'Collaborator') {
        // Collaborators can see collaborator assemblies
        $user_can_access = true;
    } elseif ($user_access_level === 'Collaborator' && $assembly_access_level === 'Public') {
        // Collaborators can see public too
        $user_can_access = true;
    }
    
    // If user cannot access, skip this assembly
    if (!$user_can_access) {
        continue;
    }
    
    // Add to config
    $config['assemblies'][] = [
        'name' => $assembly_def['name'],
        'displayName' => $assembly_def['displayName'],
        'aliases' => $assembly_def['aliases'] ?? [],
        'accessLevel' => $assembly_def['defaultAccessLevel'],
        'sequence' => $assembly_def['sequence'] ?? null
    ];
}

// If a specific assembly was requested, return just that assembly's full config
if ($requested_assembly && !empty($config['assemblies'])) {
    $config = $config['assemblies'][0];
}

echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
