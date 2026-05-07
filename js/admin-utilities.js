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
 * Background organism cache refresh — POSTs to the refresh endpoint,
 * polls until the background scan finishes, then reloads the page.
 *
 * @param {HTMLElement|null} btn      - Button to disable/update while running
 * @param {HTMLElement|null} statusEl - Element to show elapsed-time status text
 * @param {boolean}          force    - Pass true to force NCBI re-fetch (--force flag)
 * @param {string}           label    - Idle label restored on the button after failure
 */
function refreshOrganismCache(btn, statusEl, force = false, label = '<i class="fa fa-sync-alt"></i> Refresh Cache') {
  const sitePath = window.sitePath || '/moop';
  const endpoint = sitePath + '/admin/api/refresh_organism_cache.php';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Starting…'; }
  if (statusEl) { statusEl.textContent = 'Starting…'; statusEl.style.display = ''; }

  const body = new URLSearchParams({ ...(force ? { force: '1' } : {}) });
  fetch(endpoint, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        if (btn) { btn.disabled = false; btn.innerHTML = label; }
        if (statusEl) statusEl.textContent = 'Error: ' + data.error;
        return;
      }
      if (btn) btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Running…';
      const startedAt = Date.now();
      const poll = setInterval(() => {
        fetch(endpoint + '?status=1')
          .then(r => r.json())
          .then(s => {
            const elapsed = Math.round((Date.now() - startedAt) / 1000);
            if (statusEl) statusEl.textContent = 'Running… ' + elapsed + 's';
            if (s.status === 'idle' && elapsed >= 1) {
              clearInterval(poll);
              if (statusEl) statusEl.textContent = 'Done — reloading…';
              window.location.reload();
            }
          })
          .catch(() => clearInterval(poll));
      }, 2000);
    })
    .catch(err => {
      if (btn) { btn.disabled = false; btn.innerHTML = label; }
      if (statusEl) statusEl.textContent = 'Failed: ' + err;
    });
}

/**
 * Generate taxonomy tree button used in organism_checklist.php
 */
function initGenerateTreeButton() {
  const btn = document.getElementById('generateTreeBtn');
  if (!btn) return;
  btn.addEventListener('click', () => {
    refreshOrganismCache(
      btn,
      document.getElementById('generateTreeStatus'),
      false,
      btn.innerHTML
    );
  });
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
