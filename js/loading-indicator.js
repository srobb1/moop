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
 * Auto-show loading indicator ONLY when clicking manage page links
 * Only trigger on direct clicks to manage_organisms.php or manage_annotations.php
 */
document.addEventListener('click', function(event) {
  const link = event.target.closest('a');
  if (link) {
    const href = link.href.toLowerCase();
    const currentUrl = window.location.href.toLowerCase();
    
    // ONLY show spinner when explicitly clicking manage page links
    // AND we're not already on that page
    if ((href.includes('manage_organisms.php') || href.includes('manage_annotations.php')) &&
        !href.includes('#') &&
        !currentUrl.includes('manage_organisms.php') &&
        !currentUrl.includes('manage_annotations.php')) {
      showLoadingIndicator();
    }
  }
});

/**
 * Auto-hide indicator when page fully loads
 * Use both 'load' and 'pageshow' events:
 * - 'load' fires on normal page load
 * - 'pageshow' fires on back/forward navigation (including cached pages)
 */
window.addEventListener('load', function() {
  hideLoadingIndicator();
});

window.addEventListener('pageshow', function(event) {
  // If navigating back via browser history, hide the spinner
  if (event.persisted) {
    hideLoadingIndicator();
  }
});


