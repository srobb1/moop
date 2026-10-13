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
$search_url = '/' . $site . '/tools/search.php' . ($organism_name !== '' ? '?organisms=' . rawurlencode($organism_name) : '');
?>
<div class="container mt-5" style="max-width: 760px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-3"><i class="fa fa-search me-2 text-muted"></i>Gene not found</h1>
            <p class="mb-2">
                <?php if ($uniquename !== ''): ?>
                    There is no gene <code><?= htmlspecialchars($uniquename) ?></code>
                    <?= $organism_label !== '' ? 'in <em>' . htmlspecialchars($organism_label) . '</em>' : '' ?>
                    that you can open.
                <?php else: ?>
                    This link does not name a gene.
                <?php endif; ?>
            </p>
            <p class="text-muted small mb-4">
                Check the spelling — IDs include their version, e.g. <code>.1</code> — or search by name,
                description or annotation. A gene can also be in a gene set you do not have access to.
            </p>
            <a class="btn btn-moop-teal text-white me-2" href="<?= htmlspecialchars($search_url) ?>">
                <i class="fa fa-search me-1"></i>Search<?= $organism_label !== '' ? ' ' . htmlspecialchars($organism_label) : '' ?>
            </a>
            <a class="btn btn-outline-secondary" href="/<?= htmlspecialchars($site) ?>/">Home</a>
        </div>
    </div>
</div>
