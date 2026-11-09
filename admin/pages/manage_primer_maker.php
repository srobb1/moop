<?php
/**
 * MANAGE PRIMER MAKER — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $cards (PrimerPresets::all), $settings, $summary(params), $tails,
 * $langs, $form (null = no form open), $form_id, $defaults, $option_specs, $tool_on, $flash,
 * $file_write_error, $config_file. No JavaScript: every control is a form.
 */
$default_id = $settings['default'] !== '' && isset($cards[$settings['default']]) && !$cards[$settings['default']]['off']
    ? $settings['default'] : '';
$first_on = '';
foreach ($cards as $id => $c) { if (!$c['off']) { $first_on = $id; break; } }
$n_on = count(array_filter($cards, fn($c) => !$c['off']));
$fv = fn($k) => htmlspecialchars((string)($form[$k] ?? ''));
?>
<div class="container my-4">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-magic me-2" style="color:var(--moop-tool-header);"></i>Primer Maker</h3>
    <a href="<?= htmlspecialchars(moop_url_base()) ?>/tools/primer_maker.php" target="_blank" class="btn btn-outline-secondary btn-sm ms-auto">
      <i class="fa fa-external-link-alt me-1"></i>Open Primer Maker
    </a>
    <a href="manage_tools.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Manage Tools
    </a>
  </div>

  <p class="text-muted">
    Choose the cards visitors see in Primer Maker&rsquo;s <strong>What are you making?</strong> step, and add your own:
    a card is a starting point (product size, primer length, Tm, GC), and can also choose a tag.
    Visitors can still change any value under <em>More options</em>. The built-in cards can be switched off but not
    changed; to use other numbers, <strong>copy</strong> one into a new card.
  </p>

  <?php if (!$tool_on): ?>
    <div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-1"></i>Primer Maker is switched off on this site
      (<a href="manage_tools.php">Manage Tools</a>), so visitors see none of this.</div>
  <?php endif; ?>

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

  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0">Cards</h5>
      <span class="badge bg-secondary ms-2"><?= $n_on ?> of <?= count($cards) ?> on</span>
      <a href="?new=1#card-form" class="btn btn-sm btn-outline-moop ms-auto"><i class="fa fa-plus me-1"></i>New card</a>
    </div>
    <div class="card-body p-0">
      <?php // The order buttons are their own small forms; HTML cannot nest forms, so they point at
            // this one-row form with form="" rather than sitting inside the save form. ?>
      <?php foreach ($cards as $id => $c): ?>
        <form method="post" id="move-<?= htmlspecialchars($id) ?>" class="d-none">
          <?= csrf_input_field() ?>
          <input type="hidden" name="_action" value="move"><input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
        </form>
      <?php endforeach; ?>
      <form method="post">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="save_cards">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th class="ps-3" style="width:4rem;">On</th><th style="width:8rem;">Selected when the page opens</th><th>Card</th><th style="width:6rem;">Order</th><th style="width:9rem;"></th></tr>
          </thead>
          <tbody>
          <?php $i = 0; foreach ($cards as $id => $c): $i++; ?>
            <tr class="<?= $c['off'] ? 'text-muted' : '' ?>">
              <td class="ps-3">
                <input type="hidden" name="listed[]" value="<?= htmlspecialchars($id) ?>">
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" role="switch" name="on[]" value="<?= htmlspecialchars($id) ?>"
                         id="on-<?= htmlspecialchars($id) ?>" <?= $c['off'] ? '' : 'checked' ?>
                         aria-label="Show <?= htmlspecialchars($c['label']) ?>">
                </div>
              </td>
              <td>
                <input class="form-check-input" type="radio" name="default" value="<?= htmlspecialchars($id) ?>"
                       aria-label="Selected when Primer Maker opens"
                       <?= ($default_id === $id || ($default_id === '' && $first_on === $id)) ? 'checked' : '' ?>>
              </td>
              <td>
                <strong><?= htmlspecialchars($c['label']) ?></strong>
                <?= $c['custom'] ? '<span class="badge bg-light text-dark border ms-1">this site</span>' : '<span class="badge bg-light text-muted border ms-1">built in</span>' ?>
                <?php if ($id === 'rtpcr'): ?><span class="small text-muted ms-1">— never shown on a transcripts-only assembly</span><?php endif; ?>
                <div class="small text-muted"><?= htmlspecialchars($summary($c['params'])) ?><?php
                  if ($c['tail'] !== ''): ?> · <strong>tag: <?= htmlspecialchars($tails[$c['tail']]['label'] ?? $c['tail']) ?></strong><?php endif; ?></div>
                <?php if ($c['blurb'] !== ''): ?><div class="small"><?= htmlspecialchars($c['blurb']) ?></div><?php endif; ?>
              </td>
              <td class="text-nowrap">
                <button type="submit" form="move-<?= htmlspecialchars($id) ?>" name="dir" value="up" class="btn btn-sm btn-outline-secondary py-0"
                        title="Move up" aria-label="Move up" <?= $i === 1 ? 'disabled' : '' ?>><i class="fa fa-arrow-up"></i></button>
                <button type="submit" form="move-<?= htmlspecialchars($id) ?>" name="dir" value="down" class="btn btn-sm btn-outline-secondary py-0"
                        title="Move down" aria-label="Move down" <?= $i === count($cards) ? 'disabled' : '' ?>><i class="fa fa-arrow-down"></i></button>
              </td>
              <td class="text-end pe-3 text-nowrap">
                <?php if ($c['custom']): ?>
                  <a href="?edit=<?= urlencode($id) ?>#card-form" class="btn btn-sm btn-outline-secondary py-0">Edit</a>
                <?php endif; ?>
                <a href="?copy=<?= urlencode($id) ?>#card-form" class="btn btn-sm btn-outline-secondary py-0">Copy</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="p-3 border-top d-flex align-items-center gap-3">
          <button type="submit" class="btn btn-moop-teal text-nowrap flex-shrink-0" <?= $file_write_error ? 'disabled' : '' ?>><i class="fa fa-save me-1"></i>Save changes</button>
          <span class="small text-muted"><strong>On</strong>: students see the card. <strong>Selected when the page opens</strong>: the card already chosen when Primer Maker opens. The arrows set the order and save at once.</span>
        </div>
      </form>
    </div>
  </div>

  <?php if ($form !== null): ?>
  <div class="card adm-card mb-3" id="card-form">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0"><?= $form_id !== '' ? 'Edit card' : 'New card' ?></h5>
      <a href="manage_primer_maker.php" class="btn btn-sm btn-outline-secondary ms-auto">Cancel</a>
    </div>
    <div class="card-body">
      <form method="post">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="save_card">
        <input type="hidden" name="id" value="<?= htmlspecialchars($form_id) ?>">

        <div class="row g-3 mb-3">
          <?php foreach (array_unique(array_merge(['en'], $langs)) as $l): ?>
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="label-<?= $l ?>">Name (<?= htmlspecialchars(strtoupper($l)) ?>)<?= $l === 'en' ? ' <span class="text-danger">*</span>' : '' ?></label>
              <input type="text" class="form-control" id="label-<?= $l ?>" name="label[<?= $l ?>]" maxlength="80"
                     value="<?= htmlspecialchars((string)($form['label'][$l] ?? '')) ?>" <?= $l === 'en' ? 'required' : '' ?>
                     placeholder="<?= $l === 'en' ? 'e.g. WISH / RNAi probe' : 'Blank: English is shown' ?>">
              <label class="form-label mt-2" for="blurb-<?= $l ?>">Description (<?= htmlspecialchars(strtoupper($l)) ?>)</label>
              <textarea class="form-control" id="blurb-<?= $l ?>" name="blurb[<?= $l ?>]" rows="2" maxlength="400"
                        placeholder="Why a visitor would pick this card"><?= htmlspecialchars((string)($form['blurb'][$l] ?? '')) ?></textarea>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="size_range">Product size (bp) <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="size_range" name="size_range" required
                   pattern="\s*\d+\s*-\s*\d+\s*" value="<?= $fv('size_range') ?>" placeholder="e.g. 1000-2500">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="tail">Tag it selects</label>
            <select class="form-select" id="tail" name="tail">
              <option value="">No tag</option>
              <?php foreach ($tails as $t): ?>
                <option value="<?= htmlspecialchars($t['id']) ?>" <?= ($form['tail'] ?? '') === $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Visitors can still change it.</div>
          </div>
        </div>

        <table class="table table-sm table-bordered align-middle mb-2" style="max-width:40rem;">
          <thead class="table-light"><tr><th></th><th>Minimum</th><th>Optimum</th><th>Maximum</th></tr></thead>
          <tbody>
          <?php foreach (['size' => 'Primer length (nt)', 'tm' => 'Melting temperature (°C)', 'gc' => 'GC content (%)'] as $p => $name): ?>
            <tr><th class="fw-normal"><?= $name ?></th>
              <?php foreach (['min', 'opt', 'max'] as $w): $f = "{$p}_{$w}"; ?>
                <td><?php if (in_array($f, PrimerPresets::CARD_FIELDS, true)): $spec = $option_specs[$f]; ?>
                  <input type="number" class="form-control form-control-sm" name="<?= $f ?>" aria-label="<?= htmlspecialchars($spec['label']) ?>"
                         min="<?= $spec['min'] ?>" max="<?= $spec['max'] ?>" step="<?= $spec['type'] === 'int' ? '1' : '0.1' ?>"
                         value="<?= $fv($f) ?>" placeholder="<?= htmlspecialchars($defaults[$f]) ?>">
                <?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="small text-muted">A blank box uses Primer Maker&rsquo;s usual value, shown in grey.</p>

        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-moop-teal" <?= $file_write_error ? 'disabled' : '' ?>><i class="fa fa-save me-1"></i>Save card</button>
          <a href="manage_primer_maker.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
      <?php if ($form_id !== ''): ?>
        <form method="post" class="mt-3 pt-3 border-top">
          <?= csrf_input_field() ?>
          <input type="hidden" name="_action" value="delete_card"><input type="hidden" name="id" value="<?= htmlspecialchars($form_id) ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger" <?= $file_write_error ? 'disabled' : '' ?>><i class="fa fa-trash me-1"></i>Delete this card</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
