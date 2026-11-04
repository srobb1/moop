<?php
/**
 * ABOUT - Content File
 * 
 * Pure display content for about page.
 * All HTML structure handled by layout.php.
 * 
 * Available variables:
 * - $siteTitle: Site title from config
 */
?>

<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?= htmlspecialchars($siteTitle) ?></h1>
    <hr class="mx-auto" style="width: 100px; border: 2px solid #0d6efd;">
  </div>

  <!-- About Content -->
  <div class="row g-4 justify-content-center mb-5">
    <div class="col-md-12 col-lg-8">
      <div class="card h-100 shadow-sm border-0 rounded-3">
        <div class="card-body p-5">
          <h2 class="card-title fw-bold text-dark mb-4"><?= t('About MOOP') ?></h2>
          
          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('What is MOOP?') ?></h5>
            <p class="text-muted">
              <?= t_markup('%s — to keep company, associate closely.', '<strong>MOOP</strong>') ?> <?= t('MOOP is a comprehensive platform for exploring and discovering how diverse organisms associate closely together.') . "\n" ?>
            </p>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('Features') ?></h5>
            <ul class="text-muted">
              <li><?= t('Browse organisms by group or customize selection with an interactive taxonomy tree') ?></li>
              <li><?= t('Advanced search and filtering capabilities') ?></li>
              <li><?= t('Multi-organism analysis tools') ?></li>
              <li><?= t('Sequence retrieval and manipulation') ?></li>
              <li><?= t('BLAST integration for sequence comparison') ?></li>
            </ul>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2"><?= t('Getting Started') ?></h5>
            <p class="text-muted">
              <?= t('Select organisms from the home page to begin exploring. Use the group-based view for quick selection or the interactive taxonomy tree for precise customization.') . "\n" ?>
            </p>
          </div>

          <?php /* Access levels used to be explained here (harvested from the deleted
                   help/getting-started.php). Their one home is now help.php?topic=access;
                   this keeps only the pointer, so the two cannot drift. */ ?>
          <div>
            <h5 class="fw-semibold text-dark mb-2"><?= t('Who can see what') ?></h5>
            <p class="text-muted">
              <?= t('Not every organism is public, so the lists you see may be shorter than the full contents of the site.') . "\n" ?>
              <a href="help.php?topic=access" class="text-decoration-none"><?= t('Who can see what, and how to get access') ?></a>.
            </p>
          </div>

          <div>
            <h5 class="fw-semibold text-dark mb-2"><?= t('Need Help?') ?></h5>
            <p class="text-muted">
              <?= t('Contact the administrator for assistance or questions about using MOOP.') . "\n" ?>
            </p>
          </div>

          <div class="mt-5 pt-4 border-top">
            <h5 class="fw-semibold text-dark mb-2"><i class="fa fa-github"></i> <?= t('GitHub Repository') ?></h5>
            <p class="text-muted">
              <?= t('MOOP is open source.') ?> <?= t_markup('Visit the %s to view the source code, contribute, or report issues.',
                  '<a href="https://github.com/srobb1/moop" target="_blank" class="text-decoration-none">' . t('GitHub repository') . '</a>') . "\n" ?>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
