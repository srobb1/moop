/**
 * Shared Results Table Functions
 * Used by organism_display.php and groups_display.php
 * 
 * This file provides common functionality for creating and managing
 * DataTables search results across different display pages.
 */

/**
 * Creates an organism results table HTML structure
 * 
 * @param {string} organism - The organism identifier
 * @param {Array} results - Array of result objects
 * @param {string} sitePath - The site base path
 * @param {string} linkBasePath - Base path for feature links (e.g., 'tools/display/parent.php' or 'tools/search/parent.php')
 * @returns {string} HTML string for the table
 */
function createOrganismResultsTable(organism, results, sitePath, linkBasePath = 'tools/display/parent.php') {
    const tableId = '#resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const selectId = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const genus = results[0]?.genus || '';
    const species = results[0]?.species || '';
    const commonName = results[0]?.common_name || '';
    
    const organismDisplay = `<em>${genus} ${species}</em>`;
    const commonNameDisplay = commonName ? ` (${commonName})` : '';
    
    const imagePath = sitePath + '/images/';
    const imageFile = organism + '.jpg';
    const fallbackId = 'icon-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const imageHtml = `<img src="${imagePath}${imageFile}" class="organism-thumbnail" onerror="this.style.display='none'; document.getElementById('${fallbackId}').style.display='inline';" onload="document.getElementById('${fallbackId}').style.display='none';" style="margin-right: 8px;">
                       <i class="fa fa-dna" id="${fallbackId}" style="margin-right: 8px; display: none;"></i>`;
    
    const anchorId = 'results-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    
    // Check if this is a uniquename search (no annotation columns)
    const isUniquenameSearch = !results[0]?.annotation_source;
    
    let html = `
        <div class="organism-results" id="${anchorId}">
            <h5>${imageHtml}${organismDisplay}${commonNameDisplay}
                <span class="badge bg-primary">${results.length} result${results.length !== 1 ? 's' : ''}</span>
            </h5>
            <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                <table id="${tableId.substring(1)}" class="table table-sm table-striped table-hover results-table" style="width:100%; font-size: 14px;">
                    <thead>
                        <tr>
                            <th></th>
                            <th data-column-index="1"></th>
                            <th data-column-index="2"></th>
                            <th data-column-index="3"></th>
                            <th data-column-index="4"></th>
                            <th data-column-index="5"></th>`;
    
    if (!isUniquenameSearch) {
        html += `
                            <th data-column-index="6"></th>
                            <th data-column-index="7"></th>
                            <th data-column-index="8"></th>`;
    }
    
    html += `
                        </tr>
                        <tr>
                            <th style="width: 80px;">Select</th>
                            <th style="width: 150px;">Species</th>
                            <th style="width: 80px;">Type</th>
                            <th style="width: 180px;">Feature ID</th>
                            <th style="width: 100px;">Name</th>
                            <th style="width: 200px;">Description</th>`;
    
    if (!isUniquenameSearch) {
        html += `
                            <th style="width: 200px;">Annotation Source</th>
                            <th style="width: 150px;">Annotation ID</th>
                            <th style="width: 400px;">Annotation Description</th>`;
    }
    
    html += `
                        </tr>
                    </thead>
                    <tbody>`;
    
    results.forEach(result => {
        html += `
            <tr>
                <td><input type="checkbox" class="row-select"></td>
                <td><em>${result.genus} ${result.species}</em><br><small class="text-muted">${result.common_name}</small></td>
                <td>${result.feature_type}</td>
                <td><a href="${sitePath}/${linkBasePath}?organism=${encodeURIComponent(organism)}&uniquename=${encodeURIComponent(result.feature_uniquename)}" target="_blank">${result.feature_uniquename}</a></td>
                <td>${result.feature_name}</td>
                <td>${result.feature_description}</td>`;
        
        if (!isUniquenameSearch) {
            html += `
                <td>${result.annotation_source}</td>
                <td>${result.annotation_accession}</td>
                <td>${result.annotation_description}</td>`;
        }
        
        html += `
            </tr>`;
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    setTimeout(() => initializeResultsTable(tableId, selectId, isUniquenameSearch), 100);
    
    return html;
}

/**
 * Initializes a DataTable with export functionality and column filtering
 * 
 * @param {string} tableId - jQuery selector for the table (e.g., '#resultsTable_organism')
 * @param {string} selectId - Unique identifier for the select all button
 * @param {boolean} isUniquenameSearch - Whether this is a uniquename-only search (no annotation columns)
 */
function initializeResultsTable(tableId, selectId, isUniquenameSearch) {
    // Populate the first row (filter row) with Select All button and filter inputs
    $(tableId + ' thead tr:eq(0) th').each(function(i) {
        const columnIndex = $(this).data('column-index');
        if (i === 0) {
            // Select All button for first column
            $(this).html('<button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0;" class="btn btn_select_all" id="toggle-select-btn' + selectId + '"><span>Select All</span></button>');
        } else if (columnIndex !== undefined) {
            $(this).html('<input style="text-align:center; border: solid 1px #808080; border-radius: 4px; width: 100%; max-width: 200px;" type="text" placeholder="Filter..." class="column-search">');
        }
    });
    
    // Initialize DataTable
    const table = $(tableId).DataTable({
        dom: 'Brtlpi',
        pageLength: 25,
        stateSave: false,
        orderCellsTop: false,
        buttons: [
            {
                extend: 'copy',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: ':visible'
                }
            },
            {
                extend: 'csv',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'excel',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'pdf',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'print',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            'colvis'
        ],
        scrollX: false,
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
            { width: "400px", targets: 8, className: "wrap-text" }  // Annotation Description (with wrapping)
        ],
        colReorder: true,
        retrieve: true,
        initComplete: function() {
            // Force remove sorting classes from Select column
            const selectHeader = $(tableId + ' thead tr:nth-child(2) th:first-child');
            selectHeader.removeClass('sorting sorting_asc sorting_desc');
            
            // Set up column filtering
            $(tableId + ' thead tr:eq(0) th').each(function(i) {
                const columnIndex = $(this).data('column-index');
                if (columnIndex !== undefined) {
                    $('input.column-search', this).on('keyup change', function() {
                        if (table.column(columnIndex).search() !== this.value) {
                            table.column(columnIndex).search(this.value).draw();
                        }
                    });
                }
            });
        }
    });
    
    // Select/Deselect all handler - works across ALL pages
    $('#toggle-select-btn' + selectId).on('click', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const allRows = table.rows().nodes();
        const checkedCount = $(allRows).find('input.row-select:checked').length;
        const totalCount = $(allRows).find('input.row-select').length;
        const allChecked = checkedCount === totalCount;
        
        if (allChecked) {
            $(allRows).find('input.row-select').prop('checked', false);
            $btn.find('span').text('Select All');
        } else {
            $(allRows).find('input.row-select').prop('checked', true);
            $btn.find('span').text('Deselect All');
        }
    });
    
    // Individual checkbox handler
    $(tableId).on('change', '.row-select', function () {
        const allRows = table.rows().nodes();
        const totalCheckboxes = $(allRows).find('input.row-select').length;
        const checkedCheckboxes = $(allRows).find('input.row-select:checked').length;
        const $btn = $('#toggle-select-btn' + selectId);
        
        if (checkedCheckboxes === totalCheckboxes) {
            $btn.find('span').text('Deselect All');
        } else {
            $btn.find('span').text('Select All');
        }
    });
}
