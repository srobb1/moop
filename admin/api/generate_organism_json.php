<?php
/**
 * API endpoint to auto-generate organism.json files
 * Fetches data from NCBI based on organism directory name (Genus_species format)
 */

include_once __DIR__ . '/../admin_init.php';

// Only allow POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

header('Content-Type: application/json');

$organism_data_dir = $config->getPath('organism_data');
$count = 0;
$errors = [];

// Get organisms missing organism.json
$organisms_missing_json = [];
if (is_dir($organism_data_dir)) {
    foreach (scandir($organism_data_dir) as $item) {
        if ($item !== '.' && $item !== '..' && is_dir("$organism_data_dir/$item")) {
            $json_file = "$organism_data_dir/$item/organism.json";
            if (!file_exists($json_file)) {
                $organisms_missing_json[] = $item;
            }
        }
    }
}

// Generate organism.json for each missing organism
foreach ($organisms_missing_json as $organism_name) {
    // Parse organism name: Genus_species
    $parts = explode('_', $organism_name);
    
    if (count($parts) < 2) {
        $errors[] = "$organism_name: Invalid format (expected Genus_species)";
        continue;
    }
    
    $genus = $parts[0];
    $species = $parts[1];
    
    // Fetch data from NCBI
    $ncbi_data = fetchOrganismInfoFromNCBI($genus, $species);
    
    if (!empty($ncbi_data['error'])) {
        $errors[] = "$organism_name: " . $ncbi_data['error'];
        continue;
    }
    
    // Create organism.json
    $organism_json = [
        'genus' => $genus,
        'species' => $species,
        'common_name' => $ncbi_data['common_name'] ?: $organism_name,
        'taxon_id' => $ncbi_data['taxon_id']
    ];
    
    $json_file = "$organism_data_dir/$organism_name/organism.json";
    $json_content = json_encode($organism_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($json_file, $json_content) !== false) {
        $count++;
    } else {
        $errors[] = "$organism_name: Could not write to file";
    }
    
    // Be nice to NCBI
    usleep(500000); // 0.5 second delay between requests
}

echo json_encode([
    'success' => true,
    'count' => $count,
    'errors' => $errors
]);
?>
