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
$access_text = t_plain($access_display[$access_level] ?? ucfirst(strtolower($access_level)));
$access_class = [
    'PUBLIC'       => 'badge bg-secondary',
    'COLLABORATOR' => 'badge bg-info',
    'IP_IN_RANGE'  => 'badge bg-warning',
    'ADMIN'        => 'badge bg-danger',
];
$badge_class = $access_class[$access_level] ?? 'badge bg-secondary';

// An admin previewing the public view: the access level reads PUBLIC by design (the
// accessors lie, so every page renders exactly what a visitor gets), but the badge must
// still say who you are -- a plain "Public" badge hid that you were an admin at all.
$va_previewing = function_exists('moop_viewing_as_public') && moop_viewing_as_public();
if ($va_previewing) {
    $access_text = t_plain('Admin · Public view');
    $badge_class = 'badge bg-warning text-dark';
}
?>
<nav class="navbar navbar-expand-md bg-dark navbar-dark sticky-top">
  <div class="container-fluid px-3">

    <a class="navbar-brand" href="/<?= $site ?>/index.php">
      <img id="site_logo" src="<?= $favicon_path ?>" alt="Logo">
    </a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar"
            aria-controls="collapsibleNavbar" aria-expanded="false" aria-label="<?= t('Toggle navigation') ?>">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="collapsibleNavbar">

      <!-- Left nav items -->
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link site-title" href="/<?= $site ?>/index.php"><?= htmlspecialchars($title) ?></a>
        </li>
<?php /* The main bar after the site title: ConfigManager::getMainMenu() -- the site's own
         (Admin -> Manage Menu, or a page's "Show in menu") or the default Tools, About, Help,
         which reproduces the bar as it was hand-written here until 2026-11-03. */
foreach (ConfigManager::getInstance()->getMainMenu(moop_current_lang()) as $mb_item): ?>
<?php if ($mb_item['type'] === 'tools'): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="toolsDropdown" role="button"
             data-bs-toggle="dropdown" aria-expanded="false"><?= t('Tools') ?></a>
<?php /* Built from the tool registry (ConfigManager::getToolsMenu()): order, labels and dividers
         are config/tools_config.php's defaults or the site's own (Admin -> Manage Tools), and a
         switched-off tool is left out. Until 2026-11-03 this was a second, hand-written list of
         tools; the defaults reproduce it byte-for-byte. */ ?>
          <ul class="dropdown-menu" aria-labelledby="toolsDropdown">
<?php foreach (ConfigManager::getInstance()->getToolsMenu() as $tb_item): ?>
<?php if ($tb_item['type'] === 'divider'): ?>
            <li><hr class="dropdown-divider"></li>
<?php else: ?>
            <li><a class="dropdown-item" href="/<?= $site ?><?= htmlspecialchars($tb_item['url_path']) ?>"><?= htmlspecialchars(t_plain($tb_item['label'])) ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
          </ul>
        </li>
<?php elseif ($mb_item['type'] === 'link'): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= htmlspecialchars($mb_item['href']) ?>"<?= $mb_item['external'] ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($mb_item['label']) ?></a>
        </li>
<?php else: ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="<?= $mb_item['id'] ?>" role="button"
             data-bs-toggle="dropdown" aria-expanded="false"><?= htmlspecialchars($mb_item['label']) ?></a>
          <ul class="dropdown-menu" aria-labelledby="<?= $mb_item['id'] ?>">
<?php foreach ($mb_item['items'] as $mb_child): ?>
<?php if ($mb_child['type'] === 'divider'): ?>
            <li><hr class="dropdown-divider"></li>
<?php else: ?>
            <li><a class="dropdown-item" href="<?= htmlspecialchars($mb_child['href']) ?>"<?= $mb_child['external'] ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($mb_child['label']) ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
          </ul>
        </li>
<?php endif; ?>
<?php endforeach; ?>
        <?php if (is_logged_in() && moop_session_is_admin()): ?>
        <li class="nav-item">
          <a id="admin_tools_link" class="nav-link" href="/<?= $site ?>/admin/admin.php"><i class="fa fa-tools me-1"></i> <?= t('Admin Tools') ?></a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Right nav items (collapse into menu on mobile) -->
      <ul class="navbar-nav align-items-md-center gap-2">
<?php
// The language switch: only on a site with more than one language, never on admin pages (they
// stay English). Each choice is the same page with ?lang=, remembered in the moop_lang cookie.
$lb_langs = ConfigManager::getInstance()->languages();
if (count($lb_langs) > 1 && !defined('MOOP_ADMIN_PAGE')): $lb_now = moop_current_lang(); ?>
        <li class="nav-item">
          <div class="btn-group btn-group-sm" role="group" aria-label="<?= t('Language') ?>">
<?php foreach ($lb_langs as $lb_l): ?>
            <a class="btn <?= $lb_l['code'] === $lb_now ? 'btn-light' : 'btn-outline-light' ?>" href="<?= htmlspecialchars(moop_lang_url($lb_l['code'])) ?>"
               hreflang="<?= htmlspecialchars($lb_l['code']) ?>" lang="<?= htmlspecialchars($lb_l['code']) ?>" title="<?= htmlspecialchars($lb_l['name']) ?>"<?= $lb_l['code'] === $lb_now ? ' aria-current="true"' : '' ?>><?= htmlspecialchars(strtoupper($lb_l['code'])) ?></a>
<?php endforeach; ?>
          </div>
        </li>
<?php endif; ?>
        <li class="nav-item navbar-id-search">
          <?php /* Quick gene ID lookup — the index page's ID search (api/feature_search.php),
                   results in a panel under the box. js/modules/navbar-id-search.js */ ?>
          <form class="m-0" role="search" id="navIdSearch" autocomplete="off"<?= ConfigManager::getInstance()->isToolEnabled('annotation_search') ? '' : ' data-no-search="1"' ?>>
            <div class="d-flex align-items-center">
              <div class="input-group input-group-sm">
                <input type="search" class="form-control" id="navIdInput" placeholder="<?= t('Gene ID lookup') ?>"
                       aria-label="<?= t('Look up a gene, transcript or protein ID') ?>"
                       data-1p-ignore data-lpignore="true" data-form-type="other">
                <button class="btn btn-outline-light" type="submit" title="<?= t('Look up this ID') ?>"><i class="fa fa-search"></i></button>
              </div>
              <?php /* The same (i) and the same help as the index page's ID box. */ ?>
              <?= help_modal_trigger('fs-help', '', 'Help: finding a gene by its ID') ?>
            </div>
            <div class="navbar-id-panel shadow" id="navIdPanel" hidden></div>
          </form>
        </li>
        <li class="nav-item">
          <span class="<?= $badge_class ?>"><?php if ($va_previewing): ?><i class="fa fa-eye me-1" aria-hidden="true"></i><?php endif; ?><?= htmlspecialchars($access_text) ?></span>
        </li>

        <?php if (moop_viewing_as_public() || moop_session_is_admin()):
          // One toggle for both directions. While previewing, moop_session_is_admin()
          // is (correctly) false — the accessors lie — so moop_viewing_as_public() is
          // what keeps this control on screen so the admin can always switch back.
          // A bare switch: the badge beside it says which view is on, so the long
          // "View as public" label only repeated it. The name is kept for screen readers. ?>
        <li class="nav-item">
          <form method="post" action="/<?= $site ?>/view_as.php" class="m-0">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="<?= $va_previewing ? 'leave' : 'enter' ?>">
            <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
            <div class="form-check form-switch m-0 d-flex align-items-center"
                 title="<?= $va_previewing ? 'Back to admin view' : 'View as public -- see this page as a visitor who is not logged in' ?>">
              <input class="form-check-input" type="checkbox" role="switch" id="viewAsToggle"
                     <?= $va_previewing ? 'checked' : '' ?> onchange="this.form.submit()">
              <label class="visually-hidden" for="viewAsToggle"><?= $va_previewing ? 'Back to admin view' : 'View as public' ?></label>
            </div>
          </form>
        </li>
        <?php endif; ?>

        <li class="nav-item">
          <?php if (get_access_level() === 'IP_IN_RANGE' || !is_logged_in()): ?>
            <a id="login_link" class="nav-link" href="/<?= $site ?>/login.php"><?= t('Log In') ?> <i class="fa fa-sign-in-alt"></i></a>
          <?php else: ?>
            <a id="logout_link" class="nav-link" href="/<?= $site ?>/logout.php"><?= t('Log Out') ?> <i class="fa fa-sign-out-alt"></i></a>
          <?php endif; ?>
        </li>
      </ul>

    </div>
  </div>
</nav>

<?php include __DIR__ . '/feature_id_help_modal.php';   // opened by the (i) beside both ID boxes ?>

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
