<?php
/**
 * JBrowse Admin API: List Tracks
 * 
 * DataTables server-side endpoint for track listing.
 * Returns paginated, filtered, searchable track data.
 */

require_once __DIR__ . '/../../includes/config_init.php';
require_once __DIR__ . '/../../admin/admin_access_check.php';

header('Content-Type: application/json');

// Get DataTables parameters
$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 20);
$searchValue = $_POST['search']['value'] ?? '';

// Get filter parameters
$filterOrganism = $_POST['organism'] ?? '';
$filterAssembly = $_POST['assembly'] ?? '';
$filterType = $_POST['type'] ?? '';
$filterAccess = $_POST['access'] ?? '';

$config = ConfigManager::getInstance();
$tracks_dir = $config->getPath('metadata_path') . '/jbrowse2-configs/tracks';

// Collect all tracks
$allTracks = [];

if (is_dir($tracks_dir)) {
    $pattern = $tracks_dir . '/*/*/*/*json';
    $trackFiles = glob($pattern);
    
    foreach ($trackFiles as $file) {
        $track = json_decode(file_get_contents($file), true);
        if (!$track) continue;
        
        // Extract organism and assembly from path
        $pathParts = explode('/', str_replace($tracks_dir . '/', '', $file));
        $organism = $pathParts[0] ?? '';
        $assembly = $pathParts[1] ?? '';
        
        // Apply filters
        if ($filterOrganism && $organism !== $filterOrganism) continue;
        if ($filterAssembly && $assembly !== $filterAssembly) continue;
        if ($filterType && ($track['type'] ?? '') !== $filterType) continue;
        if ($filterAccess && ($track['metadata']['access_level'] ?? 'PUBLIC') !== $filterAccess) continue;
        
        // Apply search
        if ($searchValue) {
            $searchIn = strtolower(json_encode([
                $track['trackId'] ?? '',
                $track['name'] ?? '',
                $track['metadata']['description'] ?? ''
            ]));
            if (strpos($searchIn, strtolower($searchValue)) === false) continue;
        }
        
        $allTracks[] = [
            'file' => $file,
            'track' => $track,
            'organism' => $organism,
            'assembly' => $assembly
        ];
    }
}

// Sort by name
usort($allTracks, function($a, $b) {
    return strcasecmp($a['track']['name'] ?? '', $b['track']['name'] ?? '');
});

// Pagination
$totalRecords = count($allTracks);
$paginatedTracks = array_slice($allTracks, $start, $length);

// Format for DataTables
$data = [];
foreach ($paginatedTracks as $item) {
    $track = $item['track'];
    $organism = $item['organism'];
    $assembly = $item['assembly'];
    
    $trackId = $track['trackId'] ?? '';
    $name = $track['name'] ?? 'Unknown';
    $type = $track['type'] ?? 'Unknown';
    $access = $track['metadata']['access_level'] ?? 'PUBLIC';
    
    // Determine source and validate
    $source = 'Local';
    $status = '✓';
    $statusClass = 'success';
    
    // Check for remote URLs
    $uri = null;
    if (isset($track['adapter']['gffGzLocation']['uri'])) {
        $uri = $track['adapter']['gffGzLocation']['uri'];
    } elseif (isset($track['adapter']['bigWigLocation']['uri'])) {
        $uri = $track['adapter']['bigWigLocation']['uri'];
    } elseif (isset($track['adapter']['bamLocation']['uri'])) {
        $uri = $track['adapter']['bamLocation']['uri'];
    } elseif (isset($track['adapter']['vcfGzLocation']['uri'])) {
        $uri = $track['adapter']['vcfGzLocation']['uri'];
    }
    
    if ($uri) {
        if (strpos($uri, 'http://') === 0 || strpos($uri, 'https://') === 0) {
            $source = 'Remote';
            
            // Check for public sources
            $publicBases = ['ucsc.edu', 'ensembl.org', 'ncbi.nlm.nih.gov'];
            foreach ($publicBases as $base) {
                if (strpos($uri, $base) !== false) {
                    $source = strtoupper(explode('.', $base)[0]);
                    
                    // Warning if public source with non-public access
                    if ($access !== 'PUBLIC') {
                        $status = '⚠️';
                        $statusClass = 'warning';
                    }
                    break;
                }
            }
        }
    }
    
    // Access badge color
    $accessColors = [
        'PUBLIC' => 'success',
        'COLLABORATOR' => 'primary',
        'IP_IN_RANGE' => 'warning',
        'ADMIN' => 'danger'
    ];
    $accessColor = $accessColors[$access] ?? 'secondary';
    
    $data[] = [
        'checkbox' => '<input type="checkbox" name="trackSelect" value="' . htmlspecialchars($trackId) . '" data-organism="' . htmlspecialchars($organism) . '" data-assembly="' . htmlspecialchars($assembly) . '" onchange="updateBulkButtons()">',
        'name' => '<strong>' . htmlspecialchars($name) . '</strong><br><small class="text-muted">' . htmlspecialchars($trackId) . '</small>',
        'organism' => htmlspecialchars($organism),
        'assembly' => htmlspecialchars($assembly),
        'type' => '<span class="badge bg-secondary">' . htmlspecialchars($type) . '</span>',
        'access' => '<span class="badge bg-' . $accessColor . '">' . htmlspecialchars($access) . '</span>',
        'status' => '<span class="badge bg-' . $statusClass . '">' . $status . '</span> ' . htmlspecialchars($source),
        'actions' => '
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-sm" onclick="viewTrack(\'' . htmlspecialchars($trackId, ENT_QUOTES) . '\', \'' . htmlspecialchars($organism, ENT_QUOTES) . '\', \'' . htmlspecialchars($assembly, ENT_QUOTES) . '\')" title="View Details">
                    <i class="fa fa-eye"></i>
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteTrack(\'' . htmlspecialchars($trackId, ENT_QUOTES) . '\', \'' . htmlspecialchars($organism, ENT_QUOTES) . '\', \'' . htmlspecialchars($assembly, ENT_QUOTES) . '\')" title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        '
    ];
}

// Return DataTables response
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalRecords,
    'data' => $data
]);
