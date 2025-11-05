// Parent Feature Display JavaScript

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

