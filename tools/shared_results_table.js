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
 * @param {string} linkBasePath - Base path for feature links (e.g., 'tools/parent_display.php' or 'tools/parent_display.php')
 * @param {string} imageUrl - Optional URL to organism image thumbnail
 * @returns {string} HTML string for the table
 */
function createOrganismResultsTable(organism, results, sitePath, linkBasePath = 'tools/parent_display.php', imageUrl = '', searchKeywords = '') {
    const tableId = '#resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const selectId = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const genus = results[0]?.genus || '';
    const species = results[0]?.species || '';
    const commonName = results[0]?.common_name || '';
    
    const organismDisplay = `<em>${genus} ${species}</em>`;
    const commonNameDisplay = commonName ? ` (${commonName})` : '';
    
    const fallbackId = 'icon-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    
    // Helper function to highlight search terms in text
    const highlightSearchTerms = (text, keywords) => {
        if (!keywords || !text) return text;
        
        // Split keywords into individual terms (min 3 chars)
        const terms = keywords.trim().split(/\s+/).filter(t => t.length >= 3);
        if (terms.length === 0) return text;
        
        // Create regex to match any term (case insensitive, whole word)
        let highlighted = text;
        terms.forEach(term => {
            const regex = new RegExp(`\\b${term}\\b`, 'gi');
            highlighted = highlighted.replace(regex, `<strong style="background-color: #fff3cd; font-weight: bold;">$&</strong>`);
        });
        return highlighted;
    };
    
    let imageHtml = '';
    if (imageUrl) {
        imageHtml = `<img src="${imageUrl}" class="organism-thumbnail" style="height: 24px; width: 24px; margin-right: 8px; border-radius: 3px;" onerror="this.style.display='none'; document.getElementById('${fallbackId}').style.display='inline';" onload="document.getElementById('${fallbackId}').style.display='none';">
                     <i class="fa fa-dna" id="${fallbackId}" style="margin-right: 8px; display: none;"></i>`;
    } else {
        imageHtml = `<i class="fa fa-dna" style="margin-right: 8px;"></i>`;
    }
    
    const anchorId = 'results-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    
    // Check if this is a uniquename search (no annotation columns)
    // Use the uniquename_search flag from the result data
    const isUniquenameSearch = results[0]?.uniquename_search ?? false;
    
    let html = `
        <div class="organism-results" id="${anchorId}">
            <h5>${imageHtml}${organismDisplay}${commonNameDisplay}
                <span class="badge bg-primary">${results.length} result${results.length !== 1 ? 's' : ''}</span>
            </h5>
            <div class="table-responsive" style="overflow-x: auto; width: 100%; min-width: 100%;">
                <table id="${tableId.substring(1)}" class="table table-sm table-striped table-hover results-table" style="width: auto; min-width: 100%; font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="width: 120px;"></th>
                            <th style="width: 200px;" data-column-index="1"></th>
                            <th style="width: 120px;" data-column-index="2"></th>
                            <th style="width: 280px;" data-column-index="3"></th>
                            <th style="width: 180px;" data-column-index="4"></th>
                            <th style="width: 300px;" data-column-index="5"></th>`;
    
    if (!isUniquenameSearch) {
        html += `
                            <th style="width: 300px;" data-column-index="6"></th>
                            <th style="width: 220px;" data-column-index="7"></th>
                            <th style="width: 500px;" data-column-index="8"></th>`;
    }
    
    html += `
                        </tr>
                        <tr>
                            <th style="width: 120px;">Select</th>
                            <th style="width: 200px;">Species</th>
                            <th style="width: 120px;">Type</th>
                            <th style="width: 280px;">Feature ID</th>
                            <th style="width: 180px;">Name</th>
                            <th style="width: 300px;">Description</th>`;
    
    if (!isUniquenameSearch) {
        html += `
                            <th style="width: 300px;">Annotation Source</th>
                            <th style="width: 220px;">Annotation ID</th>
                            <th style="width: 500px;">Annotation Description</th>`;
    }
    
    html += `
                        </tr>
                    </thead>
                    <tbody>`;
    
    results.forEach(result => {
        let featureUrl = `${sitePath}/${linkBasePath}?organism=${encodeURIComponent(organism)}&uniquename=${encodeURIComponent(result.feature_uniquename)}`;
        
        // Add multi_search context if available (from pageContext global variable)
        if (typeof pageContext !== 'undefined' && pageContext.multi_search && Array.isArray(pageContext.multi_search)) {
            pageContext.multi_search.forEach(org => {
                featureUrl += '&multi_search[]=' + encodeURIComponent(org);
            });
        }
        
        html += `
            <tr data-genome-accession="${encodeURIComponent(result.genome_accession || '')}">
                <td><input type="checkbox" class="row-select"></td>
                <td><em>${result.genus} ${result.species}</em><br><small class="text-muted">${result.common_name}</small></td>
                <td>${result.feature_type}</td>
                <td><a href="${featureUrl}" target="_blank">${result.feature_uniquename}</a></td>
                <td>${highlightSearchTerms(result.feature_name, searchKeywords)}</td>
                <td>${highlightSearchTerms(result.feature_description, searchKeywords)}</td>`;
        
        if (!isUniquenameSearch) {
            html += `
                <td>${result.annotation_source_name}</td>
                <td>${result.annotation_accession}</td>
                <td>${highlightSearchTerms(result.annotation_description, searchKeywords)}</td>`;
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
        buttons: DataTableExportConfig.getSearchResultsButtons(),
        scrollX: true,
        scrollCollapse: false,
        autoWidth: false,
        fixedHeader: false,
        columnDefs: isUniquenameSearch ? [
            { width: "120px", targets: 0, orderable: false },  // Select - not sortable
            { width: "200px", targets: 1, visible: false }, // Species - hidden but included in exports
            { width: "120px", targets: 2 },  // Type
            { width: "280px", targets: 3 }, // Feature ID
            { width: "180px", targets: 4 }, // Name
            { width: "300px", targets: 5, className: "wrap-text" }  // Description (with wrapping)
        ] : [
            { width: "120px", targets: 0, orderable: false },  // Select - not sortable
            { width: "200px", targets: 1, visible: false }, // Species - hidden but included in exports
            { width: "120px", targets: 2 },  // Type
            { width: "280px", targets: 3 }, // Feature ID
            { width: "180px", targets: 4 }, // Name
            { width: "300px", targets: 5, className: "wrap-text" }, // Description (with wrapping)
            { width: "300px", targets: 6 }, // Annotation Source
            { width: "220px", targets: 7 }, // Annotation ID
            { width: "500px", targets: 8, className: "wrap-text" }  // Annotation Description (with wrapping)
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
                        const dt = $(tableId).DataTable();
                        if (dt.column(columnIndex).search() !== this.value) {
                            dt.column(columnIndex).search(this.value).draw();
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
