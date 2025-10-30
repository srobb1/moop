<?php
/**
 * Reusable Results Table Widget
 * Generates HTML and JavaScript for search results tables with DataTables
 */

/**
 * Generate the HTML structure for a results table
 * @param string $organism_name - The organism name (used for table ID)
 * @param bool $is_uniquename_search - Whether this is a uniquename search (fewer columns)
 * @return string HTML for the table structure
 */
function generateResultsTableHTML($organism_name, $is_uniquename_search = false) {
    $table_id = "resultsTable_" . str_replace(' ', '_', $organism_name);
    
    ob_start();
    ?>
    <div class="table-container" style="width: 100%; overflow-x: auto; margin-top: 20px;">
        <table id="<?php echo $table_id; ?>" class="display nowrap results-table" style="width:100%; font-size: 14px;">
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Species</th>
                    <th>Type</th>
                    <th>Feature ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <?php if (!$is_uniquename_search): ?>
                    <th>Annotation Source</th>
                    <th>Annotation ID</th>
                    <th>Annotation Description</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generate the JavaScript for initializing a DataTable
 * @param string $organism_name - The organism name (used for table ID)
 * @param bool $is_uniquename_search - Whether this is a uniquename search (fewer columns)
 * @return string JavaScript code for table initialization
 */
function generateResultsTableJS($organism_name, $is_uniquename_search = false) {
    $table_id = "resultsTable_" . str_replace(' ', '_', $organism_name);
    $safe_organism = str_replace(' ', '_', $organism_name);
    
    ob_start();
    ?>
function initializeResultTable_<?php echo $safe_organism; ?>(data) {
    const tableId = '#<?php echo $table_id; ?>';
    const isUniquenameSearch = <?php echo $is_uniquename_search ? 'true' : 'false'; ?>;
    
    // Destroy existing table if it exists
    if ($.fn.DataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }
    
    // Clear existing tbody
    $(tableId + ' tbody').empty();
    
    // Add data rows
    data.forEach(row => {
        let rowHtml = '<tr>';
        rowHtml += '<td><input type="checkbox" class="row-select" data-row=\'' + JSON.stringify(row) + '\'></td>';
        rowHtml += '<td>' + (row.species || '') + '</td>';
        rowHtml += '<td>' + (row.type || '') + '</td>';
        rowHtml += '<td><a href="/moop/tools/display/parent.php?id=' + encodeURIComponent(row.feature_uniquename) + '">' + (row.feature_uniquename || '') + '</a></td>';
        rowHtml += '<td>' + (row.feature_name || '') + '</td>';
        rowHtml += '<td>' + (row.feature_description || '') + '</td>';
        
        if (!isUniquenameSearch) {
            rowHtml += '<td>' + (row.annotation_source || '') + '</td>';
            rowHtml += '<td>' + (row.annotation_id || '') + '</td>';
            rowHtml += '<td>' + (row.annotation_description || '') + '</td>';
        }
        
        rowHtml += '</tr>';
        $(tableId + ' tbody').append(rowHtml);
    });
    
    // Initialize DataTable
    const table = $(tableId).DataTable({
        dom: 'Bfrtip',
        buttons: [
            { 
                extend: 'copy', 
                text: 'Copy',
                exportOptions: { 
                    columns: function(idx, data, node) {
                        return idx !== 0; // Exclude Select column
                    }
                }
            },
            { 
                extend: 'csv', 
                text: 'CSV',
                exportOptions: { 
                    columns: function(idx, data, node) {
                        return idx !== 0; // Exclude Select column
                    }
                }
            },
            { 
                extend: 'excel', 
                text: 'Excel',
                exportOptions: { 
                    columns: function(idx, data, node) {
                        return idx !== 0; // Exclude Select column
                    }
                }
            },
            { 
                extend: 'pdf', 
                text: 'PDF',
                exportOptions: { 
                    columns: function(idx, data, node) {
                        return idx !== 0; // Exclude Select column
                    }
                }
            },
            { 
                extend: 'print', 
                text: 'Print',
                exportOptions: { 
                    columns: function(idx, data, node) {
                        return idx !== 0; // Exclude Select column
                    }
                }
            },
            { extend: 'colvis', text: 'Column Visibility' }
        ],
        scrollX: true,
        scrollY: '500px',
        scrollCollapse: false,
        autoWidth: false,
        fixedHeader: false,
        columnDefs: isUniquenameSearch ? [
            { width: "80px", targets: 0, orderable: false },  // Select - not sortable
            { width: "150px", targets: 1, visible: false }, // Species - hidden but included in exports
            { width: "80px", targets: 2 },  // Type
            { width: "180px", targets: 3 }, // Feature ID
            { width: "100px", targets: 4 }, // Name
            { width: "200px", targets: 5 }  // Description
        ] : [
            { width: "80px", targets: 0, orderable: false },  // Select - not sortable
            { width: "150px", targets: 1, visible: false }, // Species - hidden but included in exports
            { width: "80px", targets: 2 },  // Type
            { width: "180px", targets: 3 }, // Feature ID
            { width: "100px", targets: 4 }, // Name
            { width: "200px", targets: 5 }, // Description
            { width: "200px", targets: 6 }, // Annotation Source
            { width: "150px", targets: 7 }, // Annotation ID
            { width: "250px", targets: 8, className: "wrap-text" }  // Annotation Description
        ],
        paging: true,
        pageLength: 50,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[3, 'asc']], // Sort by Feature ID by default
        initComplete: function() {
            const api = this.api();
            
            // Add filter inputs to header
            $(tableId + ' thead tr:eq(0) th').each(function(i) {
                if (i === 0) {
                    // Select All button for first column
                    $(this).html('<button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 5px;" class="btn btn_select_all" id="toggle-select-btn<?php echo $safe_organism; ?>"><span>Select All</span></button>');
                } else {
                    const title = $(this).text();
                    $(this).html('<input type="text" placeholder="Filter..." style="width: 100%; text-align: center; border: solid 1px #808080; border-radius: 4px; padding: 5px; box-sizing: border-box;" class="column-filter" data-column="' + i + '" />');
                }
            });
            
            // Set up column filtering
            $(tableId + ' .column-filter').on('keyup change', function() {
                const colIdx = parseInt($(this).data('column'));
                if (!isNaN(colIdx)) {
                    api.column(colIdx).search(this.value).draw();
                }
            });
            
            // Select all functionality
            $('#toggle-select-btn<?php echo $safe_organism; ?>').on('click', function() {
                const allChecked = $(tableId + ' tbody .row-select:checked').length === $(tableId + ' tbody .row-select').length;
                $(tableId + ' tbody .row-select').prop('checked', !allChecked);
                $(this).find('span').text(allChecked ? 'Select All' : 'Deselect All');
            });
        }
    });
    
    return table;
}
<?php
    return ob_get_clean();
}

/**
 * Generate complete results section with table and download options
 * @param string $organism_name - The organism name
 * @param bool $is_uniquename_search - Whether this is a uniquename search
 * @param string $organism_link - Optional link to organism page
 * @param string $organism_image - Optional path to organism image
 * @return string Complete HTML section
 */
function generateResultsSection($organism_name, $is_uniquename_search = false, $organism_link = '', $organism_image = '') {
    ob_start();
    ?>
    <div class="results-section" id="results_<?php echo str_replace(' ', '_', $organism_name); ?>" style="margin-bottom: 40px;">
        <h3 style="margin-bottom: 15px;">
            <?php if ($organism_image): ?>
            <img src="<?php echo htmlspecialchars($organism_image); ?>" alt="<?php echo htmlspecialchars($organism_name); ?>" style="height: 40px; vertical-align: middle; margin-right: 10px;">
            <?php endif; ?>
            <em><?php echo htmlspecialchars(str_replace('_', ' ', $organism_name)); ?></em>
            <?php if ($organism_link): ?>
            <a href="<?php echo htmlspecialchars($organism_link); ?>" style="font-size: 14px; margin-left: 10px;">Read more</a>
            <?php endif; ?>
        </h3>
        
        <div style="margin-bottom: 10px;">
            <small style="color: #666;">
                <strong>Download selected results:</strong> Select rows using checkboxes, then click a download button above the table (Copy, CSV, Excel, PDF, Print). 
                Use "Column Visibility" to show/hide columns.
            </small>
        </div>
        
        <?php echo generateResultsTableHTML($organism_name, $is_uniquename_search); ?>
    </div>
    
    <script>
    <?php echo generateResultsTableJS($organism_name, $is_uniquename_search); ?>
    </script>
    <?php
    return ob_get_clean();
}
?>
