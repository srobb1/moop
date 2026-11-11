<?php
/**
 * Ontology browser -- content file (controller: tools/ontology_browser.php).
 * Variables: $ontologies [prefix => name], $prefix, $parts, $relations [id => label], $release, $roots.
 * Rows: .ob-row = term | definition (one column for every depth: only the term indents).
 * Each row's counts (.ob-counts, drawn by js/ontology-browser.js): closed = the term and everything
 * below it; opened = its own share (terms directly below, by relationship; records on the term).
 */
$self = moop_url('tools/ontology_browser.php');
?>
<div class="container py-3 ob-page">
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title('Ontology Browser') ?>
      <?= help_modal_trigger('ob-help', '', 'How the Ontology Browser works') ?>
      <?= page_purpose('Explore an ontology as a tree: every term with its definition, and a page for each term.', '', false, false) ?>
    </div>
  </div>
  <?= help_modal('ob-help', 'How the Ontology Browser works', [[
      'heading' => '',
      'cards'   => [
          ['label' => 'The tree', 'text' => 'Each row is a term and its definition. Open a row (the arrow) to see the terms directly below it; the number counts them.'],
          ['label' => 'Show parts too', 'text' => 'By default a branch holds the term\'s kinds (is a). Tick "show parts too" to also see its parts and what develops from it, marked with the relationship.'],
          ['label' => 'The numbers', 'text' => 'A closed row counts the term and everything below it: how many terms, and how many published expression records (where this site has them). Open it and the numbers change to its own share -- the terms directly below it, by relationship, and the records on the term itself -- because the rows below now show the rest. The counts follow what the tree is showing: with "show parts too" off, only kinds (is a) count, so a system whose records are all on its parts shows none.'],
          ['label' => 'A term\'s page', 'text' => 'Click a term\'s name for its page: definition, synonyms, where it sits in the ontology, its parts, and any written description this site has for it.'],
          ['label' => 'Find a term', 'text' => 'Type a name or a synonym in the search box (brain finds cephalic ganglia) and pick it to open its page.'],
      ],
  ]], ['intro' => 'A tree of terms with their definitions, and a page for each term.']) ?>

  <?php if ($prefix === ''): ?>
    <div class="alert alert-light border"><?= t('No ontology has been set up on this site yet.') ?></div>
  <?php else: ?>

  <div class="ob-controls d-flex flex-wrap align-items-end gap-3 mb-3">
    <?php if (count($ontologies) > 1): ?>
    <form method="get" action="<?= htmlspecialchars($self) ?>" class="ob-pick">
      <label class="form-label small fw-semibold mb-1" for="obOntology"><?= t('Ontology') ?></label>
      <select class="form-select form-select-sm" id="obOntology" name="o">
        <?php foreach ($ontologies as $p => $name): ?>
          <option value="<?= htmlspecialchars($p) ?>"<?= $p === $prefix ? ' selected' : '' ?>><?= htmlspecialchars(t_plain($name)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($parts): ?><input type="hidden" name="parts" value="1"><?php endif; ?>
    </form>
    <?php endif; ?>
    <div class="ob-search flex-grow-1">
      <label class="form-label small fw-semibold mb-1" for="obSearchText"><?= t('Find a term') ?></label>
      <div class="onto-box" data-onto-box data-ontologies="<?= htmlspecialchars($prefix) ?>" id="obSearch" style="max-width:36rem;">
        <input type="text" class="form-control form-control-sm" id="obSearchText" autocomplete="off" placeholder="<?= t('Type a name or a synonym, e.g. brain') ?>">
        <input type="hidden" id="obSearchId">
      </div>
    </div>
    <form method="get" action="<?= htmlspecialchars($self) ?>" class="ob-parts form-check mb-1">
      <input type="hidden" name="o" value="<?= htmlspecialchars($prefix) ?>">
      <input class="form-check-input" type="checkbox" id="obParts" name="parts" value="1"<?= $parts ? ' checked' : '' ?>>
      <label class="form-check-label small" for="obParts"><?= t('Show parts too') ?></label>
    </form>
  </div>
  <p class="small text-muted mb-2"><?= htmlspecialchars(t_plain($ontologies[$prefix])) ?><?= $release !== '' ? ' &middot; ' . t('release %s', $release) : '' ?></p>

  <div class="ob-tree-card">
    <div class="ob-head"><span><?= t('Term') ?></span><span><?= t('Definition') ?></span></div>
    <ul class="ob-tree" id="obTree">
      <?php foreach ($roots as $r): ?>
      <li class="ob-d1" data-id="<?= htmlspecialchars($r['id']) ?>">
        <div class="ob-row">
          <span class="ob-term">
            <?php if ($r['children'] > 0): ?><button type="button" class="ob-toggle" aria-expanded="false" aria-label="<?= t('Show the terms below %s', $r['label']) ?>"></button><?php else: ?><span class="ob-leaf"></span><?php endif; ?>
            <a href="<?= htmlspecialchars(moop_ob_term_url($prefix, $r['id'])) ?>"><?= htmlspecialchars($r['label']) ?></a><span class="ob-counts" data-counts="<?= htmlspecialchars(json_encode(['terms_all' => $r['terms_all'], 'children' => $r['children'], 'below' => array_combine(array_map('t_plain', array_keys($r['below'])), array_values($r['below'])) ?: new stdClass(), 'records_all' => $r['records_all'], 'records_own' => $r['records_own'], 'records_url' => $r['records_url']])) ?>"></span>
          </span>
          <span class="ob-def"><?= htmlspecialchars($r['definition']) ?></span>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
