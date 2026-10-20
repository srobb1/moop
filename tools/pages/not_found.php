<?php
/**
 * NOT FOUND - Content File (moop_not_found_page(), lib/functions_display.php)
 *
 * Variables (extracted from $data by render_display_page):
 * - $site
 * - $message  plain text, RAW -- escaped here; '' for the generic wording
 */
?>
<div class="container mt-5" style="max-width: 640px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-3"><i class="fa fa-search me-2 text-muted"></i>Not found</h1>
            <p class="mb-3"><?= htmlspecialchars(($message ?? '') !== '' ? $message : 'There is nothing to open at this address.') ?></p>
            <?php include __DIR__ . '/not_found_access_hint.php'; ?>
            <a class="btn btn-moop-teal text-white" href="/<?= htmlspecialchars($site) ?>/">
                <i class="fa fa-home me-1"></i>Home
            </a>
        </div>
    </div>
</div>
