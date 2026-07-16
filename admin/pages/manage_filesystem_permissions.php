<?php
// Styles are loaded from manage-filesystem-permissions.css via page_styles in layout.php

// Only talk about SELinux when SELinux is actually enforcing. On a host without it,
// every mention below is noise pointing at a script that would do nothing — and the
// label can't be the reason anything is broken. The checks themselves already skip
// the label test when this is false (see performPermissionCheck).
$selinux_on = function_exists('moop_selinux_enforcing') && moop_selinux_enforcing();
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
        <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Filesystem Permissions Manager <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutPermissions">
        <div class="card-body">
            <p><strong>Purpose:</strong> Check that the web server can read and write exactly the files it needs — and nothing more. This page reports what is actually wrong and gives you the command to fix it.</p>

            <p><strong>Why It Matters:</strong></p>
            <ul>
                <li>The web server runs as <code><?= htmlspecialchars($web_user) ?>:<?= htmlspecialchars($web_group) ?></code> and reads data through its <strong>group</strong> — so a file never has to be world-readable to be served.</li>
                <li>When a writable directory is wrong, features fail <strong>silently</strong>: banner uploads, config saves, and index builds just stop working, with no error on the page.</li>
                <li>Over-permissive files are a real risk — world-writable (<code>777</code>, <code>666</code>) lets any local user overwrite data, and an <strong>executable data file</strong> has no legitimate reason to exist.</li>
                <?php if ($selinux_on): ?>
                <li><strong>Under SELinux (Enforcing), the label — not the Unix mode — is the real gate.</strong> A directory can look perfectly writable at <code>2775</code> and still be blocked. That is the single most common surprise here; <code>chmod</code> will not fix it.</li>
                <?php endif; ?>
                <li>SGID (the <code>s</code> in <code>drwxrwsr-x</code>) makes new files inherit the directory's group automatically, so writable areas stay writable as content is added.</li>
            </ul>

            <p><strong>How These Checks Judge:</strong></p>
            <ul>
                <li>By <strong>impact</strong>, not by matching an exact mode. <code>640</code> and <code>660</code> pass; they are not "wrong" for failing to be <code>644</code>.</li>
                <li>Findings are grouped by <strong>category</strong>, so a count means "how many things are wrong", not "how many files exist".</li>
                <li>See <strong>Best Practices</strong> below for the rules, and <strong>Permission Modes Reference</strong> for what each mode means.</li>
            </ul>

            <p class="mb-0"><strong>What You Can Do Here:</strong></p>
            <ul class="mb-0">
                <li>See current permissions, ownership<?= $selinux_on ? ', and SELinux state' : '' ?> for everything MOOP touches</li>
                <li>Fix one path with its own command, or take a whole section in a single paste</li>
                <li>Understand why each path needs the access it does</li>
            </ul>
        </div>
    </div>
</div>

<!-- Best Practices — reference material, collapsed by default so the checklist leads -->
<div class="card mb-4 border-success">
    <div class="card-header bg-success bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#bestPractices">
        <h5 class="mb-0"><i class="fa fa-star"></i> Best Practices <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="bestPractices">
        <div class="card-body">
            <p class="mb-2">These checks judge permissions by <strong>impact</strong>, not by an exact mode string. The web server runs as <code><?= htmlspecialchars($web_group) ?></code> and reads data through its <strong>group</strong>, so a file does not need to be world-readable to be served.</p>
            <ul>
                <li><strong>Owner should be <code><?= htmlspecialchars($moop_owner) ?></code>, group <code><?= htmlspecialchars($web_group) ?></code></strong> (detected from the system). The web server reads via the group.</li>
                <li><strong>Data files just need to be group-readable</strong> — FASTA, genomes, and databases at <code>640</code>, <code>660</code>, <code>664</code>, or <code>644</code> all work. <strong>World-readable (<code>644</code>) is not required, and is worse for restricted data</strong> — don't "fix" a <code>660</code> file up to <code>644</code>.</li>
                <li><strong>Never world-writable</strong> (<code>777</code>, <code>666</code>, or any <code>o+w</code>), and <strong>files should not be executable</strong> — those are the perms that genuinely matter. Directories are different: they need the traverse (<code>x</code>) bit.</li>
                <li><strong>The web server writes to a specific set of directories</strong> — logs, config, metadata, <code>data/genomes</code>, the image caches (<code>wikimedia</code>, <code>ncbi_taxonomy</code>), <code>images/banners</code>, <code>archived_gene_sets</code>, the cache dir, and the <code>organisms/</code> tree. These use SGID <code>2775</code> so new files inherit the <code><?= htmlspecialchars($web_group) ?></code> group. Everything else — top-level <code>images/</code>, <code>docs/</code>, <code>data/tracks</code>, and the <code>jbrowse2/</code> app — is <strong>read-only served content</strong>.</li>
                <?php if ($selinux_on): ?>
                <li><strong>Under SELinux (Enforcing), the label is the real gate.</strong> A directory can look writable (<code>2775</code>) yet be blocked because its SELinux type is <code>httpd_sys_content_t</code>. Writable dirs need <code>httpd_sys_rw_content_t</code>; apply it with <code>scripts/fix_moop_selinux.sh</code> (see <code>docs/SELINUX_AND_HARDENING.md</code>), not <code>chmod</code>.</li>
                <?php endif; ?>
                <li><strong>Use 664 for config/metadata files</strong> the admin UI edits (so the web group can write them).</li>
                <li><strong>The <code>organisms/</code> tree is writable — deliberately.</strong> The web builds BLAST/<code>.fai</code> indexes in place, so it needs write. That is safe because nginx refuses to execute any <code>.php</code> in the data trees (the execution layer), not because the files are locked down.<?php if ($selinux_on): ?> If a web build or rename fails, check the SELinux label here, not just the Unix mode.<?php endif; ?></li>
                <li><strong><code>jbrowse2/</code> must stay read-only.</strong> It is the browser app's own JavaScript, which every user's browser runs — and the nginx rule blocks <code>.php</code>, not <code>.js</code>. Update it from the CLI (<code>npx @jbrowse/cli upgrade</code>), never via the web server.</li>
                <li><strong>Check regularly:</strong> the admin dashboard surfaces a pointer here when something needs attention.</li>
            </ul>
        </div>
    </div>
</div>

<!-- Quick Reference — reference material, collapsed by default like About/Best Practices -->
<div class="card mb-4">
    <div class="card-header bg-light" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#modesReference">
        <h5 class="mb-0"><i class="fa fa-book"></i> Permission Modes Reference <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="modesReference">
    <div class="card-body">
        <p class="small text-muted mb-3">The web server runs as <code><?= htmlspecialchars($web_group) ?></code> and reads through the <strong>group</strong>. What matters is impact — is it group-readable, is it world-writable, is a <em>file</em> executable — not matching an exact number below.</p>
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
                    <td><code>640</code></td>
                    <td>rw-r-----</td>
                    <td>Read-only data (restricted)</td>
                    <td>Owner reads/writes, group reads, <strong>no world access</strong>. The web serves it via the group. Tightest option for restricted gene sets.</td>
                </tr>
                <tr>
                    <td><code>660</code></td>
                    <td>rw-rw----</td>
                    <td>Read-only data / <code>organism.json</code></td>
                    <td>Owner &amp; group read/write, no world access. Common across the data tree after hardening — <strong>this passes</strong>; do not "fix" it up to 644.</td>
                </tr>
                <tr>
                    <td><code>664</code></td>
                    <td>rw-rw-r--</td>
                    <td>Config the admin UI edits</td>
                    <td>Owner &amp; group read/write, others read. The web group <strong>must</strong> be able to write these or admin saves fail.</td>
                </tr>
                <tr>
                    <td><code>644</code></td>
                    <td>rw-r--r--</td>
                    <td>Public files only</td>
                    <td><strong>World-readable.</strong> Fine for public data, but never <em>required</em> — the web reads via the group. Wrong for restricted data, and the group can't write, so don't use it for admin-edited config.</td>
                </tr>
                <tr>
                    <td><code>2775</code></td>
                    <td>drwxrwsr-x</td>
                    <td>Directories the web writes into</td>
                    <td>SGID (2) + owner/group write. The <code>s</code> ensures new files inherit the directory's group.<?php if ($selinux_on): ?> Needs the <code>httpd_sys_rw_content_t</code> SELinux label too.<?php endif; ?></td>
                </tr>
                <tr>
                    <td><code>755</code></td>
                    <td>drwxr-xr-x</td>
                    <td>Read-only directories</td>
                    <td>Traverse + read for everyone; only the owner writes.</td>
                </tr>
                <tr class="table-warning">
                    <td><code>775</code></td>
                    <td>rwxrwxr-x</td>
                    <td><strong>Directories only</strong></td>
                    <td>Fine on a directory (it needs the traverse bit). On a <strong>file</strong> it means <strong>executable</strong>, which is flagged — data should never be executable. Beware <code>chmod -R 775</code>: it hits files too.</td>
                </tr>
            </tbody>
        </table>
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
    $failing = array_values(array_filter($items, fn($i) => !empty($i['issues'])));
    $total   = count($items);
    // High-cardinality rules (one rule, many paths — organism.json x85, organism.sqlite x85)
    // list ONLY what is wrong. A wall of 80 passing rows is the "how many files exist"
    // noise the impact rewrite removed; the count should mean "how many things are wrong".
    // Small groups still list every path so a clean single check stays visible.
    $to_show = ($total > 5) ? $failing : $items;
?>
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0">
            <?php
            $has_issues = count($failing) > 0;
            if ($has_issues) {
                echo '<i class="fa fa-exclamation-triangle text-warning"></i> ';
            } else {
                echo '<i class="fa fa-check-circle text-success"></i> ';
            }
            ?>
            <?= htmlspecialchars($group_name) ?>
            <?php if ($total > 5): ?>
                <small class="text-muted fw-normal">
                    — <?= $total ?> checked<?= $has_issues ? ', ' . count($failing) . ' with issues' : ', all clean' ?>
                </small>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($total > 5 && !$has_issues): ?>
        <p class="mb-0 text-success"><i class="fa fa-check-circle"></i> All <?= $total ?> pass.</p>
        <?php endif; ?>
        <?php foreach ($to_show as $check): ?>
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
                <?php
                // Judge by IMPACT, not by an exact mode string: 660 and 640 pass fine, and
                // 644 (world-readable) is wrong for restricted data. Colour follows the
                // check's own verdict; the caption states the rule its mode actually applies.
                $mode_of      = $check['check_mode'] ?? 'data';
                $clean        = empty($check['issues']);
                $checks_group = ($mode_of === 'writable');
                ?>
                <div class="perm-item">
                    <strong>Permissions</strong>
                    <div class="perm-value <?= $clean ? 'text-success' : 'text-danger' ?>">
                        <?= htmlspecialchars($check['current_perms'] ?? 'N/A') ?>
                    </div>
                    <small class="text-muted"><?= htmlspecialchars(moop_permission_expectation($mode_of, $check['type'] ?? 'file')) ?></small>
                </div>
                <div class="perm-item">
                    <strong>Owner</strong>
                    <div class="perm-value"><?= htmlspecialchars($check['current_owner'] ?? 'N/A') ?></div>
                </div>
                <div class="perm-item">
                    <strong>Group</strong>
                    <div class="perm-value <?= (!$checks_group || ($check['current_group'] ?? '') === $check['required_group']) ? 'text-success' : 'text-danger' ?>">
                        <?= htmlspecialchars($check['current_group'] ?? 'N/A') ?>
                    </div>
                    <small class="text-muted">
                        <?php if ($checks_group): ?>
                            Should be: <?= htmlspecialchars($check['required_group']) ?>
                        <?php else: ?>
                            Any group the web server can read through
                        <?php endif; ?>
                    </small>
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
            
            <!-- Fix Commands — derived from the issues actually found, not a blanket chmod -->
            <?php $fix_cmds = moop_permission_fix_commands($check, $moop_owner); ?>
            <?php if ($fix_cmds): ?>
            <div class="mt-2">
                <p class="mb-2"><strong>To fix, run:</strong></p>
                <div class="fix-command">
                    <?php foreach ($fix_cmds as $i => $cmd): ?>
                    <?= htmlspecialchars($cmd) ?><?= $i < count($fix_cmds) - 1 ? ' && \\' : '' ?><br>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Why This Matters. why_write is absent on read-only rules (jbrowse2/, SQLite) —
                 they are never written by the web server, so there is nothing to explain. -->
            <div class="mt-3 p-3 bg-light rounded border-left border-info">
                <strong><i class="fa fa-lightbulb"></i> Why:</strong>
                <p class="mb-0"><?= htmlspecialchars($check['reason'] ?? '') ?></p>
                <?php if (!empty($check['why_write'])): ?>
                <p class="mb-0 mt-2"><strong>Write Access:</strong> <?= htmlspecialchars($check['why_write']) ?></p>
                <?php endif; ?>
            </div>

            <!-- SGID Info -->
            <?php if (!empty($check['sgid_bit'])): ?>
            <div class="sticky-bit">
                <strong><i class="fa fa-sticky-note"></i> SGID (Set-Group-ID) - 2775</strong>
                <p class="mb-0 small mt-2">This directory uses SGID to auto-assign the group. Any new files created here automatically get <code><?= htmlspecialchars($check['required_group']) ?></code> as their group, displayed as lowercase <code>s</code> in the permissions: <code>drwxrwsr-x</code>.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($check !== end($to_show)): ?>
        <hr class="my-2">
        <?php endif; ?>
        <?php endforeach; ?>

        <?php
        // Every fix in this section as one paste, so a 5-file problem is one command
        // block instead of five copy-pastes. Mirrors the Assembly/FASTA sections below.
        // Deduped: a shared remedy (e.g. fix_moop_selinux.sh) should appear once.
        $group_cmds = [];
        foreach ($failing as $f) {
            foreach (moop_permission_fix_commands($f, $moop_owner) as $c) {
                $group_cmds[] = $c;
            }
        }
        $group_cmds = array_values(array_unique($group_cmds));
        ?>
        <?php if (count($failing) > 1 && $group_cmds): ?>
        <div class="mt-3 pt-3 border-top">
            <p class="mb-2"><strong>Fix all <?= count($failing) ?> in this section at once:</strong></p>
            <div class="fix-command">
                <?php foreach ($group_cmds as $c): ?>
                <?= htmlspecialchars($c) ?><br>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
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
                <small>
                    Current: <?= htmlspecialchars($issue['current_perms']) ?> (group: <?= htmlspecialchars($issue['current_group']) ?>)
                    <?php if (!empty($issue['issues'])): ?>
                        — <?= htmlspecialchars(implode('; ', $issue['issues'])) ?>
                    <?php endif; ?>
                </small>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="mb-2"><strong>To fix all assembly directories, run:</strong></p>
        <div class="fix-command">
            <?php // NOT `chmod -R 2775`: that sets FILES to 775 (executable) too, which is how
                  // executable organism.json/organism.sqlite files got created. Split dirs/files. ?>
            sudo chgrp -R <?= htmlspecialchars($web_group) ?> <?= escapeshellarg($organism_data) ?><br>
            sudo find <?= escapeshellarg($organism_data) ?> -type d -exec chmod 2775 {} +<br>
            sudo find <?= escapeshellarg($organism_data) ?> -type f -exec chmod a-x {} +
        </div>
        <?php endif; ?>

        <?php if (!empty($fasta_file_issues)): ?>
        <h6 class="mb-3"><i class="fa fa-dna"></i> FASTA Files</h6>
        <p class="text-danger mb-3"><strong>⚠️ Some FASTA files have permission issues:</strong></p>
        <div class="mb-3">
            <?php foreach ($fasta_file_issues as $issue): ?>
            <div class="alert alert-warning mb-2">
                <strong><?= htmlspecialchars($issue['path']) ?></strong><br>
                <small>
                    Current: <?= htmlspecialchars($issue['current_perms']) ?> (group: <?= htmlspecialchars($issue['current_group']) ?>)
                    <?php if (!empty($issue['issues'])): ?>
                        — <?= htmlspecialchars(implode('; ', $issue['issues'])) ?>
                    <?php endif; ?>
                </small>
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
                    $patterns[] = '-name ' . escapeshellarg(basename($pattern));
                }
            }
            // Parenthesise: with -o, an unparenthesised -exec binds only to the LAST -name.
            $find_patterns = $patterns ? '\( ' . implode(' -o ', $patterns) . ' \)' : '';
            ?>
            <?php // NOT `chmod 644`: 644 is WORLD-readable, which is wrong for restricted gene
                  // sets. FASTA only needs to be group-readable, not world-writable, not executable.
                  // -exec …{} + instead of xargs: filenames here contain spaces and colons. ?>
            sudo find <?= escapeshellarg($organism_data) ?> <?= $find_patterns ?> -exec chgrp <?= htmlspecialchars($web_group) ?> {} +<br>
            sudo find <?= escapeshellarg($organism_data) ?> <?= $find_patterns ?> -exec chmod g+r,o-w,a-x {} +
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
