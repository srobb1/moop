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
    <table id="<?php echo $table_id; ?>" class="table table-sm table-striped table-hover results-table" style="width: 100%; max-width: none;">
        <thead>
            <tr>
                <?php if (!$is_uniquename_search): ?>
                <th data-col-name="type">Type</th>
                <th data-col-name="feature_id">Feature ID</th>
                <th data-col-name="name">Name</th>
                <th data-col-name="description">Description</th>
                <th data-col-name="annotation_source">Annotation Source</th>
                <th data-col-name="annotation_id">Annotation ID</th>
                <th data-col-name="annotation_description">Annotation Description</th>
                <?php else: ?>
                <th data-col-name="type">Type</th>
                <th data-col-name="feature_id">Feature ID</th>
                <th data-col-name="name">Name</th>
                <th data-col-name="description">Description</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
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
        rowHtml += '<td><input type="checkbox" class="row-select"></td>';
        rowHtml += '<td data-col-name="type">' + (row.type || '') + '</td>';
        rowHtml += '<td data-col-name="feature_id"><a href="/moop/tools/display/parent.php?organism=' + encodeURIComponent(row.species) + '&uniquename=' + encodeURIComponent(row.feature_uniquename) + '" target="_blank">' + (row.feature_uniquename || '') + '</a></td>';
        rowHtml += '<td data-col-name="name">' + (row.feature_name || '') + '</td>';
        rowHtml += '<td data-col-name="description">' + (row.feature_description || '') + '</td>';
        
        if (!isUniquenameSearch) {
            rowHtml += '<td data-col-name="annotation_source">' + (row.annotation_source || '') + '</td>';
            rowHtml += '<td data-col-name="annotation_id">' + (row.annotation_id || '') + '</td>';
            rowHtml += '<td data-col-name="annotation_description" class="wrap-text">' + (row.annotation_description || '') + '</td>';
        }
        
        rowHtml += '</tr>';
        $(tableId + ' tbody').append(rowHtml);
    });
    
    // Add select column header
    $(tableId + ' thead tr').prepend('<th data-col-name="select">Select</th>');
    
    // Create search row with column names
    let searchRowHtml = '<tr>';
    searchRowHtml += '<th data-col-name="select"><button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0;" class="btn btn_select_all" id="toggle-select-btn<?php echo $safe_organism; ?>"><span>Select All</span></button></th>';
    
    const columnNames = isUniquenameSearch 
        ? ['type', 'feature_id', 'name', 'description']
        : ['type', 'feature_id', 'name', 'description', 'annotation_source', 'annotation_id', 'annotation_description'];
    
    columnNames.forEach(colName => {
        searchRowHtml += '<th data-col-name="' + colName + '"><input style="text-align:center; border: solid 1px #808080; border-radius: 4px; width: 100%; max-width: 200px; padding: 8px; line-height: 1.2; height: auto; vertical-align: middle; display: flex; align-items: center; justify-content: center;" type="text" placeholder="Filter..." class="column-search" data-col-name="' + colName + '" /></th>';
    });
    searchRowHtml += '</tr>';
    
    $(tableId + ' thead').prepend(searchRowHtml);
    
    // Initialize DataTable
    const table = $(tableId).DataTable({
        dom: 'Brtlpi',
        pageLength: 25,
        stateSave: false,
        orderCellsTop: true,
        stripeClasses: ['odd', 'even'],
        buttons: [
            { extend: 'copy', text: 'Copy', exportOptions: { columns: ':visible' } },
            { extend: 'csv', text: 'CSV', exportOptions: { columns: ':visible' } },
            { extend: 'excel', text: 'Excel', exportOptions: { columns: ':visible' } },
            { extend: 'pdf', text: 'PDF', exportOptions: { columns: ':visible' } },
            { extend: 'print', text: 'Print', exportOptions: { columns: ':visible' } },
            { extend: 'colvis', text: 'Column Visibility' }
        ],
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        columnDefs: isUniquenameSearch ? [
            { width: "80px", targets: 0, orderable: false },
            { width: "80px", targets: 1 },
            { width: "180px", targets: 2 },
            { width: "100px", targets: 3 },
            { width: "200px", targets: 4 }
        ] : [
            { width: "80px", targets: 0, orderable: false },
            { width: "80px", targets: 1 },
            { width: "180px", targets: 2 },
            { width: "100px", targets: 3 },
            { width: "200px", targets: 4 },
            { width: "200px", targets: 5 },
            { width: "150px", targets: 6 },
            { width: "400px", targets: 7, className: "wrap-text" }
        ],
        colReorder: true,
        retrieve: true,
        initComplete: function() {
            const api = this.api();
            
            // Apply styling to table
            $(tableId).css({
                'font-size': '13px'
            });
            
            // Set up column filtering by column name
            $(tableId + ' thead tr:eq(0) th').each(function(thIndex) {
                const $th = $(this);
                const colName = $th.data('col-name');
                const $input = $th.find('input.column-search');
                
                if ($input.length && colName && colName !== 'select') {
                    // Find the actual column index by matching data-col-name in second header row
                    let columnIndex = -1;
                    $(tableId + ' thead tr:eq(1) th').each(function(idx) {
                        if ($(this).data('col-name') === colName) {
                            columnIndex = idx;
                            return false; // break
                        }
                    });
                    
                    if (columnIndex !== -1) {
                        $input.on('keyup change', function() {
                            const searchValue = this.value;
                            if (api.column(columnIndex).search() !== searchValue) {
                                api.column(columnIndex).search(searchValue).draw();
                            }
                        });
                    }
                }
            });
            
            // Select all functionality
            $('#toggle-select-btn<?php echo $safe_organism; ?>').on('click', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const allRows = api.rows().nodes();
                const checkedCount = $(allRows).find('.row-select:checked').length;
                const totalCount = $(allRows).find('.row-select').length;
                const allChecked = checkedCount === totalCount;
                
                if (allChecked) {
                    $(allRows).find('.row-select').prop('checked', false);
                    $btn.find('span').text('Select All');
                } else {
                    $(allRows).find('.row-select').prop('checked', true);
                    $btn.find('span').text('Deselect All');
                }
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
        
        <?php echo generateResultsTableHTML($organism_name, $is_uniquename_search); ?>
    </div>
    
    <script>
    <?php echo generateResultsTableJS($organism_name, $is_uniquename_search); ?>
    </script>
    <?php
    return ob_get_clean();
}
?>
