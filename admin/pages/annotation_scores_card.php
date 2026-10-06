<?php
/**
 * "Score meanings" card on Manage Annotations. Data from admin/annotation_scores_admin.php:
 * $score_raw, $score_cfg, $score_unmatched, $score_source_total, $score_flash,
 * $score_file, $score_file_write_error.
 */
$__kind_options = function (string $selected) use ($score_cfg): string {
    $html = '';
    foreach ($score_cfg['kinds'] as $id => $k) {
        $html .= '<option value="' . htmlspecialchars($id) . '"' . ($id === $selected ? ' selected' : '') . '>'
               . htmlspecialchars($k['label']) . '</option>';
    }
    return $html;
};
// Type: a dropdown of the site's annotation types, "(any type)" first, so an empty value
// reads as a choice rather than a blank, and a typo cannot make a rule silently match nothing.
$__type_options = function (string $selected) use ($score_types): string {
    $html = '<option value="">(any type)</option>';
    foreach ($score_types as $t) {
        $html .= '<option value="' . htmlspecialchars($t) . '"' . ($t === $selected ? ' selected' : '') . '>'
               . htmlspecialchars($t) . '</option>';
    }
    return $html;
};
// What a kind looks like on the gene page, from a typical stored value.
$__example = function (string $id, array $k) use ($score_cfg): string {
    $sample = ['evalue' => 2.66983e-05, 'number' => 36.295753, 'percent' => 0.999424, 'count' => 7, 'none' => 1][$k['display']] ?? null;
    if ($k['display'] === 'words') {
        $first = array_key_first($k['words']);
        $sample = $first === null ? 1 : (int)$first;
    }
    $f = moop_format_score($sample, $id, $score_cfg);
    return htmlspecialchars((string)$sample) . ' → ' . $f['display'];
};
?>
<div class="card adm-card mb-4" id="score-meanings">
  <div class="card-header adm-head d-flex align-items-center justify-content-between">
    <h5 class="mb-0"><i class="fa fa-ruler"></i> Score meanings</h5>
    <small class="text-muted"><?= count($score_cfg['rules']) ?> rules · <?= count($score_cfg['kinds']) ?> kinds</small>
  </div>
  <div class="card-body">
    <p class="text-muted mb-3">
      The Score column means something different for each source: an E-value, a probability, a code.
      These rules decide how each source's score is shown in the gene page tables, what hovering it says,
      and what the (i) above each table explains. Add a rule when you load a new analysis.
    </p>

    <?php if ($score_flash): ?>
      <div class="alert alert-<?= htmlspecialchars($score_flash['type']) ?> py-2"><?= htmlspecialchars($score_flash['msg']) ?></div>
    <?php endif; ?>
    <?php if ($score_file_write_error): ?>
      <div class="alert alert-danger py-2">
        <code><?= htmlspecialchars($score_file) ?></code> is not writable by the web server, so changes cannot be saved.
        Fix: <code><?= htmlspecialchars($score_file_write_error['command']) ?></code>
      </div>
    <?php elseif (!is_file($score_file)): ?>
      <div class="alert alert-light border py-2 small mb-3">
        Using the shipped defaults. The first change you save writes them to <code><?= htmlspecialchars($score_file) ?></code>.
      </div>
    <?php endif; ?>

    <?php /* The one part of this card that can need ATTENTION: a source nothing explains. */ ?>
    <?php if ($score_unmatched): ?>
    <div class="card adm-card mb-3">
      <div class="card-header adm-head-warn">
        <strong><?= count($score_unmatched) ?> source<?= count($score_unmatched) === 1 ? '' : 's' ?> with no score meaning</strong>
        <span class="small">— shown as the raw number until a rule covers <?= count($score_unmatched) === 1 ? 'it' : 'them' ?></span>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($score_unmatched as $u): ?>
        <li class="list-group-item">
          <form method="post" class="d-flex flex-wrap align-items-center gap-2 mb-0">
            <?= csrf_input_field() ?>
            <input type="hidden" name="_score_action" value="rule_add">
            <input type="hidden" name="source" value="<?= htmlspecialchars($u['source']) ?>">
            <span class="me-auto"><strong><?= htmlspecialchars($u['source']) ?></strong>
              <span class="text-muted small">(<?= htmlspecialchars($u['type']) ?>)</span></span>
            <select name="kind" class="form-select form-select-sm w-auto"><?= $__kind_options('') ?></select>
            <button class="btn btn-sm btn-outline-primary" <?= $score_file_write_error ? 'disabled' : '' ?>>Add rule</button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php else: ?>
      <p class="small text-success mb-3"><i class="fa fa-check"></i> Every source on this site (<?= (int)$score_source_total ?> across all organisms) has a score meaning.</p>
    <?php endif; ?>

    <h6 class="mt-3">Rules <small class="text-muted fw-normal">— checked top to bottom; the first match decides</small></h6>
    <p class="small text-muted mb-2">
      <strong>Source starts with</strong> matches the beginning of a source name, so <code>OMA HOG orthologs</code>
      covers every species. <strong>Type</strong> is the annotation type — the table the source appears in on the
      gene page. Most rules need only a source and leave Type as <em>(any type)</em>; the rules at the bottom give
      only a type, as the fallback for any source in that table that no rule above caught. Give both to narrow a
      rule to one source in one table.
    </p>
    <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>#</th><th>Source starts with</th><th>Type</th><th>Kind</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($score_raw['rules'] as $i => $r): $form = "scoreRule$i"; ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td><input form="<?= $form ?>" name="source" class="form-control form-control-sm" value="<?= htmlspecialchars($r['source'] ?? '') ?>" placeholder="(any source)"></td>
          <td><select form="<?= $form ?>" name="type" class="form-select form-select-sm"><?= $__type_options((string)($r['type'] ?? '')) ?></select></td>
          <td><select form="<?= $form ?>" name="kind" class="form-select form-select-sm"><?= $__kind_options((string)($r['kind'] ?? '')) ?></select></td>
          <td class="text-nowrap">
            <form id="<?= $form ?>" method="post" class="d-inline">
              <?= csrf_input_field() ?>
              <input type="hidden" name="rule_index" value="<?= $i ?>">
              <button name="_score_action" value="rule_save" class="btn btn-sm btn-outline-primary" title="Save this rule">Save</button>
            </form>
            <form method="post" class="d-inline">
              <?= csrf_input_field() ?>
              <input type="hidden" name="_score_action" value="rule_move">
              <input type="hidden" name="rule_index" value="<?= $i ?>">
              <button name="dir" value="up" class="btn btn-sm btn-outline-secondary" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
              <button name="dir" value="down" class="btn btn-sm btn-outline-secondary" title="Move down" <?= $i === count($score_raw['rules']) - 1 ? 'disabled' : '' ?>>↓</button>
            </form>
            <form method="post" class="d-inline" data-confirm="Delete rule <?= $i + 1 ?>?">
              <?= csrf_input_field() ?>
              <input type="hidden" name="_score_action" value="rule_delete">
              <input type="hidden" name="rule_index" value="<?= $i ?>">
              <button class="btn btn-sm btn-outline-danger" title="Delete this rule"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
        <tr class="table-light">
          <td class="text-muted">+</td>
          <td><input form="scoreRuleNew" name="source" class="form-control form-control-sm" placeholder="e.g. InterProScan (Pfam)"></td>
          <td><select form="scoreRuleNew" name="type" class="form-select form-select-sm"><?= $__type_options('') ?></select></td>
          <td><select form="scoreRuleNew" name="kind" class="form-select form-select-sm"><?= $__kind_options('') ?></select></td>
          <td>
            <form id="scoreRuleNew" method="post" class="d-inline">
              <?= csrf_input_field() ?>
              <button name="_score_action" value="rule_add" class="btn btn-sm btn-primary">Add rule</button>
            </form>
          </td>
        </tr>
      </tbody>
    </table>
    </div>

    <h6 class="mt-4">Kinds <small class="text-muted fw-normal">— what a score is, how it is shown, and the sentence users see</small></h6>
    <?php foreach (array_merge($score_cfg['kinds'], ['' => ['label' => '', 'display' => 'evalue', 'explanation' => '', 'unit' => '', 'unit_plural' => '', 'words' => []]]) as $id => $k): ?>
      <form method="post" class="border rounded p-2 mb-2 <?= $id === '' ? 'bg-light' : '' ?>">
        <?= csrf_input_field() ?>
        <input type="hidden" name="kind_id" value="<?= htmlspecialchars($id) ?>">
        <div class="row g-2 align-items-start">
          <div class="col-md-3">
            <label class="form-label small mb-0"><?= $id === '' ? 'New kind' : 'Label' ?></label>
            <input name="label" class="form-control form-control-sm" value="<?= htmlspecialchars($k['label']) ?>" placeholder="e.g. Bit score" required>
            <?php if ($id !== ''): ?><div class="small text-muted mt-1">Shows: <?= $__example($id, $k) ?></div><?php endif; ?>
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-0">Shown as</label>
            <select name="display" class="form-select form-select-sm">
              <?php foreach (MOOP_SCORE_DISPLAYS as $d => $dl): ?>
                <option value="<?= $d ?>" <?= $d === $k['display'] ? 'selected' : '' ?>><?= htmlspecialchars($dl) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($k['display'] === 'count' || $id === ''): ?>
            <div class="d-flex gap-1 mt-1">
              <input name="unit" class="form-control form-control-sm" value="<?= htmlspecialchars($k['unit']) ?>" placeholder="unit (1)">
              <input name="unit_plural" class="form-control form-control-sm" value="<?= htmlspecialchars($k['unit_plural']) ?>" placeholder="units (2+)">
            </div>
            <?php endif; ?>
            <?php if ($k['display'] === 'words' || $id === ''): ?>
            <textarea name="words" rows="3" class="form-control form-control-sm mt-1" placeholder="one per line: 1 = 1:1"><?php
              foreach ($k['words'] as $n => $w) echo htmlspecialchars("$n = $w") . "\n"; ?></textarea>
            <?php endif; ?>
          </div>
          <div class="col-md-5">
            <label class="form-label small mb-0">What it means (hover text and the (i) on each table)</label>
            <textarea name="explanation" rows="3" class="form-control form-control-sm"><?= htmlspecialchars($k['explanation']) ?></textarea>
          </div>
          <div class="col-md-1 d-flex flex-column gap-1 pt-3">
            <button name="_score_action" value="kind_save" class="btn btn-sm <?= $id === '' ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $id === '' ? 'Add' : 'Save' ?></button>
            <?php if ($id !== ''): ?>
            <button name="_score_action" value="kind_delete" class="btn btn-sm btn-outline-danger" title="Delete (only when no rule uses it)" data-confirm="Delete this kind?"><i class="fa fa-trash"></i></button>
            <?php endif; ?>
          </div>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
</div>
