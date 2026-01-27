/**
 * Loading Indicator Functions
 * 
 * Provides JavaScript functions to control the loading indicator
 * Used during database scanning and annotation counting operations
 */

/**
 * Show the loading indicator
 */
function showLoadingIndicator() {
  const indicator = document.getElementById('loadingIndicator');
  if (indicator) {
    indicator.style.display = 'flex';
  }
}

/**
 * Hide the loading indicator
 */
function hideLoadingIndicator() {
  const indicator = document.getElementById('loadingIndicator');
  if (indicator) {
    indicator.style.display = 'none';
  }
}

/**
 * Auto-show loading indicator on page navigation (before server queries start)
 * Listen for navigation clicks to admin pages that do database scanning
 */
document.addEventListener('click', function(event) {
  // Check if clicked element is a link to manage pages
  const link = event.target.closest('a');
  if (link && (link.href.includes('manage_organisms') || link.href.includes('manage_annotations'))) {
    // Show indicator immediately on click (before server queries)
    setTimeout(function() {
      showLoadingIndicator();
    }, 50);
  }
});

/**
 * Show indicator on page load if data-needs-scan="true" attribute exists
 * Checks for data attribute on page element
 */
document.addEventListener('DOMContentLoaded', function() {
  const pageElement = document.querySelector('[data-needs-scan="true"]');
  
  if (pageElement) {
    // Give the page 100ms to start rendering, then show if still loading
    setTimeout(function() {
      const indicator = document.getElementById('loadingIndicator');
      if (indicator && !document.hidden) {
        showLoadingIndicator();
      }
    }, 100);
  }
});

/**
 * Auto-hide indicator when page is fully loaded
 */
window.addEventListener('load', function() {
  hideLoadingIndicator();
});

