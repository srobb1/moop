<?php
/**
 * PAGE search by anatomy -- content file (controller: tools/anatomy_search.php).
 * Variables: $relations ([id => name]), $releases ([prefix => release]).
 */
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
          ['label' => 'Where the terms come from', 'text' => 'The PLANA planarian anatomy ontology, with spatial terms from BSPO. They are refreshed when a new release is published.'],
      ],
  ]], ['intro' => 'Pick an anatomy term and how widely to search; the search covers the term and everything related to it.']);
  ?>

  <?php if (!$releases): ?>
    <div class="alert alert-light border"><?= t('The anatomy search is not set up on this site yet.') ?></div>
  <?php else: ?>
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="step-badge me-2">1</span><span class="fw-semibold" style="font-size:0.9rem;"><?= t('Anatomy term') ?></span>
    </div>
    <div class="card-body">
      <div class="onto-box" data-onto-box id="anatomyTermBox" style="max-width:36rem;">
        <input type="text" class="form-control" placeholder="<?= t('e.g. eye, brain, neoblast, pharynx') ?>" aria-label="<?= t('Anatomy term') ?>">
        <input type="hidden" name="term" id="anatomyTerm">
      </div>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="step-badge me-2">2</span><span class="fw-semibold" style="font-size:0.9rem;"><?= t('Relationship') ?></span>
    </div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-3" id="anatomyRelations">
        <?php $first = true; foreach ($relations as $rid => $rname): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="rel" id="rel_<?= htmlspecialchars(md5($rid)) ?>" value="<?= htmlspecialchars($rid) ?>"<?= $rid === 'BFO:0000050' || ($first && !isset($relations['BFO:0000050'])) ? ' checked' : '' ?>>
            <label class="form-check-label" for="rel_<?= htmlspecialchars(md5($rid)) ?>"><?= htmlspecialchars($rname) ?></label>
          </div>
        <?php $first = false; endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
      <span class="fw-semibold" style="font-size:0.9rem;"><?= t('What this search covers') ?></span>
    </div>
    <div class="card-body" id="anatomyCovers">
      <p class="text-muted mb-0"><?= t('Pick a term to see everything the search would include.') ?></p>
    </div>
  </div>

  <p class="small text-muted"><?= t('Ontology releases:') ?>
    <?= htmlspecialchars(implode(' · ', array_map(fn($p, $r) => "$p $r", array_keys($releases), $releases))) ?></p>
  <?php endif; ?>
</div>
