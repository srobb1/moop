<?php
// Error Log Viewer - Admin Only
session_start();
include_once __DIR__ . '/../site_config.php';
include_once __DIR__ . '/admin_header.php';
include_once __DIR__ . '/../header.php';
include_once __DIR__ . '/../access_control.php';
include_once __DIR__ . '/../error_logger.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access Denied. Admin access required.');
}

// Handle clear log action
$cleared = false;
if (isset($_GET['action']) && $_GET['action'] === 'clear' && isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    if (clearErrorLog()) {
        $cleared = true;
    }
}

// Get all errors from log
$errors = getErrorLog(100); // Get last 100 errors
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Log Viewer - <?= $siteTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; }
        .navbar { margin-bottom: 2rem; }
        .error-entry { background-color: white; margin-bottom: 1rem; border-left: 4px solid #dc3545; }
        .error-timestamp { font-weight: bold; color: #666; }
        .error-message { color: #dc3545; margin: 0.5rem 0; }
        .error-details { font-size: 0.85rem; color: #999; margin-top: 0.5rem; }
        .error-details dt { font-weight: 600; color: #333; }
        .error-details dd { margin-left: 1rem; margin-bottom: 0.25rem; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/../toolbar.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1><i class="fas fa-exclamation-triangle"></i> Error Log Viewer</h1>
            <p class="text-muted">Last 100 logged errors</p>
        </div>
    </div>

    <?php if ($cleared): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Success!</strong> Error log has been cleared.
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-12">
            <?php if (!empty($errors)): ?>
                <a href="?action=clear&confirm=1" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to clear the entire error log?');">
                    <i class="fas fa-trash"></i> Clear Log
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <?php if (empty($errors)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-check-circle"></i> No errors logged.
                </div>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="error-entry p-3">
                        <div class="error-timestamp">
                            <i class="fas fa-clock"></i> 
                            <?= htmlspecialchars($error['timestamp']) ?>
                        </div>
                        <div class="error-message">
                            <strong><?= htmlspecialchars($error['error']) ?></strong>
                        </div>
                        <div class="error-details">
                            <dl class="row">
                                <dt class="col-sm-2">Context:</dt>
                                <dd class="col-sm-10"><?= htmlspecialchars($error['context'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-2">User:</dt>
                                <dd class="col-sm-10"><?= htmlspecialchars($error['user'] ?? 'unknown') ?></dd>

                                <dt class="col-sm-2">IP:</dt>
                                <dd class="col-sm-10"><?= htmlspecialchars($error['ip'] ?? 'unknown') ?></dd>

                                <dt class="col-sm-2">Page:</dt>
                                <dd class="col-sm-10"><code><?= htmlspecialchars($error['page'] ?? 'unknown') ?></code></dd>

                                <?php if (!empty($error['details'])): ?>
                                    <dt class="col-sm-2">Details:</dt>
                                    <dd class="col-sm-10">
                                        <pre style="background-color: #f5f5f5; padding: 0.5rem; border-radius: 3px; font-size: 0.8rem;"><?= htmlspecialchars(json_encode($error['details'], JSON_PRETTY_PRINT)) ?></pre>
                                    </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../footer.php'; ?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
