<?php
/**
 * MANAGE TOOLS — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $tools, $enabled, $flash, $file_write_error, $config_file.
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
    <h3 class="mb-0"><i class="fa fa-toolbox me-2" style="color:#0891b2;"></i>Manage Tools</h3>
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
    <button type="submit" class="btn btn-primary" <?= $file_write_error ? 'disabled' : '' ?>>
      <i class="fa fa-save me-1"></i>Save
    </button>
  </form>

  <p class="small text-muted mt-3 mb-0">
    Saved in <code>config/config_editable.json</code> (<code>tools_disabled</code>). The list of
    tools itself is <code>config/tools_config.php</code>, which this page never changes.
  </p>
</div>
