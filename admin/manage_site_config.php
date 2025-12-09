<?php
/**
 * MANAGE SITE CONFIGURATION - Wrapper
 * 
 * Handles admin access verification and renders site configuration
 * management using clean architecture layout system.
 */

// Handle banner operations via AJAX BEFORE including admin_init (which includes navbar/HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle banner deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_banner') {
        // Minimal init just for config
        include_once __DIR__ . '/../includes/ConfigManager.php';
        $config = ConfigManager::getInstance();
        $config->initialize(__DIR__ . '/../config/site_config.php', __DIR__ . '/../config/tools_config.php');
        
        $banners_path = $config->getPath('absolute_images_path') . '/banners';
        $filename = $_POST['filename'] ?? '';
        
        header('Content-Type: application/json');
        
        if (!empty($filename) && preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            $file_path = $banners_path . '/' . basename($filename);
            if (file_exists($file_path) && is_file($file_path)) {
                if (unlink($file_path)) {
                    echo json_encode(['success' => true, 'message' => 'Banner deleted']);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
                    exit;
                }
            }
        }
        echo json_encode(['success' => false, 'message' => 'Invalid file']);
        exit;
    }
    
    // Handle banner upload via AJAX
    if (isset($_POST['action']) && $_POST['action'] === 'upload_banner') {
        // Minimal init just for config
        include_once __DIR__ . '/../includes/ConfigManager.php';
        $config = ConfigManager::getInstance();
        $config->initialize(__DIR__ . '/../config/site_config.php', __DIR__ . '/../config/tools_config.php');
        
        $banners_path = $config->getPath('absolute_images_path') . '/banners';
        
        // Create banners directory if it doesn't exist
        if (!is_dir($banners_path)) {
            @mkdir($banners_path, 0775, true);
        }
        
        header('Content-Type: application/json');
        
        if (!isset($_FILES['banner_file'])) {
            echo json_encode(['success' => false, 'message' => 'No file provided']);
            exit;
        }
        
        $file = $_FILES['banner_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_msg = match($file['error']) {
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
                default => 'Unknown upload error'
            };
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit;
        }
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file
        if (!in_array($file_ext, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)]);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'File too large. Maximum: 5MB']);
            exit;
        }
        
        // Get image dimensions
        $img_info = @getimagesize($file['tmp_name']);
        if ($img_info === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid image file']);
            exit;
        }
        
        $width = $img_info[0];
        $height = $img_info[1];
        
        if ($width < 1200 || $width > 4000 || $height < 200 || $height > 500) {
            echo json_encode(['success' => false, 'message' => "Image dimensions must be 1200-4000px wide and 200-500px tall. Your image: {$width}x{$height}"]);
            exit;
        }
        
        // Use original filename, sanitize it
        $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $base_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name);
        $filename = $base_name . '.' . $file_ext;
        $destination = $banners_path . '/' . $filename;
        
        // Handle filename collision
        $counter = 1;
        while (file_exists($destination)) {
            $filename = $base_name . '_' . $counter . '.' . $file_ext;
            $destination = $banners_path . '/' . $filename;
            $counter++;
        }
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo json_encode(['success' => true, 'message' => 'Banner uploaded successfully', 'filename' => $filename]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file. Check directory permissions.']);
            exit;
        }
    }
}

// Now include admin_init for regular page loads
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

$siteTitle = $config->getString('siteTitle');
$site = $config->getString('site');

$config_dir = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
$banners_path = $config->getPath('absolute_images_path') . '/banners';

// Get paths from config for modal display
$root_path = $config->getPath('root_path');
$site_path = $config->getPath('site_path');
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
        // Prepare data
        $data = [
            'siteTitle' => $_POST['siteTitle'] ?? '',
            'admin_email' => $_POST['admin_email'] ?? '',
            'header_img' => $_POST['header_img'] ?? '',
            'favicon_filename' => $_POST['favicon_filename'] ?? '',
        ];
        
        // Parse sequence types from form
        if (isset($_POST['sequence_types']) && is_array($_POST['sequence_types'])) {
            $sequence_types = [];
            foreach ($_POST['sequence_types'] as $seq_type => $seq_data) {
                if (!empty($seq_data['enabled'])) {
                    $sequence_types[$seq_type] = [
                        'pattern' => $seq_data['pattern'] ?? '',
                        'label' => $seq_data['label'] ?? $seq_type,
                        'color' => $seq_data['color'] ?? 'bg-secondary',
                    ];
                }
            }
            $data['sequence_types'] = $sequence_types;
        }
        
        // Parse IP ranges from form
        if (isset($_POST['auto_login_ip_ranges']) && is_array($_POST['auto_login_ip_ranges'])) {
            $ip_ranges = [];
            foreach ($_POST['auto_login_ip_ranges'] as $range) {
                if (!empty($range['start']) && !empty($range['end'])) {
                    $ip_ranges[] = [
                        'start' => trim($range['start']),
                        'end' => trim($range['end']),
                    ];
                }
            }
            // Only include in data if there are actual IP ranges
            if (!empty($ip_ranges)) {
                $data['auto_login_ip_ranges'] = $ip_ranges;
            }
        }
        
        // Parse BLAST sample sequences from form
        if (isset($_POST['blast_sample_sequences']) && is_array($_POST['blast_sample_sequences'])) {
            $sample_sequences = [];
            $has_protein = !empty(trim($_POST['blast_sample_sequences']['protein'] ?? ''));
            $has_nucleotide = !empty(trim($_POST['blast_sample_sequences']['nucleotide'] ?? ''));
            
            // Require at least one sequence
            if ($has_protein || $has_nucleotide) {
                if ($has_protein) {
                    $sample_sequences['protein'] = trim($_POST['blast_sample_sequences']['protein']);
                }
                if ($has_nucleotide) {
                    $sample_sequences['nucleotide'] = trim($_POST['blast_sample_sequences']['nucleotide']);
                }
                $data['blast_sample_sequences'] = $sample_sequences;
            } else {
                $error = 'BLAST sample sequences cannot be empty. Please provide at least one sample sequence.';
            }
        }
        
        // Parse sample feature IDs from form
        if (isset($_POST['sample_feature_ids']) && !empty(trim($_POST['sample_feature_ids']))) {
            $ids_text = trim($_POST['sample_feature_ids']);
            $ids = array_filter(array_map('trim', explode("\n", $ids_text)));
            
            if (!empty($ids)) {
                $data['sample_feature_ids'] = $ids;
            } else {
                $error = 'Sample feature IDs cannot be empty. Please provide at least one ID.';
            }
        } elseif (empty($error)) {
            $error = 'Sample feature IDs cannot be empty. Please provide at least one ID.';
        }
        
        // Handle file upload for header image
        if (isset($_FILES['header_upload']) && $_FILES['header_upload']['error'] == UPLOAD_ERR_OK) {
            $banners_path = $config->getPath('absolute_images_path') . '/banners';
            
            // Create banners directory if it doesn't exist
            if (!is_dir($banners_path)) {
                @mkdir($banners_path, 0775, true);
            }
            
            $file = $_FILES['header_upload'];
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file
            if (!in_array($file_ext, $allowed_types)) {
                $error = "Invalid file type. Allowed: " . implode(', ', $allowed_types);
            } elseif ($file['size'] > $max_size) {
                $error = "File too large. Maximum: 5MB";
            } else {
                // Get image dimensions
                $img_info = @getimagesize($file['tmp_name']);
                if ($img_info === false) {
                    $error = "Invalid image file";
                } else {
                    $width = $img_info[0];
                    $height = $img_info[1];
                    
                    if ($width < 1200 || $width > 4000 || $height < 200 || $height > 500) {
                        $error = "Image dimensions must be 1200-4000px wide and 200-500px tall. Your image: {$width}x{$height}";
                    } else {
                        // Upload successful
                        $filename = 'header_img.' . $file_ext;
                        $destination = $banners_path . '/' . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            $data['header_img'] = $filename;
                        } else {
                            $error = "Failed to save uploaded file. Check directory permissions.";
                        }
                    }
                }
            }
        }
        
        // Handle file upload for favicon
        if (isset($_FILES['favicon_upload']) && $_FILES['favicon_upload']['error'] == UPLOAD_ERR_OK) {
            $images_path = $config->getPath('absolute_images_path');
            
            $file = $_FILES['favicon_upload'];
            $allowed_types = ['ico', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
            $max_size = 1 * 1024 * 1024; // 1MB
            
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file
            if (!in_array($file_ext, $allowed_types)) {
                $error = "Invalid favicon file type. Allowed: " . implode(', ', $allowed_types);
            } elseif ($file['size'] > $max_size) {
                $error = "Favicon file too large. Maximum: 1MB";
            } else {
                // For favicon, check dimensions if it's an image
                if (in_array($file_ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    $img_info = @getimagesize($file['tmp_name']);
                    if ($img_info === false) {
                        $error = "Invalid image file";
                    } else {
                        $width = $img_info[0];
                        $height = $img_info[1];
                        
                        if ($width < 16 || $width > 256 || $height < 16 || $height > 256) {
                            $error = "Favicon dimensions must be 16-256px. Your image: {$width}x{$height}";
                        } else {
                            // Upload successful
                            $filename = 'favicon.' . $file_ext;
                            $destination = $images_path . '/' . $filename;
                            
                            if (move_uploaded_file($file['tmp_name'], $destination)) {
                                $data['favicon_filename'] = $filename;
                            } else {
                                $error = "Failed to save uploaded favicon. Check directory permissions.";
                            }
                        }
                    }
                } else {
                    // For .ico files, just accept them
                    $filename = 'favicon.' . $file_ext;
                    $destination = $images_path . '/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $data['favicon_filename'] = $filename;
                    } else {
                        $error = "Failed to save uploaded favicon. Check directory permissions.";
                    }
                }
            }
        }
        
        // Save if no upload error
        if (empty($error)) {
            $result = $config->saveEditableConfig($data, $config_dir);
            
            if ($result['success']) {
                $message = $result['message'];
                // Reload config to show updated values
                $editable_config = $config->getEditableConfigMetadata();
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get editable config metadata
$editable_config = $config->getEditableConfigMetadata();

// Get list of banner images in banners directory
$banner_images = [];
if (is_dir($banners_path)) {
    $files = scandir($banners_path);
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    foreach ($files as $file) {
        if ($file[0] === '.') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $banner_images[] = $file;
        }
    }
    sort($banner_images);
}

// Check file permissions
$config_file = $config_dir . '/config_editable.json';
$file_writable = is_writable($config_file);
$file_write_error = '';
if (!$file_writable && file_exists($config_file)) {
    $file_write_error = getFileWriteError($config_file);
}

// Configure display
$display_config = [
    'title' => 'Manage Site Configuration - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_site_config.php',
];

// Prepare data for content file
$data = [
    'config' => $config,
    'message' => $message,
    'error' => $error,
    'file_write_error' => $file_write_error,
    'file_writable' => $file_writable,
    'editable_config' => $editable_config,
    'banner_images' => $banner_images,
    'banners_path' => $banners_path,
    'root_path' => $root_path,
    'site' => $site,
    'site_path' => $site_path,
    'organism_data' => $organism_data,
    'metadata_path' => $metadata_path,
    'config_file' => $config_file,
    'page_styles' => [
        '/' . $site . '/admin/css/manage-site-config.css'
    ],
    'page_script' => [
        '/' . $site . '/js/admin-utilities.js',
        '/' . $site . '/js/modules/manage-site-config.js'
    ],
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
