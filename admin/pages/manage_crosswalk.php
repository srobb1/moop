<?php
/**
 * MANAGE ROSETTA STONE — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $organism, $organisms, $cw (moop_crosswalk_sources()[$organism]), $sources
 * [name => shown label], $counts [source => ids], $gene_sets ["assembly|gene_set" => label],
 * $customised, $tool_on, $saved_problem, $flash, $file_write_error, $config_file. No JavaScript: one form.
 */
$h = fn($s) => htmlspecialchars((string) $s);
$groups = (array) ($cw['source_groups'] ?? []);
$conf   = (array) ($cw['sources'] ?? []);
$always = (array) ($cw['always_show'] ?? []);
$dis    = $file_write_error ? ' disabled' : '';
?>
<div class="container my-4">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-exchange-alt me-2" style="color:var(--moop-tool-header);"></i>Rosetta Stone (ID translator)</h3>
    <a href="<?= $h(moop_url('tools/crosswalk.php')) ?>" target="_blank" class="btn btn-outline-secondary btn-sm ms-auto">
      <i class="fa fa-external-link-alt me-1"></i>Open the page
    </a>
    <a href="manage_tools.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>Manage Tools</a>
  </div>

  <p class="text-muted">
    What visitors see on the ID translator: the page title, the groups of the <strong>Translate into</strong> list, and
    for every source its name, its group and where its IDs open. The mappings themselves come from the organism&rsquo;s
    <code>crosswalk.sqlite</code>; nothing here changes them.
  </p>

  <?php if (!$tool_on): ?>
    <div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-1"></i>The ID translator is switched off on this site
      (<a href="manage_tools.php">Manage Tools</a>), so visitors see none of this.</div>
  <?php endif; ?>
  <?php if ($saved_problem !== ''): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-triangle me-1"></i>The saved settings in <code><?= $h($config_file) ?></code>
      (<code>crosswalk_display</code>) are not valid, so visitors see the shipped settings: <?= $h($saved_problem) ?>. Saving this page replaces them.</div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $h($flash['type']) ?>"><?= $flash['msg'] ?></div>
  <?php endif; ?>
  <?php if ($file_write_error): ?>
    <div class="alert alert-danger"><i class="fa fa-lock me-1"></i>The settings file cannot be written: <code><?= $h($config_file) ?></code>. <?= $h($file_write_error) ?></div>
  <?php endif; ?>

  <?php if ($organism === ''): ?>
    <div class="alert alert-light border">No organism has a crosswalk file (<code>organisms/&lt;organism&gt;/crosswalk.sqlite</code>), so there is nothing to set up.</div>
  <?php else: ?>

  <?php if (count($organisms) > 1): ?>
    <form method="get" class="d-flex align-items-center gap-2 mb-3">
      <label for="cwOrg" class="fw-semibold">Organism</label>
      <select class="form-select form-select-sm w-auto" id="cwOrg" name="organism">
        <?php foreach ($organisms as $o): ?><option value="<?= $h($o) ?>"<?= $o === $organism ? ' selected' : '' ?>><?= $h(str_replace('_', ' ', $o)) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-secondary">Show</button>
    </form>
  <?php endif; ?>

  <form method="post" action="manage_crosswalk.php?organism=<?= $h(urlencode($organism)) ?>">
    <?= csrf_input_field() ?>
    <input type="hidden" name="_action" value="save">
    <input type="hidden" name="organism" value="<?= $h($organism) ?>">

    <div class="card adm-card mb-3">
      <div class="card-header adm-head"><h5 class="mb-0">Page title</h5></div>
      <div class="card-body">
        <input type="text" class="form-control" name="name" maxlength="100" style="max-width:32rem;" value="<?= $h($cw['name'] ?? '') ?>" placeholder="Rosetta Stone ID Translator"<?= $dis ?>>
        <div class="small text-muted mt-1">Shown at the top of the page. The menu and toolbox names are set in <a href="manage_tools.php">Manage Tools</a>.</div>
      </div>
    </div>

    <div class="card adm-card mb-3">
      <div class="card-header adm-head"><h5 class="mb-0">Groups</h5></div>
      <div class="card-body">
        <p class="small text-muted">The list visitors tick from is shown in these groups, in this order (lowest number first). A source with no group goes under <em>Other</em>, last. Removing a group moves its sources to Other.</p>
        <table class="table table-sm align-middle w-auto">
          <thead><tr><th>Order</th><th>Name</th><th>Remove</th></tr></thead>
          <tbody>
          <?php $i = 0; foreach ($groups as $key => $label): $i++; ?>
            <tr>
              <td><input type="number" class="form-control form-control-sm" style="width:5rem;" name="grp[<?= $i ?>][pos]" value="<?= $i * 10 ?>"<?= $dis ?>></td>
              <td><input type="hidden" name="grp[<?= $i ?>][key]" value="<?= $h($key) ?>">
                  <input type="text" class="form-control form-control-sm" style="width:20rem;" maxlength="60" name="grp[<?= $i ?>][label]" value="<?= $h($label) ?>"<?= $dis ?>></td>
              <td class="text-center"><input type="checkbox" class="form-check-input" name="grp[<?= $i ?>][remove]" value="1" aria-label="Remove <?= $h($label) ?>"<?= $dis ?>></td>
            </tr>
          <?php endforeach; $i++; ?>
            <tr>
              <td><input type="number" class="form-control form-control-sm" style="width:5rem;" name="grp[<?= $i ?>][pos]" value="<?= $i * 10 ?>"<?= $dis ?>></td>
              <td><input type="text" class="form-control form-control-sm" style="width:20rem;" maxlength="60" name="grp[<?= $i ?>][label]" placeholder="Add a group" value=""<?= $dis ?>></td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card adm-card mb-3">
      <div class="card-header adm-head"><h5 class="mb-0">Sources <span class="badge bg-secondary ms-1"><?= count($sources) ?></span></h5></div>
      <div class="card-body">
        <p class="small text-muted mb-2">
          Every source in the crosswalk file. <strong>Opens</strong>: a gene set loaded on this site (its IDs open MOOP gene pages),
          an outside link (https, with <code>{id}</code> where the ID goes &mdash; e.g. <code>https://www.ncbi.nlm.nih.gov/nuccore/{id}</code>),
          or plain text. Plain text is better than a dead link. <strong>Every gene</strong>: shown as its own column beside every
          reference gene, whatever the visitor ticks (at most 5).
        </p>
        <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Source</th><th>Name shown</th><th>Group</th><th>Opens</th><th>Outside link</th><th>About page</th><th class="text-center">Every gene</th></tr></thead>
          <tbody>
          <?php foreach ($sources as $name => $shown): $c = (array) ($conf[$name] ?? []);
              $opens = !empty($c['gene_set']) ? ($c['assembly'] ?? '') . '|' . $c['gene_set'] : (!empty($c['url']) ? 'url' : ''); $f = 'src[' . $h($name) . ']'; ?>
            <tr>
              <td class="font-monospace small text-nowrap"><?= $h($name) ?><div class="text-muted"><?= number_format($counts[$name] ?? 0) ?> IDs</div></td>
              <td><input type="text" class="form-control form-control-sm" style="min-width:16rem;" maxlength="200" name="<?= $f ?>[label]" value="<?= $h($c['label'] ?? '') ?>" placeholder="<?= $h($name) ?>"<?= $dis ?>></td>
              <td><select class="form-select form-select-sm" style="min-width:11rem;" name="<?= $f ?>[group]"<?= $dis ?>>
                    <option value="">Other</option>
                    <?php foreach ($groups as $key => $label): ?><option value="<?= $h($key) ?>"<?= ($c['group'] ?? '') === (string) $key ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
                  </select></td>
              <td><select class="form-select form-select-sm" style="min-width:15rem;" name="<?= $f ?>[opens]"<?= $dis ?>>
                    <option value="">Plain text</option>
                    <option value="url"<?= $opens === 'url' ? ' selected' : '' ?>>Outside link</option>
                    <?php foreach ($gene_sets as $k => $label): ?><option value="<?= $h($k) ?>"<?= $opens === $k ? ' selected' : '' ?>>Gene page: <?= $h($label) ?></option><?php endforeach; ?>
                  </select></td>
              <td><input type="url" class="form-control form-control-sm" style="min-width:14rem;" maxlength="400" name="<?= $f ?>[url]" value="<?= $h($c['url'] ?? '') ?>" placeholder="https://…{id}"<?= $dis ?>></td>
              <td><input type="url" class="form-control form-control-sm" style="min-width:12rem;" maxlength="400" name="<?= $f ?>[about]" value="<?= $h($c['about'] ?? '') ?>" placeholder="https://…"<?= $dis ?>></td>
              <td class="text-center"><input type="checkbox" class="form-check-input" name="<?= $f ?>[always]" value="1"<?= in_array($name, $always, true) ? ' checked' : '' ?> aria-label="<?= $h($name) ?> in every gene"<?= $dis ?>></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <button class="btn btn-primary"<?= $dis ?>><i class="fa fa-save me-1"></i>Save</button>
  </form>

  <?php if ($customised): ?>
  <form method="post" action="manage_crosswalk.php?organism=<?= $h(urlencode($organism)) ?>" class="mt-3">
    <?= csrf_input_field() ?>
    <input type="hidden" name="_action" value="reset">
    <input type="hidden" name="organism" value="<?= $h($organism) ?>">
    <button class="btn btn-sm btn-outline-danger"<?= $dis ?>>Back to the shipped settings</button>
    <span class="small text-muted ms-2">Forgets everything saved on this page for <?= $h(str_replace('_', ' ', $organism)) ?>.</span>
  </form>
  <?php endif; ?>

  <div class="card adm-card mt-4">
    <div class="card-header adm-head"><h5 class="mb-0">Not on this page</h5></div>
    <div class="card-body small">
      The reference gene sets (<em>hubs</em>: <?= $h(implode(', ', array_keys((array) ($cw['hubs'] ?? [])))) ?>) and the folder a local build reads
      are in <code>config/site_config.php</code> (<code>crosswalk</code>). They are server paths used by the build, so they are never edited from a web page.
      The file itself is built by the compute pipeline, or here with <code>php scripts/build_crosswalk.php</code>.
    </div>
  </div>

  <?php endif; ?>
</div>
