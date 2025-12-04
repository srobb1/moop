/**
 * ADMIN UTILITIES - Shared JavaScript for all admin pages
 * 
 * Handles generic functionality used across multiple admin pages:
 * - Bootstrap collapse toggle (replaces Bootstrap's API to avoid conflicts)
 * - Generic collapse styling
 */

(function() {
    // Add styles for collapse elements
    const style = document.createElement('style');
    style.textContent = `
        .collapse {
            display: none;
        }
        .collapse.show {
            display: block;
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
                        element.classList.toggle('show');
                    }
                }
            }, true);
        });
    });
})();
