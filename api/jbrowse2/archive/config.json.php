<?php
/**
 * Dynamic JBrowse2 config.json endpoint
 * 
 * Serves JBrowse2 configuration based on user authentication
 * and available assemblies/tracks
 */

include_once __DIR__ . '/../../../includes/access_control.php';
include_once __DIR__ . '/../../../lib/moop_functions.php';
include_once __DIR__ . '/../../../includes/config_init.php';

// Get user access level
$access_level = get_access_level();
$username = get_username();

// Get available assemblies
$assemblies = [];
$config_dir = __DIR__ . '/../../../metadata/jbrowse2-configs/assemblies';

if (is_dir($config_dir)) {
    $files = glob($config_dir . '/*.json');
    foreach ($files as $file) {
        $assembly_config = json_decode(file_get_contents($file), true);
        if ($assembly_config && isset($assembly_config['accessLevel'])) {
            // Include assembly if user has access
            if ($assembly_config['accessLevel'] === 'PUBLIC' || 
                $access_level === 'ADMIN' || 
                $access_level === 'IP_IN_RANGE' ||
                $access_level === 'COLLABORATOR') {
                $assemblies[] = $assembly_config;
            }
        }
    }
}

// Build JBrowse2 config
$config = [
    'configVersion' => 3,
    'assemblies' => $assemblies,
    'plugins' => [],
    'defaultSession' => [
        'name' => 'default',
        'views' => []
    ],
    'tracks' => []
];

// Set proper headers for JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
