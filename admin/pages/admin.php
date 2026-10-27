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
        <?php /* How the PAGE works and where common jobs live. No list of the tools: the Quick
                 Links and the cards at the foot are that list (lib/admin_tools.php), and the copy
                 that used to sit here had drifted -- it was missing Glossary, JBrowse and BLAST. */ ?>
        <p>This page answers two questions: <strong>is the site healthy</strong>, and <strong>where do I go to change something</strong>.</p>

        <h6 class="mt-3">Finding your way</h6>
        <ul>
          <li><strong>Quick Links</strong> (left, on every admin page) go straight to any tool. A number beside a
            link means something there needs attention — <span class="badge rounded-pill bg-danger">red</span> critical,
            <span class="badge rounded-pill bg-warning">amber</span> to fix. On <em>Dashboard</em> it counts OS security
            updates and library advisories, which have no page of their own.</li>
          <li><strong>System Status</strong> has one card per check, grouped Data, Security &amp; software, Server and
            Maintenance. The header states the result; click it for the detail.</li>
          <li>The <strong>tool cards</strong> at the foot of the page describe each tool in a sentence.</li>
        </ul>

        <h6 class="mt-3">How current the figures are</h6>
        <p>Most status figures come from <strong>housekeeping</strong>, which runs in the background at most once an
          hour, so a card can be up to an hour old. <em>Run housekeeping now</em> in the Housekeeping card refreshes
          them, and the page a card links to always re-checks live.</p>

        <h6 class="mt-3">Common jobs</h6>
        <ul class="mb-0">
          <li><strong>Add an organism</strong> — the Setup Checklist walks through every step.</li>
          <li><strong>Make data public</strong> — Groups: the <em>Visibility</em> toggle on each gene set.
            <strong>Give one person access</strong> — Users. Both are explained in
            <a href="/<?= htmlspecialchars($site) ?>/help.php?topic=access">Who can see what</a>.</li>
          <li><strong>An organism's About text or image</strong> — Organisms, the <i class="fa fa-file-alt"></i>
            icon in its row. Paragraphs written there <em>replace</em> the automatic Wikipedia text; leave them
            empty to keep it. The same goes for the image.</li>
          <li><strong>A group's description</strong> — Groups, <em>Retrieve &amp; Save</em> fetches it from Wikipedia.</li>
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

  <?php
  // One folding card per check (admin/pages/_status_card.php): the header states the
  // result, the detail folds away. Every check renders even when it is clean -- a check
  // that vanished when fine read exactly like one that never ran. Order: the data first,
  // then what serves it, then the machinery that keeps these figures current.
  include_once __DIR__ . '/_status_card.php';

  // Housekeeping freshness -- the permission card and the housekeeping card both use it.
  // Age is RELATIVE, never absolute: PHP's date.timezone is typically unset (UTC) while
  // the host clock is local, so a printed wall-clock time reads hours off.
  $iv_hrs  = (int) round((defined('HOUSEKEEPING_MIN_INTERVAL') ? HOUSEKEEPING_MIN_INTERVAL : 4 * 3600) / 3600);
  $_hk_at  = $_SESSION['perm_summary']['checked_at'] ?? null;
  $age_s   = $_hk_at ? max(0, time() - strtotime($_hk_at)) : null;
  $scanned = $age_s === null
      ? null
      : ($age_s < 3600
          ? max(1, (int) round($age_s / 60)) . ' min ago'
          : (int) floor($age_s / 3600) . ' hr ago');
  ?>

  <div class="adm-group-label">Data</div>
  <!-- Data Health (shared partial -- the same card on the Manage Organisms page). It renders
       nothing when there is nothing to report, so the clean state is drawn here. -->
  <?php $health_card_collapsible = true; ?>
  <?php include __DIR__ . '/_data_health_card.php'; ?>
  <?php if (empty($has_health_issues)): ?>
    <?= adm_status_card('dataHealth', 'notes-medical', 'Data Health', [['No issues', 'success']], '',
          '<p class="small text-muted mb-0">Every organism passed the data checks (databases, sequence files, '
          . 'gene sets, cache). The <a href="manage_organisms.php">Manage Organisms</a> page shows the same checks per organism.</p>') ?>
  <?php endif; ?>

  <!-- Organism cache. Element ids are unchanged: the refresh JS in admin/admin.php looks
       them up by id. Opened while a refresh runs, so its progress bar is visible. -->
  <?php
    $generated   = $cache_info['generated'] ?? null;
    $org_count   = $cache_info['organism_count'] ?? 0;
    $refreshing  = $cache_info['refreshing'] ?? false;
    $stale       = ($cache_stale ?? false) && !$refreshing;
    $age_str     = 'never built';
    if ($generated) {
        $sec = time() - strtotime($generated);
        if ($sec < 60)        $age_str = $sec . 's ago';
        elseif ($sec < 3600)  $age_str = floor($sec/60) . 'm ago';
        elseif ($sec < 86400) $age_str = floor($sec/3600) . 'h ago';
        else                  $age_str = floor($sec/86400) . 'd ago';
    }
    if ($refreshing)      { $_oc_badges = [['Refreshing…', 'info']];  $_oc_tone = ''; }
    elseif (!$generated)  { $_oc_badges = [['Not built', 'danger']];  $_oc_tone = 'danger'; }
    elseif ($stale)       { $_oc_badges = [['Out of date', 'warning']]; $_oc_tone = 'warn'; }
    else                  { $_oc_badges = [['Up to date', 'success']]; $_oc_tone = ''; }
    if ($generated) $_oc_badges[] = [$org_count . ' organisms · built ' . $age_str, 'secondary'];
    ob_start();
  ?>
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
      <div id="dashCacheSummary" class="small">
        <?php if ($refreshing): ?>
          <span class="text-primary"><i class="fa fa-spinner fa-spin"></i> Refresh in progress…</span>
        <?php elseif (!$generated): ?>
          Cache not built yet — organism data may not be visible.
        <?php elseif ($stale): ?>
          <?= $org_count ?> organisms, built <strong><?= htmlspecialchars($age_str) ?></strong> — some
          organisms changed since; the Data Health card lists which.
        <?php else: ?>
          <?= $org_count ?> organisms, built <strong><?= htmlspecialchars($age_str) ?></strong>.
        <?php endif; ?>
        <span class="d-block text-muted">The organism list, counts and health checks across the site are read from this cache.</span>
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
      <!-- 20px: Bootstrap's .progress-bar label is 0.75rem/12px, so a thinner bar clips the
           percentage the poll JS writes into it. -->
      <div class="progress" style="height: 20px;">
        <div id="dashCacheProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
      </div>
      <small class="text-muted" id="dashCacheProgressText">Checking status…</small>
    </div>
  <?= adm_status_card('organismCache', 'sync-alt', 'Organism Cache', $_oc_badges, $_oc_tone, ob_get_clean(), $refreshing) ?>

  <!-- JBrowse: the installed version, and each gene set's registration (gene track index,
       coordinate files, search index). Rebuilding is automatic, in the background run only;
       the button just starts that run now. Version is report-only: upgrading is a CLI job. -->
  <?php
    $gi        = $_SESSION['jbrowse_index_summary'] ?? [];
    // 'need' counts gene sets with ANY part out of step (gene_set_jbrowse_problems()); a status
    // file from before 2026-10-21 has only the track counts.
    $gi_need   = (int)($gi['need'] ?? ((int)($gi['stale'] ?? 0) + (int)($gi['missing'] ?? 0) + (int)($gi['broken'] ?? 0)));
    $gi_failed = $gi['last_rebuild']['failed'] ?? [];
    $gi_show   = $gi_need > 0 || !empty($gi_failed);
    $gi_bad    = (int)($gi['broken'] ?? 0) > 0 || $gi_failed;
    $gi_parts  = [];
    if (!empty($gi['broken']))  $gi_parts[] = $gi['broken'] . ' broken — JBrowse shows no genes for ' . ($gi['broken'] == 1 ? 'it' : 'them');
    if (!empty($gi['stale']))   $gi_parts[] = $gi['stale'] . ' out of date with the gene set';
    if (!empty($gi['missing'])) $gi_parts[] = $gi['missing'] . ' missing an index';
    if (!empty($gi['coords']))  $gi_parts[] = $gi['coords'] . ' with coordinate files older than genes.gff (Data Exporter, BLAST links)';
    if (!empty($gi['search']))  $gi_parts[] = $gi['search'] . ' without a current search index (JBrowse search box)';
    $gi_last  = $gi['last_rebuild'] ?? null;
    // This card's OWN timestamp, not the permission scan's: different tasks, can be days apart.
    $gi_age_s = isset($gi['checked_at']) ? max(0, time() - (int)$gi['checked_at']) : null;
    $gi_age   = $gi_age_s === null ? null
        : ($gi_age_s < 3600 ? max(1, (int)round($gi_age_s / 60)) . ' min ago'
        : ($gi_age_s < 172800 ? (int)floor($gi_age_s / 3600) . ' hr ago'
        : (int)floor($gi_age_s / 86400) . ' days ago'));

    $jb_cur = $jbrowse_version['current'] ?? null;
    $jb_new = $jbrowse_version['latest'] ?? null;
    $jb_old = !empty($jbrowse_version['outdated']);
    $jb_age = '';
    if (!empty($jbrowse_version['checked_at'])) {
        $d = floor((time() - strtotime($jbrowse_version['checked_at'])) / 86400);
        $jb_age = $d < 1 ? 'today' : ($d == 1 ? 'yesterday' : $d . 'd ago');
    }

    $_jb_badges = [];
    if ($gi_failed)         $_jb_badges[] = ['Rebuild failures', 'danger'];
    if ($gi_need > 0)       $_jb_badges[] = [$gi_need . ' gene ' . ($gi_need === 1 ? 'set needs' : 'sets need') . ' Re-prep', $gi_bad ? 'danger' : 'warning'];
    elseif (!empty($gi))    $_jb_badges[] = ['Tracks current', 'success'];
    if ($jb_old)            $_jb_badges[] = ['Update available: ' . $jb_new, 'warning'];
    elseif ($jb_cur)        $_jb_badges[] = ['v' . $jb_cur, 'secondary'];
    $_jb_tone = $gi_bad ? 'danger' : (($gi_need > 0 || $jb_old) ? 'warn' : '');
    ob_start();
  ?>
    <?php if ($jb_cur): ?>
      <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
        <div class="small">
          <strong>Version</strong> —
          <?php if ($jb_old): ?>
            <?= htmlspecialchars($jb_cur) ?> installed, <strong><?= htmlspecialchars($jb_new) ?></strong> available
          <?php elseif ($jb_new): ?>
            <i class="fa fa-check text-success"></i> <?= htmlspecialchars($jb_cur) ?>, the latest release
          <?php else: ?>
            <?= htmlspecialchars($jb_cur) ?> <span class="text-muted">— latest release not checked yet</span>
          <?php endif; ?>
          <?php if ($jb_age): ?><span class="text-muted">(checked <?= htmlspecialchars($jb_age) ?>)</span><?php endif; ?>
        </div>
        <a href="<?= $jb_old ? '../docs/JBrowse2/UPGRADING.md' : 'https://github.com/GMOD/jbrowse-components/releases' ?>"
           class="btn btn-sm <?= $jb_old ? 'btn-warning' : 'btn-outline-secondary' ?> flex-shrink-0"
           <?= $jb_old ? '' : 'target="_blank" rel="noopener"' ?>>
          <i class="fa fa-book"></i> <?= $jb_old ? 'How to upgrade' : 'Release notes' ?>
        </a>
      </div>
    <?php endif; ?>
    <div class="d-flex align-items-start justify-content-between gap-3 border-top pt-2">
      <div class="small flex-grow-1">
        <strong>Gene set registration</strong> —
        <?php if ($gi_need > 0): ?>
          <?= $gi_need ?> gene <?= $gi_need === 1 ? 'set needs' : 'sets need' ?> a Re-prep:
          <?= htmlspecialchars(implode(' · ', $gi_parts)) ?>
          <?php if (!empty($gi['search']) && isset($gi['cli']) && !$gi['cli']): ?>
          <span class="d-block mt-1"><strong>Search indexes cannot be built here:</strong> the web server cannot run the
            JBrowse CLI. Install it with <code>sudo scripts/install_jbrowse_cli.sh</code>.</span>
          <?php endif; ?>
        <?php elseif ($gi_failed): ?>
          the last rebuild had failures.
        <?php elseif (!empty($gi)): ?>
          <i class="fa fa-check text-success"></i> all <?= (int)($gi['total'] ?? 0) ?> gene sets' tracks,
          coordinate files and search indexes are current.
        <?php else: ?>
          <span class="text-muted">not checked yet.</span>
        <?php endif; ?>
        <span class="d-block text-muted mt-1">
          Rebuilt automatically by the background housekeeping run — broken ones first, then smallest first.
          <?php if ($gi_last && (count($gi_last['done'] ?? []) || count($gi_last['failed'] ?? []) || !empty($gi_last['left']))): ?>
            Last run: <?= count($gi_last['done'] ?? []) ?> rebuilt, <?= count($gi_last['failed'] ?? []) ?> failed<?php if (!empty($gi_last['left'])): ?>,
            <?= (int)$gi_last['left'] ?> left for the next run<?php endif; ?>.
          <?php endif; ?>
          <?php if ($gi_age): ?>Counted <?= htmlspecialchars($gi_age) ?>.<?php endif; ?>
        </span>
        <?php if ($gi_failed): ?>
        <ul class="mb-0 mt-1">
          <?php foreach (array_slice($gi_failed, 0, 10) as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if (!empty($gi['examples'])): ?>
        <a data-bs-toggle="collapse" href="#geneIndexList" role="button" aria-expanded="false">
          Which gene sets? <i class="fa fa-chevron-down small"></i>
        </a>
        <ul class="collapse mb-0 mt-1" id="geneIndexList">
          <?php foreach ($gi['examples'] as $e): ?>
          <li><?= htmlspecialchars(str_replace('_', ' ', $e['organism']) . ' / ' . $e['assembly'] . ' / ' . $e['gene_set']) ?>
            <span class="text-muted">— <?= htmlspecialchars($e['why']) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <span class="d-block text-muted fst-italic mt-1">
          By hand: <code>sudo -u apache php scripts/run_housekeeping.php --task=jbrowse_indexes</code>
          — see <code>docs/JBrowse2/GENE_TRACK_INDEXES.md</code>.
        </span>
      </div>
      <?php if ($gi_show): ?>
      <button type="button" id="rebuildGeneIndexesBtn" class="btn btn-sm btn-<?= $gi_bad ? 'danger' : 'warning' ?> flex-shrink-0"
              data-task="jbrowse_indexes">
        <i class="fa fa-wrench"></i> Rebuild now
      </button>
      <?php endif; ?>
    </div>
    <div id="rebuildGeneIndexesResult" class="small text-muted mt-2" style="display:none;"></div>
  <?= adm_status_card('jbrowse', 'dna', 'JBrowse', $_jb_badges, $_jb_tone, ob_get_clean()) ?>

  <div class="adm-group-label">Security &amp; software</div>
  <!-- OS security updates (housekeeping_check_security_updates). Root writes a daily report
       (scripts/install_security_report.sh) because the web server may not query the package
       database itself; this only reads it. Colour follows the packages the SITE runs on -- a
       Critical fix for a desktop library is listed, but does not turn the card red. -->
  <?php
    $su   = $_SESSION['security_updates'] ?? null;
    $rank = ['Critical' => 4, 'Important' => 3, 'Moderate' => 2, 'Low' => 1, 'Security' => 3];
    $su_worst_watched = 0;
    foreach (($su['watched'] ?? []) as $_w) $su_worst_watched = max($su_worst_watched, $rank[$_w['severity']] ?? 0);
    $su_needs_setup = $su && $su['error'] && ($su['source'] ?? '') === 'direct';
    $_su_badges = []; $_su_tone = '';
    if (!$su) {
        $_su_badges[] = ['Not checked yet', 'secondary'];
    } elseif ($su_needs_setup) {
        $_su_badges[] = ['Needs a one-time setup', 'warning']; $_su_tone = 'warn';
    } elseif ($su['error']) {
        $_su_badges[] = ['Check failed', 'danger']; $_su_tone = 'danger';
    } else {
        if (!$su['by_severity']) $_su_badges[] = ['Up to date', 'success'];
        if ($su['watched'])      $_su_badges[] = [count($su['watched']) . ' site ' . (count($su['watched']) === 1 ? 'package needs' : 'packages need') . ' updates', $su_worst_watched >= 4 ? 'danger' : 'warning'];
        foreach ($su['by_severity'] as $_sev => $_n) {
            $_su_badges[] = [$_n . ' ' . strtolower($_sev), $_sev === 'Critical' ? 'danger' : ($_sev === 'Important' ? 'warning' : 'secondary')];
        }
        if (!empty($su['reboot'])) { $_su_badges[] = ['Reboot needed', 'warning']; }
        if (!empty($su['stale']))  { $_su_badges[] = ['Report is out of date', 'warning']; }
        $_su_tone = $su_worst_watched >= 4 ? 'danger' : (($su['watched'] || !empty($su['reboot']) || !empty($su['stale'])) ? 'warn' : '');
    }
    ob_start();
  ?>
    <?php if (!$su): ?>
      <p class="small text-muted mb-0">The first check runs with the next housekeeping run.</p>
    <?php elseif ($su_needs_setup): ?>
      <p class="small mb-2">The web server is not allowed to ask the package manager for pending updates
        (on SELinux hosts it may not read the package database), so a small daily report has to be
        written by root instead. Run once, on the server:</p>
      <code class="d-block small mb-2">sudo <?= htmlspecialchars($config->getPath('site_path')) ?>/scripts/install_security_report.sh</code>
      <p class="small text-muted mb-0">It installs a daily systemd timer that writes the report into MOOP's
        cache directory. MOOP only reads it and never installs anything.
        <span class="d-block">Last attempt: <?= htmlspecialchars($su['error']) ?></span></p>
    <?php elseif ($su['error']): ?>
      <p class="small mb-0"><i class="fa fa-times-circle text-danger me-1"></i><?= htmlspecialchars($su['error']) ?></p>
    <?php else: ?>
      <?php if ($su['watched']): ?>
        <p class="small mb-1"><strong>Packages the site runs on:</strong></p>
        <ul class="list-unstyled small mb-2">
          <?php foreach ($su['watched'] as $_prod => $_w): $_c = ($rank[$_w['severity']] ?? 0) >= 4 ? 'danger' : (($rank[$_w['severity']] ?? 0) >= 3 ? 'warning' : 'secondary'); ?>
            <li class="mb-1">
              <span class="badge bg-<?= $_c ?><?= $_c === 'warning' ? ' text-dark' : '' ?> me-1"><?= htmlspecialchars($_w['severity']) ?></span>
              <strong><?= htmlspecialchars($_prod) ?></strong>
              <?php if ($_w['advisories']): ?>
                <span class="text-muted">— <?= count($_w['advisories']) ?> advisor<?= count($_w['advisories']) === 1 ? 'y' : 'ies' ?>:
                  <?= htmlspecialchars(implode(', ', array_slice($_w['advisories'], 0, 4))) ?><?= count($_w['advisories']) > 4 ? ' …' : '' ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php elseif ($su['by_severity']): ?>
        <p class="small mb-2"><i class="fa fa-check text-success me-1"></i>None of the pending updates are for
          packages the site runs on (PHP, the web server, OpenSSL, SQLite, the kernel).</p>
      <?php else: ?>
        <p class="small mb-2"><i class="fa fa-check text-success me-1"></i>No security updates are pending.</p>
      <?php endif; ?>
      <?php if ($su['by_severity']): ?>
        <?php /* The steps to run, numbered, and nothing else in their way: the report refresh used to sit
                 just below and read as step 3 (Sofia, 2026-10-27). Commands come from the manager at
                 display time, not the saved result: moop_security_update_commands(). */
              $_su_cmd = moop_security_update_commands($su['manager'] ?? null); ?>
        <p class="small mb-1">In all, <?= count($su['advisories']) ?: array_sum($su['by_severity']) ?>
          <?= $su['manager'] === 'dnf' ? 'security advisories' : 'security updates' ?> are pending across
          <?= (int)$su['packages'] ?> packages. To install them all, on the server:</p>
        <ol class="small mb-2 ps-3">
          <li class="mb-1"><code><?= htmlspecialchars($_su_cmd['update'] ?? $su['command']) ?></code>
            <span class="text-muted d-block">The last part frees the downloaded packages.</span></li>
          <?php if (isset($su['watched']['Linux kernel'])): ?>
          <li class="mb-1"><i class="fa fa-power-off text-warning me-1"></i><code><?= htmlspecialchars($_su_cmd['reboot']) ?></code>
            <span class="text-muted d-block">This includes a kernel update, which runs only after a restart. The site is down for a minute or two and comes back by itself.</span></li>
          <?php endif; ?>
        </ol>
        <p class="small text-muted mb-2">That is all: this report refreshes by itself after installing.</p>
      <?php endif; ?>
      <?php if (!empty($su['reboot'])): ?>
        <p class="small mb-2"><i class="fa fa-power-off text-warning me-1"></i><strong>A newer kernel is installed than the one running.</strong>
          Restart the server to run it:
          <code class="d-block mt-1"><?= htmlspecialchars(moop_security_update_commands($su['manager'] ?? null)['reboot']) ?></code>
          <span class="text-muted d-block mt-1">The site is down for a minute or two and comes back by itself.</span></p>
      <?php endif; ?>
      <p class="small text-muted fst-italic mb-0">
        <i class="fa fa-clock"></i>
        <?php if (!empty($su['stale'])): ?><strong>This report is out of date</strong> — the daily
          <code>moop-security-report</code> timer has stopped running. Check it with
          <code>systemctl status moop-security-report.timer</code>.
        <?php endif; ?>
        <?php // Relative, never a printed clock time: PHP's timezone is unset here (UTC) while the host is local.
              $_su_age = $su['checked_at'] ? max(0, time() - (int)strtotime($su['checked_at'])) : null; ?>
        Report from <?= $_su_age === null ? 'an unknown time' : ($_su_age < 3600 ? max(1, (int)round($_su_age / 60)) . ' min ago'
                         : ($_su_age < 172800 ? (int)floor($_su_age / 3600) . ' hr ago' : (int)floor($_su_age / 86400) . ' days ago')) ?>
        <?= ($su['source'] ?? '') === 'report' ? '· written daily by the moop-security-report timer' : '' ?>
        · report-only, MOOP never installs updates.
        <?php if (($su['source'] ?? '') === 'report'): ?>
          <?php /* Housekeeping only READS root's report, so "Run housekeeping now" alone shows the same
                   answer again. Installing refreshes it by itself (the dnf/apt hook); this is for the rest. */ ?>
          <span class="d-block mt-1">To refresh it without installing anything:
            <code><?= htmlspecialchars(moop_security_update_commands($su['manager'] ?? null)['recheck']) ?></code>,
            then <strong>Run housekeeping now</strong>.</span>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  <?= adm_status_card('securityUpdates', 'shield-alt', 'OS Security Updates', $_su_badges, $_su_tone, ob_get_clean()) ?>

  <!-- Third-party libraries (housekeeping_check_library_advisories): what the site ships,
       proven by checksum, and what OSV knows about those exact versions. -->
  <?php
    $la     = $_SESSION['library_advisories'] ?? null;
    $la_pk  = $la['packages'] ?? [];
    $la_vul = array_values(array_filter($la_pk, fn($p) => !empty($p['vulns'])));
    $la_bad = array_values(array_filter($la_pk, fn($p) => !empty($p['problems'])));
    $la_high = false;
    foreach ($la_vul as $_p) foreach ($_p['vulns'] as $_v) if (in_array($_v['severity'], ['High', 'Critical'], true)) $la_high = true;
    $_la_badges = []; $_la_tone = '';
    if (!$la) {
        $_la_badges[] = ['Not checked yet', 'secondary'];
    } else {
        if ($la_vul)  $_la_badges[] = [count($la_vul) . ' with known vulnerabilities', $la_high ? 'danger' : 'warning'];
        if ($la_bad)  $_la_badges[] = [count($la_bad) . ' unverified', 'warning'];
        if (!empty($la['unlisted'])) $_la_badges[] = [count($la['unlisted']) . ' unlisted ' . (count($la['unlisted']) === 1 ? 'file' : 'files'), 'warning'];
        if (!$la_vul && !$la_bad && !empty($la['checked_at'])) $_la_badges[] = ['No known vulnerabilities', 'success'];
        if (!empty($la['osv_failed']) || empty($la['checked_at'])) $_la_badges[] = ['OSV not reached', 'secondary'];
        $_la_badges[] = [count($la_pk) . ' libraries', 'secondary'];
        $_la_tone = $la_high ? 'danger' : (($la_vul || $la_bad || !empty($la['unlisted'])) ? 'warn' : '');
    }
    ob_start();
  ?>
    <?php if (!$la): ?>
      <p class="small text-muted mb-0">The first check runs with the next housekeeping run.</p>
    <?php else: ?>
      <?php if (!empty($la['unlisted'])): ?>
        <div class="alert alert-warning small py-2 mb-2">
          <strong>Not in <code>scripts/vendor_assets.txt</code>, so never checked:</strong>
          <?= htmlspecialchars(implode(', ', $la['unlisted'])) ?>.
          Add a line for each (URL, checksum, <code>ecosystem:package@version</code>), or delete the file if nothing uses it.
        </div>
      <?php endif; ?>
      <table class="table table-sm small align-middle mb-2">
        <thead><tr><th>Library</th><th>Version</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($la_pk as $_p): ?>
          <tr>
            <td><?= htmlspecialchars($_p['name']) ?> <span class="text-muted"><?= $_p['ecosystem'] === 'Packagist' ? '(PHP)' : '' ?></span></td>
            <td><code><?= htmlspecialchars($_p['version']) ?></code></td>
            <td>
              <?php foreach ($_p['vulns'] as $_v): $_vc = in_array($_v['severity'], ['High', 'Critical'], true) ? 'danger' : 'warning'; ?>
                <div>
                  <span class="badge bg-<?= $_vc ?><?= $_vc === 'warning' ? ' text-dark' : '' ?>"><?= htmlspecialchars($_v['severity']) ?></span>
                  <a href="<?= htmlspecialchars($_v['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($_v['id']) ?></a>
                  <?= htmlspecialchars($_v['summary']) ?>
                  <?php if ($_v['fixed']): ?><span class="text-muted">— fixed in <?= htmlspecialchars(implode(', ', $_v['fixed'])) ?></span><?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php foreach ($_p['problems'] as $_pr): ?>
                <div class="text-warning-emphasis"><i class="fa fa-exclamation-triangle me-1"></i><?= htmlspecialchars($_pr) ?></div>
              <?php endforeach; ?>
              <?php if (!$_p['vulns'] && !$_p['problems']): ?><i class="fa fa-check text-success"></i><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="small text-muted mb-0">
        <?php $_la_age = !empty($la['checked_at']) ? max(0, time() - (int)strtotime($la['checked_at'])) : null; ?>
        <i class="fa fa-clock"></i>
        <?php if ($_la_age === null): ?>OSV has not been reached yet.
        <?php else: ?>Checked against <a href="https://osv.dev" target="_blank" rel="noopener">OSV</a>
          <?= $_la_age < 3600 ? max(1, (int)round($_la_age / 60)) . ' min' : ($_la_age < 172800 ? (int)floor($_la_age / 3600) . ' hr' : (int)floor($_la_age / 86400) . ' days') ?> ago<?php endif; ?>
        <?= !empty($la['osv_failed']) ? '· the last attempt could not reach it, so these are the previous results' : '' ?>
        · weekly, and whenever a version changes.
        <span class="d-block mt-1">To upgrade a front-end library, edit its line in <code>scripts/vendor_assets.txt</code>
          and run <code>scripts/fetch_vendor_assets.sh --force</code>; a PHP package, <code>composer update</code>.</span>
      </p>
    <?php endif; ?>
  <?= adm_status_card('libraries', 'cubes', 'Third-party Libraries', $_la_badges, $_la_tone, ob_get_clean()) ?>

  <!-- PHP Version: will MOOP's code keep working as PHP moves on? Deprecations are recorded
       as they happen (includes/deprecation_log.php -- error_reporting hides them on this host);
       a newer PHP is checked by CI's "PHP 8.3 readiness" job on every push. Read live: a glob of
       a few small files, cheap enough not to need housekeeping. -->
  <?php
    $_dep = [];
    foreach (glob(dirname(__DIR__, 2) . '/logs/deprecations/*.json') ?: [] as $_f) {
        $_d = json_decode((string)@file_get_contents($_f), true);
        if (is_array($_d)) { $_d['last_seen'] = @filemtime($_f); $_dep[] = $_d; }
    }
    usort($_dep, fn($a, $b) => ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0));
    $_php_badges = [['PHP ' . PHP_VERSION, 'secondary']];
    $_php_badges[] = $_dep ? [count($_dep) . ' deprecated ' . (count($_dep) === 1 ? 'call' : 'calls'), 'warning'] : ['No deprecations', 'success'];
    ob_start();
  ?>
    <p class="small mb-2">
      The site runs <strong>PHP <?= htmlspecialchars(PHP_VERSION) ?></strong>.
      Security fixes for it are reported under <em>OS Security Updates</em>; this card is about whether
      MOOP's own code will keep working on newer versions.
    </p>
    <?php if ($_dep): ?>
      <p class="small mb-1"><strong>Deprecated calls the site has made</strong> — each will stop working on a later PHP:</p>
      <ul class="small mb-2">
        <?php foreach (array_slice($_dep, 0, 15) as $_d): ?>
          <li><code><?= htmlspecialchars(($_d['file'] ?? '?') . ':' . ($_d['line'] ?? '?')) ?></code> —
            <?= htmlspecialchars($_d['message'] ?? '') ?>
            <span class="text-muted">(first seen on <?= htmlspecialchars($_d['page'] ?? '?') ?>)</span></li>
        <?php endforeach; ?>
      </ul>
      <p class="small text-muted mb-2">Once fixed, delete the matching file in <code>logs/deprecations/</code>.</p>
    <?php else: ?>
      <p class="small mb-2"><i class="fa fa-check text-success me-1"></i>No deprecated PHP call has been made since this
        check started recording (PHP hides deprecation warnings on this server, so MOOP records them itself).</p>
    <?php endif; ?>
    <p class="small text-muted mb-0">
      Newer versions are checked on every push by the <strong>PHP 8.3 readiness</strong> job in GitHub Actions:
      a syntax check and the tests under PHP 8.3, plus a scan of every file with PHPCompatibility
      (rules in <code>phpcompat.xml</code>).
    </p>
  <?= adm_status_card('phpVersion', 'code', 'PHP Version', $_php_badges, $_dep ? 'warn' : '', ob_get_clean()) ?>

  <div class="adm-group-label">Server</div>
  <!-- Filesystem permissions: grouped by CATEGORY and phrased by the worst severity PRESENT
       (never "0 high"). Precomputed once per housekeeping interval, NOT scanned on load. -->
  <?php
    $ps       = $_SESSION['perm_summary'] ?? null;
    $findings = $ps['findings'] ?? [];
    $worst    = $ps['worst'] ?? 'medium';
    $top      = array_values(array_filter($findings, fn($f) => $f['severity'] === $worst));
    $below    = count($findings) - count($top);
    if (!$ps) {
        $_pm_badges = [['Not scanned yet', 'secondary']]; $_pm_tone = '';
    } elseif (!$findings) {
        $_pm_badges = [['No issues', 'success']]; $_pm_tone = '';
    } else {
        $_pm_col    = $worst === 'high' ? 'danger' : ($worst === 'medium' ? 'warning' : 'secondary');
        $_pm_badges = [[count($top) . ' ' . (count($top) === 1 ? 'area needs' : 'areas need') . ' attention', $_pm_col]];
        if ($below > 0) $_pm_badges[] = [$below . ' lower-priority', 'secondary'];
        $_pm_tone   = $worst === 'high' ? 'danger' : ($worst === 'medium' ? 'warn' : '');
    }
    ob_start();
  ?>
    <div class="d-flex align-items-start justify-content-between gap-3">
      <div class="small">
        <?php if ($findings): ?>
          <?php
            $labels   = array_map(fn($f) => $f['category'] . ' (' . $f['count'] . ')', $top);
            $headline = $worst === 'high'
                ? 'May break tools or logging, or expose a secret.'
                : ($worst === 'medium' ? 'Worth addressing.' : 'Low priority — tidy when convenient.');
          ?>
          <strong><?= htmlspecialchars(implode(', ', $labels)) ?></strong> — <?= $headline ?>
          <?php foreach ($top as $f): if (empty($f['reasons'])) continue; ?>
            <span class="d-block text-muted"><?= htmlspecialchars($f['category']) ?>: <?= htmlspecialchars(implode('; ', $f['reasons'])) ?></span>
          <?php endforeach; ?>
        <?php elseif ($ps): ?>
          <i class="fa fa-check text-success"></i> Nothing the web server needs is blocked, and nothing secret is exposed.
        <?php else: ?>
          The first scan runs with the next housekeeping run.
        <?php endif; ?>
        <span class="d-block text-muted fst-italic mt-1">
          <i class="fa fa-clock"></i>
          <?php if ($scanned): ?>Scanned <?= htmlspecialchars($scanned) ?><?php else: ?>Cached scan<?php endif; ?>
          &middot; the Permission Manager re-checks live.
        </span>
      </div>
      <div class="d-flex flex-column align-items-stretch gap-1 flex-shrink-0">
        <a href="manage_filesystem_permissions.php" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-lock"></i> Permission Manager
        </a>
        <!-- Re-check THIS card only: runs the permission task alone (seconds) and reloads. -->
        <button type="button" id="recheckPermissionsBtn" class="btn btn-sm btn-outline-secondary"
                data-task="permission_check">
          <i class="fa fa-sync"></i> Re-check now
        </button>
      </div>
    </div>
    <div id="recheckPermissionsResult" class="small text-muted mt-2" style="display:none;"></div>
  <?= adm_status_card('permissions', 'lock', 'Filesystem Permissions', $_pm_badges, $_pm_tone, ob_get_clean()) ?>

  <!-- Server Requirements: is everything the site needs PRESENT -- PHP extensions, JWT keys,
       command-line tools, composer dependencies. (Named "Environment" until 2026-10-22; PHP
       now appears in three cards, each answering a different question: is it installed (here),
       does it need a security fix (OS Security Updates), will our code run on the next
       version (PHP Version).) -->
  <?php
    $_env = $_SESSION['env_warnings'] ?? [];
    $_env_danger = count(array_filter($_env, fn($w) => ($w['level'] ?? '') === 'danger'));
    $_env_badges = !$_env ? [['No issues', 'success']]
                 : [[count($_env) . ' ' . (count($_env) === 1 ? 'issue' : 'issues'), $_env_danger ? 'danger' : 'warning']];
    ob_start();
  ?>
    <?php if (!$_env): ?>
      <p class="small text-muted mb-0"><i class="fa fa-check text-success"></i> PHP extensions, JWT keys,
        command-line tools and composer dependencies are all in place.</p>
    <?php else: foreach ($_env as $warning): ?>
      <div class="alert alert-<?= $warning['level'] ?> mb-2 small">
        <i class="fa fa-<?= $warning['level'] === 'danger' ? 'times-circle' : 'exclamation-circle' ?> me-2"></i>
        <?= $warning['message'] ?>
      </div>
    <?php endforeach; endif; ?>
  <?= adm_status_card('environment', 'server', 'Server Requirements', $_env_badges, $_env ? ($_env_danger ? 'danger' : 'warn') : '', ob_get_clean()) ?>

  <!-- Site data backup. The git line is recomputed LIVE on every load (~10 ms), unlike the
       rest of this section: a badge that still said "3 files to commit" after a push, with no
       way to tell stale from failed, is what made it live. -->
  <?php
    $g = null;
    if ($site_data_backup !== null && !empty($site_data_backup['is_git']) && function_exists('housekeeping_git_status')) {
        $g = housekeeping_git_status($site_data_backup['path']);
    }
    if ($site_data_backup === null) {
        $_bk_badges = [['Not run yet', 'secondary']]; $_bk_tone = '';
    } elseif ($site_data_backup['status'] === 'error') {
        $_bk_badges = [['Failing', 'danger']]; $_bk_tone = 'danger';
    } else {
        $_bk_badges = [['Active', 'success']]; $_bk_tone = '';
        if ($g && !$g['clean']) {
            $_bits = [];
            if ($g['uncommitted'] > 0) $_bits[] = $g['uncommitted'] . ' to commit';
            if ($g['ahead'] > 0)       $_bits[] = $g['ahead'] . ' to push';
            $_bk_badges[] = ['Not synced: ' . ($_bits ? implode(', ', $_bits) : 'no remote'), 'warning'];
            $_bk_tone = 'warn';
        } elseif ($g) {
            $_bk_badges[] = ['Committed' . ($g['has_upstream'] ? ' & pushed' : ''), 'success'];
        }
    }
    ob_start();
  ?>
    <?php if ($site_data_backup === null): ?>
      <p class="small text-muted mb-0">The first backup runs with the next housekeeping run.</p>
    <?php elseif ($site_data_backup['status'] === 'error'): ?>
      <p class="small mb-0"><i class="fa fa-exclamation-triangle text-danger me-1"></i><?= $site_data_backup['message'] ?></p>
    <?php else: ?>
      <p class="small mb-2">
        Last run <?= htmlspecialchars($site_data_backup['last_run']) ?>,
        <?= (int) $site_data_backup['files_copied'] ?> file<?= $site_data_backup['files_copied'] == 1 ? '' : 's' ?> updated.
        <span class="d-block text-muted">Config, metadata and organism files are checked for changes with each
          housekeeping run and copied to <code><?= htmlspecialchars($site_data_backup['path']) ?></code>.</span>
      </p>
      <?php if ($g): ?>
        <div class="small border-top pt-2">
          <?php if ($g['clean']): ?>
            <span class="text-success"><i class="fa fa-code-branch"></i>
              <strong>All committed<?= $g['has_upstream'] ? ' &amp; pushed' : '' ?></strong></span>
            <span class="text-muted ms-1"><?= (int) $g['commits'] ?> commit<?= $g['commits'] == 1 ? '' : 's' ?>
              &middot; last <?= htmlspecialchars($g['last_commit']) ?><?= $g['has_upstream'] ? '' : ' &middot; no remote configured' ?></span>
          <?php else: ?>
            <span class="text-warning-emphasis"><i class="fa fa-code-branch"></i> <strong>Not synced.</strong></span>
            MOOP never commits — run this to commit and push the backup:
            <?php
              // ONE command correct in every state: housekeeping copies files in the background,
              // so the state can change between render and the admin running it. `|| true` keeps
              // the push when there was nothing to commit; `&&` keeps it from running if cd fails.
              $_cmd = "cd " . $site_data_backup['path']
                    . " && git add -A"
                    . " && (git commit -m \"Site data backup\" || true)"
                    . ($g['has_upstream'] ? " && git push" : "");
            ?>
            <code class="d-block mt-1" style="font-size: 0.85em;"><?= htmlspecialchars($_cmd) ?></code>
            <span class="text-muted d-block mt-1">Last commit <?= htmlspecialchars($g['last_commit']) ?>.</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?= adm_status_card('backup', 'save', 'Site Data Backup', $_bk_badges, $_bk_tone, ob_get_clean()) ?>

  <div class="adm-group-label">Maintenance</div>
  <!-- Housekeeping: what refreshes every card above. Last, because it is the machinery, not
       a finding. The task list is rendered from housekeeping_task_registry() -- the same list
       that RUNS them -- so it cannot drift. -->
  <?php
    $_hk_running = function_exists('housekeeping_is_running') && housekeeping_is_running();
    $_hk_badges  = $_hk_running ? [['Running', 'info']]
                 : [[$scanned ? 'Last run ' . $scanned : 'Not run yet', 'secondary']];
    ob_start();
  ?>
    <?php if ($_hk_running): ?>
    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-2" role="status">
      <span class="spinner-border spinner-border-sm flex-shrink-0"></span>
      <div class="small">
        <strong>Housekeeping is running.</strong> These figures were taken before it started —
        <a href="">reload</a> in a few seconds.
      </div>
    </div>
    <?php endif; ?>
    <div class="d-flex align-items-center justify-content-between gap-3">
      <div class="small text-muted">
        Runs in the background when an admin loads a page, at most once
        <?= $iv_hrs === 1 ? 'an hour' : 'every ' . $iv_hrs . ' hours' ?>, and never slows it down.
        Every card above is a cached result of it, so a fix made since the last run may still be listed.
        <a class="ms-1" data-bs-toggle="collapse" href="#housekeepingTasks" role="button"
           aria-expanded="false" aria-controls="housekeepingTasks">
          What runs? <i class="fa fa-chevron-down small"></i>
        </a>
      </div>
      <button type="button" id="rerunHousekeepingBtn" class="btn btn-sm btn-outline-secondary flex-shrink-0"
              data-tasks="<?= htmlspecialchars(json_encode(array_column(housekeeping_task_registry(), 'name'))) ?>">
        <i class="fa fa-sync"></i> Run housekeeping now
      </button>
    </div>
    <div class="collapse" id="housekeepingTasks">
      <hr class="my-2">
      <p class="small text-muted mb-2"><strong>Nothing to set up — no cron.</strong></p>
      <?php foreach (housekeeping_task_registry() as $_t): ?>
      <div class="mb-2">
        <div class="small"><strong><?= htmlspecialchars($_t['label']) ?></strong>
          <code class="text-muted ms-1"><?= htmlspecialchars($_t['name']) ?></code>
        </div>
        <div class="small text-muted"><?= htmlspecialchars($_t['desc']) ?></div>
      </div>
      <?php endforeach; ?>
      <p class="small text-muted mb-0 fst-italic">Defined in <code>lib/housekeeping.php</code>.</p>
    </div>
    <div id="rerunHousekeepingResult" class="small mt-2"></div>
  <?= adm_status_card('housekeeping', 'broom', 'Housekeeping', $_hk_badges, '', ob_get_clean(), $_hk_running) ?>
  <div class="mb-4"></div>


  <!-- The admin tools: moop_admin_tools() (lib/admin_tools.php), the same list as the Quick
       Links sidebar, so a tool added there appears in both. These were hand-written cards in
       three idioms -- some with a header bar, some without, button colours mixed within a group. -->
  <?php require_once __DIR__ . '/../../lib/admin_tools.php'; ?>
  <?php foreach (moop_admin_tools() as $_grp): ?>
  <div class="mt-5">
    <h3 class="mb-3"><i class="fa fa-<?= htmlspecialchars($_grp['icon']) ?>"></i> <?= htmlspecialchars($_grp['heading']) ?></h3>
    <div class="row">
      <?php foreach ($_grp['tools'] as $_tool): ?>
      <div class="col-md-6 mb-3">
        <div class="card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fa fa-<?= htmlspecialchars($_tool['icon']) ?>"></i> <?= htmlspecialchars($_tool['title']) ?></h5>
            <p class="card-text"><?= htmlspecialchars($_tool['desc']) ?></p>
            <a href="<?= htmlspecialchars($_tool['file']) ?>" class="btn <?= $_grp['btn'] ?>"><?= htmlspecialchars($_tool['cta']) ?></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
