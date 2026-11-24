<?php
/**
 * GENE NOT FOUND - Content File (tools/parent.php, HTTP 404)
 *
 * Variables (extracted from $data by render_display_page):
 * - $uniquename      the ID that was asked for, RAW -- escape on output
 * - $organism_name   validated organism directory name, '' when the organism itself was the problem
 * - $organism_label  its display name ("Congeria kusceri"), or ''
 * - $site
 * - $browser_only    [chr, start, end, browser_url|null] when the ID is a gene of the genome browser
 *                    that has no gene page (non-coding; tools/parent.php moop_gene_browser_only())
 *
 * Replaces a bare die() line with no navigation, sent as HTTP 200. That is what a tester following
 * an old or mistyped link landed on, and the raw ID in it was also a reflected-XSS hole (2026-10-13).
 */
$search_url = moop_url_base() . '/tools/search.php' . ($organism_name !== '' ? '?organisms=' . rawurlencode($organism_name) : '');
?>
<div class="container mt-5" style="max-width: 760px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
<?php if (!empty($browser_only)): ?>
            <h1 class="h4 mb-3"><i class="fa fa-info-circle me-2 text-muted"></i><?= t('This gene has no gene page') ?></h1>
            <p class="mb-2">
                <?= t_markup('%s is shown in the genome browser only.', '<code>' . htmlspecialchars($uniquename) . '</code>') . "\n" ?>
                <?= t('Gene pages are made for protein-coding genes; non-coding genes, such as lncRNA, tRNA and rRNA genes, are in the genome browser but not in the gene pages.') . "\n" ?>
            </p>
            <p class="text-muted small mb-3">
                <?= t('Location') ?>: <?= htmlspecialchars($browser_only['chr']) ?>:<?= number_format($browser_only['start']) ?>&ndash;<?= number_format($browser_only['end']) . "\n" ?>
            </p>
<?php if (!empty($browser_only['browser_url'])): ?>
            <a class="btn btn-moop-teal text-white me-2" href="<?= htmlspecialchars($browser_only['browser_url']) ?>">
                <i class="fa fa-dna me-1"></i><?= t('View in the genome browser') ?>
            </a>
<?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(moop_url_base()) ?>/"><?= t('Home') ?></a>
<?php else: ?>
            <h1 class="h4 mb-3"><i class="fa fa-search me-2 text-muted"></i><?= t('Gene not found') ?></h1>
            <p class="mb-2">
                <?php if ($uniquename !== ''): ?>
                    <?= ($organism_label !== ''
                        ? t_markup('There is no gene %1$s in %2$s that you can open.', '<code>' . htmlspecialchars($uniquename) . '</code>',
                                   '<em>' . htmlspecialchars($organism_label) . '</em>')
                        : t_markup('There is no gene %s that you can open.', '<code>' . htmlspecialchars($uniquename) . '</code>')) . "\n" ?>
                <?php else: ?>
                    <?= t('This link does not name a gene.') . "\n" ?>
                <?php endif; ?>
            </p>
            <p class="text-muted small mb-3">
                <?= t_markup('Check the spelling — IDs include their version, e.g. %s — or search by name, description or annotation.', '<code>.1</code>') . "\n" ?>
            </p>
            <?php include __DIR__ . '/not_found_access_hint.php'; ?>
<?php if (moop_tool_on('annotation_search')): ?>
            <a class="btn btn-moop-teal text-white me-2" href="<?= htmlspecialchars($search_url) ?>">
                <i class="fa fa-search me-1"></i><?= $organism_label !== '' ? t('Search %s', $organism_label) : t('Search') ?>
            </a>
<?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(moop_url_base()) ?>/"><?= t('Home') ?></a>
<?php endif; ?>
        </div>
    </div>
</div>
