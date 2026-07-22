<?php
if (!class_exists('ConfigManager')) {
    include_once __DIR__ . '/config_init.php';
}
$config = ConfigManager::getInstance();
$site   = $config->getString('site');
$title  = $config->getString('siteTitle');
$favicon_path = $config->getUrl('favicon_path');

$access_level = get_access_level();
$access_display = [
    'PUBLIC'       => 'Public',
    'COLLABORATOR' => 'Collaborator',
    'IP_IN_RANGE'  => 'Trusted Network',
    'ADMIN'        => 'Administrator',
];
$access_text = $access_display[$access_level] ?? ucfirst(strtolower($access_level));
$access_class = [
    'PUBLIC'       => 'badge bg-secondary',
    'COLLABORATOR' => 'badge bg-info',
    'IP_IN_RANGE'  => 'badge bg-warning',
    'ADMIN'        => 'badge bg-danger',
];
$badge_class = $access_class[$access_level] ?? 'badge bg-secondary';
?>
<nav class="navbar navbar-expand-md bg-dark navbar-dark sticky-top">
  <div class="container-fluid px-3">

    <a class="navbar-brand" href="/<?= $site ?>/index.php">
      <img id="site_logo" src="<?= $favicon_path ?>" alt="Logo">
    </a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar"
            aria-controls="collapsibleNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="collapsibleNavbar">

      <!-- Left nav items -->
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/index.php"><i class="fa fa-home me-1"></i><?= htmlspecialchars($title) ?></a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="toolsDropdown" role="button"
             data-bs-toggle="dropdown" aria-expanded="false">Tools</a>
          <ul class="dropdown-menu" aria-labelledby="toolsDropdown">
            <li><a class="dropdown-item" href="/<?= $site ?>/jbrowse2.php">Genome Browser</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/search.php">Annotation Search</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/moopmart.php">MOOPmart: Data Exporter</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/blast.php">BLAST Search</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/retrieve_sequences.php">Retrieve Sequences</a></li>
            <li><a class="dropdown-item" href="/<?= $site ?>/tools/downloads.php">Downloads</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/about.php">About</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/<?= $site ?>/help.php">Help</a>
        </li>
        <?php if (is_logged_in() && moop_session_is_admin()): ?>
        <li class="nav-item">
          <a id="admin_tools_link" class="nav-link" href="/<?= $site ?>/admin/admin.php"><i class="fa fa-tools me-1"></i> Admin Tools</a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Right nav items (collapse into menu on mobile) -->
      <ul class="navbar-nav align-items-md-center gap-2">
        <li class="nav-item">
          <span class="<?= $badge_class ?>"><?= htmlspecialchars($access_text) ?></span>
        </li>

        <?php if (moop_viewing_as_public()): ?>
        <!-- Second exit from the public preview. The banner above the navbar scrolls away on a
             long page; this one is in the sticky bar, so the way out is always on screen. -->
        <li class="nav-item">
          <form method="post" action="/<?= $site ?>/view_as.php" class="m-0 d-inline">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="leave">
            <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
            <button type="submit" class="btn btn-sm btn-warning">
              <i class="fa fa-eye me-1"></i>Leave preview
            </button>
          </form>
        </li>
        <?php elseif (moop_session_is_admin()): ?>
        <li class="nav-item">
          <form method="post" action="/<?= $site ?>/view_as.php" class="m-0 d-inline">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="enter">
            <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
            <button type="submit" class="btn btn-sm btn-outline-light"
                    title="See this page as an unauthenticated visitor sees it">
              <i class="fa fa-eye me-1"></i>View as public
            </button>
          </form>
        </li>
        <?php endif; ?>

        <li class="nav-item">
          <?php if (get_access_level() === 'IP_IN_RANGE' || !is_logged_in()): ?>
            <a id="login_link" class="nav-link" href="/<?= $site ?>/login.php">Log In <i class="fa fa-sign-in-alt"></i></a>
          <?php else: ?>
            <a id="logout_link" class="nav-link" href="/<?= $site ?>/logout.php">Log Out <i class="fa fa-sign-out-alt"></i></a>
          <?php endif; ?>
        </li>
      </ul>

    </div>
  </div>
</nav>

<?php if (is_logged_in() && moop_session_is_admin()): ?>
<!-- Admin dashboard loading overlay. The dashboard's first load per session can be slow
     (once-per-session housekeeping snapshot + cold DB reads right after a data rebuild),
     so give the admin immediate feedback instead of a frozen page — otherwise it looks
     broken and they click the link repeatedly. Cleared automatically when the next page loads. -->
<div id="adminLoadingOverlay"
     style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(15,23,42,.78);
            align-items:center;justify-content:center;flex-direction:column;color:#fff;text-align:center;padding:1rem;">
  <div class="spinner-border mb-3" role="status" style="width:3rem;height:3rem;color:#0891b2;"></div>
  <div style="font-size:1.1rem;">Loading admin dashboard…</div>
  <div style="font-size:.85rem;opacity:.75;margin-top:.4rem;">The first load after a data rebuild can take a moment.</div>
</div>
<script>
(function () {
  var link = document.getElementById('admin_tools_link');
  var overlay = document.getElementById('adminLoadingOverlay');
  if (!link || !overlay) return;
  link.addEventListener('click', function (e) {
    // Skip modified clicks (open-in-new-tab etc.) — the current page isn't navigating away.
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    overlay.style.display = 'flex';
    link.style.pointerEvents = 'none';
    link.classList.add('disabled');
  });
})();
</script>
<?php endif; ?>
