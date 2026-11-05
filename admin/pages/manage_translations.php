<?php
/**
 * MANAGE TRANSLATIONS — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $flash, $others (non-English languages), $lang, $lang_name,
 * $counts (moop_i18n_counts()), $catalog, $pending (a checked upload awaiting Save), $gaps_on.
 */
$h = fn($s) => htmlspecialchars((string) $s);
$st_badge = ['reviewed' => 'bg-success', 'machine draft' => 'bg-info text-dark', 'needs translation' => 'bg-secondary'];
?>
<div class="container my-4" style="max-width: 1000px;">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-language me-2" style="color:var(--moop-tool-header);"></i>Manage Translations</h3>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="fa fa-arrow-left me-1"></i>Back to Dashboard</a>
  </div>

  <p class="text-muted">
    The site's own words — buttons, labels, help — in its other languages. Your <a href="manage_pages.php">pages</a>
    and <a href="manage_menu.php">menu</a> are translated where you write them; this is everything else. The work
    is done in a spreadsheet: download it, have it translated and checked, upload it back. Anything not translated
    shows in English, so visitors never see a gap.
  </p>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $h($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= $flash['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

<?php if (!$others): ?>
  <div class="card adm-card mb-3">
    <div class="card-header adm-head"><h5 class="mb-0">This site is in English only</h5></div>
    <div class="card-body">
      <p class="mb-0">Add a language on <a href="manage_languages.php">Manage Languages</a> first. Until then there is
        nothing to translate and the site shows exactly what it shows today.</p>
    </div>
  </div>
<?php else: ?>

  <?php if (count($others) > 1): ?>
  <ul class="nav nav-tabs mb-3">
    <?php foreach ($others as $l): ?>
      <li class="nav-item"><a class="nav-link<?= $l['code'] === $lang ? ' active' : '' ?>" href="?lang=<?= urlencode($l['code']) ?>"><?= $h($l['name']) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0"><?= $h($lang_name) ?>: where things stand</h5>
    </div>
    <div class="card-body">
      <?php $shown = $counts['shown']; $done = $counts['reviewed'] + $counts['machine draft']; ?>
      <p class="mb-2"><strong><?= $shown ?></strong> line<?= $shown === 1 ? '' : 's' ?> of text seen on this site's pages so far
        <?php if ($shown): ?>— <strong><?= $done ?></strong> translated (<?= (int) round(100 * $done / $shown) ?>%).<?php endif; ?></p>
      <div class="d-flex flex-wrap gap-2 mb-2">
        <span class="badge bg-success"><?= $counts['reviewed'] ?> reviewed</span>
        <span class="badge bg-info text-dark"><?= $counts['machine draft'] ?> machine draft</span>
        <span class="badge bg-secondary"><?= $counts['needs translation'] ?> needs translation</span>
      </div>
      <p class="small text-muted mb-0">
        "Seen" fills in as pages are viewed — open each public page once (in any language) before exporting, so the
        spreadsheet holds everything. <?= $counts['in_code'] ?> lines are marked for translation in the code; the
        full export includes those not seen yet (for example, text of tools this site has switched off).
        <?php if ($counts['unused']): ?><?= $counts['unused'] ?> translated line<?= $counts['unused'] === 1 ? ' is' : 's are' ?> no longer used anywhere and kept in case it comes back.<?php endif; ?>
      </p>
    </div>
  </div>

  <?php if ($pending): $r = $pending['res']; ?>
  <div class="card adm-card mb-3">
    <div class="card-header adm-head-warn d-flex align-items-center">
      <h5 class="mb-0">Check the upload before saving: <?= $h($pending['file']) ?></h5>
    </div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge bg-primary"><?= count($r['changes']) ?> new or changed</span>
        <span class="badge bg-light text-dark border"><?= $r['same'] ?> unchanged</span>
        <?php if ($r['kept']): ?><span class="badge bg-light text-dark border"><?= $r['kept'] ?> blank in the sheet — existing translation kept</span><?php endif; ?>
        <?php if ($r['refused']): ?><span class="badge bg-danger"><?= count($r['refused']) ?> refused</span><?php endif; ?>
        <?php if ($r['unknown']): ?><span class="badge bg-warning text-dark"><?= count($r['unknown']) ?> English not found</span><?php endif; ?>
      </div>

      <?php if ($r['refused']): ?>
        <h6>Refused — fix these in the spreadsheet and upload again</h6>
        <table class="table table-sm small mb-3"><thead class="table-light"><tr><th>English</th><th>Why</th></tr></thead><tbody>
        <?php foreach ($r['refused'] as [$en, $why]): ?><tr><td><?= $h($en) ?></td><td><?= $h($why) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>

      <?php if ($r['unknown']): ?>
        <h6>English the site does not have</h6>
        <p class="small text-muted mb-1">Usually the English column was edited by mistake (it must stay exactly as exported), or the
          text has since changed on the site. These lines are not saved.</p>
        <ul class="small mb-3"><?php foreach (array_slice($r['unknown'], 0, 50) as $en): ?><li><?= $h($en) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>

      <?php if ($r['changes']): ?>
        <h6>Will be saved</h6>
        <div style="max-height: 24rem; overflow:auto;" class="mb-3">
        <table class="table table-sm small mb-0"><thead class="table-light" style="position:sticky;top:0;"><tr><th>English</th><th>Now</th><th>After saving</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($r['changes'] as $en => $c): ?>
          <tr><td><?= $h($en) ?></td><td class="text-muted"><?= $h($catalog[$en]['text'] ?? '') ?></td><td><?= $h($c['text']) ?></td>
              <td><span class="badge <?= $st_badge[$c['status']] ?? 'bg-secondary' ?>"><?= $h($c['status']) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        </div>
      <?php endif; ?>

      <div class="d-flex gap-2">
        <?php if ($r['changes']): ?>
        <form method="post"><?= csrf_input_field() ?><input type="hidden" name="_action" value="apply"><input type="hidden" name="lang" value="<?= $h($lang) ?>">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save me-1"></i>Save <?= count($r['changes']) ?> line<?= count($r['changes']) === 1 ? '' : 's' ?></button></form>
        <?php endif; ?>
        <form method="post"><?= csrf_input_field() ?><input type="hidden" name="_action" value="discard"><input type="hidden" name="lang" value="<?= $h($lang) ?>">
          <button type="submit" class="btn btn-outline-secondary btn-sm">Discard this upload</button></form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head"><h5 class="mb-0">1 · Download the spreadsheet</h5></div>
    <div class="card-body">
      <p class="small mb-2">One row per line of text: the English, the <?= $h($lang_name) ?> so far, its status, the codes it must keep
        (like <code>%d</code>), and the pages it was seen on. A second sheet tells the translator how to fill it in.</p>
      <a class="btn btn-primary btn-sm" href="?lang=<?= urlencode($lang) ?>&amp;export=1"><i class="fa fa-file-excel me-1"></i>Download — text this site shows</a>
      <a class="btn btn-outline-secondary btn-sm ms-1" href="?lang=<?= urlencode($lang) ?>&amp;export=1&amp;scope=all">Everything in the code</a>
    </div>
  </div>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head"><h5 class="mb-0">2 · Translate, then review</h5></div>
    <div class="card-body small">
      <ol class="mb-0">
        <li>Have an agent fill the empty <strong>Translation</strong> cells and set their Status to <em>machine draft</em>.</li>
        <li>A person who reads <?= $h($lang_name) ?> checks each line, corrects it, and sets Status to <em>reviewed</em>.</li>
        <li>Nobody edits the <strong>English</strong> column: it is how each line is matched back to the site.</li>
      </ol>
    </div>
  </div>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head"><h5 class="mb-0">3 · Upload it back</h5></div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="upload">
        <input type="hidden" name="lang" value="<?= $h($lang) ?>">
        <input type="file" name="sheet" accept=".xlsx,.csv" class="form-control form-control-sm" style="max-width: 24rem;" required>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-upload me-1"></i>Check this file</button>
      </form>
      <p class="small text-muted mt-2 mb-0">Nothing is saved until you have seen what would change. The same file can be uploaded again
        after more review: only lines that changed are saved.</p>
    </div>
  </div>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0">Show gaps on the site</h5>
      <span class="badge <?= $gaps_on ? 'bg-warning text-dark' : 'bg-light text-dark border' ?> ms-2"><?= $gaps_on ? 'on' : 'off' ?></span>
    </div>
    <div class="card-body">
      <p class="small mb-2">While this is on, text with no <?= $h($lang_name) ?> yet is marked <strong>⟦like this⟧</strong> on the site's
        pages when they are shown in <?= $h($lang_name) ?> — for you only. Visitors always see the English instead.</p>
      <form method="post" class="d-inline">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="gaps">
        <input type="hidden" name="lang" value="<?= $h($lang) ?>">
        <input type="hidden" name="on" value="<?= $gaps_on ? '' : '1' ?>">
        <button type="submit" class="btn btn-sm <?= $gaps_on ? 'btn-outline-secondary' : 'btn-outline-primary' ?>"><?= $gaps_on ? 'Turn off' : 'Turn on' ?></button>
      </form>
      <a class="btn btn-sm btn-outline-secondary ms-1" href="/<?= $h($site) ?>/index.php?lang=<?= urlencode($lang) ?>" target="_blank">Open the home page in <?= $h($lang_name) ?></a>
    </div>
  </div>

<?php endif; ?>
</div>
