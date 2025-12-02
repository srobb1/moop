<?php
/**
 * Filesystem Permissions Tutorial & Checker
 * Comprehensive guide and validation for all files/directories needing specific permissions
 */

ob_start();
include_once __DIR__ . '/admin_init.php';

// Get paths from config
$organism_data = $config->getPath('organism_data');
$metadata_path = $config->getPath('metadata_path');
$absolute_images_path = $config->getPath('absolute_images_path');
$site_path = $config->getPath('site_path');
$root_path = $config->getPath('root_path');
$docs_path = $config->getPath('docs_path');

// Get web server user/group info from system
$webserver = getWebServerUser();
$web_user = $webserver['user'];
$web_group = $webserver['group'];

// Get moop owner from /moop directory (where the actual files are owned)
$moop_owner = 'ubuntu';  // Default fallback
if (function_exists('posix_getpwuid')) {
    $moop_info = @stat(__DIR__ . '/..');  // Get stat of /moop parent directory
    if ($moop_info) {
        $moop_pwd = posix_getpwuid($moop_info['uid']);
        if ($moop_pwd) {
            $moop_owner = $moop_pwd['name'];
        }
    }
}

// Define all required permissions
$permission_items = [
    // Configuration Files - Require Write
    [
        'name' => 'Metadata Configuration Files',
        'description' => 'JSON configuration files for annotations, taxonomy, and groups',
        'type' => 'file',
        'paths' => [
            $metadata_path . '/annotation_config.json',
            $metadata_path . '/taxonomy_tree_config.json',
            $metadata_path . '/group_descriptions.json',
            $metadata_path . '/organism_assembly_groups.json',
        ],
        'required_perms' => '664',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'Configuration files are edited by admins and read by the web server',
        'why_write' => 'Admin interface needs to modify these files when you change settings',
    ],
    
    // Metadata Directory - SGID for Group Assignment
    [
        'name' => 'Metadata Directory',
        'description' => 'Parent directory for all configuration files',
        'type' => 'directory',
        'paths' => [$metadata_path],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit (shown as \'s\' in permissions) ensures new files automatically get www-data as group',
        'why_write' => 'Web server needs to create/write files here. SGID ensures group is always www-data without manual fixes',
        'sgid_bit' => true,
    ],
    
    // Organism Directories
    [
        'name' => 'Organism Data Directories',
        'description' => 'Parent directory and subdirectories for all organisms',
        'type' => 'directory',
        'paths' => [
            $organism_data,
        ],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new files automatically get www-data as group',
        'why_write' => 'Web server needs to read databases and organism.json files',
        'sgid_bit' => true,
    ],
    
    // Organism.json Files - Require Write
    [
        'name' => 'Organism Metadata Files',
        'description' => 'JSON files describing each organism (genus, species, images, etc.)',
        'type' => 'file_pattern',
        'pattern' => 'organisms/*/organism.json',
        'required_perms' => '664',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'Edited by admin interface, read by web server',
        'why_write' => 'Admin can update organism metadata (descriptions, images, feature types)',
    ],
    
    // Database Files - Read Only
    [
        'name' => 'SQLite Database Files',
        'description' => 'Database files containing feature, annotation, and genome data',
        'type' => 'file_pattern',
        'pattern' => 'organisms/*/organism.sqlite',
        'required_perms' => '644',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'Web server reads data; files are pre-built and not modified',
        'why_write' => 'Database files must be readable by web server but typically not written to',
    ],
    
    // Logs Directory - Write Required
    [
        'name' => 'Logs Directory',
        'description' => 'Application log files for debugging and monitoring',
        'type' => 'directory',
        'paths' => [$site_path . '/logs'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new log files automatically get www-data as group',
        'why_write' => 'Web server writes error and debug logs here',
        'sgid_bit' => true,
    ],
    
    // Images Directory - Write for Uploads
    [
        'name' => 'Images Directory',
        'description' => 'Organism images and banners displayed on web pages',
        'type' => 'directory',
        'paths' => [$absolute_images_path],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new image files automatically get www-data as group',
        'why_write' => 'Admin may upload new organism images via web interface',
        'sgid_bit' => true,
    ],
    
    // NCBI Taxonomy Images Cache - Write for Downloaded Images
    [
        'name' => 'NCBI Taxonomy Images Cache',
        'description' => 'Cached images downloaded from NCBI taxonomy database',
        'type' => 'directory',
        'paths' => [$absolute_images_path . '/ncbi_taxonomy'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures downloaded taxonomy images automatically get www-data as group',
        'why_write' => 'Web server downloads and caches organism images from NCBI when generating taxonomy tree',
        'sgid_bit' => true,
    ],
    
    // Documentation Directory
    [
        'name' => 'Documentation Directory',
        'description' => 'README files and documentation for the system',
        'type' => 'directory',
        'paths' => [$docs_path],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures new documentation files automatically get www-data as group',
        'why_write' => 'Docs may be updated through admin interface',
        'sgid_bit' => true,
    ],
    
    // Backups Directory
    [
        'name' => 'Metadata Backups Directory',
        'description' => 'Automatic backups of configuration files',
        'type' => 'directory',
        'paths' => [$metadata_path . '/backups'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures backup files automatically get www-data as group',
        'why_write' => 'Web server creates backup files when configs are updated',
        'sgid_bit' => true,
    ],
    
    // Change Log Directory
    [
        'name' => 'Change Log Directory',
        'description' => 'Records of changes made through admin interface',
        'type' => 'directory',
        'paths' => [$metadata_path . '/change_log'],
        'required_perms' => '2775',
        'required_owner' => $moop_owner,
        'required_group' => 'www-data',
        'reason' => 'SGID (Set-Group-ID) bit ensures change log files automatically get www-data as group',
        'why_write' => 'Web server logs all admin actions for auditing',
        'sgid_bit' => true,
    ],
];

// Check permissions for each item
$checks = [];
foreach ($permission_items as $item) {
    if ($item['type'] === 'directory' || ($item['type'] === 'file' && !isset($item['pattern']))) {
        foreach ($item['paths'] ?? [] as $path) {
            $checks[] = performPermissionCheck($path, $item);
        }
    }
}

function performPermissionCheck($path, $item) {
    $result = [
        'name' => $item['name'],
        'path' => $path,
        'exists' => file_exists($path),
        'type' => $item['type'],
        'required_perms' => $item['required_perms'],
        'required_group' => $item['required_group'] ?? 'www-data',
        'reason' => $item['reason'] ?? '',
        'why_write' => $item['why_write'] ?? '',
        'sticky_bit' => $item['sticky_bit'] ?? false,
        'issues' => [],
    ];
    
    if (!$result['exists']) {
        $result['issues'][] = 'Path does not exist';
        return $result;
    }
    
    $perms_full = substr(sprintf('%o', fileperms($path)), -4);
    // Remove leading zero for comparison (0664 -> 664, 02775 -> 2775)
    $perms = ltrim($perms_full, '0') ?: '0';
    $owner = posix_getpwuid(fileowner($path))['name'] ?? 'unknown';
    $group = posix_getgrgid(filegroup($path))['name'] ?? 'unknown';
    
    $result['current_perms'] = $perms;
    $result['current_owner'] = $owner;
    $result['current_group'] = $group;
    $result['is_readable'] = is_readable($path);
    $result['is_writable'] = is_writable($path);
    
    // Check permissions
    if ($perms !== $item['required_perms']) {
        $result['issues'][] = "Permissions are $perms, should be " . $item['required_perms'];
    }
    
    // Check group
    if (isset($item['required_group']) && $group !== $item['required_group']) {
        $result['issues'][] = "Group is $group, should be " . $item['required_group'];
    }
    
    return $result;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Filesystem Permissions</title>
    <?php include_once __DIR__ . '/../includes/head.php'; ?>
    <style>
        .permission-status {
            font-family: monospace;
            font-size: 0.9em;
            padding: 8px 12px;
            border-radius: 4px;
            background: #f8f9fa;
            border-left: 4px solid #6c757d;
        }
        .permission-status.ok {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .permission-status.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .permission-status.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .perm-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .perm-item strong {
            display: block;
            margin-bottom: 5px;
            font-size: 0.85em;
            color: #495057;
        }
        .perm-value {
            font-weight: bold;
            color: #212529;
            font-size: 1.1em;
        }
        .sticky-bit {
            background: #e7f3ff;
            padding: 10px;
            border-left: 4px solid #0066cc;
            margin-top: 10px;
            border-radius: 4px;
        }
        .fix-command {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-5">
    <h2><i class="fa fa-lock"></i> Filesystem Permissions</h2>
    <p class="text-muted">Complete guide to file and directory permissions required for the system</p>
    
    <!-- Overview Card -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10">
            <h5 class="mb-0"><i class="fa fa-info-circle"></i> Why Permissions Matter</h5>
        </div>
         <div class="card-body">
            <p><strong>The web server (<?= htmlspecialchars($web_user) ?>:<?= htmlspecialchars($web_group) ?>) needs different permission levels for different files:</strong></p>
            <ul>
                <li><strong>Read (644):</strong> Database files that are pre-built and read-only</li>
                <li><strong>Read + Write (664):</strong> Configuration files that admins edit</li>
                <li><strong>Directory with SGID (2775):</strong> Directories where files are created - SGID ensures new files automatically inherit the group</li>
            </ul>
            <div class="mt-3 p-3 bg-light rounded">
                <strong><i class="fa fa-sticky-note"></i> What is SGID (Set-Group-ID)?</strong>
                <p class="mb-0 mt-2">SGID is the first digit in 4-digit permissions (the "2" in 2775). When set on a directory, it ensures all new files created within that directory automatically inherit the directory's group (www-data in our case). This displays as a lowercase <code>s</code> in the group execute position: <code>drwxrwsr-x</code>.</p>
                <p class="mb-0 mt-2"><strong>Note:</strong> This is different from the sticky bit (which would show as <code>t</code> and uses "1" as the first digit, like 1775). SGID is what we use here for automatic group assignment.</p>
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
            $total = count($checks);
            $ok = count(array_filter($checks, fn($c) => empty($c['issues'])));
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
                        sudo chmod <?= $check['required_perms'] ?> <?= escapeshellarg($check['path']) ?> && \<br>
                        sudo chown <?= htmlspecialchars($moop_owner) ?>:<?= htmlspecialchars($check['required_group']) ?> <?= escapeshellarg($check['path']) ?>
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
    </div>
    <?php endforeach; ?>
    


</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
