<?php
/**
 * SEARCH & FILTER TUTORIAL - Content File
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
?>

<div class="container mt-5">
  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <h1 class="fw-bold mb-4"><i class="fa fa-search"></i> Search & Filter</h1>

      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Finding What You Need</h3>
          <p class="text-muted mb-4">
            MOOP provides powerful search and filtering capabilities to help you find specific sequences and annotations.
          </p>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Basic Search</h4>
          <p class="text-muted mb-3">
            Use the Search tool to find sequences by:
          </p>
          <ul class="text-muted">
            <li>Gene name or ID</li>
            <li>Sequence description</li>
            <li>Annotation text</li>
            <li>Keyword matching</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Advanced Filtering</h4>
          <p class="text-muted mb-3">
            Refine your results with advanced filters:
          </p>
          <ul class="text-muted">
            <li><strong>Organism:</strong> Filter by specific organisms</li>
            <li><strong>Annotation Type:</strong> Show only certain annotation categories</li>
            <li><strong>Length:</strong> Filter sequences by length range</li>
            <li><strong>Quality:</strong> Filter by quality scores or confidence levels</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Working with Results</h4>
          <p class="text-muted mb-3">
            Once you have search results:
          </p>
          <ul class="text-muted">
            <li><strong>Sort columns:</strong> Click column headers to sort by any field</li>
            <li><strong>Search within results:</strong> Use the search box at the top of the table</li>
            <li><strong>Export results:</strong> Download selected results as CSV or FASTA</li>
            <li><strong>View details:</strong> Click on a result row to see full information</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Search Tips</h4>
          <ul class="text-muted">
            <li>Use quotation marks for exact matches: <code>"exact phrase"</code></li>
            <li>Use wildcards (* or ?) for partial matching: <code>gene*</code></li>
            <li>Combine multiple filters for more precise results</li>
            <li>Save common searches for quick access later</li>
          </ul>

          <h4 class="fw-semibold text-dark mt-4 mb-2">Saved Searches</h4>
          <p class="text-muted">
            You can save frequently used searches to quickly re-run them later. 
            Use the "Save Search" button in the search interface.
          </p>
        </div>
      </div>

      <div class="mb-4">
        <a href="help.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-arrow-left"></i> Back to Help
        </a>
      </div>
    </div>
  </div>
</div>
