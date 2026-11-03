<?php
/**
 * MANAGE MENU — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $flash, $customised. The editor itself: js/modules/manage-menu.js,
 * drawn into #mm-editor from window.mmData.
 */
?>
<div class="container my-4" style="max-width: 1100px;">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-bars me-2" style="color:#0891b2;"></i>Manage Menu</h3>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="fa fa-arrow-left me-1"></i>Back to Dashboard</a>
  </div>

  <p class="text-muted">
    The menu bar at the top of every page, after the site name. Add links — to one of your
    <a href="manage_pages.php">pages</a>, to About or Help, or to any address — and drop-downs that hold
    several links. The <strong>Tools</strong> drop-down is arranged on <a href="manage_tools.php">Manage Tools</a>;
    here you choose where it sits. A page can also be placed from its own editor.
  </p>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= $flash['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form method="post" id="mm-form">
    <?= csrf_input_field() ?>
    <input type="hidden" name="_action" value="save_menu">
    <input type="hidden" name="menu_json" id="mm-json" value="">

    <div class="card adm-card mb-3">
      <div class="card-header adm-head"><h5 class="mb-0">Menu bar, left to right</h5></div>
      <div class="card-body">
        <div id="mm-editor"><noscript>This editor needs JavaScript.</noscript></div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="button" class="btn btn-sm btn-outline-primary" data-mm-add="link"><i class="fa fa-link me-1"></i>Add a link</button>
          <button type="button" class="btn btn-sm btn-outline-primary" data-mm-add="dropdown"><i class="fa fa-caret-square-down me-1"></i>Add a drop-down</button>
          <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-mm-add="tools" id="mm-add-tools"><i class="fa fa-toolbox me-1"></i>Put the Tools menu back</button>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i>Save</button>
  </form>

  <?php if ($customised): ?>
  <form method="post" class="mt-3" onsubmit="return confirm('Put the menu back to Tools, About, Help? Your drop-downs and links will be removed (the pages themselves are kept).');">
    <?= csrf_input_field() ?>
    <input type="hidden" name="_action" value="reset">
    <button type="submit" class="btn btn-sm btn-outline-danger">Back to the default menu</button>
    <span class="small text-muted ms-2">Tools, About, Help — as shipped.</span>
  </form>
  <?php endif; ?>

  <p class="small text-muted mt-3 mb-0">
    Saved in <code>config/config_editable.json</code> (<code>main_menu</code>). Spanish labels are shown to
    Spanish-language visitors once the site offers Spanish; left empty, the English label is used.
  </p>
</div>
