<?php
/**
 * MOOP Display Functions
 * Organism display, image handling, and presentation utilities
 */

/**
 * Load organism info and get image path
 * 
 * Combines organism.json loading with image path resolution.
 * Uses loadOrganismInfo() for JSON loading and getOrganismImagePath() for image logic.
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
    
    // Use consolidated loadOrganismInfo() instead of manual JSON loading
    $organism_info = loadOrganismInfo($organism_name, $organism_data);
    if ($organism_info) {
        $result['organism_info'] = $organism_info;
        $result['image_path'] = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
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
 * Get organism image with path and caption
 * 
 * Convenience function combining getOrganismImagePath() and getOrganismImageCaption()
 * Used when both image URL and caption are needed (common display pattern).
 * 
 * @param array $organism_info Array from organism.json with keys: images, taxon_id
 * @param string $images_path URL path to images directory (e.g., 'moop/images')
 * @param string $absolute_images_path Absolute file system path to images directory
 * @return array ['image_path' => string, 'caption' => string, 'link' => string or empty]
 */
function getOrganismImageWithCaption($organism_info, $images_path = 'moop/images', $absolute_images_path = '') {
    $image_path = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
    $image_info = getOrganismImageCaption($organism_info, $absolute_images_path);
    
    return [
        'image_path' => $image_path,
        'caption' => $image_info['caption'],
        'link' => $image_info['link']
    ];
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
        'errors' => [],
        'owner' => null,
        'perms' => null,
        'web_user' => null,
        'web_group' => null
    ];
    
    if (!file_exists($json_path)) {
        $validation['errors'][] = 'organism.json file does not exist';
        return $validation;
    }
    
    $validation['exists'] = true;
    
    // Get file ownership and permissions
    if (@is_readable($json_path) || @file_exists($json_path)) {
        $perms = @fileperms($json_path);
        if ($perms !== false) {
            $validation['perms'] = substr(sprintf('%o', $perms), -3);
        }
        $owner = @posix_getpwuid(@fileowner($json_path));
        if ($owner !== false) {
            $validation['owner'] = $owner['name'] ?? 'unknown';
        }
    }
    
    // Get web server user/group
    $current_user = get_current_user();
    if ($current_user) {
        $validation['web_user'] = $current_user;
        $group_info = @posix_getgrgid(@posix_getegid());
        if ($group_info !== false) {
            $validation['web_group'] = $group_info['name'] ?? 'www-data';
        }
    }
    
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

/**
 * Fetch and cache organism image from NCBI to ncbi_taxonomy directory
 * 
 * Downloads organism images from NCBI taxonomy API and caches them locally.
 * Returns the web-accessible image path or null if download fails.
 * 
 * @param int $taxon_id NCBI Taxonomy ID
 * @param string|null $organism_name Optional organism name (for reference)
 * @param string $absolute_images_path Absolute filesystem path to images directory
 * @return string|null Web path to image (e.g., 'images/ncbi_taxonomy/12345.jpg'), or null if failed
 */
function fetch_organism_image($taxon_id, $organism_name = null, $absolute_images_path = null) {
    if ($absolute_images_path === null) {
        $config = ConfigManager::getInstance();
        $absolute_images_path = $config->getPath('absolute_images_path');
    }
    
    $ncbi_dir = $absolute_images_path . '/ncbi_taxonomy';
    $image_path = $ncbi_dir . '/' . $taxon_id . '.jpg';
    
    // Check if image already cached
    if (file_exists($image_path)) {
        return 'images/ncbi_taxonomy/' . $taxon_id . '.jpg';
    }
    
    // Ensure directory exists
    if (!is_dir($ncbi_dir)) {
        @mkdir($ncbi_dir, 0755, true);
    }
    
    // Download from NCBI
    $image_url = "https://api.ncbi.nlm.nih.gov/datasets/v2/taxonomy/taxon/{$taxon_id}/image";
    
    $context = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'MOOP']]);
    $image_data = @file_get_contents($image_url, false, $context);
    
    if ($image_data === false || strlen($image_data) < 100) {
        return null;
    }
    
    // Save image
    if (file_put_contents($image_path, $image_data) !== false) {
        return 'images/ncbi_taxonomy/' . $taxon_id . '.jpg';
    }
    
    return null;
}

/**
 * Generate a permission alert HTML for a file or directory
 * 
 * Shows current status (readable, writable) and provides either:
 * 1. A "Fix Permissions" button if web server can fix it automatically
 * 2. Manual fix instructions with commands if web server lacks permissions
 * 
 * @param string $file_path Path to file or directory
 * @param string $title Alert title (e.g., "Metadata File Permission Issue")
 * @param string $problem Description of the problem
 * @param string $file_type Type for AJAX call: 'file' or 'directory'
 * @param string $organism Optional organism name for targeting
 * @return string HTML for the permission alert, empty if no issues
 */
function generatePermissionAlert($file_path, $title = '', $problem = '', $file_type = 'file', $organism = '') {
    // Check current permissions
    $readable = is_readable($file_path);
    $writable = is_writable($file_path);
    
    // If everything is fine, return empty
    if ($readable && $writable) {
        return '';
    }
    
    // Get file/directory info
    $exists = file_exists($file_path);
    if (!$exists) {
        return '';
    }
    
    $is_dir = is_dir($file_path);
    $file_size = $is_dir ? 'directory' : filesize($file_path) . ' bytes';
    $owner = @posix_getpwuid(fileowner($file_path));
    $owner_name = $owner !== false ? $owner['name'] : 'unknown';
    $perms = substr(sprintf('%o', fileperms($file_path)), -3);
    $web_user = get_current_user() ?: 'www-data';
    $web_group_info = @posix_getgrgid(@posix_getegid());
    $web_group = $web_group_info !== false ? $web_group_info['name'] : 'www-data';
    
    // Determine if web server can fix permissions
    $can_fix = is_writable(dirname($file_path)) || is_writable($file_path);
    
    // Determine what's wrong
    $issue = '';
    if (!$readable && !$writable) {
        $issue = 'Cannot read or write';
    } elseif (!$readable) {
        $issue = 'Cannot read (permission denied)';
    } else {
        $issue = 'Cannot write (read-only)';
    }
    
    $safe_path = htmlspecialchars($file_path);
    $safe_title = htmlspecialchars($title ?: $issue);
    $safe_problem = htmlspecialchars($problem);
    $safe_organism = htmlspecialchars($organism);
    
    // Start building alert HTML
    $html = '<div class="alert alert-warning alert-dismissible fade show mb-3">' . "\n";
    $html .= '  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . "\n";
    $html .= '  <h6><i class="fa fa-exclamation-circle"></i> ' . $safe_title . '</h6>' . "\n";
    
    if ($safe_problem) {
        $html .= '  <p class="mb-2"><strong>Problem:</strong> ' . $safe_problem . '</p>' . "\n";
    }
    
    $html .= '  <p class="mb-3"><strong>Current Status:</strong></p>' . "\n";
    $html .= '  <ul class="mb-3 small">' . "\n";
    $html .= '    <li>Path: <code>' . $safe_path . '</code></li>' . "\n";
    $html .= '    <li>Type: ' . ($is_dir ? 'Directory' : 'File') . '</li>' . "\n";
    $html .= '    <li>Owner: <code>' . htmlspecialchars($owner_name) . '</code></li>' . "\n";
    $html .= '    <li>Permissions: <code>' . $perms . '</code></li>' . "\n";
    $html .= '    <li>Readable: <span class="badge ' . ($readable ? 'bg-success' : 'bg-danger') . '">' . ($readable ? '✓ Yes' : '✗ No') . '</span></li>' . "\n";
    $html .= '    <li>Writable: <span class="badge ' . ($writable ? 'bg-success' : 'bg-danger') . '">' . ($writable ? '✓ Yes' : '✗ No') . '</span></li>' . "\n";
    $html .= '    <li>Web server user: <code>' . htmlspecialchars($web_user) . '</code></li>' . "\n";
    $html .= '  </ul>' . "\n";
    
    if ($can_fix) {
        // Web server can fix it - offer button
        $resultId = 'fixResult-' . uniqid();
        $html .= '  <p class="mb-2"><strong>Quick Fix:</strong> Click the button below to attempt automatic fix:</p>' . "\n";
        $html .= '  <button class="btn btn-warning btn-sm" onclick=\'fixFilePermissions(event, ' . json_encode($file_path) . ', ' . json_encode($file_type) . ', ' . json_encode($organism) . ', ' . json_encode($resultId) . ');\'>' . "\n";
        $html .= '    <i class="fa fa-wrench"></i> Fix Permissions' . "\n";
        $html .= '  </button>' . "\n";
        $html .= '  <div id="' . $resultId . '" class="mt-3"></div>' . "\n";
        
        // Show manual instructions as alternative
        $html .= '  <p class="mb-2 mt-4"><strong>Or manually on the server:</strong></p>' . "\n";
        $html .= '  <div style="margin: 10px 0; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">' . "\n";
        $html .= '    <code style="word-break: break-all; display: block; font-size: 0.9em;">' . "\n";
        if ($is_dir) {
            $html .= '      sudo chown -R ' . htmlspecialchars($web_user) . ':' . htmlspecialchars($web_group) . ' ' . $safe_path . '<br>' . "\n";
            $html .= '      sudo chmod -R 775 ' . $safe_path . "\n";
        } else {
            $html .= '      sudo chown ' . htmlspecialchars($web_user) . ':' . htmlspecialchars($web_group) . ' ' . $safe_path . '<br>' . "\n";
            $html .= '      sudo chmod 664 ' . $safe_path . "\n";
        }
        $html .= '    </code>' . "\n";
        $html .= '  </div>' . "\n";
    } else {
        // Web server cannot fix - show manual instructions
        $html .= '  <p class="mb-2"><strong>To Fix:</strong> Run this command on the server:</p>' . "\n";
        $html .= '  <div style="margin: 10px 0; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">' . "\n";
        $html .= '    <code style="word-break: break-all; display: block; font-size: 0.9em;">' . "\n";
        if ($is_dir) {
            $html .= '      sudo chown -R ' . htmlspecialchars($web_user) . ':' . htmlspecialchars($web_group) . ' ' . $safe_path . '<br>' . "\n";
            $html .= '      sudo chmod -R 775 ' . $safe_path . "\n";
        } else {
            $html .= '      sudo chown ' . htmlspecialchars($web_user) . ':' . htmlspecialchars($web_group) . ' ' . $safe_path . '<br>' . "\n";
            $html .= '      sudo chmod 664 ' . $safe_path . "\n";
        }
        $html .= '    </code>' . "\n";
        $html .= '  </div>' . "\n";
    }
    
    $html .= '  <p class="small text-muted mb-0">After fixing permissions, refresh this page.</p>' . "\n";
    $html .= '</div>' . "\n";
    
    return $html;
}

/**
 * Get web server user information
 * 
 * @return array Array with 'user' and 'group' keys
 */
function getWebServerUserInfo() {
    $user = get_current_user() ?: 'www-data';
    $group_info = @posix_getgrgid(@posix_getegid());
    $group = $group_info !== false ? $group_info['name'] : 'www-data';
    
    return ['user' => $user, 'group' => $group];
}
