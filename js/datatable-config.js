// DataTables Export/Download Configuration
// Centralized button and export settings for consistent behavior across all pages

const DataTableExportConfig = {
    // Button definitions (DRY)
    buttonDefs: {
        copy: {
            extend: 'copy',
            text: '<i class="far fa-copy"></i> Copy'
        },
        csv: {
            extend: 'csv',
            text: '<i class="fas fa-file-csv"></i> CSV'
        },
        excel: {
            extend: 'excel',
            text: '<i class="fas fa-file-excel"></i> Excel'
        },
        print: {
            extend: 'print',
            text: '<i class="fas fa-print"></i> Print / PDF'
        }
    },

    // Validate that at least one row is selected before export
    validateSelectedRows: function() {
        const checkedRows = $('input.row-select:checked');
        if (checkedRows.length === 0) {
            alert('Please select at least one row to export.\n\nClick "Select All" to select all rows, or check individual row checkboxes.');
            return false;
        }
        return true;
    },

    // Create button with export options
    createButton: function(buttonType, selectedRowsOnly = false) {
        const exportOptions = {
            columns: ':visible, .export-only'
        };
        
        if (selectedRowsOnly) {
            exportOptions.rows = function(idx, data, node) {
                return $(node).find('input.row-select:checked').length > 0;
            };
        }
        
        return {
            extend: buttonType,
            ...this.buttonDefs[buttonType],
            className: 'btn btn-sm btn-secondary',
            exportOptions: exportOptions,
            init: selectedRowsOnly ? function(dt, node, config) {
                $(node).on('click.dt', function(e) {
                    if ($('input.row-select:checked').length === 0) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        DataTableExportConfig.validateSelectedRows();
                        return false;
                    }
                });
            } : undefined
        };
    },

    // Get buttons for annotation tables (without column visibility)
    getAnnotationButtons: function() {
        return [
            this.createButton('copy'),
            this.createButton('csv'),
            this.createButton('excel'),
            this.createButton('print')
        ];
    },

    // Get buttons for search results tables (with column visibility)
    getSearchResultsButtons: function() {
        return [
            this.createButton('copy', true),
            this.createButton('csv', true),
            this.createButton('excel', true),
            this.createButton('print', true),
            'colvis'
        ];
    },
    
    // Legacy support - use getAnnotationButtons instead
    get buttons() {
        return this.getAnnotationButtons();
    },
    
    // Standard DataTable options
    defaultOptions: {
        dom: 'Bfrtlip',
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[4, 'asc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        },
        columnDefs: [
            { targets: '_all', className: 'dt-body-left' },
            { targets: [0, 1, 2, 3], visible: false, className: 'export-only' }
        ]
    },
    
    // Helper function to initialize DataTables with standard config
    initialize: function(tableSelector, customOptions = {}) {
        return $(tableSelector).DataTable({
            buttons: this.buttons,
            ...this.defaultOptions,
            ...customOptions
        });
    },
    
    // Helper function to reinitialize existing DataTables
    reinitialize: function(tableSelector, customOptions = {}) {
        var $table = $(tableSelector);
        
        if ($.fn.DataTable.isDataTable(tableSelector)) {
            $table.DataTable().destroy();
        }
        
        return this.initialize(tableSelector, customOptions);
    }
};

