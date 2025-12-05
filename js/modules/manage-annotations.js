/**
 * Manage Annotations - Page-Specific Functionality
 */

/**
 * Manual Collapse Handler with Chevron Rotation
 * Handles collapse toggles and rotates chevron icons
 */
(function() {
    // Add styles for collapse behavior
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
        .collapse.show ~ .card-header .fa-chevron-down,
        [data-bs-target].collapsed .fa-chevron-down {
            transform: rotate(-180deg);
        }
    `;
    document.head.appendChild(style);
    
    // Add toggle functionality
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
                        element.classList.toggle('show');
                        
                        // Rotate chevron
                        const chevron = this.querySelector('.fa-chevron-down');
                        if (chevron) {
                            chevron.style.transform = element.classList.contains('show') 
                                ? 'rotate(-180deg)' 
                                : 'rotate(0deg)';
                        }
                    }
                }
            }, true);
        });
    });
})();

document.addEventListener('DOMContentLoaded', function() {
  // Setup DataTables if present
  const annotationsTables = document.querySelectorAll('.annotations-table');
  annotationsTables.forEach(table => {
    if (typeof DataTable !== 'undefined') {
      new DataTable(table);
    }
  });
});
