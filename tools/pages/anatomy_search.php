<?php
/**
 * Anatomical Expression Search -- content file (controller: tools/anatomy_search.php). ONE place for every way in
 * (Sofia, 2026-11-09: people should not have to know "term here, ID there"): three tabs, one
 * results table (_page_results.php).
 * Variables: $mode (term|pub|ids), $relations, $releases, $organism, $term, $term_label, $rel, $ev,
 *            $evidence_choices, $result (null before a search: ['total']), $covered, $records_url, $download_url,
 *            $pq, $pmid, $papers, $paper, $ids_text, $id_result, $crosswalk_name, $source, $source_url
 */
$self = moop_url_base() . '/tools/anatomy_search.php';
$tabs = ['term' => t_plain('By anatomy term'), 'pub' => t_plain('By publication'), 'ids' => t_plain('By gene or transcript ID')];
?>
<div class="container py-3">
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title('Anatomical Expression Search') ?>
      <?= help_modal_trigger('anatomy-search-help', '', 'How the Anatomical Expression Search works') ?>
      <?= page_purpose('Find where planarian genes were published as expressed: by body part, by paper, or by gene.', '', false, false) ?>
    </div>
  </div>
  <?php
  echo help_modal('anatomy-search-help', 'How the Anatomical Expression Search works', [[
      'heading' => '',
      'cards'   => [
          ['label' => 'By anatomy term', 'text' => 'Type a body part, cell type or region (synonyms work: brain finds cephalic ganglia), choose how far the search reaches -- part of the eye also covers the optic cup and everything else in the eye, at any depth -- and optionally the kind of evidence.'],
          ['label' => 'By publication', 'text' => 'Type a PubMed ID, or words from the title, an author (any author, accents optional) or the journal. Pick a paper to see every record curated from it.'],
          ['label' => 'By gene or transcript ID', 'text' => 'Paste one or many ids of any kind -- reference genes, older transcriptomes, ESTs, array probes, gene symbols. The Rosetta Stone maps each to its gene, so records curated under any id of the same gene are found.'],
          ['label' => 'Where the data comes from', 'text' => 'Expression records curated from published papers, each recorded against a term of an anatomy ontology. The data source is named under the title. A record entered twice is listed once.'],
      ],
  ]], ['intro' => 'Three ways in; the results are the same kind of table: what was expressed where, by what evidence, in which paper.']);
  ?>

  <?php if (!$releases || !$organism): ?>
    <div class="alert alert-light border"><?= t('The Anatomical Expression Search is not set up on this site yet.') ?></div>
  <?php else: ?>

  <?php if ($source !== ''): ?>
  <p class="small text-muted mb-2"><?= t('Data:') ?> <?= $source_url !== '' ? '<a href="' . htmlspecialchars($source_url) . '" target="_blank" rel="noopener">' . htmlspecialchars($source) . '</a>' : htmlspecialchars($source) ?></p>
  <?php endif; ?>
  <ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $m => $label): ?>
      <li class="nav-item"><?= '<a class="nav-link' . ($m === $mode ? ' active" aria-current="page"' : '"') . ' href="' . htmlspecialchars($self . '?mode=' . $m) . '">' . htmlspecialchars($label) . '</a>' ?></li>
    <?php endforeach; ?>
  </ul>

  <?php if ($mode === 'term'): ?>
  <form method="get" action="<?= htmlspecialchars($self) ?>" id="anatomySearchForm">
    <input type="hidden" name="mode" value="term">
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

  <?php elseif ($mode === 'pub'): ?>
  <form method="get" action="<?= htmlspecialchars($self) ?>" class="card mb-3 shadow-sm">
    <input type="hidden" name="mode" value="pub">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
      <div class="flex-grow-1" style="max-width:36rem;">
        <label class="form-label small mb-1" for="pubQuery"><?= t('PubMed ID, or words from the title, an author or the journal') ?></label>
        <input type="text" class="form-control" id="pubQuery" name="q" placeholder="<?= t('e.g. 27034770, Currie, sanchez alvarado 2018, neoblast') ?>" value="<?= htmlspecialchars($pq) ?>">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa fa-search me-1"></i><?= t('Find papers') ?></button>
    </div>
  </form>
  <?php if ($pq !== '' && !$papers): ?>
    <div class="alert alert-light border"><?= t('No curated paper matches that.') ?></div>
  <?php elseif ($papers): ?>
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <p class="small text-muted mb-2"><?= count($papers) !== 1 ? t('%d curated papers match.', count($papers)) : t('1 curated paper matches.') ?></p>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th><?= t('Year') ?></th><th><?= t('Paper') ?></th><th class="text-end"><?= t('Records') ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($papers as $p): ?>
            <tr<?= $p['pmid'] === $pmid ? ' class="table-active"' : '' ?>>
              <td class="small text-nowrap"><?= htmlspecialchars((string) $p['year']) ?></td>
              <td class="small"><strong><?= htmlspecialchars((string) $p['authors']) ?></strong> <?= htmlspecialchars((string) $p['title']) ?> <span class="text-muted"><?= htmlspecialchars((string) $p['journal']) ?> · <a href="https://pubmed.ncbi.nlm.nih.gov/<?= rawurlencode((string) $p['pmid']) ?>/" target="_blank" rel="noopener"><?= t('PMID %s', (string) $p['pmid']) ?></a></span></td>
              <td class="small text-end"><?= number_format((int) $p['records']) ?></td>
              <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($self . '?' . http_build_query(['mode' => 'pub', 'q' => $pq, 'pmid' => $p['pmid']])) ?>#pageResultsCard"><?= t('Show records') ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php else: /* ids */ ?>
  <form method="get" action="<?= htmlspecialchars($self) ?>" class="card mb-3 shadow-sm">
    <input type="hidden" name="mode" value="ids">
    <div class="card-body">
      <label class="form-label small mb-1" for="pageIds"><?= t('Gene or transcript IDs, one per line or separated by spaces or commas (up to 500)') ?></label>
      <textarea class="form-control font-monospace" id="pageIds" name="ids" rows="4" placeholder="<?= t('e.g. h1SMcG0021890, SMED30018592, dd_Smed_v6_48430_0_1, ovo') ?>"><?= htmlspecialchars($ids_text) ?></textarea>
      <div class="d-flex align-items-center gap-2 mt-2">
        <button type="submit" class="btn btn-primary"><i class="fa fa-search me-1"></i><?= t('Search') ?></button>
        <?php if ($crosswalk_name !== ''): ?><span class="small text-muted"><?= t('IDs are matched across transcriptomes with the %s.', t_plain($crosswalk_name)) ?></span><?php endif; ?>
      </div>
    </div>
  </form>
  <?php if ($id_result && $id_result['unknown']): ?>
    <div class="alert alert-light border small"><?= t('Not found:') ?> <span class="font-monospace"><?= htmlspecialchars(implode(' ', array_slice($id_result['unknown'], 0, 30))) ?><?= count($id_result['unknown']) > 30 ? ' …' : '' ?></span></div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($result !== null): ?>
  <div class="card mb-3 shadow-sm" id="pageResultsCard">
    <div class="card-header py-2 tool-header">
      <span class="fw-semibold"><?= t('Results') ?></span>
    </div>
    <div class="card-body">
      <p class="mb-2">
        <?php if ($mode === 'term'): ?>
          <?= t_markup('%1$s expression records in %2$s', '<strong>' . number_format($result['total']) . '</strong>', '<strong>' . htmlspecialchars($term_label) . '</strong>') ?>
          <?= $covered ? t('and %d terms related to it by "%s".', $covered, $relations[$rel] ?? $rel) : '' ?>
        <?php elseif ($mode === 'pub'): ?>
          <?= t_markup('%1$s expression records from %2$s', '<strong>' . number_format($result['total']) . '</strong>', '<strong>' . htmlspecialchars(trim(($paper['authors'] ?? '') . ' ' . ($paper['year'] ?? '')) ?: 'PMID ' . $pmid) . '</strong>') ?>
        <?php else: ?>
          <?= t_markup('%1$s expression records for %2$s matched IDs', '<strong>' . number_format($result['total']) . '</strong>', '<strong>' . count($id_result['matched'] ?? []) . '</strong>') ?>
        <?php endif; ?>
      </p>
      <?php if ($result['total'] > 0): ?>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <a class="btn btn-sm btn-outline-secondary" id="pageDownload" href="<?= htmlspecialchars($download_url) ?>"
           data-base="<?= htmlspecialchars($download_url) ?>"><i class="fa fa-download me-1"></i><?= t('Download all records (TSV)') ?></a>
        <span class="small text-muted" id="pageDownloadNote"><?= t('The filter box searches every record; the download follows it.') ?></span>
      </div>
      <?php include __DIR__ . '/_page_results.php'; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <p class="small text-muted"><?= t('Ontology releases:') ?>
    <?= htmlspecialchars(implode(' · ', array_map(fn($p, $r) => "$p $r", array_keys($releases), $releases))) ?></p>
  <?php endif; ?>
</div>
