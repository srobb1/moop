<?php
// Styles are loaded from manage-filesystem-permissions.css via page_styles in layout.php
?>

<div class="container py-4">
    <!-- Back to Admin Dashboard Link -->
    <div class="mb-4">
      <a href="admin.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
      </a>
    </div>

    <div class="row">
        <div class="col-12">

<h2><i class="fa fa-lock"></i> Filesystem Permissions</h2>
<p class="text-muted">Manage file and directory permissions for system reliability</p>

<!-- About Section -->
<div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutPermissions">
        <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Filesystem Permissions <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutPermissions">
        <div class="card-body">
            <p><strong>Purpose:</strong> Verify and manage file and directory permissions to ensure the web server can read and write files it needs while maintaining security.</p>
            
            <p><strong>Why It Matters:</strong></p>
            <ul>
                <li>The web server (<?= htmlspecialchars($web_user) ?>:<?= htmlspecialchars($web_group) ?>) needs specific permissions to function correctly</li>
                <li>Incorrect permissions can cause upload failures, configuration save errors, and feature breakdowns</li>
                <li>Over-permissive files (777, 666) create security vulnerabilities</li>
                <li>SGID bits on directories ensure new files automatically inherit the correct group ownership</li>
            </ul>
            
            <p><strong>Permission Levels:</strong></p>
            <ul>
                <li><strong>644 (rw-r--r--):</strong> Read-only files that don't change (databases)</li>
                <li><strong>664 (rw-rw-r--):</strong> Configuration files edited by admins through the web interface</li>
                <li><strong>2775 (drwxrwsr-x):</strong> Directories with SGID - ensures new files automatically get correct group</li>
            </ul>
            
            <p class="mb-0"><strong>What You Can Do Here:</strong></p>
            <ul class="mb-0">
                <li>View current permissions for all system files and directories</li>
                <li>Identify permission issues immediately with visual indicators</li>
                <li>Get copy-paste commands to fix any permission problems</li>
                <li>Understand why each permission is needed</li>
            </ul>
        </div>
    </div>
</div>

<!-- Permission Summary -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fa fa-check-square"></i> Permission Checklist</h5>
    </div>
    <div class="card-body">
        <?php 
        $all_checks = array_merge($checks, $assembly_subdir_issues);
        $total = count($all_checks);
        $ok = count(array_filter($all_checks, fn($c) => empty($c['issues'])));
        $warning = $total - $ok;
        ?>
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?= $ok ?>/<?= $total ?></h3>
                        <p class="mb-0 small">Correct Permissions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?= $warning ?></h3>
                        <p class="mb-0 small">Issues Found</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


    <!-- Summary & Best Practices -->
<div class="card mb-4 border-success">
    <div class="card-header bg-success bg-opacity-10">
        <h5 class="mb-0"><i class="fa fa-star"></i> Best Practices</h5>
    </div>
     <div class="card-body">
        <ul>
            <li><strong>Owner should always be:</strong> <code><?= htmlspecialchars($moop_owner) ?></code> (detected from system)</li>
            <li><strong>Group should always be:</strong> <code><?= htmlspecialchars($web_group) ?></code> (detected from system)</li>
            <li><strong>Always use SGID (2775) on directories</strong> to auto-assign group to new files</li>
            <li><strong>Use 664 for files that need write:</strong> Configuration JSONs, metadata files</li>
            <li><strong>Use 644 for read-only files:</strong> Database files that don't change</li>
            <li><strong>Never use 777 or 666:</strong> This allows anyone to access/modify files (security risk)</li>
            <li><strong>Check permissions regularly:</strong> Use this page to verify all permissions are correct</li>
            <li><strong>For assembly rename operations:</strong> Organism directories need 2775 (write + execute) to allow web server to rename/move subdirectories. If rename fails in the admin interface, verify permissions here.</li>
        </ul>
    </div>
</div>

<!-- Quick Reference -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fa fa-book"></i> Permission Modes Reference</h5>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Mode</th>
                    <th>Name</th>
                    <th>Usage</th>
                    <th>Explanation</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>644</code></td>
                    <td>rw-r--r--</td>
                    <td>Config/JSON files</td>
                    <td>Owner reads/writes, group & others read only</td>
                </tr>
                <tr>
                    <td><code>664</code></td>
                    <td>rw-rw-r--</td>
                    <td>Shared config files</td>
                    <td>Owner & group read/write, others read only</td>
                </tr>
                <tr>
                    <td><code>2775</code></td>
                    <td>drwxrwsr-x</td>
                    <td>Shared directories</td>
                    <td>SGID (2) + owner/group write. The <code>s</code> (SGID) ensures new files inherit the directory's group</td>
                </tr>
                <tr>
                    <td><code>775</code></td>
                    <td>drwxrwxr-x</td>
                    <td>General directories</td>
                    <td>Owner & group can read/write/execute, others read/execute only</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


    <!-- Detailed Permission Groups -->
<?php
$grouped = [];
foreach ($checks as $check) {
    $name = $check['name'];
    if (!isset($grouped[$name])) {
        $grouped[$name] = [];
    }
    $grouped[$name][] = $check;
}

foreach ($grouped as $group_name => $items):
?>
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0">
            <?php
            $has_issues = count(array_filter($items, fn($i) => !empty($i['issues']))) > 0;
            if ($has_issues) {
                echo '<i class="fa fa-exclamation-triangle text-warning"></i> ';
            } else {
                echo '<i class="fa fa-check-circle text-success"></i> ';
            }
            ?>
            <?= htmlspecialchars($group_name) ?>
        </h6>
    </div>
    <div class="card-body">
        <?php foreach ($items as $check): ?>
        <div class="mb-3">
            <p class="mb-2">
                <strong><?= htmlspecialchars($check['path']) ?></strong>
                <?php if (empty($check['issues'])): ?>
                <span class="badge bg-success">✓ OK</span>
                <?php else: ?>
                <span class="badge bg-danger">✗ Issues</span>
                <?php endif; ?>
            </p>
            
            <!-- Current Status -->
            <div class="perm-grid">
                <div class="perm-item">
                    <strong>Permissions</strong>
                    <div class="perm-value <?= $check['current_perms'] === $check['required_perms'] ? 'text-success' : 'text-danger' ?>">
                        <?= $check['current_perms'] ?? 'N/A' ?>
                    </div>
                    <small class="text-muted">Should be: <?= $check['required_perms'] ?></small>
                </div>
                <div class="perm-item">
                    <strong>Owner</strong>
                    <div class="perm-value"><?= htmlspecialchars($check['current_owner'] ?? 'N/A') ?></div>
                </div>
                <div class="perm-item">
                    <strong>Group</strong>
                    <div class="perm-value <?= ($check['current_group'] ?? '') === $check['required_group'] ? 'text-success' : 'text-danger' ?>">
                        <?= htmlspecialchars($check['current_group'] ?? 'N/A') ?>
                    </div>
                    <small class="text-muted">Should be: <?= htmlspecialchars($check['required_group']) ?></small>
                </div>
            </div>
            
            <!-- Issues -->
            <?php if (!empty($check['issues'])): ?>
            <div class="alert alert-danger mt-2 mb-2">
                <strong>Issues:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($check['issues'] as $issue): ?>
                    <li><?= htmlspecialchars($issue) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Fix Commands -->
            <div class="mt-2">
                <p class="mb-2"><strong>To fix, run:</strong></p>
                <div class="fix-command">
                    <?php if ($check['type'] === 'directory'): ?>
                    <!-- For directories, use -R to fix all existing files, then reapply directory SGID -->
                    sudo chown -R <?= htmlspecialchars($moop_owner) ?>:<?= htmlspecialchars($check['required_group']) ?> <?= escapeshellarg($check['path']) ?> && \<br>
                    sudo chmod -R 775 <?= escapeshellarg($check['path']) ?> && \<br>
                    sudo chmod <?= $check['required_perms'] ?> <?= escapeshellarg($check['path']) ?>
                    <?php else: ?>
                    <!-- For files, single commands -->
                    sudo chmod <?= $check['required_perms'] ?> <?= escapeshellarg($check['path']) ?> && \<br>
                    sudo chown <?= htmlspecialchars($moop_owner) ?>:<?= htmlspecialchars($check['required_group']) ?> <?= escapeshellarg($check['path']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Why This Matters -->
            <div class="mt-3 p-3 bg-light rounded border-left border-info">
                <strong><i class="fa fa-lightbulb"></i> Why:</strong>
                <p class="mb-0"><?= htmlspecialchars($check['reason']) ?></p>
                <p class="mb-0 mt-2"><strong>Write Access:</strong> <?= htmlspecialchars($check['why_write']) ?></p>
            </div>
            
            <!-- SGID Info -->
            <?php if ($check['sgid_bit']): ?>
            <div class="sticky-bit">
                <strong><i class="fa fa-sticky-note"></i> SGID (Set-Group-ID) - 2775</strong>
                <p class="mb-0 small mt-2">This directory uses SGID to auto-assign the group. Any new files created here automatically get <code><?= htmlspecialchars($check['required_group']) ?></code> as their group, displayed as lowercase <code>s</code> in the permissions: <code>drwxrwsr-x</code>.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($check !== end($items)): ?>
        <hr class="my-2">
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <!-- Assembly Subdirectory Check -->
    <?php if ($group_name === 'Organism Data Directories'): ?>
    <div class="card-footer bg-light">
        <?php if (!empty($assembly_subdir_issues)): ?>
        <h6 class="mb-3"><i class="fa fa-folder"></i> Assembly Subdirectories</h6>
        <p class="text-danger mb-3"><strong>⚠️ Some assembly subdirectories have permission issues:</strong></p>
        <div class="mb-3">
            <?php foreach ($assembly_subdir_issues as $issue): ?>
            <div class="alert alert-warning mb-2">
                <strong><?= htmlspecialchars($issue['path']) ?></strong><br>
                <small>Current: <?= htmlspecialchars($issue['current_perms']) ?> (group: <?= htmlspecialchars($issue['current_group']) ?>) | Required: 2775 (group: www-data)</small>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="mb-2"><strong>To fix all assembly directories, run:</strong></p>
        <div class="fix-command">
            sudo chmod -R 2775 <?= htmlspecialchars($organism_data) ?><br>
            sudo chgrp -R www-data <?= htmlspecialchars($organism_data) ?>
        </div>
        <?php else: ?>
        <p class="mb-0 text-success"><strong><i class="fa fa-check-circle"></i> ✓ All assembly subdirectories have correct permissions (2775) for rename/move operations.</strong></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

        </div>
    </div>

    <!-- Back to Admin Dashboard Link (Bottom) -->
    <div class="mt-5 mb-4">
      <a href="admin.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
      </a>
    </div>
</div>
