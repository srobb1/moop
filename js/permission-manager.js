/**
 * Permission Manager - Handle file/directory permission fixes
 * 
 * Provides AJAX functionality to fix file permissions from the browser
 * when the web server has sufficient permissions.
 */

/**
 * Fix file or directory permissions via AJAX
 * 
 * @param {Event} event - Click event
 * @param {string} filePath - Path to file or directory
 * @param {string} fileType - 'file' or 'directory'
 * @param {string} organism - Optional organism name (for organism-specific fixes)
 * @param {string} resultId - ID of result div to show status
 */
function fixFilePermissions(event, filePath, fileType, organism, resultId) {
  event.preventDefault();
  
  const resultDiv = document.getElementById(resultId);
  const button = event.target.closest('button');
  
  if (!resultDiv) {
    console.error('Result div not found: ' + resultId);
    return;
  }
  
  // Disable button and show loading
  const originalHtml = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Fixing...';
  
  resultDiv.innerHTML = '<div class="alert alert-info small">Processing...</div>';
  
  // Send AJAX request
  const data = new FormData();
  data.append('action', 'fix_file_permissions');
  data.append('file_path', filePath);
  data.append('file_type', fileType);
  if (organism) {
    data.append('organism', organism);
  }
  
  fetch(window.location.pathname, {
    method: 'POST',
    body: data
  })
  .then(response => response.json())
  .then(json => {
    if (json.success) {
      resultDiv.innerHTML = '<div class="alert alert-success small">' +
        '<i class="fa fa-check-circle"></i> ' + escapeHtml(json.message) + ' ' +
        '<strong>Refreshing...</strong></div>';
      
      // Refresh page after short delay
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } else {
      resultDiv.innerHTML = '<div class="alert alert-danger small">' +
        '<i class="fa fa-times-circle"></i> <strong>Failed:</strong> ' + escapeHtml(json.message) + '</div>';
      
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  })
  .catch(error => {
    resultDiv.innerHTML = '<div class="alert alert-danger small">' +
      '<i class="fa fa-exclamation-triangle"></i> <strong>Error:</strong> ' + escapeHtml(error.message) + '</div>';
    
    button.disabled = false;
    button.innerHTML = originalHtml;
  });
}

/**
 * MD5 hash function for generating consistent IDs
 * Simple implementation for ID generation
 */
function md5(str) {
  // Simple hash function - not cryptographically secure but good for IDs
  let hash = 0;
  if (str.length === 0) return hash.toString();
  
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash; // Convert to 32bit integer
  }
  
  return Math.abs(hash).toString(16);
}

/**
 * Escape HTML special characters to prevent XSS
 */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
