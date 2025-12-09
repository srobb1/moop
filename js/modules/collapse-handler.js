/**
 * COLLAPSE HANDLER - Reusable collapse toggle utility
 * 
 * Handles manual collapse toggle for elements with data-bs-toggle="collapse"
 * Works around Bootstrap's native collapse handler conflicts
 * 
 * Usage:
 * - Include this script on any page that uses collapse sections
 * - Elements with .collapse-section class and data-bs-target will automatically work
 * - Also handles any element with data-bs-toggle="collapse" and data-bs-target
 */

(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Handle collapse triggers (both .collapse-section and any [data-bs-toggle="collapse"])
        var triggers = document.querySelectorAll('[data-bs-toggle="collapse"]');
        
        triggers.forEach(function(trigger) {
            if (trigger.hasAttribute('data-bs-target')) {
                // Remove data-bs-toggle to prevent Bootstrap from handling it
                trigger.removeAttribute('data-bs-toggle');
                
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    var target = this.getAttribute('data-bs-target');
                    if (target) {
                        var element = document.querySelector(target);
                        if (element) {
                            element.classList.toggle('show');
                            
                            // Toggle the icon if present
                            var icon = this.querySelector('.toggle-icon, .fa-chevron-down, .fa-minus, .fa-plus');
                            if (icon) {
                                if (element.classList.contains('show')) {
                                    icon.classList.remove('fa-plus');
                                    icon.classList.add('fa-minus');
                                } else {
                                    icon.classList.remove('fa-minus');
                                    icon.classList.add('fa-plus');
                                }
                            }
                        }
                    }
                }, true);
            }
        });
    });
})();
