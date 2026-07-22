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
  <div class="card mb-4">
    <div class="card-header adm-head" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutAdminTools">
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
  
  <!-- Everything from here to the housekeeping card answers "is the site healthy and how
       current are these figures" — backup state, data-health alerts, environment and
       permission warnings, cache freshness, and the control that refreshes them. It had no
       heading while every other group on this page does, so the cards read as loose items
       above the first real section rather than as a group of their own. -->
  <h3 class="mb-3"><i class="fa fa-heartbeat"></i> System Status</h3>

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
        <?php
          // Recomputed LIVE on every load, not read from the housekeeping snapshot.
          // Everything else on this page is precomputed because it is expensive — the
          // permission sweep and the organism-tree walk cost hundreds of ms. This costs
          // ~10ms (git status --porcelain 5ms + ahead count 4ms), so it never needed to
          // inherit a 4-hour staleness. It did, and the result was a badge that still read
          // "3 files to commit" after an admin had committed and pushed, with no way to
          // tell whether the number was stale or the push had failed. A figure this cheap
          // should simply be true.
          $g = null;
          if (!empty($site_data_backup['is_git']) && function_exists('housekeeping_git_status')) {
              $g = housekeeping_git_status($site_data_backup['path']);
          }
        ?>
        <?php if ($site_data_backup['is_git'] && !empty($g)): ?>
          <div class="mt-2 pt-2 border-top">
            <?php // Git icon, not a second check-circle: this line sits inside an alert that
                  // already leads with a ✓, so two ticks just read as "success success". The
                  // green text carries the state; the icon should say WHAT this line is about. ?>
            <?php if ($g['clean']): ?>
              <span class="text-success"><i class="fa fa-code-branch"></i>
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
                <?php
                  // ONE command that is correct in every state, rather than one chosen from the
                  // state at render time — housekeeping copies files in the background, so the
                  // state can change between this page rendering and the admin running the command.
                  //
                  // The original was "cd … && git add -A && git commit && git push", which silently
                  // skipped the push whenever there was nothing to commit: git commit exits non-zero
                  // and && short-circuits. The admin sees "nothing to commit, working tree clean",
                  // concludes they are done, and the badge still says not synced.
                  //
                  // `|| true` on the commit is what fixes it, NOT dropping the commit or using `;`.
                  // A bare `;` before push would also run the push if the `cd` itself failed, i.e.
                  // in whatever directory the shell happened to be in.
                  $_cmd = "cd " . $site_data_backup['path']
                        . " && git add -A"
                        . " && (git commit -m \"Site data backup\" || true)"
                        . ($g['has_upstream'] ? " && git push" : "");
                ?>
                <code style="font-size: 0.85em;"><?= htmlspecialchars($_cmd) ?></code>
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
    <div class="card-header adm-head-warn" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#envWarnings">
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

  <?php
  // Housekeeping freshness — computed OUTSIDE the permission-card conditional below,
  // because the "Run housekeeping now" row uses it too and must stay visible when the
  // cards are clean. Age is shown RELATIVE, never absolute: PHP's date.timezone is
  // typically unset (UTC) while the host clock is local, so a printed wall-clock time
  // reads hours off.
  $iv_hrs  = (int) round((defined('HOUSEKEEPING_MIN_INTERVAL') ? HOUSEKEEPING_MIN_INTERVAL : 4 * 3600) / 3600);
  $_hk_at  = $_SESSION['perm_summary']['checked_at'] ?? null;
  $age_s   = $_hk_at ? max(0, time() - strtotime($_hk_at)) : null;
  $scanned = $age_s === null
      ? null
      : ($age_s < 3600
          ? max(1, (int) round($age_s / 60)) . ' min ago'
          : (int) floor($age_s / 3600) . ' hr ago');
  ?>

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
  ?>
  <div class="alert alert-<?= $cls ?> d-flex align-items-center justify-content-between gap-3 mb-4" role="alert">
    <div>
      <i class="fa fa-<?= $icon ?> me-2"></i>
      <strong><?= count($top) ?> <?= $headline ?></strong>
      <span class="d-block small text-muted">
        <?= htmlspecialchars(implode(', ', $labels)) ?><?php if ($below > 0): ?> &middot; <?= $below ?> lower-priority <?= $below === 1 ? 'area' : 'areas' ?><?php endif; ?>
      </span>
      <span class="d-block small text-muted fst-italic mt-1">
        <i class="fa fa-clock"></i>
        <?php if ($scanned): ?>Scanned <?= htmlspecialchars($scanned) ?><?php else: ?>Cached scan<?php endif; ?>
        &middot; the Permission Manager re-checks live.
      </span>
    </div>
    <a href="manage_filesystem_permissions.php" class="btn btn-sm btn-<?= $cls ?> flex-shrink-0">
      <i class="fa fa-lock"></i> Permission Manager
    </a>
  </div>
  <?php endif; ?>


  <!-- Housekeeping freshness + manual re-run.
       Sits with the health cards above because it is what refreshes them, and stays
       visible when they are all clean — "did my fix work?" is exactly when you want it.
       The staleness explanation lives HERE rather than on each card: every card above is
       precomputed on the same interval, and saying it once beats four copies drifting.
       The task list is rendered from housekeeping_task_registry() — the same list that
       RUNS them — so it cannot drift out of date. -->
  <div class="card mb-4">
    <div class="card-body py-2">
      <?php if (function_exists('housekeeping_is_running') && housekeeping_is_running()): ?>
      <!-- A background run is in flight RIGHT NOW. The numbers on this page were rendered
           before it finishes, so say so plainly rather than let the admin wonder why their
           fix is still listed. This is the honest face of the async trade-off. -->
      <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-2" role="status">
        <span class="spinner-border spinner-border-sm flex-shrink-0"></span>
        <div class="small">
          <strong>Housekeeping is running.</strong> These figures were taken before it started —
          <a href="">reload</a> in a few seconds.
        </div>
      </div>
      <?php endif; ?>

    <!-- JBrowse2 version. Same row as the organism cache and housekeeping freshness: all
         three answer "how current is this install". Always visible, like the cache widget,
         because "you are on the latest" is worth seeing — a card that only appears when
         something is wrong cannot tell you the check is actually running.
         Report-only: upgrading is a CLI job (docs/JBrowse2/UPGRADING.md), so there is no
         button here. The figure is precomputed by housekeeping; GitHub is contacted at most
         once a week, so `checked_at` is stated rather than implied. -->
    <?php if (!empty($jbrowse_version['current'])): ?>
      <?php
        $jb_cur  = $jbrowse_version['current'];
        $jb_new  = $jbrowse_version['latest']   ?? null;
        $jb_when = $jbrowse_version['checked_at'] ?? null;
        $jb_old  = !empty($jbrowse_version['outdated']);
        $jb_age  = '';
        if ($jb_when) {
            $d = floor((time() - strtotime($jb_when)) / 86400);
            $jb_age = $d < 1 ? 'today' : ($d == 1 ? 'yesterday' : $d . 'd ago');
        }
      ?>
      <div class="<?= $jb_old ? 'alert alert-warning' : '' ?> mb-2">
        <div class="d-flex align-items-center justify-content-between gap-3">
          <div>
            <i class="fa fa-dna me-2"></i>
            <strong>JBrowse2</strong> —
            <?php if ($jb_old): ?>
              version <strong><?= htmlspecialchars($jb_cur) ?></strong>, but
              <strong><?= htmlspecialchars($jb_new) ?></strong> is available
            <?php elseif ($jb_new): ?>
              <i class="fa fa-check text-success"></i> Up to date — version
              <strong><?= htmlspecialchars($jb_cur) ?></strong>
            <?php else: ?>
              version <strong><?= htmlspecialchars($jb_cur) ?></strong>
              <span class="text-muted">— latest release not checked yet</span>
            <?php endif; ?>
            <?php if ($jb_age): ?>
              <span class="text-muted small">(checked <?= htmlspecialchars($jb_age) ?>)</span>
            <?php endif; ?>
          </div>
          <div class="flex-shrink-0">
            <a href="<?= $jb_old
                        ? '../docs/JBrowse2/UPGRADING.md'
                        : 'https://github.com/GMOD/jbrowse-components/releases' ?>"
               class="btn btn-sm <?= $jb_old ? 'btn-warning' : 'btn-outline-secondary' ?>"
               <?= $jb_old ? '' : 'target="_blank" rel="noopener"' ?>>
              <i class="fa fa-book"></i> <?= $jb_old ? 'How to upgrade' : 'Release notes' ?>
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Organism cache freshness. Sits with the housekeeping card below rather than under
         Data Management: both answer "how current are the figures on this page", and the
         cache status alone above a row of navigation cards read as an orphan. Element IDs
         are unchanged — the refresh JS in admin/admin.php looks everything up by id. -->
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
    <div class="<?= $alert_class === 'alert-warning' ? 'alert alert-warning' : '' ?> mb-2">
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

      <?php
        // Whether ANY health card actually rendered above. When everything is clean they are
        // all hidden, and the old wording ("Health checks above are cached") pointed at an
        // empty page — exactly the state an admin is in when checking whether a fix landed.
        // $has_health_issues is set by _data_health_card.php and stays in scope after it.
        $_cards_shown = !empty($has_health_issues)
                     || !empty($_SESSION['perm_summary']['findings'])
                     || !empty($_SESSION['env_warnings']);
      ?>
      <div class="d-flex align-items-center justify-content-between gap-3">
        <div class="small text-muted">
          <i class="fa fa-clock me-1"></i>
          <?php if ($_cards_shown): ?>
          Health checks above are cached<?php if ($scanned): ?> — last run
          <strong><?= htmlspecialchars($scanned) ?></strong><?php endif; ?>,
          so a recent fix may still be listed.
          <?php else: ?>
          <i class="fa fa-check-circle text-success"></i>
          All health checks passed<?php if ($scanned): ?> as of
          <strong><?= htmlspecialchars($scanned) ?></strong><?php endif; ?>.
          Results are cached, so a problem introduced since then may not be listed yet.
          <?php endif; ?>
          <a class="ms-1" data-bs-toggle="collapse" href="#housekeepingTasks" role="button"
             aria-expanded="false" aria-controls="housekeepingTasks" style="cursor:pointer;">
            What runs? <i class="fa fa-chevron-down small"></i>
          </a>
        </div>
        <div class="flex-shrink-0">
          <button type="button" id="rerunHousekeepingBtn" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-sync"></i> Run housekeeping now
          </button>
        </div>
      </div>

      <div class="collapse" id="housekeepingTasks">
        <hr class="my-2">
        <p class="small text-muted mb-2">
          <strong>Nothing to set up — no cron.</strong> These run in the background when an
          admin loads a page, at most once
          <?= $iv_hrs === 1 ? 'an hour' : 'every ' . $iv_hrs . ' hours' ?>,
          and never slow it down. New figures appear on your next visit.
        </p>
        <?php foreach (housekeeping_task_registry() as $_t): ?>
        <div class="mb-2">
          <div class="small"><strong><?= htmlspecialchars($_t['label']) ?></strong>
            <code class="text-muted ms-1"><?= htmlspecialchars($_t['name']) ?></code>
          </div>
          <div class="small text-muted"><?= htmlspecialchars($_t['desc']) ?></div>
        </div>
        <?php endforeach; ?>
        <p class="small text-muted mb-0 fst-italic">
          Defined in <code>lib/housekeeping.php</code>.
        </p>
      </div>
    </div>
  </div>
  <div id="rerunHousekeepingResult" class="small mt-2 mb-4"></div>

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


    <div class="row">
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-header adm-head">
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
            <h5 class="card-title"><i class="fa fa-book"></i> Manage Glossary</h5>
            <p class="card-text">Define the dashed-underline help terms shown across the site.</p>
            <a href="manage_glossary.php" class="btn btn-success">Go to Manage Glossary</a>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-header adm-head">
            <h5 class="card-title mb-0"><i class="fa fa-dna"></i> JBrowse Track Management</h5>
          </div>
          <div class="card-body">
            <p class="card-text">Manage JBrowse tracks and configurations. Register Google Sheets, sync tracks, validate URLs, and monitor track access.</p>
            <a href="manage_jbrowse.php" class="btn btn-primary">Go to JBrowse Management</a>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-header adm-head">
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
