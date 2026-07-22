<?php
/**
 * HELP & DOCUMENTATION LANDING — Content File
 *
 * Replaces the old flat 15-card dashboard.php grid, which showed every topic to
 * everyone — so a biologist's first stop listed SELinux and permission management
 * alongside BLAST, and MOOP read as a sysadmin tool.
 *
 * Two audiences, kept apart:
 *   For users        — expanded, the default view
 *   For admins       — collapsed; setup and maintenance, for someone standing up
 *                      or inheriting a MOOP site
 *
 * Topic list comes from topics.php (single source of truth) — do not inline a copy.
 *
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */

$topics    = include __DIR__ . '/topics.php';
$site      = $config->getString('site');
$forUsers  = array_values(array_filter($topics, fn($t) => $t['category'] === 'general'));
$forAdmins = array_values(array_filter($topics, fn($t) => $t['category'] === 'technical'));

/** Render one topic card. */
$topic_card = function (array $t) use ($site) {
    ?>
    <div class="col-md-6 col-lg-4 mb-3">
      <a href="/<?= htmlspecialchars($site) ?>/help.php?topic=<?= htmlspecialchars($t['id']) ?>"
         class="text-decoration-none">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <h6 class="fw-semibold mb-1 text-body">
              <i class="fa <?= htmlspecialchars($t['icon']) ?> me-2" style="color:#0891b2;"></i><?= $t['title'] ?>
            </h6>
            <p class="text-muted small mb-0"><?= $t['description'] ?></p>
          </div>
        </div>
      </a>
    </div>
    <?php
};
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">
        <i class="fa fa-book me-2"></i>Help &amp; Documentation
      </span>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        Guides for using <?= htmlspecialchars($siteTitle) ?> — searching, BLAST, building gene lists and
        exporting data. Setting up or maintaining a site instead? That is covered separately below.
      </p>
    </div>
  </div>

  <!-- ── For users ─────────────────────────────────────────────────────────── -->
  <div class="d-flex align-items-baseline justify-content-between mb-2">
    <h5 class="fw-semibold mb-0">
      <i class="fa fa-user me-2" style="color:#0891b2;"></i>Using <?= htmlspecialchars($siteTitle) ?>
      <span class="badge rounded-pill ms-2"
            style="background-color:#e0f2f7; color:#0e7490;"><?= count($forUsers) ?></span>
    </h5>
  </div>
  <p class="text-muted small mb-3">Finding data, running searches, and getting it back out.</p>

  <div class="row">
    <?php foreach ($forUsers as $t) { $topic_card($t); } ?>
  </div>

  <!-- ── For administrators ────────────────────────────────────────────────── -->
  <div class="card shadow-sm mt-4 mb-5">
    <div class="card-body p-0">
      <button class="btn btn-link w-100 text-start text-decoration-none p-3 d-flex align-items-center justify-content-between"
              type="button" data-bs-toggle="collapse" data-bs-target="#adminHelpSection"
              aria-expanded="false" aria-controls="adminHelpSection">
        <span>
          <i class="fa fa-server me-2 text-secondary"></i>
          <span class="fw-semibold text-body">Setting up &amp; maintaining a MOOP site</span>
          <span class="badge rounded-pill ms-2 bg-secondary"><?= count($forAdmins) ?></span>
          <span class="text-muted small d-block mt-1 ms-4 ps-1">
            For administrators — what is involved in installing, loading data, and keeping a site running.
          </span>
        </span>
        <i class="fa fa-chevron-down text-muted ms-3"></i>
      </button>

      <div class="collapse" id="adminHelpSection">
        <div class="px-3 pb-3">
          <div class="row">
            <?php foreach ($forAdmins as $t) { $topic_card($t); } ?>
          </div>
          <p class="text-muted small mb-0">
            Deeper technical references — installation, SELinux and hardening, resource planning —
            live in the <code>docs/</code> directory of the MOOP repository.
          </p>
        </div>
      </div>
    </div>
  </div>

</div>

<style>
  .help-topic-card { transition: border-color .12s ease, box-shadow .12s ease; border-color: rgba(0,0,0,.1); }
  .help-topic-card:hover { border-color: #0891b2; box-shadow: 0 .25rem .6rem rgba(8,145,178,.15) !important; }
</style>
