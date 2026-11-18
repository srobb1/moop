<?php
/* Spatial atlas tool page (tools/spatial_atlas.php). The title, paper title, abstract and figure are the
   atlas's own (atlas.json, as the authors wrote them) -- data, not translated. */
if ($missing): ?>
<div class="container my-4">
    <div class="alert alert-warning"><?= t('This atlas does not exist, or you do not have access to it.') ?></div>
</div>
<?php return; endif;
$logo = $asset_url('logo');
$fig  = $asset_url('figure');
$pmid = (string) ($meta['pmid'] ?? '');
?>
<div class="container-fluid my-3">
<div class="sa-page">
    <div class="row g-4">
        <div class="col-lg-5 sa-desc">
            <div class="clearfix mb-3">
                <?php if ($logo): ?><img src="<?= htmlspecialchars($logo) ?>" alt="" class="sa-logo"><?php endif; ?>
                <h1><?= htmlspecialchars((string) $meta['title']) ?></h1>
                <?php if (!empty($meta['paper_title'])): ?><div class="sa-paper"><?= htmlspecialchars((string) $meta['paper_title']) ?></div><?php endif; ?>
            </div>
            <h2 class="h4"><?= t('Project description') ?></h2>
            <?php if ($fig): ?><?= '<img src="' . htmlspecialchars($fig) . '" alt="' . t('How the atlas was made: cells projected onto a 3-D model, then expression predicted for every gene') . '" class="sa-figure my-2">' . "\n" ?><?php endif; ?>
            <?php if (!empty($meta['abstract'])): ?><p><?= htmlspecialchars((string) $meta['abstract']) ?></p><?php endif; ?>
            <p class="sa-cite mb-1">
                <?= htmlspecialchars((string) $meta['citation']) ?>
                <?php if ($pmid !== ''): ?><a href="https://pubmed.ncbi.nlm.nih.gov/<?= rawurlencode($pmid) ?>/" target="_blank" rel="noopener"><?= t('PubMed %s', $pmid) ?></a><?php endif; ?>
                <?php if (!empty($meta['data_url'])): ?> &middot; <a href="<?= htmlspecialchars((string) $meta['data_url']) ?>" target="_blank" rel="noopener"><?= t('Sequencing data (GEO)') ?></a><?php endif; ?>
            </p>
            <p class="sa-cite"><?= t('Values are rounded for display. The exact data are at GEO.') ?></p>
        </div>
        <div class="col-lg-7">
            <h2 class="h4 text-center"><?= htmlspecialchars(t_plain('%s database', (string) $meta['title'])) ?></h2>
            <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap my-3">
                <label for="saPick" class="mb-0"><?= t('Please type or select a gene') ?></label>
                <div class="sa-pick flex-grow-1">
                    <input type="search" id="saPick" class="form-control" autocomplete="off" placeholder="<?= t('Gene name or ID') ?>"
                           aria-describedby="saPickNote">
                    <div class="list-group" id="saPickList" role="listbox" hidden></div>
                </div>
            </div>
            <div id="saPickNote" class="text-center small mb-2 <?= $not_found ? 'text-warning' : 'visually-hidden' ?>"><?=
                $not_found ? t('%s is not in this atlas. Showing the default gene.', $asked) : t('Type at least two letters to see matching genes.') ?></div>
            <div id="saViewer"></div>
        </div>
    </div>
</div>
</div>
