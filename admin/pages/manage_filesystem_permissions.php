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
                <li><strong>640 / 660 / 644 (group-readable):</strong> Read-only data the web serves (FASTA, genomes, databases) — the web server reads via its group, so any of these work; world-readable is not required</li>
                <li><strong>664 (rw-rw-r--):</strong> Configuration files edited by admins through the web interface</li>
                <li><strong>2775 (drwxrwsr-x):</strong> Directories the web server writes into — SGID ensures new files inherit the correct group (needs the <code>httpd_sys_rw_content_t</code> SELinux label too)</li>
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
        $all_checks = array_merge($checks, $assembly_subdir_issues, $fasta_file_issues);
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
        <p class="mb-2">These checks judge permissions by <strong>impact</strong>, not by an exact mode string. The web server runs as <code><?= htmlspecialchars($web_group) ?></code> and reads data through its <strong>group</strong>, so a file does not need to be world-readable to be served.</p>
        <ul>
            <li><strong>Owner should be <code><?= htmlspecialchars($moop_owner) ?></code>, group <code><?= htmlspecialchars($web_group) ?></code></strong> (detected from the system). The web server reads via the group.</li>
            <li><strong>Data files just need to be group-readable</strong> — FASTA, genomes, and databases at <code>640</code>, <code>660</code>, <code>664</code>, or <code>644</code> all work. <strong>World-readable (<code>644</code>) is not required, and is worse for restricted data</strong> — don't "fix" a <code>660</code> file up to <code>644</code>.</li>
            <li><strong>Never world-writable</strong> (<code>777</code>, <code>666</code>, or any <code>o+w</code>), and <strong>data files should not be executable</strong> — those are the perms that genuinely matter.</li>
            <li><strong>Only a specific set of directories is writable by the web server</strong> — logs, config, metadata, <code>data/genomes</code>, the image caches (<code>wikimedia</code>, <code>ncbi_taxonomy</code>), and the cache dir. These use SGID <code>2775</code> so new files inherit the <code><?= htmlspecialchars($web_group) ?></code> group. Everything else — including the whole <code>organisms/</code> tree, uploaded images, and <code>docs/</code> — is <strong>read-only served content</strong>.</li>
            <li><strong>Under SELinux (Enforcing), the label is the real gate.</strong> A directory can look writable (<code>2775</code>) yet be blocked because its SELinux type is <code>httpd_sys_content_t</code>. Writable dirs need <code>httpd_sys_rw_content_t</code>; apply it with <code>scripts/fix_moop_selinux.sh</code> (see <code>docs/SELINUX_AND_HARDENING.md</code>), not <code>chmod</code>.</li>
            <li><strong>Use 664 for config/metadata files</strong> the admin UI edits (so the web group can write them).</li>
            <li><strong>The <code>organisms/</code> tree is read-only</strong> except <code>organism.json</code> (and, once the index refactor ships, a writable <code>index/</code> subdir for BLAST indexes). BLAST-index builds and renames are handled there — not by loosening the whole tree. If a web build/rename fails, check the SELinux label here, not just the Unix mode.</li>
            <li><strong>Check regularly:</strong> the admin dashboard now surfaces a pointer here when something needs attention.</li>
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
                    <!-- For directories, create if missing, then fix permissions -->
                    <?php if (!$check['exists']): ?>
                    sudo mkdir -p <?= escapeshellarg($check['path']) ?> && \<br>
                    <?php endif; ?>
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
                <small>Current: <?= htmlspecialchars($issue['current_perms']) ?> (group: <?= htmlspecialchars($issue['current_group']) ?>) | Required: 2775 (group: <?= htmlspecialchars($web_group) ?>)</small>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="mb-2"><strong>To fix all assembly directories, run:</strong></p>
        <div class="fix-command">
            sudo chmod -R 2775 <?= htmlspecialchars($organism_data) ?><br>
            sudo chgrp -R <?= htmlspecialchars($web_group) ?> <?= htmlspecialchars($organism_data) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($fasta_file_issues)): ?>
        <h6 class="mb-3"><i class="fa fa-dna"></i> FASTA Files</h6>
        <p class="text-danger mb-3"><strong>⚠️ Some FASTA files have permission issues:</strong></p>
        <div class="mb-3">
            <?php foreach ($fasta_file_issues as $issue): ?>
            <div class="alert alert-warning mb-2">
                <strong><?= htmlspecialchars($issue['path']) ?></strong><br>
                <small>Current: <?= htmlspecialchars($issue['current_perms']) ?> (group: <?= htmlspecialchars($issue['current_group']) ?>) | Required: 644 (group: <?= htmlspecialchars($web_group) ?>)</small>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="mb-2"><strong>To fix all FASTA files, run:</strong></p>
        <div class="fix-command">
            <?php 
            $sequence_types = $config->getSequenceTypes();
            $patterns = [];
            foreach ($sequence_types as $seq_config) {
                $pattern = $seq_config['pattern'] ?? '';
                if (!empty($pattern)) {
                    $basename = basename($pattern);
                    // Extract the filename pattern (e.g., "*.fa" from "protein.aa.fa")
                    $patterns[] = '-name "' . $basename . '"';
                }
            }
            $find_patterns = implode(' -o ', $patterns);
            ?>
            sudo find <?= htmlspecialchars($organism_data) ?> <?= $find_patterns ?> | xargs sudo chmod 644<br>
            sudo find <?= htmlspecialchars($organism_data) ?> <?= $find_patterns ?> | xargs sudo chgrp <?= htmlspecialchars($web_group) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($assembly_subdir_issues) && empty($fasta_file_issues)): ?>
        <p class="mb-0 text-success"><strong><i class="fa fa-check-circle"></i> ✓ All assembly subdirectories and FASTA files have correct permissions.</strong></p>
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
