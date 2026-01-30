<?php
/**
 * Shared image upload handler for admin pages
 * 
 * Usage in your admin page:
 *   include_once __DIR__ . '/api/handle_image_upload.php';
 *   handleImageUpload($config);
 * 
 * POST parameters:
 *   - upload_image: 'true' (string)
 *   - image_file: file upload
 * 
 * Returns JSON:
 *   - success: true/false
 *   - filename: uploaded filename (on success)
 *   - error: error message (on failure)
 */

function handleImageUpload($config) {
    if (!isset($_POST['upload_image']) || $_POST['upload_image'] !== 'true' || !isset($_FILES['image_file'])) {
        return false;
    }
    
    header('Content-Type: application/json');
    
    $upload_dir = $config->getPath('absolute_images_path');
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    $file = $_FILES['image_file'];
    
    // Validate upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $file['error']]);
        exit;
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'File too large (max 5MB)']);
        exit;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP']);
        exit;
    }
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = pathinfo($file['name'], PATHINFO_FILENAME) . '.' . $ext;
    $original_filename = $filename;
    $counter = 1;
    $filepath = $upload_dir . '/' . $filename;
    
    // If file exists, add counter
    while (file_exists($filepath)) {
        $filename = pathinfo($original_filename, PATHINFO_FILENAME) . '_' . $counter . '.' . $ext;
        $filepath = $upload_dir . '/' . $filename;
        $counter++;
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        chmod($filepath, 0664);
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    }
    exit;
}
?>
