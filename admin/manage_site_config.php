<?php
include_once __DIR__ . '/admin_init.php';

// Check admin access
if (!isAdmin()) {
    header('Location: /moop/access_denied.php');
    exit;
}

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
        ];
        
        // Save
        $result = $config->saveEditableConfig($data, $config_dir);
        
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
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
                    <form method="post" id="configForm">
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

</body>
</html>
