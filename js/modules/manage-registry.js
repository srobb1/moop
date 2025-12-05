/**
 * Manage Registry - Page-specific functionality
 * 
 * Handles registry update operations and result display
 */

/**
 * Update registry (PHP or JS)
 */
function updateRegistry(type) {
    const typeLabel = type === 'php' ? 'PHP' : 'JavaScript';
    const btn = event.target.closest('button');
    const originalText = btn.textContent;
    const resultDiv = document.getElementById(type + 'Result');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Updating...';
    resultDiv.classList.add('d-none');
    
    const data = new FormData();
    data.append('action', 'update_registry');
    data.append('type', type);
    
    fetch(window.location.pathname, {
      method: 'POST',
      body: data
    })
    .then(response => response.json())
    .then(json => {
      if (json.success) {
        // Show success message
        resultDiv.innerHTML = '<div class="alert alert-success">' +
          '<i class="fa fa-check-circle"></i> ' + typeLabel + ' registry updated successfully!' +
          '</div>';
        resultDiv.classList.remove('d-none');
        
        btn.innerHTML = '<i class="fa fa-check"></i> Updated!';
        
        // Update the last updated timestamp
        const now = new Date();
        const timestamp = now.getFullYear() + '-' + 
          String(now.getMonth() + 1).padStart(2, '0') + '-' + 
          String(now.getDate()).padStart(2, '0') + ' ' +
          String(now.getHours()).padStart(2, '0') + ':' +
          String(now.getMinutes()).padStart(2, '0') + ':' +
          String(now.getSeconds()).padStart(2, '0');
        
        const timestampEl = document.querySelector('#' + type + 'Result').previousElementSibling;
        if (timestampEl && timestampEl.textContent.includes('Last updated')) {
          timestampEl.innerHTML = '<i class="fa fa-clock-o"></i> Last updated: <strong>' + timestamp + '</strong>';
        }
        
        setTimeout(() => {
          btn.disabled = false;
          btn.textContent = originalText;
        }, 2000);
      } else {
        resultDiv.innerHTML = '<div class="alert alert-danger">' +
          '<i class="fa fa-exclamation-triangle"></i> Error: ' + (json.message || 'Unknown error') + 
          '<br><small>Check the permission alerts above if this is a permission error.</small>' +
          '</div>';
        resultDiv.classList.remove('d-none');
        
        btn.disabled = false;
        btn.textContent = originalText;
      }
    })
    .catch(error => {
      resultDiv.innerHTML = '<div class="alert alert-danger">' +
        '<i class="fa fa-exclamation-triangle"></i> Error: ' + error.message +
        '</div>';
      resultDiv.classList.remove('d-none');
      
      btn.disabled = false;
      btn.textContent = originalText;
    });
}
