<?php
/**
 * Ontology browser -- content file (controller: tools/ontology_browser.php).
 * Variables: $ontologies [prefix => name], $prefix, $parts, $relations [id => label], $release, $roots,
 *            $genes_missing (GO: visible organisms whose gene counts are not built yet),
 *            $scope (GO: the organism / assembly / gene set the counts are limited to; [] = all).
 * Rows: .ob-row = term | definition (one column for every depth: only the term indents).
 * Each row's counts (.ob-counts, drawn by js/ontology-browser.js): closed = the term and everything
 * below it; opened = its own share (terms directly below, by relationship; records on the term).
 */
$self = moop_url('tools/ontology_browser.php');
?>
<div class="container py-3 ob-page">
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title('Anatomy & Gene Function') ?>
      <?= help_modal_trigger('ob-help', '', 'How Anatomy & Gene Function works') ?>
      <?= page_purpose('Find genes by anatomy term or GO term: a tree of terms with their definitions, and a page for each term.', '', false, false) ?>
    </div>
    <?php // The subtitle (2026-11-24): always shown, and it names GO only where GO is offered. ?>
    <div class="tool-page-blurb"><?= isset($ontologies['GO']) ? t('Find genes by anatomy term or GO term') : t('Find genes by anatomy term') ?></div>
    <?php // Where each kind of term comes from (Sofia 2026-11-24): curated vs predicted is the first thing a
          // biologist needs to weigh a hit. GO sources checked 2026-11-24: all 82 organisms carry exactly
          // OMA2GO, EggNOG2GO, PANTHER2GO, InterPro2GO -- no curated GO anywhere. Update this if that changes. ?>
    <div class="ob-sources">
      <p class="ob-howto"><i class="fa fa-search" aria-hidden="true"></i> <?= t('Type the term you want in the search box, or browse the expandable tree below.') ?></p>
      <?php if (isset($ontologies['PLANA'])): ?>
      <p><i class="fa fa-microscope" aria-hidden="true"></i> <?= t_markup('%s are linked to genes by hand, from the literature: each record is a gene that a published paper reports as expressed in that body part, cell type or stage.', '<strong>' . t('Anatomy terms (PLANA)') . '</strong>') ?></p>
      <?php endif; ?>
      <?php if (isset($ontologies['GO'])): ?>
      <p><i class="fa fa-dna" aria-hidden="true"></i> <?= t_markup('%s are linked to genes by computer, not by hand: carried over from orthologs in other species (OMA, eggNOG) and from protein families and domains found by InterProScan (PANTHER, InterPro).', '<strong>' . t('GO terms') . '</strong>') ?></p>
      <?php endif; ?>
    </div>
  </div>
  <?= help_modal('ob-help', 'How Anatomy & Gene Function works', [[
      // For someone new to the site and to tools like this (Sofia, work list 2026-11-18).
      'heading' => 'New to this?',
      'cards'   => [
          ['label' => 'What an ontology is', 'text' => 'A shared vocabulary for biology. Each term -- pharynx, neoblast, cell division -- has an ID and a definition, and sits under broader terms: the pharynx is part of the digestive system. Researchers use the same terms, so their data can be compared and searched.'],
          ['label' => 'Why it is on this site', 'text' => 'Published expression records are tagged with PLANA terms (by hand, from papers), and genes with GO terms (by computer). Find a body part, cell type or process here, and see which records or genes carry it -- including those on its more specific terms.'],
          ['label' => 'The icons on each row', 'html' => true, 'text' =>
              '<i class="fa fa-microscope" aria-hidden="true"></i> <strong>' . t('records') . '</strong> &mdash; '
              . t('published expression records (where a gene was seen, for example by in situ hybridization) on the term and the terms below it. Once you open a row, the count is for that term itself; the rows below show their own. Click to list them.')
              . '<br><i class="fa fa-dna" aria-hidden="true"></i> <strong>' . t('genes') . '</strong> &mdash; '
              . t('genes annotated with the term or a more specific one (GO). Click for the gene table.')],
          ['label' => 'Which ontology', 'text' => 'PLANA describes planarian anatomy and life stages. GO (Gene Ontology) describes what genes do: their molecular functions, the processes they take part in, and where in the cell they act.'],
      ],
  ], [
      'heading' => 'Using the browser',
      'cards'   => [
          ['label' => 'The tree', 'text' => 'Each row is a term and its definition. Open a row (the arrow) to see the terms directly below it; the number counts them.'],
          ['label' => 'Show parts too', 'text' => 'By default a branch holds the term\'s kinds (is a). Tick "show parts too" to also see its parts and what develops from it, marked with the relationship.'],
          ['label' => 'The numbers', 'text' => 'A closed row counts the term and everything below it: how many terms, and how many published expression records (where this site has them). Open it and the numbers change to its own share -- the terms directly below it, by relationship, and the records on the term itself -- because the rows below now show the rest. The counts follow what the tree is showing: with "show parts too" off, only kinds (is a) count, so a system whose records are all on its parts shows none.'],
          ['label' => 'Gene counts (GO)', 'text' => 'In GO, a row shows how many genes carry the term or a more specific one, across the organisms you can see; click it for the term\'s gene table, one row per gene set, where a switch shows the genes on the term itself instead. Every GO source is counted together, and a gene counts once. A gene is carried up by "is a" and "part of" only -- GO\'s own rule -- whatever the tree is showing.'],
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
      <?php foreach (moop_ob_scope_params() as $_k => $_v): foreach ((array) $_v as $_one): ?><input type="hidden" name="<?= htmlspecialchars($_k . (is_array($_v) ? '[]' : '')) ?>" value="<?= htmlspecialchars($_one) ?>"><?php endforeach; endforeach; ?>
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
      <?php foreach (moop_ob_scope_params() as $_k => $_v): foreach ((array) $_v as $_one): ?><input type="hidden" name="<?= htmlspecialchars($_k . (is_array($_v) ? '[]' : '')) ?>" value="<?= htmlspecialchars($_one) ?>"><?php endforeach; endforeach; ?>
      <input class="form-check-input" type="checkbox" id="obParts" name="parts" value="1"<?= $parts ? ' checked' : '' ?>>
      <label class="form-check-label small" for="obParts"><?= t('Show parts too') ?></label>
    </form>
  </div>
  <p class="small text-muted mb-2"><?= htmlspecialchars(t_plain($ontologies[$prefix])) ?><?= $release !== '' ? ' &middot; ' . t('release %s', $release) : '' ?></p>
  <?php if (!empty($scope)): ?>
    <div class="alert alert-light border py-2 px-3 mb-2 small d-flex flex-wrap align-items-center gap-2">
      <i class="fa fa-filter" aria-hidden="true"></i>
      <span><?= t_markup('Gene counts for %s only.', '<strong>' . htmlspecialchars($scope['label']) . '</strong>') ?></span>
      <a class="ms-auto" href="<?= htmlspecialchars(moop_url('tools/ontology_browser.php') . '?' . http_build_query(['o' => $prefix] + ($parts ? ['parts' => 1] : []))) ?>"><?= t('Show all organisms') ?></a>
    </div>
  <?php endif; ?>
  <?php if ($genes_missing > 0): ?>
    <p class="small text-muted mb-2"><i class="fa fa-hourglass-half" aria-hidden="true"></i>
      <?= $genes_missing === 1 ? t('Gene counts are still being prepared for 1 organism, so some numbers are lower than they will be.')
                               : t('Gene counts are still being prepared for %d organisms, so some numbers are lower than they will be.', $genes_missing) ?></p>
  <?php endif; ?>

  <div class="ob-tree-card">
    <div class="ob-head"><span><?= t('Term') ?></span><span><?= t('Definition') ?></span></div>
    <ul class="ob-tree" id="obTree">
      <?php foreach ($roots as $r): ?>
      <li class="ob-d1" data-id="<?= htmlspecialchars($r['id']) ?>">
        <div class="ob-row">
          <span class="ob-term">
            <?php if ($r['children'] > 0): ?><button type="button" class="ob-toggle" aria-expanded="false" aria-label="<?= t('Show the terms below %s', $r['label']) ?>"></button><?php else: ?><span class="ob-leaf"></span><?php endif; ?>
            <a href="<?= htmlspecialchars(moop_ob_term_url($prefix, $r['id'])) ?>"><?= htmlspecialchars($r['label']) ?></a><span class="ob-counts" data-counts="<?= htmlspecialchars(json_encode(['terms_all' => $r['terms_all'], 'children' => $r['children'], 'below' => array_combine(array_map('t_plain', array_keys($r['below'])), array_values($r['below'])) ?: new stdClass(), 'records_all' => $r['records_all'], 'records_own' => $r['records_own'], 'records_url' => $r['records_url'], 'genes_all' => $r['genes_all'], 'genes_own' => $r['genes_own'], 'gene_organisms' => $r['gene_organisms'], 'url' => moop_ob_term_url($prefix, $r['id'])])) ?>"></span>
          </span>
          <span class="ob-def"><?= htmlspecialchars($r['definition']) ?></span>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
