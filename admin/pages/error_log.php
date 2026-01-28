<?php
/**
 * ERROR LOG VIEWER - Content File
 * 
 * Pure display content - no HTML structure, scripts, or styling.
 * 
 * Layout system (layout.php) handles:
 * - HTML structure (<!DOCTYPE>, <html>, <head>, <body>)
 * - All CSS and resources
 * - All scripts
 * - Navbar and footer
 * 
 * This file has access to variables passed from error_log.php:
 * - $cleared (bool) - Whether log was just cleared
 * - $errors (array) - Filtered error entries
 * - $all_errors (array) - All error entries
 * - $error_types (array) - Unique error types for filter dropdown
 * - $organisms (array) - Unique organisms for filter dropdown
 * - $filter_type, $filter_organism, $filter_search (string) - Current filter values
 */
?>

<div class="container mt-4">
    <!-- Back to Admin Link -->
    <div class="mb-4">
      <a href="admin.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Admin
      </a>
    </div>

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

    <!-- About Section -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutErrorLog">
            <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Error Log Viewer <i class="fa fa-chevron-down float-end"></i></h5>
        </div>
        <div class="collapse" id="aboutErrorLog">
            <div class="card-body">
                <p><strong>Purpose:</strong> Monitor and troubleshoot errors that occur in the system. All errors are logged automatically to help diagnose issues.</p>
                
                <p><strong>What Gets Logged:</strong></p>
                <ul>
                    <li>Database connection errors</li>
                    <li>File permission issues</li>
                    <li>Missing data files or configuration</li>
                    <li>BLAST execution errors</li>
                    <li>Search and query failures</li>
                    <li>User authentication problems</li>
                    <li>Invalid input or malformed requests</li>
                </ul>
                
                <p><strong>How to Use This Page:</strong></p>
                <ul>
                    <li><strong>View errors:</strong> Latest errors appear at the top of the list</li>
                    <li><strong>Filter by type:</strong> Use "Error Type" dropdown to see specific error categories</li>
                    <li><strong>Filter by organism:</strong> Use "Organism" dropdown to see errors related to specific organisms</li>
                    <li><strong>Search:</strong> Use the search box to find errors by message, user, or IP address</li>
                    <li><strong>Clear log:</strong> Use the "Clear Log" button to remove all errors (after reviewing them)</li>
                </ul>
                
                <p><strong>Error Information Displayed:</strong></p>
                <ul>
                    <li><strong>Timestamp:</strong> When the error occurred</li>
                    <li><strong>Error Type:</strong> Category of error (e.g., "Database Error", "File Not Found")</li>
                    <li><strong>Context:</strong> Which organism or component was affected</li>
                    <li><strong>User:</strong> Which user was logged in (or "anonymous")</li>
                    <li><strong>IP Address:</strong> Where the request came from</li>
                    <li><strong>Page:</strong> Which page or script generated the error</li>
                    <li><strong>Details:</strong> Additional error information for debugging</li>
                </ul>
                
                <p class="mb-0"><strong>Tips for Troubleshooting:</strong> If errors persist after clearing the log, check file permissions on the organisms directory, verify database files exist and are readable, and ensure all required configuration files are present.</p>
            </div>
        </div>
    </div>

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
                    <div class="card mb-3" style="border-left: 4px solid #dc3545;">
                        <div class="card-body">
                            <div class="mb-2">
                                <div style="font-weight: bold; color: #666;">
                                    <i class="fas fa-clock"></i> 
                                    <?= htmlspecialchars($error['timestamp']) ?>
                                </div>
                                <div style="color: #dc3545; margin: 0.5rem 0;">
                                    <strong><?= htmlspecialchars($error['error']) ?></strong>
                                </div>
                            </div>
                            <div style="font-size: 0.85rem; color: #999; margin-top: 0.5rem;">
                                <dl class="row">
                                    <dt class="col-sm-2" style="font-weight: 600; color: #333;">Context:</dt>
                                    <dd class="col-sm-10"><?= htmlspecialchars($error['context'] ?? 'N/A') ?></dd>

                                    <dt class="col-sm-2" style="font-weight: 600; color: #333;">User:</dt>
                                    <dd class="col-sm-10"><?= htmlspecialchars($error['user'] ?? 'unknown') ?></dd>

                                    <dt class="col-sm-2" style="font-weight: 600; color: #333;">IP:</dt>
                                    <dd class="col-sm-10"><?= htmlspecialchars($error['ip'] ?? 'unknown') ?></dd>

                                    <dt class="col-sm-2" style="font-weight: 600; color: #333;">Page:</dt>
                                    <dd class="col-sm-10"><code><?= htmlspecialchars($error['page'] ?? 'unknown') ?></code></dd>

                                    <?php if (!empty($error['details'])): ?>
                                        <dt class="col-sm-2" style="font-weight: 600; color: #333;">Details:</dt>
                                        <dd class="col-sm-10">
                                            <pre class="bg-light p-2 rounded small"><?= htmlspecialchars(json_encode($error['details'], JSON_PRETTY_PRINT)) ?></pre>
                                        </dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back to Admin Link (Bottom) -->
    <div class="mt-5 mb-4">
      <a href="admin.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Admin
      </a>
    </div>
</div>
