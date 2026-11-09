<?php
/**
 * GENE NOT FOUND - Content File (tools/parent.php, HTTP 404)
 *
 * Variables (extracted from $data by render_display_page):
 * - $uniquename      the ID that was asked for, RAW -- escape on output
 * - $organism_name   validated organism directory name, '' when the organism itself was the problem
 * - $organism_label  its display name ("Congeria kusceri"), or ''
 * - $site
 *
 * Replaces a bare die() line with no navigation, sent as HTTP 200. That is what a tester following
 * an old or mistyped link landed on, and the raw ID in it was also a reflected-XSS hole (2026-10-13).
 */
$search_url = moop_url_base() . '/tools/search.php' . ($organism_name !== '' ? '?organisms=' . rawurlencode($organism_name) : '');
?>
<div class="container mt-5" style="max-width: 760px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
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
        </div>
    </div>
</div>
