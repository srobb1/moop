<?php
// Image gallery (tools/image_gallery.php): tiles grouped by stage; a tile opens the quick view
// (js/modules/gene-images.js). Movies show their poster with a play badge.
$__L   = fn(?string $id) => $id === null || $id === '' ? '' : ($labels[$id] ?? $id);
$__url = fn(array $q) => moop_url('tools/image_gallery.php') . '?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== []));
$__img = fn(string $file, array $extra = []) => moop_url('api/gene_image.php') . '?' . http_build_query(['organism' => $organism, 'file' => $file] + $extra);
$__a   = $analysis !== '' ? $analyses[$analysis] : null;
$__nm  = fn(string $id) => isset($names[$id]) ? "$id ({$names[$id]})" : $id;   // "SMED30007406 (SMEDWI-1)"
$__kind_words = ['movie' => t_plain('movies'), 'panel' => t_plain('stage panels'), 'in situ' => t_plain('in situ images'),
                 'feature plot' => t_plain('feature plots'), 'violin plot' => t_plain('violin plots')];
// A side menu (the gene page's "Jump to", js/modules/parent-nav.js) once there is more than one
// stage to jump between: one row per stage, with its image count.
$__nav = count($by_stage) > 1;
$__heading = $__a ? $__a['title'] : t_plain('Image gallery');
?>
<div class="container-fluid py-3 image-gallery">
    <?php if ($__nav): ?>
    <div class="pnav-jumpbar">
      <button class="pnav-jb-btn" id="pnavJbBtn" type="button" aria-expanded="false" aria-controls="pnavJbDd">
        <svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2 4h12v1.6H2zM2 7.2h12v1.6H2zM2 10.4h8V12H2z"/></svg>
        <?= t('Jump to') . "\n" ?>
        <svg class="pnav-jb-chev" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
      </button>
      <span class="pnav-jb-current" id="pnavJbCurrent"><?= htmlspecialchars($__heading) ?></span>
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
    <?php endif; ?>
    <div id="pnav-overview" data-nav-label="<?= htmlspecialchars($__heading) ?>">
    <h2 class="h4 mb-1"><?= htmlspecialchars($__heading) ?></h2>
    <?php if ($__a && ($__a['description'] || $__a['pmid'])): ?>
    <p class="text-muted mb-2"><?= htmlspecialchars((string) $__a['description']) ?>
        <?php if ($__a['pmid']): ?><a href="https://pubmed.ncbi.nlm.nih.gov/<?= rawurlencode($__a['pmid']) ?>/" target="_blank" rel="noopener"><?= htmlspecialchars($__a['citation'] ?: 'PMID ' . $__a['pmid']) ?></a><?php endif; ?>
        <?php if (!empty($__a['link_url'])): ?><br><a href="<?= htmlspecialchars($__a['link_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(t_plain($__a['link_text'])) ?></a><?php endif; ?>
    </p>
    <?php endif; ?>

    <p class="mb-3">
        <?php if ($kinds): ?><span class="badge text-bg-light border"><?= htmlspecialchars(implode(', ', array_map(fn($k) => $__kind_words[$k] ?? $k, $kinds))) ?></span><?php endif; ?>
        <?php if ($gene !== ''): ?>
            <span class="badge text-bg-light border"><?= t('gene') ?>
                <a class="font-monospace" href="<?= htmlspecialchars(moop_url('tools/parent.php') . '?' . http_build_query(['organism' => $organism, 'uniquename' => $gene])) ?>"><?= htmlspecialchars($gene) ?></a></span>
        <?php endif; ?>
        <?php if ($term !== ''): ?><span class="badge text-bg-light border"><?= t('anatomy') ?>: <?= htmlspecialchars($__L($term)) ?></span><?php endif; ?>
        <?php if ($stage !== ''): ?><span class="badge text-bg-light border"><?= htmlspecialchars($__L($stage)) ?></span><?php endif; ?>
        <span class="text-muted small ms-1"><?= $total !== 1 ? t('%d images', $total) : t('%d image', 1) ?></span>
        <?php if (($gene !== '' || $term !== '' || $stage !== '') && $analysis !== ''): ?>
            <a class="small ms-2" href="<?= htmlspecialchars($__url(['organism' => $organism, 'analysis' => $analysis, 'kind' => implode(',', $kinds)])) ?>"><?= t('Show all of them, for every gene') ?></a>
        <?php endif; ?>
    </p>
    </div>

    <?php if (!$by_stage): ?>
        <div class="alert alert-light border"><?= t('No images match.') ?></div>
    <?php endif; ?>

    <?php if ($total > 1): /* Filter as you type (js/image-gallery.js) on what each tile carries. */ ?>
    <div class="gallery-filter mb-2">
        <label class="form-label small fw-semibold mb-1" for="galleryFilter"><?= t('Show only images that match') ?></label>
        <input type="search" class="form-control form-control-sm" id="galleryFilter" autocomplete="off"
               placeholder="<?= t('Gene ID, gene name or anatomy term, e.g. piwi-1 or pharynx') ?>">
        <div class="small text-muted mt-1" id="galleryFilterStatus" aria-live="polite"></div>
        <div class="alert alert-light border small mt-2 mb-0" id="galleryFilterNone" hidden>
            <?= t('No image here matches that.') ?>
            <a id="galleryFilterGeneLink" href="<?= htmlspecialchars($__url(['organism' => $organism, 'analysis' => $analysis, 'kind' => implode(',', $kinds)])) ?>"><?= t('Look it up as a gene ID under all of the gene\'s other IDs') ?></a>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($by_stage as $__st => $__imgs):
        $__n = count($__imgs);
        $__stage_name = $__st !== '' ? $__L($__st) : t_plain('No stage recorded');
        $__n_words = $__n !== 1 ? t_plain('%d images', $__n) : t_plain('%d image', 1);
    ?>
    <section class="gallery-stage" id="<?= htmlspecialchars('stage-' . ($__st !== '' ? preg_replace('/[^A-Za-z0-9]+/', '-', $__st) : 'none')) ?>"
             data-nav-label="<?= htmlspecialchars($__stage_name) ?>" data-nav-count="<?= $__n ?>" data-nav-count-words="<?= htmlspecialchars($__n_words) ?>">
    <h3 class="gallery-stage-head"><?= htmlspecialchars($__stage_name) ?> <span class="gallery-stage-count" title="<?= htmlspecialchars($__n_words) ?>" aria-label="<?= htmlspecialchars($__n_words) ?>"><?= $__n ?></span></h3>
    <div class="gene-images-grid">
        <?php foreach ($__imgs as $__im):
            $__movie = $__im['kind'] === 'movie';
            $__title = trim(implode(', ', array_map($__nm, $__im['accessions'])) . ' · ' . t_plain($__im['kind']));
            $__data = ['full' => $__img($__im['file']), 'movie' => $__movie ? '1' : '', 'title' => $__title,
                       'caption' => (string) $__im['caption'], 'genes' => implode(', ', array_map($__nm, $__im['accessions'])),
                       'terms' => implode(', ', array_map($__L, $__im['terms'])), 'credit' => trim((string) $__im['credit'])];
            $__data['search'] = mb_strtolower(implode(' ', array_merge(array_map($__nm, $__im['accessions']), $__im['terms'],
                                    array_map($__L, $__im['terms']), [(string) $__im['caption'], $__im['kind']])));
            $__thumb = $__movie ? ($__im['poster'] ? $__img($__im['file'], ['poster' => 1, 'size' => 'thumb']) : '') : $__img($__im['file'], ['size' => 'thumb']);
        ?>
        <?= '<button type="button" class="gene-image-tile' . ($__movie ? ' gene-image-is-movie' : '') . '" title="' . htmlspecialchars($__title) . '"'
            . implode('', array_map(fn($k, $v) => ' data-' . $k . '="' . htmlspecialchars($v) . '"', array_keys($__data), $__data)) . '>' . "\n" ?>
            <?php if ($__thumb !== ''): ?>
            <?= '<img src="' . htmlspecialchars($__thumb) . '" alt="' . htmlspecialchars($__title) . '" loading="lazy" decoding="async" width="160" height="120">' . "\n" ?>
            <?php else: ?>
            <span class="gene-image-movie" aria-hidden="true"><i class="fas fa-play"></i></span>
            <?php endif; ?>
            <?php if ($__movie && $__thumb !== ''): ?><span class="gene-image-play" aria-hidden="true"><i class="fas fa-play"></i></span><?php endif; ?>
            <span class="gene-image-label"><?php foreach ($__im['accessions'] as $__id): ?><span class="d-block"><span class="font-monospace"><?= htmlspecialchars($__id) ?></span><?= isset($names[$__id]) ? ' <strong>' . htmlspecialchars($names[$__id]) . '</strong>' : '' ?></span><?php endforeach; ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    </section>
    <?php endforeach; ?>
    <?php if ($capped): ?><p class="text-muted small mt-3"><?= t('Only the first %d are shown.', $total) ?></p><?php endif; ?>
    <?php if ($__nav): ?>
      </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="geneImageModal" tabindex="-1" aria-labelledby="geneImageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="geneImageModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('Close') ?>"></button>
            </div>
            <div class="modal-body text-center">
                <div class="gene-image-full mb-3"></div>
                <dl class="row small text-start mb-0 gene-image-facts"></dl>
            </div>
            <div class="modal-footer">
                <a class="btn btn-sm btn-outline-secondary gene-image-open" href="#" target="_blank" rel="noopener"><?= t('Open full size') ?></a>
            </div>
        </div>
    </div>
</div>
