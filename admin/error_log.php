<?php
// Error Log Viewer - Admin Only
session_start();
include_once __DIR__ . '/../site_config.php';
include_once __DIR__ . '/admin_access_check.php';
include_once __DIR__ . '/../access_control.php';
include_once __DIR__ . '/../tools/moop_functions.php';
include_once __DIR__ . '/../includes/head.php';
include_once __DIR__ . '/../includes/navbar.php';

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
$all_errors = getErrorLog(500); // Get more for filtering

// Apply filters
$filter_type = $_GET['filter_type'] ?? '';
$filter_organism = $_GET['filter_organism'] ?? '';
$filter_search = $_GET['filter_search'] ?? '';

$errors = $all_errors;

// Filter by error type
if (!empty($filter_type)) {
    $errors = array_filter($errors, function($error) use ($filter_type) {
        return strpos($error['error'], $filter_type) !== false;
    });
}

// Filter by organism (in context field)
if (!empty($filter_organism)) {
    $errors = array_filter($errors, function($error) use ($filter_organism) {
        return strpos($error['context'], $filter_organism) !== false;
    });
}

// Search in error message and details
if (!empty($filter_search)) {
    $search_term = strtolower($filter_search);
    $errors = array_filter($errors, function($error) use ($search_term) {
        $searchable = strtolower(json_encode($error));
        return strpos($searchable, $search_term) !== false;
    });
}

// Get unique error types and organisms for filter dropdowns
$error_types = [];
$organisms = [];
foreach ($all_errors as $error) {
    if (!in_array($error['error'], $error_types)) {
        $error_types[] = $error['error'];
    }
    if (!empty($error['context']) && !in_array($error['context'], $organisms)) {
        $organisms[] = $error['context'];
    }
}
sort($organisms);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Error Log Viewer - <?= $siteTitle ?></title>
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
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Filter Panel -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fa fa-filter"></i> Filter & Search</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="filter_type" class="form-label">Error Type</label>
                    <select class="form-select form-select-sm" id="filter_type" name="filter_type">
                        <option value="">All Types</option>
                        <?php foreach ($error_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $filter_type === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_organism" class="form-label">Organism</label>
                    <select class="form-select form-select-sm" id="filter_organism" name="filter_organism">
                        <option value="">All Organisms</option>
                        <?php foreach ($organisms as $org): ?>
                            <option value="<?= htmlspecialchars($org) ?>" <?= $filter_organism === $org ? 'selected' : '' ?>>
                                <?= htmlspecialchars($org) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filter_search" class="form-label">Search</label>
                    <input type="text" class="form-control form-control-sm" id="filter_search" name="filter_search" 
                           placeholder="Search message, user, IP..." value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fa fa-search"></i> Filter
                    </button>
                </div>
            </form>
            <?php if (!empty($filter_type) || !empty($filter_organism) || !empty($filter_search)): ?>
                <div class="mt-2">
                    <a href="?" class="btn btn-secondary btn-sm">
                        <i class="fa fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <p class="text-muted">
                Showing <strong><?= count($errors) ?></strong> of <strong><?= count($all_errors) ?></strong> logged errors
            </p>
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
                                        <pre class="bg-light p-2 rounded small"><?= htmlspecialchars(json_encode($error['details'], JSON_PRETTY_PRINT)) ?></pre>
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
