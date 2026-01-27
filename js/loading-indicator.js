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
 * Show indicator on page load if database scanning might occur
 * Checks for data-needs-scan="true" attribute on page element
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
