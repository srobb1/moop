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
// Setup and maintenance topics are for the site's administrators only (Sofia, 2026-11-06): a
// visitor -- a student on cuttingclass, a biologist on simrbase -- never needs SELinux or backups.
$is_admin  = has_access('ADMIN');
// "Who can see what" explains logins and restricted data. On a site where every assembly is
// public (cuttingclass) there is nothing to explain, so it is left out.
$restricted = moop_site_has_restricted_data();
// The AI assistant guide is opt-in per site (setting ai_guide): off -> no card; on another site
// (Planosphere, Cutting Class -> SIMRbase) -> the card opens that site's page.
$ai_guide  = moop_ai_guide();
$forUsers  = array_values(array_filter($topics, fn($t) => $t['category'] === 'general'
                                                     && ($t['id'] !== 'access' || $restricted)
                                                     && ($t['id'] !== 'ai-assistants' || $ai_guide)));
foreach ($forUsers as &$__t) {
    if ($__t['id'] === 'ai-assistants' && !$ai_guide['here']) $__t['href'] = $ai_guide['help'];
}
unset($__t);
$forAdmins = $is_admin ? array_values(array_filter($topics, fn($t) => $t['category'] === 'technical')) : [];

/** Render one topic card. */
$topic_card = function (array $t) use ($site) {
    ?>
    <?php /* data-search carries title + description lowercased, so the filter never has to
             read rendered DOM text (which would also match the icon markup and the badge). */ ?>
    <div class="col-md-6 col-lg-4 mb-3 help-topic"
         data-search="<?= htmlspecialchars(strtolower(strip_tags($t['title'] . ' ' . $t['description'])), ENT_QUOTES) ?>">
      <a href="<?= htmlspecialchars($t['href'] ?? moop_url_base() . '/help.php?topic=' . $t['id']) ?>"
         class="text-decoration-none"<?= isset($t['href']) ? ' target="_blank" rel="noopener"' : '' ?>>
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <h6 class="fw-semibold mb-1 text-body">
              <i class="fa <?= htmlspecialchars($t['icon']) ?> me-2" style="color:var(--moop-tool-header);"></i><?= $t['title'] ?>
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
    <div class="card-header text-white d-flex align-items-center" style="background-color:var(--moop-tool-header);">
      <?php /* h1, not a styled span — see the note on retrieve_sequences.php. */ ?>
      <h1 class="text-uppercase fw-semibold mb-0 d-inline" style="letter-spacing:0.1em; font-size:0.8rem;">
        <i class="fa fa-book me-2"></i><?= t('Help & Documentation') . "\n" ?>
      </h1>
    </div>
    <div class="card-body p-4">
      <?php $info_icon = '<i class="fa fa-info-circle" style="color:var(--moop-tool-header);" aria-label="' . t('information') . '"></i>'; ?>
      <p class="text-muted mb-0">
        <?= t_markup('Where to go, how the data fits together, and where it comes from. How to use a particular page is on that page — look for its %s buttons.', $info_icon) ?>
        <?= ($is_admin ? t('Setting up or maintaining a site instead? That is covered separately below.') : '') . "\n" ?>
      </p>
    </div>
  </div>

  <?php /* One box over BOTH audiences. The old dashboard.php had a topic filter; it was lost
           when that page was replaced by landing.php + topics.php on 2026-07-22 — the registry
           extraction was right, the filter simply did not come with it. With ~15 topics and more
           coming (tracks server, upgrading, user management), a static list is where filtering
           stops being optional. */ ?>
  <div class="row mb-4">
    <div class="col-lg-7">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
        <input type="search" id="help-filter" class="form-control border-start-0 moop-input"
               placeholder="<?= $is_admin ? 'Filter topics — try &quot;BLAST&quot;, &quot;download&quot;, &quot;permissions&quot;…' : 'Filter topics — try &quot;BLAST&quot;, &quot;download&quot;, &quot;glossary&quot;…' ?>"
               autocomplete="off" spellcheck="false" aria-label="<?= t('Filter help topics') ?>">
      </div>
      <div id="help-filter-msg" class="small text-muted mt-1" style="display:none;"></div>
    </div>
  </div>

  <!-- ── For users ─────────────────────────────────────────────────────────── -->
  <div class="help-section" id="help-users">
  <div class="d-flex align-items-baseline justify-content-between mb-2">
    <h5 class="fw-semibold mb-0">
      <i class="fa fa-user me-2" style="color:var(--moop-tool-header);"></i>Using <?= htmlspecialchars($siteTitle) ?>
      <span class="badge rounded-pill ms-2"
            style="background-color:var(--moop-main-tint); color:var(--moop-tool-hover);"><?= count($forUsers) ?></span>
    </h5>
  </div>
  <p class="text-muted small mb-3"><?= t('Finding data, running searches, and getting it back out.') ?></p>

  <div class="row">
    <?php foreach ($forUsers as $t) { $topic_card($t); } ?>
  </div>
  </div><!-- /#help-users -->

  <!-- ── For administrators ────────────────────────────────────────────────── -->
<?php if ($forAdmins): ?>
  <div class="card shadow-sm mt-4 mb-5 help-section" id="help-admins">
    <div class="card-body p-0">
      <button class="btn btn-link w-100 text-start text-decoration-none p-3 d-flex align-items-center justify-content-between"
              type="button" data-bs-toggle="collapse" data-bs-target="#adminHelpSection"
              aria-expanded="false" aria-controls="adminHelpSection">
        <span>
          <i class="fa fa-server me-2 text-secondary"></i>
          <span class="fw-semibold text-body"><?= t('Setting up & maintaining a MOOP site') ?></span>
          <span class="badge rounded-pill ms-2 bg-secondary"><?= count($forAdmins) ?></span>
          <span class="text-muted small d-block mt-1 ms-4 ps-1">
            <?= t('For administrators — what is involved in installing, loading data, and keeping a site running.') . "\n" ?>
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
<?php endif; ?>

</div>

<?php // Topic filter: js/help-landing.js (loaded by help.php on this page). ?>

<style>
  .help-topic-card { transition: border-color .12s ease, box-shadow .12s ease; border-color: rgba(0,0,0,.1); }
  .help-topic-card:hover { border-color: var(--moop-tool-header); box-shadow: 0 .25rem .6rem rgba(var(--moop-main-rgb),.15) !important; }
</style>
