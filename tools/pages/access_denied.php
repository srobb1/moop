<?php
/**
 * ACCESS DENIED - Content File
 * 
 * Pure display content showing permission denied message.
 * All HTML structure handled by layout.php.
 * 
 * Available variables:
 * - $siteTitle: Site title from config
 * - $adminEmail: Administrator email from config
 * - $site: Site path from config
 */
?>

<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?= htmlspecialchars($siteTitle) ?></h1>
    <hr class="mx-auto page-header-divider">
  </div>

  <!-- Access Denied Card -->
  <div class="row g-4 justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="card h-100 shadow-sm border-0 rounded-3">
        <div class="card-body p-5 text-center">
          <div class="mb-4" style="font-size: 60px;">🔒</div>
          <h2 class="card-title fw-bold text-dark mb-4"><?= t('Access Denied') ?></h2>
          
          <p class="card-text text-muted mb-4">
            <?= t('You don\'t have permission to view this page. If you believe you\'re seeing this page erroneously, please contact the administrator.') . "\n" ?>
          </p>
          
          <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <i class="fa fa-info-circle"></i> <strong><?= t('Administrator Contact:') ?></strong>
            <br>
            <a href="mailto:<?= htmlspecialchars($adminEmail) ?>" class="text-decoration-none">
              <?= htmlspecialchars($adminEmail) ?>
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= t('Close') ?>"></button>
          </div>
          
          <a href="<?= htmlspecialchars(moop_url_base()) ?>/index.php" class="btn btn-primary btn-lg w-100">
            <i class="fa fa-home"></i> <?= t('Return to Home') . "\n" ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
