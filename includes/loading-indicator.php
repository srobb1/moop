<?php
/**
 * Loading Indicator HTML Include
 * 
 * Displays a full-screen loading indicator with spinner animation
 * Used during database scanning and annotation counting operations
 * 
 * Include this file in your page template where you want the indicator to appear
 * Then use JavaScript functions: showLoadingIndicator() and hideLoadingIndicator()
 * 
 * To auto-show on page load, add data-needs-scan="true" to your container element
 */
?>

<!-- Loading Indicator for Database Scanning -->
<div id="loadingIndicator" class="loading-indicator" style="display: none;">
  <div class="loading-content">
    <div class="spinner">
      <div class="spinner-ring"></div>
      <div class="spinner-ring"></div>
      <div class="spinner-ring"></div>
      <div class="spinner-ring"></div>
    </div>
    <h4 class="mt-3 mb-2"><i class="fa fa-database"></i> Scanning Databases</h4>
    <p class="text-muted mb-2">Counting annotations across all organisms... This may take a few seconds.</p>
    <p class="text-muted small mb-0">
      <strong>Note:</strong> If this takes too long or times out, run the scan manually from the command line:<br>
      <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 0.9em;">
        php scripts/warm_organism_cache.php
      </code>
    </p>
  </div>
</div>
