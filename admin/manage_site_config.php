<?php
include_once __DIR__ . '/admin_init.php';

$config_dir = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
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
            'favicon_path' => $_POST['favicon_path'] ?? '',
        ];
        
        // Parse sequence types from form
        if (isset($_POST['sequence_types']) && is_array($_POST['sequence_types'])) {
            $sequence_types = [];
            foreach ($_POST['sequence_types'] as $seq_type => $seq_data) {
                if (!empty($seq_data['enabled'])) {
                    $sequence_types[$seq_type] = [
                        'pattern' => $seq_data['pattern'] ?? '',
                        'label' => $seq_data['label'] ?? $seq_type,
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

// Check file permissions
$config_file = $config_dir . '/config_editable.json';
$file_writable = is_writable($config_file);
$file_write_error = '';
if (!$file_writable && file_exists($config_file)) {
    $file_write_error = getFileWriteError($config_file);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Site Configuration - Admin</title>
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fa fa-cog"></i> Manage Site Configuration</h2>
            <p class="text-muted">Edit site-wide settings and appearance</p>
            
            <!-- About Section -->
            <div class="card mb-4 border-info">
                <div class="card-header bg-info bg-opacity-10">
                    <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Site Configuration</h5>
                </div>
                <div class="card-body">
                    <p>This page allows you to edit key site settings without modifying configuration files directly.</p>
                    
                    <p><strong>What you can change:</strong></p>
                    <ul>
                        <li><strong>Site Title</strong> - Name displayed in the browser tab, header, and throughout the site</li>
                        <li><strong>Admin Email</strong> - Contact email for site administrators</li>
                    </ul>
                    
                    <p class="mb-0"><small class="text-muted"><i class="fa fa-lock"></i> Structural settings (paths, directories) are not editable to maintain system integrity.</small></p>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Permission Warning -->
            <?php if ($file_write_error): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <h4><i class="fa fa-exclamation-triangle"></i> File Permission Issue</h4>
                    <p><strong>Problem:</strong> The configuration file is not writable by the web server.</p>
                    
                    <p><strong>Current Status:</strong></p>
                    <ul class="mb-3">
                        <li>File: <code><?= htmlspecialchars($config_file) ?></code></li>
                        <li>Owner: <code><?= htmlspecialchars($file_write_error['owner']) ?></code></li>
                        <li>Permissions: <code><?= $file_write_error['perms'] ?></code></li>
                        <li>Web server user: <code><?= htmlspecialchars($file_write_error['web_user']) ?></code></li>
                    </ul>
                    
                    <p><strong>To Fix:</strong> Run this command on the server:</p>
                    <div style="background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd; margin: 10px 0;">
                        <code style="word-break: break-all; display: block; font-size: 0.9em;">
                            <?= htmlspecialchars($file_write_error['command']) ?>
                        </code>
                    </div>
                    
                    <p><small class="text-muted">After running the command, refresh this page.</small></p>
                </div>
            <?php endif; ?>

            <!-- Configuration Form -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fa fa-edit"></i> Edit Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="configForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_config">
                        
                        <!-- Site Title -->
                        <div class="mb-4">
                            <label for="siteTitle" class="form-label">
                                <strong><?= htmlspecialchars($editable_config['siteTitle']['label']) ?></strong>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="siteTitle" 
                                name="siteTitle" 
                                value="<?= htmlspecialchars($editable_config['siteTitle']['current_value']) ?>"
                                maxlength="<?= $editable_config['siteTitle']['max_length'] ?>"
                                required>
                            <small class="form-text text-muted">
                                <?= htmlspecialchars($editable_config['siteTitle']['description']) ?>
                            </small>
                        </div>

                        <!-- Admin Email -->
                        <div class="mb-4">
                            <label for="admin_email" class="form-label">
                                <strong><?= htmlspecialchars($editable_config['admin_email']['label']) ?></strong>
                            </label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="admin_email" 
                                name="admin_email" 
                                value="<?= htmlspecialchars($editable_config['admin_email']['current_value']) ?>"
                                maxlength="<?= $editable_config['admin_email']['max_length'] ?>"
                                required>
                            <small class="form-text text-muted">
                                <?= htmlspecialchars($editable_config['admin_email']['description']) ?>
                            </small>
                        </div>

                        <!-- Sequence Types -->
                        <div class="mb-4">
                            <label class="form-label">
                                <strong><?= htmlspecialchars($editable_config['sequence_types']['label']) ?></strong>
                            </label>
                            <p class="text-muted small mb-3">
                                <?= htmlspecialchars($editable_config['sequence_types']['description']) ?>
                            </p>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 60px;">Enabled</th>
                                            <th style="width: 40%;">Type</th>
                                            <th style="width: 40%;">Display Label</th>
                                            <th style="width: 20%;">File Pattern</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($editable_config['sequence_types']['current_value'] as $seq_type => $seq_config): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       name="sequence_types[<?= htmlspecialchars($seq_type) ?>][enabled]" 
                                                       value="1" 
                                                       class="form-check-input" 
                                                       checked>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($seq_type) ?></code>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="sequence_types[<?= htmlspecialchars($seq_type) ?>][label]" 
                                                       value="<?= htmlspecialchars($seq_config['label'] ?? $seq_type) ?>"
                                                       class="form-control form-control-sm"
                                                       placeholder="Display name">
                                            </td>
                                            <td>
                                                <code class="small"><?= htmlspecialchars($seq_config['pattern'] ?? '') ?></code>
                                                <input type="hidden" 
                                                       name="sequence_types[<?= htmlspecialchars($seq_type) ?>][pattern]" 
                                                       value="<?= htmlspecialchars($seq_config['pattern'] ?? '') ?>">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fa fa-info-circle"></i> <?= htmlspecialchars($editable_config['sequence_types']['note']) ?>
                            </small>
                        </div>

                        <!-- Header Image Upload -->
                        <div class="mb-4">
                            <label class="form-label">
                                <strong><?= htmlspecialchars($editable_config['header_img']['label']) ?></strong>
                            </label>
                            <p class="text-muted small mb-3">
                                <?= htmlspecialchars($editable_config['header_img']['description']) ?>
                            </p>
                            
                            <div class="alert alert-info mb-3">
                                <strong>Recommended:</strong> <?= $editable_config['header_img']['upload_info']['recommended_dimensions'] ?> <br>
                                <strong>Accepted:</strong> <?= implode(', ', array_map('strtoupper', $editable_config['header_img']['upload_info']['allowed_types'])) ?> <br>
                                <strong>Max size:</strong> <?= $editable_config['header_img']['upload_info']['max_size_mb'] ?> MB
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Image:</label>
                                    <code><?= htmlspecialchars($editable_config['header_img']['current_value']) ?></code>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="header_upload" class="form-label">Upload New Image:</label>
                                    <input type="file" class="form-control" id="header_upload" name="header_upload" accept="image/*">
                                    <small class="form-text text-muted">Leave empty to keep current image</small>
                                </div>
                            </div>
                            <input type="hidden" name="header_img" value="<?= htmlspecialchars($editable_config['header_img']['current_value']) ?>">
                        </div>

                        <!-- Favicon Path -->
                        <div class="mb-4">
                            <label for="favicon_path" class="form-label">
                                <strong><?= htmlspecialchars($editable_config['favicon_path']['label']) ?></strong>
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="favicon_path" 
                                name="favicon_path" 
                                value="<?= htmlspecialchars($editable_config['favicon_path']['current_value']) ?>"
                                maxlength="<?= $editable_config['favicon_path']['max_length'] ?>"
                                placeholder="/moop/images/favicon.ico">
                            <small class="form-text text-muted">
                                <?= htmlspecialchars($editable_config['favicon_path']['description']) ?> <br>
                                <?= htmlspecialchars($editable_config['favicon_path']['note']) ?>
                            </small>
                        </div>

                        <!-- Auto-Login IP Ranges -->
                        <div class="mb-4">
                            <label class="form-label">
                                <strong><?= htmlspecialchars($editable_config['auto_login_ip_ranges']['label']) ?></strong>
                            </label>
                            <div class="alert alert-warning mb-3">
                                <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($editable_config['auto_login_ip_ranges']['note']) ?>
                            </div>
                            <p class="text-muted small mb-3">
                                <?= htmlspecialchars($editable_config['auto_login_ip_ranges']['description']) ?>
                            </p>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>IP Range Start</th>
                                            <th>IP Range End</th>
                                            <th style="width: 60px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ip_ranges_tbody">
                                        <?php if (empty($editable_config['auto_login_ip_ranges']['current_value'])): ?>
                                        <tr class="no-ranges-row">
                                            <td colspan="3" class="text-center text-muted"><em>No IP ranges configured</em></td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($editable_config['auto_login_ip_ranges']['current_value'] as $idx => $range): ?>
                                        <tr class="ip-range-row">
                                            <td>
                                                <input type="text" 
                                                       name="auto_login_ip_ranges[<?= $idx ?>][start]" 
                                                       value="<?= htmlspecialchars($range['start']) ?>"
                                                       class="form-control form-control-sm"
                                                       placeholder="192.168.1.0">
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="auto_login_ip_ranges[<?= $idx ?>][end]" 
                                                       value="<?= htmlspecialchars($range['end']) ?>"
                                                       class="form-control form-control-sm"
                                                       placeholder="192.168.1.255">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger remove-ip-range">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" id="add_ip_range">
                                <i class="fa fa-plus"></i> Add IP Range
                            </button>
                            <small class="form-text text-muted d-block mt-2">
                                Example: 127.0.0.0 to 127.0.0.255 for localhost
                            </small>
                        </div>

                        <!-- Submit -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="saveBtn" <?= !$file_writable ? 'disabled' : '' ?>>
                                <i class="fa fa-save"></i> Save Changes
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fa fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Values Info -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fa fa-eye"></i> Current Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded mb-3">
                                <strong>Site Title:</strong><br>
                                <code><?= htmlspecialchars($config->getString('siteTitle', 'Not set')) ?></code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded mb-3">
                                <strong>Admin Email:</strong><br>
                                <code><?= htmlspecialchars($config->getString('admin_email', 'Not set')) ?></code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change History -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fa fa-history"></i> Recent Changes</h5>
                </div>
                <div class="card-body">
                    <?php
                    $editable_json = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : null;
                    if ($editable_json && isset($editable_json['_metadata'])):
                        $metadata = $editable_json['_metadata'];
                    ?>
                        <ul class="list-unstyled">
                            <li>
                                <strong>Last updated:</strong> 
                                <?= isset($metadata['last_updated']) ? htmlspecialchars($metadata['last_updated']) : 'Never' ?>
                            </li>
                            <li>
                                <strong>Updated by:</strong> 
                                <?= isset($metadata['last_updated_by']) ? htmlspecialchars($metadata['last_updated_by']) : 'Unknown' ?>
                            </li>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No changes recorded yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// IP Ranges management
let ipRangeCounter = 0;

document.getElementById('add_ip_range')?.addEventListener('click', function() {
    const tbody = document.getElementById('ip_ranges_tbody');
    
    // Remove "no ranges" message if present
    const noRangesRow = tbody.querySelector('.no-ranges-row');
    if (noRangesRow) {
        noRangesRow.remove();
    }
    
    const row = document.createElement('tr');
    row.className = 'ip-range-row';
    row.innerHTML = `
        <td>
            <input type="text" 
                   name="auto_login_ip_ranges[${ipRangeCounter}][start]" 
                   class="form-control form-control-sm"
                   placeholder="192.168.1.0">
        </td>
        <td>
            <input type="text" 
                   name="auto_login_ip_ranges[${ipRangeCounter}][end]" 
                   class="form-control form-control-sm"
                   placeholder="192.168.1.255">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger remove-ip-range">
                <i class="fa fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
    ipRangeCounter++;
    
    // Attach delete handler
    row.querySelector('.remove-ip-range').addEventListener('click', function() {
        row.remove();
        
        // If no rows left, show "no ranges" message
        if (tbody.querySelectorAll('.ip-range-row').length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'no-ranges-row';
            emptyRow.innerHTML = '<td colspan="3" class="text-center text-muted"><em>No IP ranges configured</em></td>';
            tbody.appendChild(emptyRow);
        }
    });
});

// Attach delete handlers to existing rows
document.querySelectorAll('.remove-ip-range').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.ip-range-row').remove();
        
        const tbody = document.getElementById('ip_ranges_tbody');
        if (tbody.querySelectorAll('.ip-range-row').length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'no-ranges-row';
            emptyRow.innerHTML = '<td colspan="3" class="text-center text-muted"><em>No IP ranges configured</em></td>';
            tbody.appendChild(emptyRow);
        }
    });
});
</script>

</body>
</html>
