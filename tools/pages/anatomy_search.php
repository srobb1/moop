<?php
/**
 * PAGE search by anatomy -- content file (controller: tools/anatomy_search.php).
 * Variables: $relations [id => name], $releases [prefix => release], $organism, $term, $term_label,
 *            $rel, $ev, $evidence_choices, $result (null before a search), $labels [id => name], $covered,
 *            $genes [accession_lc => ['show' => [[id, url, external]], 'all' => [ids]]] (crosswalk)
 */
$L = fn(?string $id) => $id === null || $id === '' ? '' : ($labels[$id] ?? $id);
?>
<div class="container py-3">
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title('PAGE: Planarian Anatomy Gene Expression') ?>
      <?= help_modal_trigger('anatomy-search-help', '', 'How the anatomy search works') ?>
      <?= page_purpose('Find genes published as expressed in a part of the planarian body.', '', false, false) ?>
    </div>
  </div>
  <?php
  echo help_modal('anatomy-search-help', 'How the anatomy search works', [[
      'heading' => '',
      'cards'   => [
          ['num' => '1', 'label' => 'Anatomy term', 'text' => 'Type a body part, cell type or region. Synonyms work too: brain finds cephalic ganglia, and the list says which synonym matched.'],
          ['num' => '2', 'label' => 'Relationship', 'text' => 'Choose how far the search reaches. Part of the eye also covers the optic cup, photoreceptor neurons and everything else that is part of the eye, at any depth. Is a covers every kind of the term.'],
          ['num' => '3', 'label' => 'Evidence', 'text' => 'Optionally keep only some kinds of evidence. Any in situ hybridization includes colorimetric and fluorescent in situ; the groups come from the Evidence and Conclusion Ontology.'],
          ['label' => 'Where the data comes from', 'text' => 'Expression records curated from published papers by the PAGE project, with terms from the PLANA planarian anatomy ontology. A record entered twice is listed once.'],
      ],
  ]], ['intro' => 'Pick an anatomy term and how widely to search; the results list every published expression record in the term or anything related to it.']);
  ?>

  <?php if (!$releases || !$organism): ?>
    <div class="alert alert-light border"><?= t('The anatomy search is not set up on this site yet.') ?></div>
  <?php else: ?>
  <form method="get" action="<?= htmlspecialchars(moop_url_base()) ?>/tools/anatomy_search.php" id="anatomySearchForm">
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="step-badge me-2">1</span><span class="fw-semibold" style="font-size:0.9rem;"><?= t('Anatomy term') ?></span>
    </div>
    <div class="card-body">
      <div class="onto-box" data-onto-box id="anatomyTermBox" style="max-width:36rem;">
        <label class="visually-hidden" for="anatomyTermText"><?= t('Anatomy term') ?></label>
        <input type="text" class="form-control" id="anatomyTermText" placeholder="<?= t('e.g. eye, brain, neoblast, pharynx') ?>" value="<?= htmlspecialchars($term_label) ?>">
        <input type="hidden" name="term" id="anatomyTerm" value="<?= htmlspecialchars($term) ?>">
      </div>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="step-badge me-2">2</span><span class="fw-semibold" style="font-size:0.9rem;"><?= t('Relationship') ?></span>
    </div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-3" id="anatomyRelations">
        <?php foreach ($relations as $rid => $rname): $hid = 'rel_' . md5($rid); ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="rel" id="<?= $hid ?>" value="<?= htmlspecialchars($rid) ?>"<?= $rid === $rel ? ' checked' : '' ?>>
            <label class="form-check-label" for="<?= $hid ?>"><?= htmlspecialchars($rname) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="small text-muted mt-2" id="anatomyCovers"></div>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="step-badge me-2">3</span><span class="fw-semibold" style="font-size:0.9rem;"><?= t('Evidence') ?></span>
    </div>
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
      <div style="min-width:20rem;">
        <select class="form-select" name="ev" aria-label="<?= t('Evidence') ?>">
          <option value=""><?= t('Any evidence') ?></option>
          <?php foreach ($evidence_choices as $c): ?>
            <option value="<?= htmlspecialchars($c['id']) ?>"<?= $c['id'] === $ev ? ' selected' : '' ?>><?= htmlspecialchars(($c['group'] ? t_plain('any %s', $c['label']) : $c['label']) . ' (' . number_format($c['count']) . ')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa fa-search me-1"></i><?= t('Search') ?></button>
    </div>
  </div>
  </form>

  <?php if ($result !== null): ?>
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 tool-header">
      <span class="fw-semibold"><?= t('Results') ?></span>
    </div>
    <div class="card-body">
      <p class="mb-2">
        <?= t_markup('%1$s expression records in %2$s', '<strong>' . number_format($result['total']) . '</strong>', '<strong>' . htmlspecialchars($term_label) . '</strong>') ?>
        <?= $covered ? t('and %d terms related to it by "%s".', $covered, $relations[$rel] ?? $rel) : '' ?>
        <?php if ($result['capped']): ?><span class="text-warning"><?= t('Showing the first %s.', number_format(count($result['rows']))) ?></span><?php endif; ?>
      </p>
      <?php if ($result['rows']): ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle" id="pageResults" style="width:100%">
          <thead><tr>
            <th><?= t('Transcript') ?></th><th><?= t('Gene') ?></th><th><?= t('Expressed in') ?></th><th><?= t('Evidence') ?></th>
            <th><?= t('Life stage') ?></th><th><?= t('Paper') ?></th><th><?= t('Curated') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $r): ?>
            <tr>
              <td class="font-monospace small"><?= htmlspecialchars($r['accession']) ?></td>
              <?php $g = $genes[mb_strtolower($r['accession'])] ?? null; ?>
              <td class="font-monospace small"<?= $g && count($g['all']) > 1 ? ' title="' . htmlspecialchars(implode(' ', $g['all'])) . '"' : '' ?>>
                <?php if (!$g): ?><span class="text-muted">&ndash;</span><?php else: foreach (array_slice($g['show'], 0, 2) as $i => $x): ?><?= $i ? ' ' : '' ?><a href="<?= htmlspecialchars($x['url']) ?>"<?= $x['external'] ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($x['id']) ?></a><?php endforeach; ?><?= count($g['show']) > 2 ? ' <span class="text-muted">+' . (count($g['show']) - 2) . '</span>' : '' ?><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($L($r['term_id'])) ?> <span class="text-muted small font-monospace"><?= htmlspecialchars($r['term_id']) ?></span></td>
              <td class="small"><?= htmlspecialchars($L($r['evidence_id'])) ?></td>
              <td class="small"><?= htmlspecialchars($L($r['stage_id'])) ?></td>
              <td class="small"><a href="https://pubmed.ncbi.nlm.nih.gov/<?= rawurlencode((string) $r['pmid']) ?>/" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) $r['title']) ?>"><?= htmlspecialchars(trim(($r['authors'] ?: 'PMID ' . $r['pmid']) . ' ' . $r['year'])) ?></a></td>
              <td class="small text-nowrap"><?= htmlspecialchars((string) $r['curated_on']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <p class="small text-muted"><?= t('Ontology releases:') ?>
    <?= htmlspecialchars(implode(' · ', array_map(fn($p, $r) => "$p $r", array_keys($releases), $releases))) ?></p>
  <?php endif; ?>
</div>
