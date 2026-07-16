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
          <li>Keep organisms discoverable on the homepage selector (handled automatically by cache refresh)</li>
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
        <br><small class="text-muted">Config, metadata, and organism files are checked for changes on every admin login and copied to <code><?= htmlspecialchars($site_data_backup['path']) ?></code>.</small>
        <?php if ($site_data_backup['is_git'] && !empty($site_data_backup['git'])): $g = $site_data_backup['git']; ?>
          <div class="mt-2 pt-2 border-top">
            <?php if ($g['clean']): ?>
              <span class="text-success"><i class="fa fa-check-circle"></i>
                <strong>All committed<?= $g['has_upstream'] ? ' &amp; pushed' : '' ?></strong></span>
              <small class="text-muted ms-1"><?= (int) $g['commits'] ?> commit<?= $g['commits'] == 1 ? '' : 's' ?>
                &middot; last <?= htmlspecialchars($g['last_commit']) ?><?= $g['has_upstream'] ? '' : ' &middot; no remote configured' ?></small>
            <?php else: ?>
              <span class="text-warning"><i class="fa fa-exclamation-triangle"></i>
                <strong>Backup not synced</strong></span>
              <small class="text-muted ms-1"><?php
                $bits = [];
                if ($g['uncommitted'] > 0) $bits[] = $g['uncommitted'] . ' file' . ($g['uncommitted'] == 1 ? '' : 's') . ' to commit';
                if ($g['ahead'] > 0)       $bits[] = $g['ahead'] . ' commit' . ($g['ahead'] == 1 ? '' : 's') . ' to push';
                if (!$g['has_upstream'])   $bits[] = 'no remote configured';
                echo htmlspecialchars(implode(', ', $bits));
              ?></small>
              <br><small class="text-muted d-block mt-1">
                <code style="font-size: 0.85em;">cd <?= htmlspecialchars($site_data_backup['path']) ?> &amp;&amp; git add -A &amp;&amp; git commit -m "Site data backup"<?= $g['has_upstream'] ? ' &amp;&amp; git push' : '' ?></code>
              </small>
            <?php endif; ?>
          </div>
        <?php elseif ($site_data_backup['is_git']): ?>
          <span class="badge bg-secondary ms-2">Git available</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Data Health Issues (shared partial — identical card on the Manage Organisms page) -->
  <?php include __DIR__ . '/_data_health_card.php'; ?>

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

  <!-- Filesystem Permissions pointer — a router to the detail page, grouped by CATEGORY
       (one finding per section, not per file) and phrased by the worst severity PRESENT
       (never "0 high"). Precomputed once per housekeeping interval into
       logs/.housekeeping_status.json — NOT scanned on dashboard load. Hidden when clean. -->
  <?php if (!empty($_SESSION['perm_summary']['findings'])):
    $ps       = $_SESSION['perm_summary'];
    $worst    = $ps['worst'] ?? 'medium';
    $cls      = $worst === 'high' ? 'danger' : ($worst === 'medium' ? 'warning' : 'secondary');
    $icon     = $worst === 'high' ? 'lock' : ($worst === 'medium' ? 'exclamation-triangle' : 'info-circle');
    // Findings at the worst tier lead; anything below is a muted footnote.
    $top      = array_values(array_filter($ps['findings'], fn($f) => $f['severity'] === $worst));
    $below    = count($ps['findings']) - count($top);
    $labels   = array_map(fn($f) => $f['category'] . ' (' . $f['count'] . ')', $top);
    $headline = $worst === 'high'
        ? 'permission ' . (count($top) === 1 ? 'area needs' : 'areas need') . ' attention — may break tools, logging, or expose a secret'
        : ($worst === 'medium'
            ? 'permission ' . (count($top) === 1 ? 'area' : 'areas') . ' to address'
            : 'low-priority permission ' . (count($top) === 1 ? 'item' : 'items') . ' to tidy');

    // Staleness note. This is a cached scan, so a finding may already be fixed — that
    // is not hypothetical: the banners label was repaired ~5 min after a scan and the
    // card kept reporting it. Show age RELATIVE, never absolute: PHP's date.timezone is
    // typically unset (UTC) while the host clock is local, so a printed wall-clock time
    // reads hours off. The Permission Manager re-scans live on every load.
    $iv_hrs  = (int) round((defined('HOUSEKEEPING_MIN_INTERVAL') ? HOUSEKEEPING_MIN_INTERVAL : 4 * 3600) / 3600);
    $age_s   = !empty($ps['checked_at']) ? max(0, time() - strtotime($ps['checked_at'])) : null;
    $scanned = $age_s === null
        ? null
        : ($age_s < 3600
            ? max(1, (int) round($age_s / 60)) . ' min ago'
            : (int) floor($age_s / 3600) . ' hr ago');
  ?>
  <div class="alert alert-<?= $cls ?> d-flex align-items-center justify-content-between gap-3 mb-4" role="alert">
    <div>
      <i class="fa fa-<?= $icon ?> me-2"></i>
      <strong><?= count($top) ?> <?= $headline ?></strong>
      <span class="d-block small text-muted">
        <?= htmlspecialchars(implode(', ', $labels)) ?><?php if ($below > 0): ?> &middot; <?= $below ?> lower-priority <?= $below === 1 ? 'area' : 'areas' ?><?php endif; ?>
      </span>
      <span class="d-block small text-muted fst-italic mt-1">
        <i class="fa fa-clock-o"></i>
        <?php if ($scanned): ?>Scanned <?= htmlspecialchars($scanned) ?><?php else: ?>Cached scan<?php endif; ?>
        &middot; rescanned every <?= $iv_hrs ?> hr, so anything already fixed may still be listed here.
        The Permission Manager re-checks live.
      </span>
    </div>
    <a href="manage_filesystem_permissions.php" class="btn btn-sm btn-<?= $cls ?> flex-shrink-0">
      <i class="fa fa-lock"></i> Permission Manager
    </a>
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
      $generated   = $cache_info['generated'] ?? null;
      $org_count   = $cache_info['organism_count'] ?? 0;
      $refreshing  = $cache_info['refreshing'] ?? false;
      $stale       = ($cache_stale ?? false) && !$refreshing;
      $changed     = $cache_changed_orgs ?? [];
      $age_str     = 'never built';
      if ($generated) {
          $sec = time() - strtotime($generated);
          if ($sec < 60)        $age_str = $sec . 's ago';
          elseif ($sec < 3600)  $age_str = floor($sec/60) . 'm ago';
          elseif ($sec < 86400) $age_str = floor($sec/3600) . 'h ago';
          else                  $age_str = floor($sec/86400) . 'd ago';
      }
      // The "cache out of date" warning + changed-organism list live in the Data Health
      // card above; this widget just shows status + the refresh control + progress bar.
      $alert_class = !$generated ? 'alert-warning' : 'alert-secondary';
    ?>
    <div class="alert <?= $alert_class ?> mb-4">
      <div class="d-flex align-items-center justify-content-between gap-3">
        <div>
          <i class="fa fa-sync-alt me-2"></i>
          <strong>Organism Cache</strong> —
          <span id="dashCacheSummary">
            <?php if ($refreshing): ?>
              <span class="text-primary"><i class="fa fa-spinner fa-spin"></i> Refresh in progress…</span>
            <?php elseif (!$generated): ?>
              Cache not built yet — organism data may not be visible
            <?php elseif ($stale): ?>
              <?= $org_count ?> organisms, built <strong><?= htmlspecialchars($age_str) ?></strong>
            <?php else: ?>
              <i class="fa fa-check text-success"></i> Up to date — <?= $org_count ?> organisms, built <strong><?= htmlspecialchars($age_str) ?></strong>
            <?php endif; ?>
          </span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <button id="dashRefreshBtn"
                  class="btn btn-sm <?= (!$generated || $stale) ? 'btn-warning' : 'btn-outline-secondary' ?>"
                  onclick="startOrganismCacheRefresh(this)"
                  <?= $refreshing ? 'disabled' : '' ?>>
            <i class="fa fa-sync-alt"></i> <?= $refreshing ? 'Refreshing…' : 'Update Cache' ?>
          </button>
          <a href="manage_organisms.php" class="btn btn-sm btn-outline-primary">
            <i class="fa fa-list"></i> View Organisms
          </a>
        </div>
      </div>
      <div id="dashCacheProgressWrap" class="mt-2" style="display: <?= $refreshing ? 'block' : 'none' ?>;">
        <div class="progress" style="height: 6px;">
          <div id="dashCacheProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
        </div>
        <small class="text-muted" id="dashCacheProgressText">Checking status…</small>
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

      <div class="col-md-6 mb-3">
        <div class="card h-100 border-primary">
          <div class="card-header bg-primary bg-opacity-10">
            <h5 class="card-title mb-0"><i class="fa fa-external-link-alt"></i> BLAST Linkouts</h5>
          </div>
          <div class="card-body">
            <p class="card-text">Configure which linkout buttons appear on BLAST results: gene page, JBrowse, and custom external URLs with placeholder support.</p>
            <a href="manage_blast_linkouts.php" class="btn btn-primary">Manage Linkouts</a>
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
