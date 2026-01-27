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
          <h2 class="card-title fw-bold text-dark mb-4">About MOOP</h2>
          
          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2">What is MOOP?</h5>
            <p class="text-muted">
              <strong>MOOP</strong> — to keep company, associate closely. MOOP is a comprehensive platform for exploring and discovering how diverse organisms associate closely together.
            </p>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2">Features</h5>
            <ul class="text-muted">
              <li>Browse organisms by group or customize selection with an interactive taxonomy tree</li>
              <li>Advanced search and filtering capabilities</li>
              <li>Multi-organism analysis tools</li>
              <li>Sequence retrieval and manipulation</li>
              <li>BLAST integration for sequence comparison</li>
            </ul>
          </div>

          <div class="mb-4">
            <h5 class="fw-semibold text-dark mb-2">Getting Started</h5>
            <p class="text-muted">
              Select organisms from the home page to begin exploring. Use the group-based view for quick selection or the interactive taxonomy tree for precise customization.
            </p>
          </div>

          <div>
            <h5 class="fw-semibold text-dark mb-2">Need Help?</h5>
            <p class="text-muted">
              Contact the administrator for assistance or questions about using MOOP.
            </p>
          </div>

          <div class="mt-5 pt-4 border-top">
            <h5 class="fw-semibold text-dark mb-2"><i class="fa fa-github"></i> GitHub Repository</h5>
            <p class="text-muted">
              MOOP is open source. Visit the <a href="https://github.com/srobb1/moop" target="_blank" class="text-decoration-none">GitHub repository</a> to view the source code, contribute, or report issues.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
