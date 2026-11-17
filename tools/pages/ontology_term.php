<?php
/**
 * Ontology term page -- content file (controller: tools/ontology_term.php). Laid out like the gene
 * page: the same section sidebar (js/modules/parent-nav.js lists every [data-nav-label] card).
 * Variables: $term (null = not found), $prefix, $ontology_name, $release, $view (paths, parents,
 * kinds, outgoing, incoming, written), $rels, $written_html, $records, $records_elsewhere, $links,
 * $gene_rows (GO: one row per visible gene set, else null), $genes_missing, $gene_download.
 * $scope: the organism / assembly / gene set the table is limited to (moop_ob_scope(); [] = all).
 * Genes are opened or downloaded ONE gene set at a time, by design: an all-organisms export of a
 * broad term would be enormous (Sofia, 2026-11-17).
 */
$browser = moop_ob_browser_url($prefix);   // keeps the scope
$rows = function (array $list) use ($prefix): string {
    $h = '<ul class="ob-tree ob-list">';
    foreach ($list as $r) {
        $h .= '<li class="ob-d1"><div class="ob-row"><span class="ob-term"><span class="ob-leaf"></span>'
            . '<a href="' . htmlspecialchars(moop_ob_term_url($prefix, $r['id'])) . '">' . htmlspecialchars($r['label']) . '</a>'
            . (isset($r['records']) ? ($r['records'] > 0
                ? '<a class="ob-rec" href="' . htmlspecialchars($r['records_url']) . '" title="' . t('Published expression records in %s or its parts', $r['label']) . '">'
                  . '<i class="fa fa-microscope" aria-hidden="true"></i> '
                  . ($r['records'] === 1 ? t('%s record', number_format($r['records'])) : t('%s records', number_format($r['records']))) . '</a>'
                : '<span class="ob-rec-none">' . t('no records') . '</span>') : '')
            . '</span><span class="ob-def">' . htmlspecialchars($r['definition']) . '</span></div></li>';
    }
    return $h . '</ul>';
};
?>
<?php if (!$term): ?>
<div class="container py-4">
  <div class="alert alert-light border">
    <h1 class="h5"><?= t('Term not found') ?></h1>
    <p class="mb-0"><?= t('There is no term with that ID in this ontology.') ?> <a href="<?= htmlspecialchars($browser) ?>"><?= t('Open the Ontology Browser') ?></a></p>
  </div>
</div>
<?php return; endif; ?>
<div class="page_container ob-term-page">
    <div class="pnav-jumpbar">
      <button class="pnav-jb-btn" id="pnavJbBtn" type="button" aria-expanded="false" aria-controls="pnavJbDd">
        <svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2 4h12v1.6H2zM2 7.2h12v1.6H2zM2 10.4h8V12H2z"/></svg>
        <?= t('Jump to') . "\n" ?>
        <svg class="pnav-jb-chev" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
      </button>
      <span class="pnav-jb-current" id="pnavJbCurrent"><?= htmlspecialchars($term['label']) ?></span>
      <div class="pnav-jb-dd" id="pnavJbDd"></div>
    </div>
    <div class="pnav-layout">
      <aside class="pnav-side" aria-label="<?= t('Jump to') ?>">
        <div class="pnav-side-head">
          <span class="pnav-side-title"><?= t('Jump to') ?></span>
          <button class="pnav-toggle" id="pnavToggle" type="button" aria-label="<?= t('Collapse navigation') ?>" aria-expanded="true" title="<?= t('Collapse (content goes full width)') ?>">
            <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
          </button>
        </div>
        <div class="pnav-scroll" id="pnavToc"></div>
      </aside>
      <div class="pnav-main">

    <div class="card shadow-sm mb-4 ob-term-head" id="pnav-overview" data-nav-label="<?= htmlspecialchars($term['label']) ?>" data-nav-tag="<?= htmlspecialchars($term['id']) ?>">
      <div class="card-header tool-header text-white">
        <h1 class="ob-term-title"><?= htmlspecialchars($term['label']) ?></h1>
        <div class="ob-term-meta">
          <span class="ob-id"><?= htmlspecialchars($term['id']) ?></span>
          <span><?= htmlspecialchars(t_plain($ontology_name)) ?><?= $release !== '' ? ' &middot; ' . t('release %s', $release) : '' ?></span>
          <a class="ob-back" href="<?= htmlspecialchars($browser) ?>"><?= t('Ontology Browser') ?></a>
        </div>
      </div>
      <div class="card-body">
        <?php if ($term['obsolete']): ?>
          <div class="alert alert-warning py-2"><?= t('This term is obsolete.') ?>
            <?php if (!empty($term['replaced_by'])): ?> <?= t('Replaced by') ?> <a href="<?= htmlspecialchars(moop_ob_term_url($prefix, $term['replaced_by'])) ?>"><?= htmlspecialchars($term['replaced_by']) ?></a>.<?php endif; ?>
          </div>
        <?php endif; ?>
        <h2 class="ob-h"><?= t('Definition') ?></h2>
        <p class="ob-definition"><?= $term['definition'] !== '' ? htmlspecialchars($term['definition']) : '<span class="text-muted">' . t('No definition.') . '</span>' ?></p>
        <?php if ($term['synonyms']): ?>
        <h2 class="ob-h"><?= t('Synonyms') ?></h2>
        <dl class="ob-syn">
          <?php foreach ($term['synonyms'] as $kind => $list): ?>
            <dt><?= htmlspecialchars(t_plain($kind)) ?></dt>
            <dd><?php foreach ($list as $s): ?><span class="ob-chip"><?= htmlspecialchars($s) ?></span><?php endforeach; ?></dd>
          <?php endforeach; ?>
        </dl>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm mb-4" id="pnav-lineage" data-nav-label="<?= t('Where it sits') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Where it sits') ?></h2></div>
      <div class="card-body">
        <?php if ($view['paths']): ?>
        <div class="ob-paths">
          <?php foreach ($view['paths'] as $path): ?>
            <div class="ob-path"><?php foreach ($path as $i => $step): ?><?= $i ? '<span class="ob-sep">&rsaquo;</span>' : '' ?><?php if ($step['id'] === $term['id']): ?><strong><?= htmlspecialchars($step['label']) ?></strong><?php else: ?><a href="<?= htmlspecialchars(moop_ob_term_url($prefix, $step['id'])) ?>"><?= htmlspecialchars($step['label']) ?></a><?php endif; ?><?php endforeach; ?></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <h3 class="ob-h"><?= t('Is a') ?> <span class="ob-hcount">(<?= count($view['parents']) ?>)</span></h3>
        <?= $view['parents'] ? $rows($view['parents']) : '<p class="text-muted small">' . t('None: a top term.') . '</p>' ?>
        <h3 class="ob-h mt-3"><?= t('Kinds of %s', $term['label']) ?> <span class="ob-hcount">(<?= count($view['kinds']) ?>)</span></h3>
        <?= $view['kinds'] ? $rows($view['kinds']) : '<p class="text-muted small">' . t('None.') . '</p>' ?>
      </div>
    </div>

    <?php if ($view['outgoing'] || $view['incoming']): ?>
    <div class="card shadow-sm mb-4" id="pnav-relations" data-nav-label="<?= t('Parts and relationships') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Parts and relationships') ?></h2></div>
      <div class="card-body">
        <?php $__first = true; foreach ($view['outgoing'] as $label => $list): ?>
          <h3 class="ob-h<?= $__first ? '' : ' mt-3' ?>"><?php $__first = false; ?><?= htmlspecialchars(ucfirst(t_plain($label))) ?> <span class="ob-hcount">(<?= count($list) ?>)</span></h3>
          <?= $rows($list) ?>
        <?php endforeach; ?>
        <?php foreach ($view['incoming'] as $label => $list): ?>
          <h3 class="ob-h mt-3"><?= t('Terms that are %s %s', t_plain($label), $term['label']) ?> <span class="ob-hcount">(<?= count($list) ?>)</span></h3>
          <?= $rows($list) ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($written_html !== ''): ?>
    <div class="card shadow-sm mb-4" id="pnav-description" data-nav-label="<?= t('Description') ?>">
      <div class="card-header ob-card-head d-flex align-items-center">
        <h2 class="h5 mb-0"><?= t('Description') ?></h2>
        <a class="ms-auto small" href="<?= htmlspecialchars(moop_page_url($view['written']['slug'])) ?>"><?= t('Open as its own page') ?></a>
      </div>
      <div class="card-body content-page-body ob-written"><?= $written_html ?></div>
    </div>
    <?php endif; ?>

    <?php if ($gene_rows !== null): ?>
    <?php $_orgs = count(array_unique(array_column($gene_rows, 'organism'))); ?>
    <div class="card shadow-sm mb-4" id="pnav-genes" data-nav-label="<?= t('Genes') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Genes') ?> <span class="ob-hcount">(<?= number_format(count($gene_rows)) ?>)</span></h2></div>
      <div class="card-body">
        <?php if (!empty($scope)): ?>
          <div class="alert alert-light border py-2 px-3 mb-3 small d-flex flex-wrap align-items-center gap-2">
            <i class="fa fa-filter" aria-hidden="true"></i>
            <span><?= t_markup('Showing %s only.', '<strong>' . htmlspecialchars($scope['label']) . '</strong>') ?></span>
            <a class="ms-auto" href="<?= htmlspecialchars(moop_url('tools/ontology_term.php') . '?' . http_build_query(['o' => $prefix, 'id' => $term['id']]) . '#pnav-genes') ?>"><?= t('Show all organisms') ?></a>
          </div>
        <?php endif; ?>
        <?php if ($gene_rows): ?>
          <?php
          // A TSV of these genes straight from the exporter: hidden fields + the gene set ($key).
          // $sub: with the more specific terms (the switch on) or the term itself only.
          $dl_form = function (string $key, bool $sub) use ($gene_download): string {
              $h = '<form method="post" action="' . htmlspecialchars($gene_download['action']) . '" class="d-inline">' . csrf_input_field();
              foreach (['ann_criteria_sub[]' => $sub ? '1' : '0', 'sources[]' => $key] + $gene_download['fields'] as $k => $v) {
                  foreach ((array) $v as $one) $h .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($one) . '">';
              }
              return $h . '<button type="submit" class="btn btn-sm btn-link p-0 ms-1 ob-dl" title="' . t('Download these genes with their GO terms (TSV)') . '">'
                   . '<i class="fa fa-download" aria-hidden="true"></i></button></form>';
          };
          // data-* attributes from [name => value], escaped (values are data and links).
          $attrs = function (array $a): string {
              $h = '';
              foreach ($a as $k => $v) $h .= ' data-' . $k . '="' . htmlspecialchars((string) $v) . '"';
              return $h;
          };
          // One Genes cell: the number (-> the Data Exporter) and the download arrow; a plain 0 when none.
          // The number opens the gene list below the table (js/ontology-term.js); its href is the
          // Data Exporter, for a new tab or without JavaScript.
          $cell = function (int $n, string $url, string $key, bool $sub) use ($dl_form): string {
              if ($n === 0) return '<span class="text-muted">0</span>';
              return '<a class="ob-rec ob-genes-open" href="' . htmlspecialchars($url) . '" data-sub="' . ($sub ? 1 : 0) . '" title="' . t('Show these genes') . '">'
                   . '<i class="fa fa-dna" aria-hidden="true"></i> ' . number_format($n) . '</a> ' . $dl_form($key, $sub);
          };
          ?>
          <div class="d-flex flex-wrap align-items-start gap-3 mb-3">
            <p class="mb-0 flex-grow-1" style="flex-basis:20rem;">
              <span class="ob-g-all"><?= $_orgs === 1
                  ? t('Genes annotated to %s or to a more specific term (is a, part of), in 1 organism.', $term['label'])
                  : t('Genes annotated to %s or to a more specific term (is a, part of), in %d organisms.', $term['label'], $_orgs) ?></span>
              <span class="ob-g-own" hidden><?= t('Genes annotated to %s itself, not counting the more specific terms below it.', $term['label']) ?></span>
              <?= t('Every GO source counts; a gene counts once. Click a number for those genes in the Data Exporter, or the arrow beside it to download them.') . "\n" ?>
            </p>
            <div class="form-check form-switch mb-0 text-nowrap">
              <input class="form-check-input" type="checkbox" role="switch" id="obGenesSub" checked>
              <label class="form-check-label small" for="obGenesSub"><?= t('Include more specific terms') ?></label>
            </div>
          </div>

          <table class="table table-sm table-hover align-middle w-100 ob-genes" id="obGenes">
            <thead><tr>
              <th><?= t('Organism') ?></th><th><?= t('Assembly') ?></th><th><?= t('Gene set') ?></th>
              <th class="text-end ob-g-all"><?= t('Genes') ?></th>
              <th class="text-end ob-g-own"><?= t('Genes') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($gene_rows as $g): ?>
              <tr<?= $attrs([
                  'organism' => $g['organism'], 'assembly' => $g['assembly'], 'gene-set' => $g['gene_set'],
                  'label' => str_replace('_', ' ', $g['organism']) . ' › ' . ($g['genome_name'] !== '' ? $g['genome_name'] : $g['assembly']) . ' › ' . $g['gene_set']]) ?>>
                <td><em><?= htmlspecialchars(str_replace('_', ' ', $g['organism'])) ?></em></td>
                <td><?= htmlspecialchars($g['genome_name'] !== '' ? $g['genome_name'] . ' (' . $g['assembly'] . ')' : $g['assembly']) ?></td>
                <td><?= htmlspecialchars($g['gene_set']) ?></td>
                <td class="text-end text-nowrap ob-g-all" data-order="<?= $g['genes'] ?>"><?= $cell($g['genes'], $g['url'], $g['source_key'], true) ?></td>
                <td class="text-end text-nowrap ob-g-own" data-order="<?= $g['direct'] ?>"><?= $cell($g['direct'], $g['url_own'], $g['source_key'], false) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <!-- One gene set's genes, opened from its number (js/ontology-term.js, api/go_term_genes.php).
               Opens by itself when the table has a single gene set. -->
          <div class="ob-gene-list mt-3" id="obGeneList" hidden<?= $attrs([
                   'api' => moop_url('api/go_term_genes.php'), 'term' => $term['id'],
                   'gene-url' => moop_url('tools/parent.php'), 'term-url' => moop_url('tools/ontology_term.php')]) ?>>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <h3 class="h6 mb-0 me-auto" id="obGeneListTitle"></h3>
              <span id="obGeneListActions" class="d-inline-flex align-items-center gap-2"></span>
              <button type="button" class="btn-close" id="obGeneListClose" aria-label="<?= t('Close the gene list') ?>"></button>
            </div>
            <div id="obGeneListBody"></div>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0"><?= !empty($scope) ? t('No genes here are annotated to this term or a more specific one.')
                                                       : t('No genes you can see are annotated to this term or a more specific one.') ?></p>
        <?php endif; ?>
        <?php if ($genes_missing > 0): ?>
          <p class="small text-muted mt-2 mb-0"><i class="fa fa-hourglass-half" aria-hidden="true"></i>
            <?= $genes_missing === 1 ? t('Gene counts are still being prepared for 1 organism, so some numbers are lower than they will be.')
                                     : t('Gene counts are still being prepared for %d organisms, so some numbers are lower than they will be.', $genes_missing) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($records !== null): ?>
    <div class="card shadow-sm mb-4" id="pnav-records" data-nav-label="<?= t('Expression records') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Expression records') ?></h2></div>
      <div class="card-body">
        <?php if ($records['total'] > 0): ?>
          <p class="mb-2"><?= t_markup('%s published expression records in %s or any of its parts.', '<strong>' . number_format($records['total']) . '</strong>', htmlspecialchars($term['label'])) ?></p>
          <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($records['url']) ?>"><?= t('See them in the Anatomical Expression Search') ?></a>
        <?php else: ?>
          <p class="text-muted mb-0"><?= t('No published expression records for this term or its parts.') ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($term_images)): ?>
    <div class="card shadow-sm mb-4" id="pnav-term-images" data-nav-label="<?= t('Images') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Images') ?></h2></div>
      <div class="card-body">
        <p class="mb-2"><?= t_markup('%s images of %s or any of its parts.', '<strong>' . number_format($term_images['total']) . '</strong>', htmlspecialchars($term['label'])) ?></p>
        <div class="d-flex flex-wrap gap-2 mb-2">
          <?php foreach ($term_images['sample'] as $__im):
              $__q = ['organism' => $term_images['organism'], 'file' => $__im['file'], 'size' => 'thumb'];
              if ($__im['kind'] === 'movie') $__q['poster'] = 1;
              if ($__im['kind'] === 'movie' && empty($__im['poster'])) continue; ?>
            <?= '<a href="' . htmlspecialchars($term_images['url']) . '"><img src="' . htmlspecialchars(moop_url('api/gene_image.php') . '?' . http_build_query($__q)) . '" alt="" loading="lazy" width="120" height="90" style="object-fit:contain;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px"></a>' . "\n" ?>
          <?php endforeach; ?>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($term_images['url']) ?>"><?= t('See them in the image gallery') ?></a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($records === null && $records_elsewhere !== ''): ?>
    <div class="card shadow-sm mb-4" id="pnav-records" data-nav-label="<?= t('Expression records') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Expression records') ?></h2></div>
      <div class="card-body">
        <p class="mb-2"><?= t('Published expression records for %s and its parts are searched on SIMRbase.', $term['label']) ?></p>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($records_elsewhere) ?>"><?= t('See the expression records') ?></a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($links): ?>
    <div class="card shadow-sm mb-4" id="pnav-links" data-nav-label="<?= t('Links') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Links') ?></h2></div>
      <div class="card-body">
        <?php foreach ($links as $name => $url): ?>
          <a class="btn btn-sm btn-outline-secondary me-2 mb-1" href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($name) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

      </div>
    </div>
</div>
