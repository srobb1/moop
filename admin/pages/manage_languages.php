<?php
/**
 * MANAGE LANGUAGES — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $flash, $languages, $retired, $written [code => pages with text].
 */
$pages_in = fn(string $code) => ($written[$code] ?? 0) . ' page' . (($written[$code] ?? 0) === 1 ? '' : 's');
?>
<div class="container my-4" style="max-width: 900px;">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-language me-2" style="color:var(--moop-tool-header);"></i>Manage Languages</h3>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="fa fa-arrow-left me-1"></i>Back to Dashboard</a>
  </div>

  <p class="text-muted">
    The languages this site writes its <a href="manage_pages.php">pages</a> and <a href="manage_menu.php">menu</a>
    labels in. Each one adds a tab to every page editor and a label box to every menu item. English is always
    first: anything not yet translated is shown in English.
  </p>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= $flash['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0">Languages in use</h5><span class="badge bg-secondary ms-2"><?= count($languages) ?></span>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Language</th><th>Code</th><th>Pages written in it</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($languages as $l): ?>
          <tr>
            <td class="ps-3"><strong><?= htmlspecialchars($l['name']) ?></strong></td>
            <td><code><?= htmlspecialchars($l['code']) ?></code></td>
            <td class="small"><?= $pages_in($l['code']) ?></td>
            <td class="text-end pe-3">
              <?php if ($l['code'] === 'en'): ?>
                <span class="small text-muted">always used — the fallback</span>
              <?php else: ?>
                <form method="post" class="d-inline"
                      onsubmit="return confirm(<?= htmlspecialchars(json_encode('Retire ' . $l['name'] . '? It leaves the editors and the site. Nothing written in it is deleted, and Restore brings it back.')) ?>);">
                  <?= csrf_input_field() ?>
                  <input type="hidden" name="_action" value="retire">
                  <input type="hidden" name="code" value="<?= htmlspecialchars($l['code']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fa fa-archive me-1"></i>Retire</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head"><h5 class="mb-0">Add a language</h5></div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-end">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="add">
        <div class="col-sm-3">
          <label class="form-label small mb-1" for="code">Code</label>
          <input type="text" class="form-control form-control-sm" id="code" name="code" required
                 pattern="[a-z]{2}(-[a-z]{2,4})?" placeholder="es" title="Two letters (ISO 639-1), e.g. es, fr; or with a region, pt-br">
        </div>
        <div class="col-sm-5">
          <label class="form-label small mb-1" for="name">Name, as its speakers write it</label>
          <input type="text" class="form-control form-control-sm" id="name" name="name" required maxlength="40" placeholder="Español">
        </div>
        <div class="col-sm-4"><button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-plus me-1"></i>Add</button></div>
      </form>
      <p class="small text-muted mt-2 mb-0">Common codes: <code>es</code> Español · <code>fr</code> Français ·
        <code>pt</code> Português · <code>de</code> Deutsch · <code>it</code> Italiano.</p>
    </div>
  </div>

  <?php if ($retired): ?>
  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0">Retired</h5><span class="badge bg-secondary ms-2"><?= count($retired) ?></span>
    </div>
    <div class="card-body p-0">
      <p class="small text-muted px-3 pt-2 mb-1">Not shown anywhere. Everything written in them is kept; Restore brings it back.</p>
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Language</th><th>Code</th><th>Pages still holding it</th><th>Retired</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($retired as $l): ?>
          <tr>
            <td class="ps-3"><?= htmlspecialchars($l['name']) ?></td>
            <td><code><?= htmlspecialchars($l['code']) ?></code></td>
            <td class="small"><?= $pages_in($l['code']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars(substr((string) ($l['retired_at'] ?? ''), 0, 10)) ?><?= !empty($l['retired_by']) ? ' · ' . htmlspecialchars($l['retired_by']) : '' ?></td>
            <td class="text-end pe-3">
              <form method="post" class="d-inline">
                <?= csrf_input_field() ?>
                <input type="hidden" name="_action" value="restore">
                <input type="hidden" name="code" value="<?= htmlspecialchars($l['code']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-success"><i class="fa fa-undo me-1"></i>Restore</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
