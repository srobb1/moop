<?php
/**
 * Expression Explorer -- content file (controller: tools/expression.php).
 * Variables: $gene_sets, $chosen, $experiments, $selected_slugs, $genes_text, $typed, $too_many,
 *            $form_sent, $genes, $unresolved, $ambiguous, $via_parent, $no_rows, $heatmap,
 *            $mode, $sort, $link (callable: same request with some settings changed), $site
 */
// The site-wide form, "Genus species · Name (Accession) › gene set" (gene_set_label()).
$gs_label = fn(array $gs, bool $html = false) => gene_set_label($gs['organism'], $gs['genome_name'] ?? '', $gs['assembly'], $gs['gene_set'], $html);
?>
<div class="container py-3">
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title('Expression Explorer') ?>
      <?= help_modal_trigger('expr-tool-help', '', 'What the Expression Explorer does') ?>
      <?= page_purpose('Compare a list of genes across expression experiments as a heatmap.', '', false, false) ?>
    </div>
  </div>
  <?php
  echo help_modal('expr-tool-help', 'What the Expression Explorer does', [[
      'heading' => '',
      'cards'   => [
          ['num' => '1', 'label' => 'Gene set', 'text' => 'Only gene sets with expression data are listed.'],
          ['num' => '2', 'label' => 'Genes', 'text' => 'Paste gene, transcript or protein IDs — a transcript or protein is shown as its gene. Up to ' . MOOP_EXPRESSION_TOOL_MAX_GENES . ' at a time.'],
          ['num' => '3', 'label' => 'Experiments', 'text' => 'Tick the experiments to show. Each column is one condition: the average of its samples.'],
          ['label' => 'Level or pattern', 'text' => 'Level shades every cell on one scale (1, 10, 100, 1000 in even steps, in each experiment\'s unit — TPM for bulk RNA-seq, CPM for single-cell), so genes can be compared with each other. Pattern scales each gene to its own highest condition, to compare the shape of genes expressed at very different levels.'],
          ['label' => 'No data', 'text' => 'A hatched cell means there is no measurement — not zero.'],
      ],
  ]], ['intro' => 'Pick a gene set, paste genes, choose experiments, and see them side by side. Hover any cell for its value; download the samples behind it as a TSV.']);
  ?>

  <?php if (!$gene_sets): ?>
    <div class="alert alert-light border">No gene set you can see has expression data yet.</div>
  <?php else: ?>
  <form method="get" action="/<?= htmlspecialchars($site) ?>/tools/expression.php">
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
        <span class="step-badge me-2">1</span><span class="fw-semibold" style="font-size:0.9rem;">Gene set</span>
      </div>
      <div class="card-body">
        <?php if (count($gene_sets) === 1): $gs = $gene_sets[0]; ?>
          <div><?= $gs_label($gs, true) ?> <span class="text-muted small">· <?= (int)$gs['experiments'] ?> experiment<?= $gs['experiments'] === 1 ? '' : 's' ?> — the only gene set with expression data so far</span></div>
          <input type="hidden" name="organism" value="<?= htmlspecialchars($gs['organism']) ?>">
          <input type="hidden" name="assembly" value="<?= htmlspecialchars($gs['assembly']) ?>">
          <input type="hidden" name="gene_set" value="<?= htmlspecialchars($gs['gene_set']) ?>">
        <?php else: ?>
          <?php foreach ($gene_sets as $gs):
              $on = $chosen && $chosen['organism'] === $gs['organism'] && $chosen['assembly'] === $gs['assembly'] && $chosen['gene_set'] === $gs['gene_set']; ?>
            <a class="btn btn-sm <?= $on ? 'btn-primary' : 'btn-outline-primary' ?> me-2 mb-2"
               href="<?= htmlspecialchars("/$site/tools/expression.php?" . http_build_query(['organism' => $gs['organism'], 'assembly' => $gs['assembly'], 'gene_set' => $gs['gene_set']])) ?>">
              <?= htmlspecialchars($gs_label($gs)) ?> <span class="badge bg-light text-dark"><?= (int)$gs['experiments'] ?></span></a>
          <?php endforeach; ?>
          <?php if ($chosen): ?>
            <input type="hidden" name="organism" value="<?= htmlspecialchars($chosen['organism']) ?>">
            <input type="hidden" name="assembly" value="<?= htmlspecialchars($chosen['assembly']) ?>">
            <input type="hidden" name="gene_set" value="<?= htmlspecialchars($chosen['gene_set']) ?>">
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($chosen): ?>
    <div class="row g-3 mb-3">
      <div class="col-lg-5">
        <div class="card shadow-sm h-100">
          <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
            <span class="step-badge me-2">2</span><span class="fw-semibold" style="font-size:0.9rem;">Genes</span>
          </div>
          <div class="card-body">
            <textarea name="genes" class="form-control font-monospace" rows="9"
                      placeholder="One ID per line — gene, transcript or protein"><?= htmlspecialchars($genes_text) ?></textarea>
            <div class="form-text">Up to <?= MOOP_EXPRESSION_TOOL_MAX_GENES ?> IDs.</div>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card shadow-sm h-100">
          <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
            <span class="step-badge me-2">3</span><span class="fw-semibold" style="font-size:0.9rem;">Experiments</span>
          </div>
          <div class="card-body">
            <?php foreach ($experiments as $e):
                $conds = array_column(moop_expression_group_means($e['samples'], []), 'group'); ?>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="experiments[]" id="exp-<?= htmlspecialchars($e['slug']) ?>"
                       value="<?= htmlspecialchars($e['slug']) ?>" <?= in_array($e['slug'], $selected_slugs, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="exp-<?= htmlspecialchars($e['slug']) ?>">
                  <strong><?= htmlspecialchars($e['label']) ?></strong>
                  <span class="text-muted small">· <?= count($e['samples']) ?> samples, <?= count($conds) ?> conditions</span>
                  <div class="small text-muted"><?= htmlspecialchars(implode(' · ', $conds)) ?></div>
                  <?php if (!empty($e['meta']['summary'])): ?><div class="small"><?= htmlspecialchars($e['meta']['summary']) ?></div><?php endif; ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <input type="hidden" name="show" value="1">
    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    <button type="submit" class="btn btn-primary mb-4"><i class="fa fa-th me-1"></i> Show heatmap</button>
    <?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if (($form_sent || $typed) && $chosen): ?>
    <?php if (!$typed): ?>
      <div class="alert alert-warning">Paste at least one gene ID.</div>
    <?php elseif (!$selected_slugs): ?>
      <div class="alert alert-warning">Tick at least one experiment.</div>
    <?php endif; ?>
    <?php if ($too_many): ?>
      <div class="alert alert-warning">Only the first <?= MOOP_EXPRESSION_TOOL_MAX_GENES ?> IDs are shown.</div>
    <?php endif; ?>
    <?php if ($unresolved): ?>
      <div class="alert alert-warning small mb-2"><strong><?= count($unresolved) ?> ID<?= count($unresolved) === 1 ? '' : 's' ?> not found in this gene set:</strong>
        <?= htmlspecialchars(implode(', ', $unresolved)) ?></div>
    <?php endif; ?>
    <?php if ($ambiguous): ?>
      <div class="alert alert-warning small mb-2"><strong>Matched more than one version, so all are shown:</strong>
        <?php foreach ($ambiguous as $in => $hits): ?><?= htmlspecialchars("$in → " . implode(', ', $hits)) ?>; <?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($no_rows): ?>
      <div class="alert alert-light border small mb-2"><strong>No expression rows for:</strong> <?= htmlspecialchars(implode(', ', $no_rows)) ?> — shown hatched.</div>
    <?php endif; ?>
    <?php if ($via_parent): ?>
      <?php /* One line, the list folded: with 200 transcripts pasted it would fill the page. */ ?>
      <details class="small text-muted mb-2">
        <summary><?= count($via_parent) ?> transcript or protein ID<?= count($via_parent) === 1 ? ' is' : 's are' ?> shown as <?= count($via_parent) === 1 ? 'its' : 'their' ?> gene
          <span class="ms-1">(the TSV lists them in <code>input_ids</code>)</span></summary>
        <div class="expr-via-list mt-1">
          <?php foreach ($via_parent as $in => $g): ?><span><?= htmlspecialchars($in) ?> → <?= htmlspecialchars($g) ?></span><?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($heatmap): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-header py-2 d-flex align-items-center flex-wrap gap-2 tool-header">
      <span class="fw-semibold" style="font-size:0.9rem;"><?= count($genes) ?> gene<?= count($genes) === 1 ? '' : 's' ?> × <?= count($selected_slugs) ?> experiment<?= count($selected_slugs) === 1 ? '' : 's' ?></span>
      <?php /* The (i) is grouped with the control it explains, and the next control is pushed away,
               so it cannot be read as help for "As pasted / Highest first". */ ?>
      <span class="d-inline-flex align-items-center gap-1 ms-auto me-2">
      <div class="btn-group btn-group-sm" role="group" aria-label="Shading">
        <a class="btn btn-light<?= $mode === 'level' ? ' active' : '' ?>" href="<?= htmlspecialchars($link(['mode' => 'level'])) ?>">Level</a>
        <a class="btn btn-light<?= $mode === 'pattern' ? ' active' : '' ?>" href="<?= htmlspecialchars($link(['mode' => 'pattern'])) ?>">Pattern</a>
      </div>
      <?= field_help(
          'Level: one scale for every gene, so you can compare genes with each other (darker = more). '
          . 'Pattern: each gene scaled to its own highest condition (100%), to compare where genes peak, '
          . 'even a gene at 5 against one at 5,000.',
          'Level or Pattern'
      ) ?>
      </span>
      <div class="btn-group btn-group-sm" role="group" aria-label="Order">
        <a class="btn btn-light<?= $sort === 'input' ? ' active' : '' ?>" href="<?= htmlspecialchars($link(['sort' => 'input'])) ?>">As pasted</a>
        <a class="btn btn-light<?= $sort === 'level' ? ' active' : '' ?>" href="<?= htmlspecialchars($link(['sort' => 'level'])) ?>">Highest first</a>
      </div>
      <a class="btn btn-sm btn-light" href="<?= htmlspecialchars($link(['download' => 'tsv'])) ?>"><i class="fa fa-download me-1"></i>TSV</a>
    </div>
    <div class="card-body">
      <div class="expr-hm-legend small mb-2">
        <?php if ($mode === 'level'): ?>
          <?php foreach ([0, 1, 10, 100, 1000] as $tpm): ?>
            <span><i style="background:<?= moop_expression_shade_colour(moop_expression_shade((float)$tpm, 'level', 0)) ?>"></i><?= $tpm === 1000 ? '≥ 1000' : $tpm ?></span>
          <?php endforeach; ?> <span class="text-muted"><?= htmlspecialchars($units_label) ?>, average of each condition's samples</span>
        <?php else: ?>
          <?php foreach ([0, 0.25, 0.5, 0.75, 1] as $f): ?>
            <span><i style="background:<?= moop_expression_shade_colour($f) ?>"></i><?= (int)($f * 100) ?>%</span>
          <?php endforeach; ?> <span class="text-muted">of each gene's highest condition (genes under <?= (int)MOOP_EXPRESSION_SPARK_FLOOR ?> <?= htmlspecialchars($units_label) ?> stay pale)</span>
        <?php endif; ?>
        <span><i class="expr-hm-nodata"></i>no data</span>
      </div>
      <div class="expr-hm-scroll"><?= $heatmap ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>
