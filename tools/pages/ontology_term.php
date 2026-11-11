<?php
/**
 * Ontology term page -- content file (controller: tools/ontology_term.php). Laid out like the gene
 * page: the same section sidebar (js/modules/parent-nav.js lists every [data-nav-label] card).
 * Variables: $term (null = not found), $prefix, $ontology_name, $release, $view (paths, parents,
 * kinds, outgoing, incoming, written), $rels, $written_html, $records, $records_elsewhere, $links.
 */
$browser = moop_url('tools/ontology_browser.php') . '?o=' . rawurlencode($prefix);
$rows = function (array $list) use ($prefix): string {
    $h = '<ul class="ob-tree ob-list">';
    foreach ($list as $r) {
        $h .= '<li class="ob-d1"><div class="ob-row"><span class="ob-term"><span class="ob-leaf"></span>'
            . '<a href="' . htmlspecialchars(moop_ob_term_url($prefix, $r['id'])) . '">' . htmlspecialchars($r['label']) . '</a>'
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
        <h3 class="ob-h"><?= t('Is a') ?> <span class="ob-n"><?= count($view['parents']) ?></span></h3>
        <?= $view['parents'] ? $rows($view['parents']) : '<p class="text-muted small">' . t('None: a top term.') . '</p>' ?>
        <h3 class="ob-h mt-3"><?= t('Kinds of %s', $term['label']) ?> <span class="ob-n"><?= count($view['kinds']) ?></span></h3>
        <?= $view['kinds'] ? $rows($view['kinds']) : '<p class="text-muted small">' . t('None.') . '</p>' ?>
      </div>
    </div>

    <?php if ($view['outgoing'] || $view['incoming']): ?>
    <div class="card shadow-sm mb-4" id="pnav-relations" data-nav-label="<?= t('Parts and relationships') ?>">
      <div class="card-header ob-card-head"><h2 class="h5 mb-0"><?= t('Parts and relationships') ?></h2></div>
      <div class="card-body">
        <?php $__first = true; foreach ($view['outgoing'] as $label => $list): ?>
          <h3 class="ob-h<?= $__first ? '' : ' mt-3' ?>"><?php $__first = false; ?><?= htmlspecialchars(ucfirst(t_plain($label))) ?> <span class="ob-n"><?= count($list) ?></span></h3>
          <?= $rows($list) ?>
        <?php endforeach; ?>
        <?php foreach ($view['incoming'] as $label => $list): ?>
          <h3 class="ob-h mt-3"><?= t('Terms that are %s %s', t_plain($label), $term['label']) ?> <span class="ob-n"><?= count($list) ?></span></h3>
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
