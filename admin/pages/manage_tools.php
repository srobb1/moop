<?php
/**
 * MANAGE TOOLS — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $tools, $enabled, $flash, $file_write_error, $config_file,
 * $menu_rows, $box_rows, $label_of(id, where), $default_label_of(id, where), $customised.
 * Reordering: js/modules/manage-tools.js (up/down, add/remove divider).
 */
$where = function ($pages): string {
    if ($pages === 'all' || $pages === null) return 'every page';
    $names = ['index' => 'home', 'parent' => 'gene', 'gene_set' => 'gene set', 'multi_organism_search' => 'multi-organism search'];
    return implode(', ', array_map(fn($p) => $names[$p] ?? str_replace('_', ' ', $p), (array) $pages)) . ' page' . (count((array) $pages) === 1 ? '' : 's');
};
$n_off = count(array_filter($enabled, fn($on) => !$on));
?>
<div class="container my-4">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-toolbox me-2" style="color:var(--moop-tool-header);"></i>Manage Tools</h3>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm ms-auto">
      <i class="fa fa-arrow-left me-1"></i>Back to Dashboard
    </a>
  </div>

  <p class="text-muted">
    Choose which tools this site offers. A tool you switch off disappears from the toolbox and
    from the <strong>Tools</strong> menu in the navigation bar, and its page answers
    <em>not found</em>. Nothing is deleted: switch it back on and it returns as it was.
  </p>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= $flash['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($file_write_error): ?>
    <div class="alert alert-warning">
      <i class="fa fa-exclamation-triangle me-1"></i>
      <strong>Read-only:</strong> the web server cannot write
      <code><?= htmlspecialchars($config_file) ?></code>, so changes cannot be saved. Run:
      <?= adm_command($file_write_error['command'] ?? '') ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_input_field() ?>
    <input type="hidden" name="_action" value="save_tools">
    <div class="card adm-card mb-3">
      <div class="card-header adm-head d-flex align-items-center">
        <h5 class="mb-0">Tools</h5>
        <span class="badge bg-secondary ms-2"><?= count($tools) - $n_off ?> of <?= count($tools) ?> on</span>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th class="ps-3" style="width:6rem;">On</th><th>Tool</th><th>Shown on</th><th>Address</th></tr>
          </thead>
          <tbody>
          <?php foreach ($tools as $id => $tool): $on = $enabled[$id] ?? true; ?>
            <tr class="<?= $on ? '' : 'text-muted' ?>">
              <td class="ps-3">
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" role="switch" name="enabled[]"
                         id="tool_<?= htmlspecialchars($id) ?>" value="<?= htmlspecialchars($id) ?>"
                         <?= $on ? 'checked' : '' ?> <?= $file_write_error ? 'disabled' : '' ?>>
                </div>
              </td>
              <td>
                <label for="tool_<?= htmlspecialchars($id) ?>" class="mb-0">
                  <i class="fa <?= htmlspecialchars($tool['icon'] ?? 'fa-wrench') ?> me-1" aria-hidden="true"></i>
                  <strong><?= htmlspecialchars($tool['name'] ?? $id) ?></strong>
                  <span class="d-block small text-muted"><?= htmlspecialchars($tool['description'] ?? '') ?></span>
                </label>
              </td>
              <td class="small"><?= htmlspecialchars($where($tool['pages'] ?? 'all')) ?></td>
              <td class="small"><code>/<?= htmlspecialchars($site . ($tool['url_path'] ?? '')) ?></code></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    // One row of an ordering list. Hidden *_order[] inputs carry the order the rows are in when
    // the form is sent, so moving a row is all the up/down buttons have to do.
    $order_row = function (string $list, array $row) use ($tools, $enabled, $label_of, $default_label_of, $file_write_error): string {
        $dis = $file_write_error ? ' disabled' : '';
        $moves = '<div class="btn-group btn-group-sm">'
               . '<button type="button" class="btn btn-outline-secondary mt-up" title="Move up" aria-label="Move up"' . $dis . '><i class="fa fa-arrow-up"></i></button>'
               . '<button type="button" class="btn btn-outline-secondary mt-down" title="Move down" aria-label="Move down"' . $dis . '><i class="fa fa-arrow-down"></i></button></div>';
        if ($row['id'] === '|') {
            return '<tr class="mt-divider table-light"><td class="ps-3">' . $moves
                 . '<input type="hidden" name="menu_order[]" value="|"></td>'
                 . '<td colspan="2"><span class="text-muted small"><i class="fa fa-minus me-1"></i>divider line</span></td>'
                 . '<td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-danger mt-remove"' . $dis . '>Remove</button></td></tr>';
        }
        $id   = $row['id'];
        $h    = htmlspecialchars($id);
        $off  = !($enabled[$id] ?? true);
        $tool = $tools[$id] ?? [];
        return '<tr' . ($off ? ' class="text-muted"' : '') . '><td class="ps-3">' . $moves
             . '<input type="hidden" name="' . $list . '_order[]" value="' . $h . '"></td>'
             . '<td><div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="' . $list . '_show[]" value="' . $h . '"'
             . ' id="' . $list . '_' . $h . '"' . ($row['show'] ? ' checked' : '') . $dis . '>'
             . '<label class="form-check-label" for="' . $list . '_' . $h . '"><i class="fa ' . htmlspecialchars($tool['icon'] ?? 'fa-wrench') . ' me-1"></i>'
             . htmlspecialchars($tool['name'] ?? $id) . ($off ? ' <span class="badge bg-secondary">switched off</span>' : '') . '</label></div></td>'
             . '<td><input type="text" class="form-control form-control-sm" maxlength="60" name="label_' . ($list === 'menu' ? 'menu' : 'toolbox') . '[' . $h . ']"'
             . ' value="' . htmlspecialchars($label_of($id, $list === 'menu' ? 'menu' : 'toolbox')) . '"'
             . ' placeholder="' . htmlspecialchars($default_label_of($id, $list === 'menu' ? 'menu' : 'toolbox')) . '"' . $dis . '></td>'
             . '<td></td></tr>';
    };
    ?>

    <div class="card adm-card mb-3">
      <div class="card-header adm-head"><h5 class="mb-0">Tools menu in the navigation bar</h5></div>
      <div class="card-body p-0">
        <p class="small text-muted px-3 pt-3 mb-2">Ticked tools appear in the <strong>Tools</strong> menu, top to
          bottom in this order, with the label you give them. A divider draws a line between groups.</p>
        <table class="table table-sm align-middle mb-0 mt-list" data-list="menu">
          <thead class="table-light"><tr><th class="ps-3" style="width:7rem;">Order</th><th>Show</th><th>Label in the menu</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($menu_rows as $row) echo $order_row('menu', $row); ?>
          </tbody>
        </table>
        <div class="px-3 py-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="mt-add-divider" <?= $file_write_error ? 'disabled' : '' ?>>
            <i class="fa fa-plus me-1"></i>Add a divider</button>
        </div>
      </div>
    </div>

    <div class="card adm-card mb-3">
      <div class="card-header adm-head"><h5 class="mb-0">Toolbox</h5></div>
      <div class="card-body p-0">
        <p class="small text-muted px-3 pt-3 mb-2">The toolbox on organism, assembly, gene set and gene pages. Ticked
          tools appear in this order, each where it can run: a tool that needs a gene shows only on gene pages.</p>
        <table class="table table-sm align-middle mb-0 mt-list" data-list="toolbox">
          <thead class="table-light"><tr><th class="ps-3" style="width:7rem;">Order</th><th>Show</th><th>Label on the button</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($box_rows as $row) echo $order_row('toolbox', $row); ?>
          </tbody>
        </table>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" <?= $file_write_error ? 'disabled' : '' ?>>
      <i class="fa fa-save me-1"></i>Save
    </button>
    <?php if ($customised): ?>
      <span class="small text-muted ms-2">This site has its own order or labels. Put everything back as shipped,
        then Save, to follow the defaults again.</span>
    <?php endif; ?>
  </form>

  <p class="small text-muted mt-3 mb-0">
    Saved in <code>config/config_editable.json</code> (<code>tools_disabled</code>, <code>tools_layout</code>);
    a choice that matches the shipped default is not saved, so the site keeps following it. The list of
    tools itself is <code>config/tools_config.php</code>, which this page never changes.
  </p>
</div>
