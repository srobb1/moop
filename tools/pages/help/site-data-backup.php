<?php
/**
 * SITE DATA BACKUP - Admin Help Tutorial
 *
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
$backup_path = $config->getPath('site_data_path');
?>

<div class="container mt-5">
  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-4"><i class="fa fa-save"></i> Site Data Backup</h1>

      <!-- Overview -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">What Is the Site Data Backup?</h3>
          <p class="text-muted mb-3">
            MOOP automatically snapshots your site-specific configuration and metadata on every
            admin login. This keeps a safe copy of your settings <em>separate from the application
            code</em>, so you can update MOOP (or even reinstall it) without losing your
            customizations, user accounts, or organism metadata.
          </p>
          <p class="text-muted mb-0">
            The backup runs silently as part of the housekeeping system — no cron jobs or manual
            steps required. Status is shown on the Admin Dashboard after each login.
          </p>
        </div>
      </div>

      <!-- What Gets Backed Up -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">What Gets Backed Up</h3>
          <p class="text-muted mb-3">
            Only files that have actually changed since the last backup are copied (content-diff
            check). The following files are included:
          </p>
          <div class="table-responsive">
            <table class="table table-bordered table-sm">
              <thead class="table-light">
                <tr>
                  <th>File</th>
                  <th>Purpose</th>
                </tr>
              </thead>
              <tbody>
                <tr><td><code>config/config_editable.json</code></td><td>Admin-edited site settings (title, branding, features)</td></tr>
                <tr><td><code>config/secrets.php</code></td><td>API keys and credentials</td></tr>
                <tr><td><code>metadata/annotation_config.json</code></td><td>Annotation display configuration</td></tr>
                <tr><td><code>metadata/group_descriptions.json</code></td><td>Organism group definitions</td></tr>
                <tr><td><code>metadata/organism_assembly_groups.json</code></td><td>Which organisms belong to which groups (visibility)</td></tr>
                <tr><td><code>metadata/taxonomy_tree_config.json</code></td><td>Taxonomy tree structure</td></tr>
                <tr><td><code>users.json</code></td><td>User accounts and access levels</td></tr>
                <tr><td><code>organisms/{name}/organism.json</code></td><td>Per-organism metadata — one file per organism (genus, species, taxon ID, feature types)</td></tr>
              </tbody>
            </table>
          </div>
          <div class="alert alert-secondary mt-3 mb-0">
            <strong>Not backed up here:</strong> genome sequences (<code>.fa</code>), SQLite
            databases, BLAST indexes, JBrowse2 track data, and log files. These are either too
            large or can be regenerated via the Admin panel.
          </div>
        </div>
      </div>

      <!-- Backup Location -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Backup Location</h3>
          <p class="text-muted mb-2">
            The backup directory is configured via <code>site_data_path</code> in
            <code>config/site_config.php</code>. The current configured path is:
          </p>
          <pre class="bg-light p-3 rounded mb-3"><?= htmlspecialchars($backup_path ?: '(not configured)') ?></pre>
          <p class="text-muted mb-0">
            The directory is created automatically on first admin login if it does not exist.
            To change the path, edit <code>site_data_path</code> in <code>config/site_config.php</code>
            (or override it in <code>config/config_editable.json</code>).
            Set it to an empty string to disable backups entirely.
          </p>
        </div>
      </div>

      <!-- Git Version History -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Optional: Git Version History</h3>
          <p class="text-muted mb-3">
            If you initialize the backup directory as a git repository, MOOP will show a
            <strong>Git available</strong> badge on the Admin Dashboard. MOOP does <em>not</em>
            auto-commit — you run git manually, giving you full control over when changes are
            versioned and pushed.
          </p>
          <p class="text-muted mb-2">To enable:</p>
          <pre class="bg-light p-3 rounded mb-3">cd <?= htmlspecialchars($backup_path ?: '/path/to/moop-site-data') ?>

git init -b main
git add -A
git commit -m "Initial snapshot"</pre>
          <p class="text-muted mb-2">
            To push to a remote for off-server backup (e.g., a private GitHub/GitLab repo):
          </p>
          <pre class="bg-light p-3 rounded mb-0">git remote add origin git@github.com:your-org/moop-site-data.git
git push -u origin main</pre>
        </div>
      </div>

      <!-- Dashboard Status -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Checking Backup Status</h3>
          <p class="text-muted mb-3">
            After each admin login, the Admin Dashboard shows a status banner:
          </p>
          <ul class="text-muted mb-3">
            <li><strong class="text-success">Green — Site data backup active:</strong> The backup ran successfully. Shows how many files were updated and the timestamp.</li>
            <li><strong>Git badge:</strong> Shown when the backup directory is a git repo; changes were auto-committed.</li>
            <li><strong class="text-warning">Yellow warning:</strong> The backup directory could not be created or a file copy failed — check directory permissions.</li>
          </ul>
          <p class="text-muted mb-0">
            The backup only runs once per admin session, so the count reflects changes since the
            previous login.
          </p>
        </div>
      </div>

      <!-- Security Note -->
      <div class="card shadow-sm border-danger border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-danger mb-3"><i class="fa fa-exclamation-triangle"></i> Keep This Directory Private</h3>
          <p class="text-muted mb-3">
            The backup directory contains <code>secrets.php</code> (API keys) and
            <code>users.json</code> (bcrypt-hashed passwords and access configuration).
            Ensure it is:
          </p>
          <ul class="text-muted mb-0">
            <li>Located <strong>outside the web root</strong>, or protected by your web server so it is not publicly accessible</li>
            <li>If stored in a git remote, use a <strong>private repository</strong></li>
            <li>File permissions set to <code>0750</code> (owner + group only) — this is the default when MOOP creates the directory</li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>
