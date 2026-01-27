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
  if (link) {
    const href = link.href.toLowerCase();
    // Match manage_organisms.php or manage_annotations.php links
    // Exclude hash links and any admin pages
    if ((href.includes('manage_organisms.php') || href.includes('manage_annotations.php')) &&
        !href.includes('#') && 
        !href.includes('/admin/')) {
      // Show indicator immediately on click (before server queries)
      setTimeout(function() {
        showLoadingIndicator();
      }, 50);
    }
  }
});

/**
 * Hide indicator when user uses browser back/forward buttons
 * (popstate fires when going back/forward in history)
 * Don't show spinner when navigating away from manage pages
 */
window.addEventListener('popstate', function() {
  // Going back to a previous page, hide the spinner and don't show it
  hideLoadingIndicator();
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

