<?php
/**
 * Banner shown while an administrator is previewing the site as a public visitor.
 *
 * Rendered from navbar.php, which layout.php includes on every page, so the preview can
 * always be left from wherever the admin has browsed to. The toolbar carries a second
 * "Leave preview" control in the sticky navbar for when this has scrolled out of view —
 * being unable to find the way out is the one failure this mode must not have.
 */

if (!function_exists('moop_viewing_as_public') || !moop_viewing_as_public()) {
    return;
}

$vab_config = ConfigManager::getInstance();
$vab_site   = $vab_config->getString('site');
$vab_return = $_SERVER['REQUEST_URI'] ?? ('/' . $vab_site . '/index.php');
?>
<div class="alert alert-warning border-0 rounded-0 mb-0 py-2" role="status"
     style="border-bottom: 3px solid #d97706 !important;">
  <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
      <i class="fa fa-eye" aria-hidden="true"></i>
      <span>
        <strong>Previewing as a public visitor.</strong>
        You are seeing only data in the <code>PUBLIC</code> group — your administrator access is paused.
      </span>
    </div>
    <form method="post" action="/<?= htmlspecialchars($vab_site) ?>/view_as.php" class="m-0">
      <?= csrf_input_field() ?>
      <input type="hidden" name="action" value="leave">
      <input type="hidden" name="return" value="<?= htmlspecialchars($vab_return) ?>">
      <button type="submit" class="btn btn-sm btn-dark">
        <i class="fa fa-times me-1" aria-hidden="true"></i>Leave preview
      </button>
    </form>
  </div>
</div>
