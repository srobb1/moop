<?php
/**
 * CHOOSE A GENE SET - Content File (tools/parent.php)
 *
 * Shown when a gene page link names an ID that more than one gene set the viewer can open
 * contains, and does not say which (no assembly + gene_set). The page asks rather than guesses.
 *
 * Variables (extracted from $data by render_display_page):
 * - $uniquename      the ID asked for, RAW -- escape on output
 * - $organism_label  "Congeria kusceri"
 * - $choices         [['assembly_label', 'gene_set', 'url'], ...], at least two
 * - $site
 */
?>
<div class="container mt-5" style="max-width: 760px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-3"><i class="fa fa-code-branch me-2 text-muted"></i>Which gene set?</h1>
            <p class="mb-3">
                <code><?= htmlspecialchars($uniquename) ?></code> is in <?= count($choices) ?> gene sets of
                <em><?= htmlspecialchars($organism_label) ?></em>. Pick the one you want:
            </p>
            <div class="list-group mb-3">
                <?php foreach ($choices as $choice): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                       href="<?= htmlspecialchars($choice['url']) ?>">
                        <span><strong><?= htmlspecialchars($choice['gene_set']) ?></strong>
                            <span class="text-muted ms-2"><?= htmlspecialchars($choice['assembly_label']) ?></span></span>
                        <i class="fa fa-chevron-right text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="text-muted small mb-0">
                The same ID in two gene sets is usually a newer annotation that kept the older IDs; the
                genes need not be identical.
            </p>
        </div>
    </div>
</div>
