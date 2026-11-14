<?php
/**
 * ACCESS OVERVIEW — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $flash, $rows (lib/access_overview.php), $rules_ok, $users_ok, $errors,
 * $organisms, $kinds, $access_datasets. Filters: js/modules/access-overview.js.
 */
$restricted_n = count(array_filter($rows, fn($r) => $r['restricted']));
?>
<div class="container-fluid my-4" style="max-width: 1500px;">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-user-shield me-2" style="color:var(--moop-tool-header);"></i>Access Overview</h3>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="fa fa-arrow-left me-1"></i>Back to Dashboard</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= $flash['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (!$rules_ok): ?>
    <div class="alert alert-danger"><strong><?= htmlspecialchars(basename(moop_access_rules_file())) ?> cannot be read.</strong>
      Until it is fixed, every page and analysis with a rule is shown to admins only, and nothing can be changed here.
      File: <code><?= htmlspecialchars(moop_access_rules_file()) ?></code></div>
  <?php endif; ?>
  <?php if (!$users_ok): ?>
    <div class="alert alert-warning"><strong>The users file cannot be read</strong>, so collaborators are not listed below —
      a gene set may be open to more people than it says. Check Manage Users.</div>
  <?php endif; ?>
  <?php foreach ($errors as $fn => $msg): ?>
    <div class="alert alert-warning">Could not list everything: <code><?= htmlspecialchars($fn) ?></code> — <?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>

  <p class="text-muted">
    Who can see each thing on this site. <strong>If any parent is private, so is the item</strong>: an analysis or page
    can only be limited further than its data set, never opened wider. Data sets are changed in
    <a href="manage_groups.php">Groups</a> (public or not) and <a href="manage_users.php">Users</a> (which collaborators);
    JBrowse tracks in their track sheet; pages and analyses here.
  </p>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center flex-wrap gap-2">
      <h5 class="mb-0">Who can see what</h5>
      <span class="badge bg-secondary"><span class="js-ao-shown"><?= count($rows) ?></span> of <?= count($rows) ?></span>
      <span class="badge bg-warning text-dark"><?= $restricted_n ?> restricted</span>
    </div>
    <div class="card-body pb-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-4"><input type="search" class="form-control form-control-sm js-ao-text" placeholder="Filter: name, organism, group, user…" aria-label="Filter"></div>
        <div class="col-md-3"><select class="form-select form-select-sm js-ao-kind" aria-label="Kind">
          <option value="">Every kind</option>
          <optgroup label="Groups">
            <option value="group:data">Data (gene sets, ID Translator, curations)</option>
            <option value="group:analysis">Analyses (expression, images, clades, tracks)</option>
            <option value="group:page">Pages</option>
          </optgroup>
          <optgroup label="One kind">
            <?php foreach ($kinds as $k => $label): ?><option value="kind:<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
          </optgroup>
        </select></div>
        <div class="col-md-3"><select class="form-select form-select-sm js-ao-org" aria-label="Organism">
          <option value="">Every organism</option>
          <?php foreach ($organisms as $o): ?><option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars(str_replace('_', ' ', $o)) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><div class="form-check">
          <input class="form-check-input js-ao-restricted" type="checkbox" id="ao_restricted">
          <label class="form-check-label small" for="ao_restricted">Only restricted</label>
        </div></div>
      </div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm align-middle mb-0" id="access-overview-table">
        <thead class="table-light"><tr><th class="ps-3">Kind</th><th>Item</th><th>Who can see it</th><th>Why</th><th class="pe-3">Change</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr data-kind="<?= htmlspecialchars($r['kind']) ?>" data-group="<?= htmlspecialchars($r['group']) ?>"
              data-organism="<?= htmlspecialchars($r['organism']) ?>" data-restricted="<?= $r['restricted'] ? '1' : '0' ?>">
            <td class="ps-3 small text-nowrap"><?= htmlspecialchars($kinds[$r['kind']] ?? $r['kind']) ?></td>
            <td><strong><?= htmlspecialchars($r['item']) ?></strong>
              <span class="d-block small text-muted"><?= htmlspecialchars($r['detail']) ?></span></td>
            <td class="small"><?php if ($r['restricted']): ?><i class="fa fa-lock text-warning me-1" title="Not everyone"></i><?php else: ?><i class="fa fa-lock-open text-muted me-1" title="Everyone"></i><?php endif; ?><?= htmlspecialchars($r['who']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($r['why']) ?></td>
            <td class="pe-3 text-nowrap">
              <?php if ($r['edit'] && $rules_ok): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary js-access-edit"
                        data-kind="<?= htmlspecialchars($r['edit']['kind']) ?>" data-key="<?= htmlspecialchars($r['edit']['key']) ?>"
                        data-title="<?= htmlspecialchars($r['edit']['title']) ?>" data-rule="<?= htmlspecialchars(json_encode($r['edit']['rule'])) ?>"
                        data-default="<?= htmlspecialchars($r['edit']['default'] ?? '') ?>"><i class="fa fa-pen me-1"></i>Edit</button>
              <?php endif; ?>
              <?php foreach ($r['links'] as $l): ?>
                <a class="btn btn-sm btn-link px-1" href="<?= htmlspecialchars($l['url']) ?>"><?= htmlspecialchars($l['text']) ?> <i class="fa fa-arrow-right small"></i></a>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="text-muted small p-3 mb-0 d-none js-ao-none">Nothing matches the filter.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_access_rule_dialog.php'; ?>
