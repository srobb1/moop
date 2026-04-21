<?php
/**
 * ADMIN DASHBOARD - Content File
 * 
 * Pure display content - no HTML structure, scripts, or styling.
 * 
 * Layout system (layout.php) handles:
 * - HTML structure (<!DOCTYPE>, <html>, <head>, <body>)
 * - All CSS and resources
 * - All scripts
 * - Navbar and footer
 * 
 * This file has access to variables passed from admin.php:
 * - $config (ConfigManager instance)
 * - $site (site name)
 */
?>

<div class="container mt-5">
  <h2><i class="fa fa-tools"></i> Admin Tools</h2>
  
  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutAdminTools">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Admin Tools <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutAdminTools">
      <div class="card-body">
        <p><strong>Purpose:</strong> Central navigation hub for all administrative functions.</p>
        
        <p><strong>Why This Matters:</strong> This is the entry point for managing your MOOP system. Use these tools to:</p>
        <ul>
          <li>Control who has access to what organisms</li>
          <li>Manage your organism data and metadata</li>
          <li>Organize annotations for display</li>
          <li>Build the taxonomy tree for discovery</li>
          <li>Maintain user accounts and permissions</li>
          <li>Monitor system health and errors</li>
        </ul>
        
        <p><strong>Available Tools:</strong></p>
        <ul class="mb-0">
          <li><strong>New Organism Setup Checklist</strong> - Step-by-step guide for adding new organisms with links to admin tools</li>
          <li><strong>Manage Site Configuration</strong> - Edit site title, admin email, and appearance settings</li>
          <li><strong>Manage Users</strong> - Create collaborator accounts and control access</li>
          <li><strong>Manage Organisms</strong> - View and manage all organism data</li>
          <li><strong>Manage Groups</strong> - Tag organisms with flexible categories</li>
          <li><strong>Manage Annotations</strong> - Customize annotation display</li>
          <li><strong>Manage Taxonomy Tree</strong> - Build the organism selector</li>
          <li><strong>Error Logs</strong> - Monitor system health</li>
          <li><strong>PHP Function Registry</strong> - Maintain PHP code documentation</li>
          <li><strong>JavaScript Function Registry</strong> - Maintain JavaScript code documentation</li>
          <li><strong>Filesystem Permissions</strong> - Check and fix file permissions</li>
        </ul>
      </div>
    </div>
  </div>
  
  <!-- Site Data Backup Status -->
  <?php if ($site_data_backup !== null): ?>
    <?php if ($site_data_backup['status'] === 'error'): ?>
    <div class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-exclamation-triangle me-2"></i>
      <div><strong>Backup issue:</strong> <?= $site_data_backup['message'] ?></div>
    </div>
    <?php elseif ($site_data_backup['status'] === 'ok'): ?>
    <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
      <i class="fa fa-check-circle me-2"></i>
      <div class="w-100">
        <strong>Site data backup active</strong> &mdash;
        last run: <?= htmlspecialchars($site_data_backup['last_run']) ?>,
        <?= $site_data_backup['files_copied'] ?> file(s) updated
        <?php if ($site_data_backup['is_git']): ?>
          <span class="badge bg-secondary ms-2">Git available</span>
          <br><small class="text-muted mt-1 d-block">
            Backup directory is a git repository. To version these changes, run:<br>
            <code style="font-size: 0.85em;">cd <?= htmlspecialchars($site_data_backup['backup_path'] ?? '/path/to/backup') ?> && git add -A && git commit -m "Site data backup" && git push</code>
          </small>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Environment Warnings -->
  <?php if (!empty($_SESSION['env_warnings'])): ?>
  <div class="card mb-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#envWarnings">
      <h5 class="mb-0">
        <i class="fa fa-exclamation-triangle text-warning"></i>
        Environment Issues (<?= count($_SESSION['env_warnings']) ?>)
        <i class="fa fa-chevron-down float-end"></i>
      </h5>
    </div>
    <div class="collapse show" id="envWarnings">
      <div class="card-body p-0">
        <?php foreach ($_SESSION['env_warnings'] as $warning): ?>
        <div class="alert alert-<?= $warning['level'] ?> mb-0 border-0 rounded-0 border-bottom">
          <i class="fa fa-<?= $warning['level'] === 'danger' ? 'times-circle' : 'exclamation-circle' ?> me-2"></i>
          <?= $warning['message'] ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- System Configuration -->
  <div class="mt-5">
    <h3 class="mb-3"><i class="fa fa-cog"></i> System Configuration</h3>
    <div class="row">
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-cog"></i> Manage Site Configuration</h5>
            <p class="card-text">Edit site settings like title, branding, and admin contact information.</p>
            <a href="manage_site_config.php" class="btn btn-primary">Go to Site Configuration</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-users"></i> Manage Users</h5>
            <p class="card-text">Add new users to the system and manage user accounts.</p>
            <a href="manage_users.php" class="btn btn-primary">Go to Manage Users</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Organism Cache Status -->
  <div class="mt-5">
    <h3 class="mb-3"><i class="fa fa-database"></i> Data Management</h3>

    <?php
      $generated  = $cache_info['generated'] ?? null;
      $org_count  = $cache_info['organism_count'] ?? 0;
      $refreshing = $cache_info['refreshing'] ?? false;
      $age_str    = 'never built';
      if ($generated) {
          $sec = time() - strtotime($generated);
          if ($sec < 60)        $age_str = $sec . 's ago';
          elseif ($sec < 3600)  $age_str = floor($sec/60) . 'm ago';
          elseif ($sec < 86400) $age_str = floor($sec/3600) . 'h ago';
          else                  $age_str = floor($sec/86400) . 'd ago';
      }
    ?>
    <div class="alert <?= $generated ? 'alert-secondary' : 'alert-warning' ?> d-flex align-items-center justify-content-between gap-3 mb-4">
      <div>
        <i class="fa fa-sync-alt me-2"></i>
        <strong>Organism Cache</strong> —
        <?php if ($refreshing): ?>
          <span class="text-primary"><i class="fa fa-spinner fa-spin"></i> Refresh in progress…</span>
        <?php elseif ($generated): ?>
          <?= $org_count ?> organisms, last updated <strong><?= htmlspecialchars($age_str) ?></strong>
        <?php else: ?>
          Cache not built yet — organism data may not be visible
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <span id="dashCacheStatus" class="text-muted small" style="display:none;"></span>
        <button id="dashRefreshBtn"
                class="btn btn-sm <?= $generated ? 'btn-outline-secondary' : 'btn-warning' ?>"
                onclick="refreshOrganismCache(this, document.getElementById('dashCacheStatus'))"
                <?= $refreshing ? 'disabled' : '' ?>>
          <i class="fa fa-sync-alt"></i> <?= $refreshing ? 'Refreshing…' : 'Update Cache' ?>
        </button>
        <a href="manage_organisms.php" class="btn btn-sm btn-outline-primary">
          <i class="fa fa-list"></i> View Organisms
        </a>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <div class="card h-100 border-success">
          <div class="card-header bg-success bg-opacity-10">
            <h5 class="card-title mb-0"><i class="fa fa-clipboard-list"></i> New Organism Setup Checklist</h5>
          </div>
          <div class="card-body">
            <p class="card-text">Step-by-step guide for adding a new organism with quick links to all required admin tools.</p>
            <a href="organism_checklist.php" class="btn btn-success">Go to Setup Checklist</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-dna"></i> Manage Organisms</h5>
            <p class="card-text">View current organisms, assemblies, and learn how to add new data.</p>
            <a href="manage_organisms.php" class="btn btn-success">Go to Manage Organisms</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-layer-group"></i> Manage Groups</h5>
            <p class="card-text">Configure organism assembly groups and group descriptions.</p>
            <a href="manage_groups.php" class="btn btn-success">Go to Manage Groups</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-tags"></i> Manage Annotation Sections</h5>
            <p class="card-text">Configure annotation section types and descriptions.</p>
            <a href="manage_annotations.php" class="btn btn-success">Go to Annotation Sections</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-project-diagram"></i> Manage Taxonomy Tree</h5>
            <p class="card-text">Generate and customize the taxonomy tree from organism taxonomy data.</p>
            <a href="manage_taxonomy_tree.php" class="btn btn-success">Go to Taxonomy Tree</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100 border-primary">
          <div class="card-header bg-primary bg-opacity-10">
            <h5 class="card-title mb-0"><i class="fa fa-dna"></i> JBrowse Track Management</h5>
          </div>
          <div class="card-body">
            <p class="card-text">Manage JBrowse tracks and configurations. Register Google Sheets, sync tracks, validate URLs, and monitor track access.</p>
            <a href="manage_jbrowse.php" class="btn btn-primary">Go to JBrowse Management</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Developer & Maintenance -->
  <div class="mt-5">
    <h3 class="mb-3"><i class="fa fa-wrench"></i> Developer & Maintenance</h3>
    <div class="row">
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-code"></i> PHP Function Registry</h5>
            <p class="card-text">Manage and view all PHP functions. Update, search, and track usage.</p>
            <a href="manage_registry.php" class="btn btn-info">Manage PHP Registry</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-file-code"></i> JavaScript Function Registry</h5>
            <p class="card-text">Manage and view all JavaScript functions. Update, search, and track usage.</p>
            <a href="manage_js_registry.php" class="btn btn-info">Manage JS Registry</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-lock"></i> Filesystem Permissions</h5>
            <p class="card-text">Complete guide to file and directory permissions. Check and fix permission issues.</p>
            <a href="manage_filesystem_permissions.php" class="btn btn-info">Check Permissions</a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-exclamation-triangle"></i> Error Logs</h5>
            <p class="card-text">View and manage application error logs for debugging and monitoring.</p>
            <a href="manage_error_log.php" class="btn btn-info">View Error Logs</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
