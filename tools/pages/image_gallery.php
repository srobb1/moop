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
?>
<div class="container-fluid py-3 image-gallery">
    <h2 class="h4 mb-1"><?= htmlspecialchars($__a ? $__a['title'] : t_plain('Image gallery')) ?></h2>
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

    <?php if (!$by_stage): ?>
        <div class="alert alert-light border"><?= t('No images match.') ?></div>
    <?php endif; ?>

    <?php foreach ($by_stage as $__st => $__imgs): ?>
    <h3 class="h6 mt-3 mb-2"><?= $__st !== '' ? htmlspecialchars($__L($__st)) : t('No stage recorded') ?> <span class="text-muted small"><?= count($__imgs) ?></span></h3>
    <div class="gene-images-grid">
        <?php foreach ($__imgs as $__im):
            $__movie = $__im['kind'] === 'movie';
            $__title = trim(implode(', ', array_map($__nm, $__im['accessions'])) . ' · ' . t_plain($__im['kind']));
            $__data = ['full' => $__img($__im['file']), 'movie' => $__movie ? '1' : '', 'title' => $__title,
                       'caption' => (string) $__im['caption'], 'genes' => implode(', ', array_map($__nm, $__im['accessions'])),
                       'terms' => implode(', ', array_map($__L, $__im['terms'])), 'credit' => trim((string) $__im['credit'])];
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
    <?php endforeach; ?>
    <?php if ($capped): ?><p class="text-muted small mt-3"><?= t('Only the first %d are shown.', $total) ?></p><?php endif; ?>
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
