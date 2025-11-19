<?php
/**
 * MOOP Display Functions
 * Organism display, image handling, and presentation utilities
 */

/**
 * Load organism info and get image path
 * 
 * Loads organism.json file and returns the image path using getOrganismImagePath()
 * Encapsulates all the loading logic in one place.
 * 
 * @param string $organism_name The organism name
 * @param string $images_path URL path to images directory (e.g., 'moop/images')
 * @param string $absolute_images_path Absolute file system path to images directory
 * @return array ['organism_info' => array, 'image_path' => string]
 */
function loadOrganismAndGetImagePath($organism_name, $images_path = 'moop/images', $absolute_images_path = '') {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    
    $result = [
        'organism_info' => [],
        'image_path' => ''
    ];
    
    $organism_json_path = "$organism_data/$organism_name/organism.json";
    if (file_exists($organism_json_path)) {
        $organism_info = json_decode(file_get_contents($organism_json_path), true);
        if ($organism_info) {
            $result['organism_info'] = $organism_info;
            $result['image_path'] = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
        }
    }
    
    return $result;
}

/**
 * Get organism image file path
 * 
 * Returns the URL path to an organism's image with fallback logic:
 * 1. Custom image from organism.json if defined
 * 2. NCBI taxonomy image if taxon_id exists and image file found
 * 3. Empty string if no image available
 * 
 * @param array $organism_info Array from organism.json with keys: images, taxon_id
 * @param string $images_path URL path to images directory (e.g., 'moop/images')
 * @param string $absolute_images_path Absolute file system path to images directory
 * @return string URL path to image file or empty string if no image
 */
function getOrganismImagePath($organism_info, $images_path = 'moop/images', $absolute_images_path = '') {
    // Validate input
    if (empty($organism_info) || !is_array($organism_info)) {
        logError('getOrganismImagePath received invalid organism_info', 'organism_image', [
            'organism_info_type' => gettype($organism_info),
            'organism_info_empty' => empty($organism_info)
        ]);
        return '';
    }
    
    // Check for custom image first
    if (!empty($organism_info['images']) && is_array($organism_info['images'])) {
        return "/$images_path/" . htmlspecialchars($organism_info['images'][0]['file']);
    }
    
    // Fall back to NCBI taxonomy image if taxon_id exists
    if (!empty($organism_info['taxon_id'])) {
        // Construct path - use absolute_images_path if provided, otherwise fall back
        if (!empty($absolute_images_path)) {
            $ncbi_image_file = "$absolute_images_path/ncbi_taxonomy/" . $organism_info['taxon_id'] . '.jpg';
        } else {
            $ncbi_image_file = __DIR__ . '/../../images/ncbi_taxonomy/' . $organism_info['taxon_id'] . '.jpg';
        }
        
        if (file_exists($ncbi_image_file)) {
            return "/moop/images/ncbi_taxonomy/" . $organism_info['taxon_id'] . ".jpg";
        } else {
            logError('NCBI taxonomy image not found', 'organism_image', [
                'taxon_id' => $organism_info['taxon_id'],
                'expected_path' => $ncbi_image_file
            ]);
        }
    }
    
    return '';
}

/**
 * Get organism image caption with optional link
 * 
 * Returns display caption for organism image:
 * - Custom images: caption from organism.json or empty string
 * - NCBI taxonomy fallback: "Image from NCBI Taxonomy" with link to NCBI
 * 
 * @param array $organism_info Array from organism.json with keys: images, taxon_id
 * @param string $absolute_images_path Absolute file system path to images directory
 * @return array ['caption' => caption text, 'link' => URL or empty string]
 */
function getOrganismImageCaption($organism_info, $absolute_images_path = '') {
    $result = [
        'caption' => '',
        'link' => ''
    ];
    
    // Validate input
    if (empty($organism_info) || !is_array($organism_info)) {
        logError('getOrganismImageCaption received invalid organism_info', 'organism_image', [
            'organism_info_type' => gettype($organism_info),
            'organism_info_empty' => empty($organism_info)
        ]);
        return $result;
    }
    
    // Custom image caption
    if (!empty($organism_info['images']) && is_array($organism_info['images'])) {
        if (!empty($organism_info['images'][0]['caption'])) {
            $result['caption'] = $organism_info['images'][0]['caption'];
        }
        return $result;
    }
    
    // NCBI taxonomy caption with link
    if (!empty($organism_info['taxon_id'])) {
        // Construct path - use absolute_images_path if provided, otherwise fall back
        if (!empty($absolute_images_path)) {
            $ncbi_image_file = "$absolute_images_path/ncbi_taxonomy/" . $organism_info['taxon_id'] . '.jpg';
        } else {
            $ncbi_image_file = __DIR__ . '/../../images/ncbi_taxonomy/' . $organism_info['taxon_id'] . '.jpg';
        }
        
        if (file_exists($ncbi_image_file)) {
            $result['caption'] = 'Image from NCBI Taxonomy';
            $result['link'] = 'https://www.ncbi.nlm.nih.gov/datasets/taxonomy/' . htmlspecialchars($organism_info['taxon_id']);
        }
    }
    
    return $result;
}

/**
 * Validate organism.json file
 * 
 * Checks:
 * - File exists
 * - File is readable
 * - Valid JSON format
 * - Contains required fields (genus, species, common_name, taxon_id)
 * 
 * @param string $json_path - Path to organism.json file
 * @return array - Validation results with status and details
 */
function validateOrganismJson($json_path) {
    $validation = [
        'exists' => false,
        'readable' => false,
        'writable' => false,
        'valid_json' => false,
        'has_required_fields' => false,
        'required_fields' => ['genus', 'species', 'common_name', 'taxon_id'],
        'missing_fields' => [],
        'errors' => []
    ];
    
    if (!file_exists($json_path)) {
        $validation['errors'][] = 'organism.json file does not exist';
        return $validation;
    }
    
    $validation['exists'] = true;
    
    if (!is_readable($json_path)) {
        $validation['errors'][] = 'organism.json file is not readable';
        return $validation;
    }
    
    $validation['readable'] = true;
    $validation['writable'] = is_writable($json_path);
    
    $content = file_get_contents($json_path);
    $json_data = json_decode($content, true);
    
    if ($json_data === null) {
        $validation['errors'][] = 'organism.json contains invalid JSON: ' . json_last_error_msg();
        return $validation;
    }
    
    $validation['valid_json'] = true;
    
    // Handle wrapped JSON (single-level wrapping)
    if (!isset($json_data['genus']) && !isset($json_data['common_name'])) {
        $keys = array_keys($json_data);
        if (count($keys) > 0 && is_array($json_data[$keys[0]])) {
            $json_data = $json_data[$keys[0]];
        }
    }
    
    // Check for required fields
    foreach ($validation['required_fields'] as $field) {
        if (!isset($json_data[$field]) || empty($json_data[$field])) {
            $validation['missing_fields'][] = $field;
        }
    }
    
    $validation['has_required_fields'] = empty($validation['missing_fields']);
    
    if (!$validation['has_required_fields']) {
        $validation['errors'][] = 'Missing required fields: ' . implode(', ', $validation['missing_fields']);
    }
    
    return $validation;
}

/**
 * Complete setup for organism display pages
 * Validates parameter, loads organism info, checks access, returns context
 * Use this to replace boilerplate in organism_display, assembly_display, parent_display
 * 
 * @param string $organism_name Organism from GET/POST
 * @param string $organism_data_dir Path to organism data directory
 * @param bool $check_access Whether to check access control (default: true)
 * @param string $redirect_home Home URL for redirects (default: /moop/index.php)
 * @return array Array with 'name' and 'info' keys, or exits on error
 */
function setupOrganismDisplayContext($organism_name, $organism_data_dir, $check_access = true, $redirect_home = '/moop/index.php') {
    // Validate organism parameter
    $organism_name = validateOrganismParam($organism_name, $redirect_home);
    
    // Load and validate organism info
    $organism_info = loadOrganismInfo($organism_name, $organism_data_dir);
    
    if (!$organism_info) {
        header("Location: $redirect_home");
        exit;
    }
    
    // Check access (unless it's public)
    if ($check_access) {
        $is_public = is_public_organism($organism_name);
        if (!$is_public) {
            require_access('Collaborator', $organism_name);
        }
    }
    
    return [
        'name' => $organism_name,
        'info' => $organism_info
    ];
}
