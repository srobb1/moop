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
    <?php /* data-search carries title + description lowercased, so the filter never has to
             read rendered DOM text (which would also match the icon markup and the badge). */ ?>
    <div class="col-md-6 col-lg-4 mb-3 help-topic"
         data-search="<?= htmlspecialchars(strtolower(strip_tags($t['title'] . ' ' . $t['description'])), ENT_QUOTES) ?>">
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
      <?php /* h1, not a styled span — see the note on retrieve_sequences.php. */ ?>
      <h1 class="text-uppercase fw-semibold mb-0 d-inline" style="letter-spacing:0.1em; font-size:0.8rem;">
        <i class="fa fa-book me-2"></i>Help &amp; Documentation
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        Guides for using <?= htmlspecialchars($siteTitle) ?> — searching, BLAST, building gene lists and
        exporting data. Setting up or maintaining a site instead? That is covered separately below.
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
               placeholder="Filter topics — try &quot;BLAST&quot;, &quot;download&quot;, &quot;permissions&quot;…"
               autocomplete="off" spellcheck="false" aria-label="Filter help topics">
      </div>
      <div id="help-filter-msg" class="small text-muted mt-1" style="display:none;"></div>
    </div>
  </div>

  <!-- ── For users ─────────────────────────────────────────────────────────── -->
  <div class="help-section" id="help-users">
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
  </div><!-- /#help-users -->

  <!-- ── For administrators ────────────────────────────────────────────────── -->
  <div class="card shadow-sm mt-4 mb-5 help-section" id="help-admins">
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

<script>
/* Filter the topic cards. Reads data-search (title + description, lowercased server-side)
   rather than rendered text, so it never matches icon markup or the count badge.

   Two behaviours the old dashboard.php filter did not have, both needed because the admin
   topics now sit in a COLLAPSED section:
     - a match hidden inside the collapsed "Setting up & maintaining" panel is useless, so
       the panel is opened automatically while a filter is active, and restored when cleared;
     - a section whose cards have all been filtered out is hidden entirely, rather than
       leaving an empty heading and a count badge that now lies. */
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('help-filter');
    if (!input) return;
    var msg      = document.getElementById('help-filter-msg');
    var cards    = Array.prototype.slice.call(document.querySelectorAll('.help-topic'));
    var sections = Array.prototype.slice.call(document.querySelectorAll('.help-section'));
    var adminPanel = document.getElementById('adminHelpSection');
    var adminWasOpen = false, filtering = false;

    function apply() {
        var q = input.value.trim().toLowerCase();
        var shown = 0;
        cards.forEach(function (c) {
            var hit = !q || (c.dataset.search || '').indexOf(q) !== -1;
            c.style.display = hit ? '' : 'none';
            if (hit) shown++;
        });
        // Hide a section that has nothing left in it.
        sections.forEach(function (sec) {
            var any = sec.querySelector('.help-topic:not([style*="display: none"])');
            sec.style.display = (q && !any) ? 'none' : '';
        });
        if (adminPanel) {
            // Open the collapsed admin panel ONLY when a surviving match is actually inside
            // it. Opening on any query would expand a section full of SELinux and permissions
            // topics because someone typed "blast" — the opposite of filtering.
            var hitInAdmin = !!adminPanel.querySelector('.help-topic:not([style*="display: none"])');
            if (q && !filtering) { adminWasOpen = adminPanel.classList.contains('show'); filtering = true; }
            if (q && hitInAdmin) adminPanel.classList.add('show');
            else if (q && !adminWasOpen) adminPanel.classList.remove('show');
            else if (!q && filtering) { if (!adminWasOpen) adminPanel.classList.remove('show'); filtering = false; }
        }
        if (msg) {
            msg.style.display = q ? '' : 'none';
            msg.textContent = shown === 0
                ? 'No topics match \u201c' + input.value.trim() + '\u201d'
                : shown + (shown === 1 ? ' topic' : ' topics') + ' match';
        }
    }
    input.addEventListener('input', apply);
    input.addEventListener('search', apply);   // the clear (x) in a type=search box
});
</script>

<style>
  .help-topic-card { transition: border-color .12s ease, box-shadow .12s ease; border-color: rgba(0,0,0,.1); }
  .help-topic-card:hover { border-color: #0891b2; box-shadow: 0 .25rem .6rem rgba(8,145,178,.15) !important; }
</style>
