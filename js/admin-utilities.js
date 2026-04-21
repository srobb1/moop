/**
 * ADMIN UTILITIES - Shared JavaScript for all admin pages
 * 
 * Handles generic functionality used across multiple admin pages:
 * - Bootstrap collapse toggle (replaces Bootstrap's API to avoid conflicts)
 * - Generic collapse styling with chevron rotation
 */

(function() {
    // Add styles for collapse elements with chevron animation
    const style = document.createElement('style');
    style.textContent = `
        .collapse {
            display: none;
        }
        .collapse.show {
            display: block;
        }
        .fa-chevron-down {
            transition: transform 0.3s ease;
        }
    `;
    document.head.appendChild(style);
    
    // Manual collapse toggle - replaces Bootstrap Collapse API
    document.addEventListener('DOMContentLoaded', function() {
        const triggers = document.querySelectorAll('[data-bs-toggle="collapse"]');
        triggers.forEach(function(trigger) {
            // Remove data-bs-toggle to prevent Bootstrap from handling it
            trigger.removeAttribute('data-bs-toggle');
            
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                const target = this.getAttribute('data-bs-target') || this.getAttribute('href');
                if (target) {
                    const element = document.querySelector(target);
                    if (element) {
                        const isOpen = element.classList.contains('show');
                        element.classList.toggle('show');
                        
                        // Rotate chevron if present
                        const chevron = this.querySelector('.fa-chevron-down');
                        if (chevron) {
                            chevron.style.transform = !isOpen 
                                ? 'rotate(-180deg)' 
                                : 'rotate(0deg)';
                        }
                    }
                }
            }, true);
        });
    });
})();

/**
 * Generate taxonomy tree from NCBI without leaving current page
 * Used in organism_checklist.php
 */
function initGenerateTreeButton() {
  const btn = document.getElementById('generateTreeBtn');
  if (!btn) return;
  
  btn.addEventListener('click', generateTreeFromChecklist);
}

async function generateTreeFromChecklist() {
  const btn = document.getElementById('generateTreeBtn');
  const statusDiv = document.getElementById('generateTreeStatus');
  
  // Disable button and show loading
  btn.disabled = true;
  statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Generating taxonomy tree from NCBI (this may take a minute)...</div>';
  statusDiv.style.display = 'block';
  
  try {
    const response = await fetch('manage_taxonomy_tree.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'action=generate'
    });
    
    const text = await response.text();
    
    if (response.ok) {
      statusDiv.innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>Success!</strong> Taxonomy tree has been generated. Reloading...</div>';
      
      // Reload the page after a short delay
      setTimeout(() => {
        location.reload();
      }, 2000);
    } else {
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> Failed to generate tree. Please try again or use the full management page.</div>';
      btn.disabled = false;
    }
  } catch (error) {
    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + error.message + '</div>';
    btn.disabled = false;
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initGenerateTreeButton);

// Also try immediate initialization in case DOMContentLoaded already fired
if (document.readyState === 'loading') {
  // DOM is still loading, wait for DOMContentLoaded
} else {
  // DOM is already loaded
  initGenerateTreeButton();
}

/**
 * Shared organism cache refresh — works from any admin page.
 * btn: the button element to disable/update during the run.
 * statusEl: element to write progress text into (optional).
 */
function refreshOrganismCache(btn, statusEl) {
  const sitePath = window.sitePath || '/moop';
  const endpoint = sitePath + '/admin/api/refresh_organism_cache.php';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Refreshing…'; }
  if (statusEl) { statusEl.textContent = 'Starting…'; statusEl.style.display = ''; }

  fetch(endpoint, {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken }
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-sync-alt"></i> Refresh Cache'; }
      if (statusEl) statusEl.textContent = 'Error: ' + data.error;
      return;
    }
    const startedAt = Date.now();
    const poll = setInterval(() => {
      fetch(endpoint + '?status=1')
        .then(r => r.json())
        .then(s => {
          const elapsed = Math.round((Date.now() - startedAt) / 1000);
          if (statusEl) statusEl.textContent = 'Scanning… ' + elapsed + 's';
          if (s.status === 'idle' && elapsed > 2) {
            clearInterval(poll);
            if (statusEl) statusEl.textContent = 'Done — reloading…';
            window.location.reload();
          }
        })
        .catch(() => clearInterval(poll));
    }, 2000);
  })
  .catch(err => {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-sync-alt"></i> Refresh Cache'; }
    if (statusEl) statusEl.textContent = 'Failed: ' + err;
  });
}
