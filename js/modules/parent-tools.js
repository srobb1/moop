// Parent Feature Display JavaScript

$(document).ready(function() {
    // Initialize DataTables for annotation tables with export buttons
    $('table[id^="annotTable_"]').each(function() {
        var tableId = '#' + $(this).attr('id');
        DataTableExportConfig.reinitialize(tableId);
    });

    // Initialize Bootstrap 5 tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Manual collapse toggle for collapse-section spans
    // Similar to admin-utilities.js approach
    document.querySelectorAll('.collapse-section').forEach(function(trigger) {
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
                    
                    // Toggle the icon
                    var icon = this.querySelector('.toggle-icon');
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
    });

    // Toggle icons on collapse (Bootstrap 5)
    $('.collapse').on('show.bs.collapse', function(e) {
        if (e.target !== this) return;
        $('[data-bs-target="#' + this.id + '"] .toggle-icon')
            .removeClass('fa-plus')
            .addClass('fa-minus');
    });

    $('.collapse').on('hide.bs.collapse', function(e) {
        if (e.target !== this) return;
        $('[data-bs-target="#' + this.id + '"] .toggle-icon')
            .removeClass('fa-minus')
            .addClass('fa-plus');
    });
});

