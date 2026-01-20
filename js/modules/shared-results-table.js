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
 * @param {string} linkBasePath - Base path for feature links (e.g., 'tools/parent.php' or 'tools/parent.php')
 * @param {string} imageUrl - Optional URL to organism image thumbnail
 * @returns {string} HTML string for the table
 */
function createOrganismResultsTable(organism, results, sitePath, linkBasePath = 'tools/parent.php', imageUrl = '', searchKeywords = '') {
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
        
        // Create regex to match any term (case insensitive, including partial matches like HDAC in HDAC6)
        let highlighted = text;
        terms.forEach(term => {
            const regex = new RegExp(`${term}`, 'gi');
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
            <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                <table id="${tableId.substring(1)}" class="table table-sm table-striped table-hover results-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">
                                <div class="column-filter-container" data-column-index="0"></div>
                                <div>Select</div>
                            </th>
                            <th style="width: 200px;">
                                <div class="column-filter-container" data-column-index="1"></div>
                                <div>Species</div>
                            </th>
                            <th style="width: 120px;">
                                <div class="column-filter-container" data-column-index="2"></div>
                                <div>Type</div>
                            </th>
                            <th style="width: 280px;">
                                <div class="column-filter-container" data-column-index="3"></div>
                                <div>Feature ID</div>
                            </th>
                            <th style="width: 180px;">
                                <div class="column-filter-container" data-column-index="4"></div>
                                <div>Name</div>
                            </th>
                            <th style="width: 300px;">
                                <div class="column-filter-container" data-column-index="5"></div>
                                <div>Description</div>
                            </th>`;
    
    if (!isUniquenameSearch) {
        html += `
                            <th style="width: 300px;">
                                <div class="column-filter-container" data-column-index="6"></div>
                                <div>Annotation Source</div>
                            </th>
                            <th style="width: 220px;">
                                <div class="column-filter-container" data-column-index="7"></div>
                                <div>Annotation ID</div>
                            </th>
                            <th style="width: 500px;">
                                <div class="column-filter-container" data-column-index="8"></div>
                                <div>Annotation Description</div>
                            </th>`;
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
    // Populate filter containers with filter inputs and Select All button
    $(tableId + ' .column-filter-container').each(function(i) {
        const columnIndex = $(this).data('column-index');
        if (columnIndex === 0) {
            // Select All button for first column
            $(this).html('<button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0;" class="btn btn_select_all" id="toggle-select-btn' + selectId + '"><span>Select All</span></button>');
        } else {
            $(this).html('<input style="text-align:left; border: solid 1px #808080; border-radius: 4px; width: 100%;" type="text" placeholder="Filter..." class="column-search">');
        }
    });
    
    // Initialize DataTable
    const table = $(tableId).DataTable({
        dom: 'Brtlpi',
        pageLength: 25,
        stateSave: false,
        orderCellsTop: false,
        buttons: DataTableExportConfig.getSearchResultsButtons(),
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        fixedHeader: false,
        columnDefs: isUniquenameSearch ? [
            { targets: 0, orderable: false, className: "export-exclude" },  // Select - not sortable, exclude from export
            { targets: 1, visible: false, className: "export-only" }, // Species - hidden but included in exports
            { targets: 2 },  // Type
            { targets: 3 }, // Feature ID
            { targets: 4 }, // Name
            { targets: 5, className: "wrap-text" }  // Description (with wrapping)
        ] : [
            { targets: 0, orderable: false, className: "export-exclude" },  // Select - not sortable, exclude from export
            { targets: 1, visible: false, className: "export-only" }, // Species - hidden but included in exports
            { targets: 2 },  // Type
            { targets: 3 }, // Feature ID
            { targets: 4 }, // Name
            { targets: 5, className: "wrap-text" }, // Description (with wrapping)
            { targets: 6 }, // Annotation Source
            { targets: 7 }, // Annotation ID
            { targets: 8, className: "wrap-text" }  // Annotation Description (with wrapping)
        ],
        colReorder: true,
        retrieve: true,
        initComplete: function() {
            // Force remove sorting classes from Select column
            const selectHeader = $(tableId + ' thead th:first-child');
            selectHeader.removeClass('sorting sorting_asc sorting_desc');
            
            // Set up column filtering
            $(tableId + ' .column-filter-container').each(function() {
                const columnIndex = $(this).data('column-index');
                if (columnIndex !== undefined && columnIndex !== 0) {
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

/**
 * Groups search results by feature_uniquename
 * 
 * @param {Array} results - Array of result objects
 * @returns {Object} Grouped results: {feature_uniquename: {feature: obj, annotations: [array]}}
 */
function groupResultsByFeature(results) {
    const grouped = {};
    results.forEach(result => {
        const key = result.feature_uniquename;
        if (!grouped[key]) {
            grouped[key] = {
                feature: result,
                annotations: []
            };
        }
        grouped[key].annotations.push(result);
    });
    return grouped;
}

/**
 * Creates a simple results table showing unique features only
 * Similar to uniquename search view but with match counts
 * 
 * @param {string} organism - The organism identifier
 * @param {Array} results - Array of result objects
 * @param {string} sitePath - The site base path
 * @param {string} linkBasePath - Base path for feature links
 * @param {string} imageUrl - Optional URL to organism image thumbnail
 * @returns {string} HTML string for the simple table
 */
function createSimpleResultsTable(organism, results, sitePath, linkBasePath = 'tools/parent.php', imageUrl = '') {
    const tableId = 'simple_resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const selectId = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const genus = results[0]?.genus || '';
    const species = results[0]?.species || '';
    const commonName = results[0]?.common_name || '';
    
    const organismDisplay = `<em>${genus} ${species}</em>`;
    const commonNameDisplay = commonName ? ` (${commonName})` : '';
    
    const fallbackId = 'icon-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    
    let imageHtml = '';
    if (imageUrl) {
        imageHtml = `<img src="${imageUrl}" class="organism-thumbnail" style="height: 24px; width: 24px; margin-right: 8px; border-radius: 3px;" onerror="this.style.display='none'; document.getElementById('${fallbackId}').style.display='inline';" onload="document.getElementById('${fallbackId}').style.display='none';">
                     <i class="fa fa-dna" id="${fallbackId}" style="margin-right: 8px; display: none;"></i>`;
    } else {
        imageHtml = `<i class="fa fa-dna" style="margin-right: 8px;"></i>`;
    }
    
    const anchorId = 'results-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    
    // Group results by feature to get unique features and match counts
    const grouped = groupResultsByFeature(results);
    const uniqueFeatureCount = Object.keys(grouped).length;
    
    let html = `
        <div class="organism-results" id="${anchorId}">
            <h5>${imageHtml}${organismDisplay}${commonNameDisplay}
                <span class="badge bg-primary">${uniqueFeatureCount} result${uniqueFeatureCount !== 1 ? 's' : ''}</span>
                <span class="badge bg-info">${results.length} total annotation match${results.length !== 1 ? 'es' : ''}</span>
            </h5>
            <div class="mb-2 d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-primary toggle-view-btn" data-organism="${selectId}" data-view="simple" title="Toggle between simple and detailed view">
                    <i class="fa fa-expand"></i> Expand All Matches
                </button>
                <button type="button" class="btn btn-sm btn-outline-info info-icon-btn" data-info-id="info-${selectId}" title="Learn about search results" style="cursor: pointer; padding: 0.375rem 0.625rem; border: 1px solid #17a2b8;">
                    <i class="fa fa-info-circle"></i>
                </button>
            </div>
            <div class="info-box mb-3" id="info-${selectId}" style="display: none; background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 0.25rem; padding: 0.75rem 1.25rem;">
                <p><strong>Simple View:</strong> Displays a unique list of sequence IDs that have matches to your search terms. This gives you a clean overview of all sequences found without duplication.</p>
                
                <p><strong>What Gets Matched:</strong> Your search terms are matched against:</p>
                <ul style="margin-bottom: 0.5rem;">
                    <li><strong>Sequence Name:</strong> The name of the sequence</li>
                    <li><strong>Sequence Description:</strong> The description of the sequence</li>
                    <li><strong>Annotations:</strong> Matches from comparative analyses like BLAST, which compare your sequences against sequences in other organisms</li>
                </ul>
                
                <p style="margin-bottom: 0.5rem;"><strong>Why Different Results:</strong> A sequence may have a name and description different from what matched your search. This means one of its annotations (from analyses like BLAST) matched your search terms, not the sequence name or description itself.</p>
                
                <p style="margin-bottom: 0;"><strong>View All Matches:</strong> Click "Expand All Matches" to see all matching annotations for each sequence. This shows exactly which search terms were found and where they were found (name, description, or specific annotation).</p>
            </div>
            <div class="simple-view-container" data-organism="${selectId}">
                <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                    <table id="${tableId}" class="table table-sm table-striped table-hover results-table">
                        <thead>
                            <tr>
                                <th style="width: 120px;">
                                    <div class="column-filter-container" data-column-index="0"></div>
                                    <div>Select</div>
                                </th>
                                <th style="width: 200px;">
                                    <div class="column-filter-container" data-column-index="1"></div>
                                    <div>Species</div>
                                </th>
                                <th style="width: 120px;">
                                    <div class="column-filter-container" data-column-index="2"></div>
                                    <div>Type</div>
                                </th>
                                <th style="width: 280px;">
                                    <div class="column-filter-container" data-column-index="3"></div>
                                    <div>Feature ID</div>
                                </th>
                                <th style="width: 180px;">
                                    <div class="column-filter-container" data-column-index="4"></div>
                                    <div>Name</div>
                                </th>
                                <th style="width: 300px;">
                                    <div class="column-filter-container" data-column-index="5"></div>
                                    <div>Description</div>
                                </th>
                                <th style="width: 120px;">
                                    <div class="column-filter-container" data-column-index="6"></div>
                                    <div>Matches</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>`;
    
    // Get first result for each unique feature and include match count
    Object.entries(grouped).forEach(([uniquename, data]) => {
        const result = data.feature;
        const matchCount = data.annotations.length;
        const featureUrl = `${sitePath}/${linkBasePath}?organism=${encodeURIComponent(organism)}&uniquename=${encodeURIComponent(uniquename)}`;
        
        html += `
            <tr data-genome-accession="${encodeURIComponent(result.genome_accession || '')}">
                <td><input type="checkbox" class="row-select"></td>
                <td><em>${result.genus} ${result.species}</em><br><small class="text-muted">${result.common_name}</small></td>
                <td>${result.feature_type}</td>
                <td><a href="${featureUrl}" target="_blank">${result.feature_uniquename}</a></td>
                <td>${result.feature_name || '—'}</td>
                <td>${result.feature_description || '—'}</td>
                <td><span class="badge bg-secondary">${matchCount}</span></td>
            </tr>`;
    });
    
    html += `
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="expanded-view-container" data-organism="${selectId}" style="display: none;">
                <!-- Full table will be inserted here -->
            </div>
        </div>
    `;
    
    setTimeout(() => {
        initializeSimpleResultsTable(tableId, selectId, organism, results, sitePath);
    }, 100);
    
    return html;
}

/**
 * Initialize simple results table with DataTables and toggle functionality
 */
function initializeSimpleResultsTable(tableId, selectId, organism, results, sitePath) {
    // Populate filter containers
    $('#' + tableId + ' .column-filter-container').each(function(i) {
        const columnIndex = $(this).data('column-index');
        if (columnIndex === 0) {
            $(this).html('<button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0;" class="btn btn_select_all" id="toggle-select-btn' + selectId + '"><span>Select All</span></button>');
        } else {
            $(this).html('<input style="text-align:left; border: solid 1px #808080; border-radius: 4px; width: 100%;" type="text" placeholder="Filter..." class="column-search">');
        }
    });
    
    // Initialize DataTable
    const table = $('#' + tableId).DataTable({
        dom: 'Brtlpi',
        pageLength: 25,
        stateSave: false,
        orderCellsTop: false,
        buttons: DataTableExportConfig.getSearchResultsButtons(),
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        fixedHeader: false,
        columnDefs: [
            { targets: 0, orderable: false, className: "export-exclude" },
            { targets: 1, visible: false, className: "export-only" },
            { targets: 2 },
            { targets: 3 },
            { targets: 4 },
            { targets: 5, className: "wrap-text" },
            { targets: 6 }
        ],
        colReorder: true,
        retrieve: true,
        initComplete: function() {
            const selectHeader = $('#' + tableId + ' thead th:first-child');
            selectHeader.removeClass('sorting sorting_asc sorting_desc');
            
            $('#' + tableId + ' .column-filter-container').each(function() {
                const columnIndex = $(this).data('column-index');
                if (columnIndex !== undefined && columnIndex !== 0) {
                    $('input.column-search', this).on('keyup change', function() {
                        const dt = $('#' + tableId).DataTable();
                        if (dt.column(columnIndex).search() !== this.value) {
                            dt.column(columnIndex).search(this.value).draw();
                        }
                    });
                }
            });
        }
    });
    
    // Select/Deselect all handler
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
    $('#' + tableId).on('change', '.row-select', function () {
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
    
    // Setup info icon toggle
    $(document).on('click', `.info-icon-btn[data-info-id="info-${selectId}"]`, function(e) {
        e.preventDefault();
        const infoBox = $(`#info-${selectId}`);
        infoBox.slideToggle(200);
    });
    
    // Setup toggle button
    initializeViewToggle(organism, results, sitePath, selectId);
}

/**
 * Toggle between simple and expanded views
 */
function initializeViewToggle(organism, results, sitePath, selectId) {
    
    // Find the parent organism results div
    const containers = document.querySelectorAll(`[data-organism="${selectId}"]`);
    if (containers.length === 0) {
        return;
    }
    
    const parentDiv = containers[0].closest('.organism-results');
    if (!parentDiv) {
        return;
    }
    
    const toggleBtn = parentDiv.querySelector('.toggle-view-btn');
    const simpleContainer = parentDiv.querySelector(`.simple-view-container[data-organism="${selectId}"]`);
    const expandedContainer = parentDiv.querySelector(`.expanded-view-container[data-organism="${selectId}"]`);
    
    
    if (!toggleBtn || !simpleContainer || !expandedContainer) {
        return;
    }
    
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        
        
        if (expandedContainer.style.display === 'none') {
            // Switch to expanded view
            // Check if table already exists (by looking for actual table element)
            if (expandedContainer.querySelector('table') === null) {
                const tableId = 'resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
                const selectIdClean = organism.replace(/[^a-zA-Z0-9]/g, '_');
                const genus = results[0]?.genus || '';
                const species = results[0]?.species || '';
                const commonName = results[0]?.common_name || '';
                
                const organismDisplay = `<em>${genus} ${species}</em>`;
                const commonNameDisplay = commonName ? ` (${commonName})` : '';
                
                let html = `
                    <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                        <table id="${tableId}" class="table table-sm table-striped table-hover results-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">
                                        <div class="column-filter-container" data-column-index="0"></div>
                                        <div>Select</div>
                                    </th>
                                    <th style="width: 200px;">
                                        <div class="column-filter-container" data-column-index="1"></div>
                                        <div>Species</div>
                                    </th>
                                    <th style="width: 120px;">
                                        <div class="column-filter-container" data-column-index="2"></div>
                                        <div>Type</div>
                                    </th>
                                    <th style="width: 280px;">
                                        <div class="column-filter-container" data-column-index="3"></div>
                                        <div>Feature ID</div>
                                    </th>
                                    <th style="width: 180px;">
                                        <div class="column-filter-container" data-column-index="4"></div>
                                        <div>Name</div>
                                    </th>
                                    <th style="width: 300px;">
                                        <div class="column-filter-container" data-column-index="5"></div>
                                        <div>Description</div>
                                    </th>
                                    <th style="width: 300px;">
                                        <div class="column-filter-container" data-column-index="6"></div>
                                        <div>Annotation Source</div>
                                    </th>
                                    <th style="width: 220px;">
                                        <div class="column-filter-container" data-column-index="7"></div>
                                        <div>Annotation ID</div>
                                    </th>
                                    <th style="width: 500px;">
                                        <div class="column-filter-container" data-column-index="8"></div>
                                        <div>Annotation Description</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>`;
                
                // Add all result rows
                results.forEach(result => {
                    const featureUrl = `${sitePath}/tools/parent.php?organism=${encodeURIComponent(organism)}&uniquename=${encodeURIComponent(result.feature_uniquename)}`;
                    html += `
                        <tr data-genome-accession="${encodeURIComponent(result.genome_accession || '')}">
                            <td><input type="checkbox" class="row-select"></td>
                            <td><em>${result.genus} ${result.species}</em><br><small class="text-muted">${result.common_name}</small></td>
                            <td>${result.feature_type}</td>
                            <td><a href="${featureUrl}" target="_blank">${result.feature_uniquename}</a></td>
                            <td>${result.feature_name || ''}</td>
                            <td>${result.feature_description || ''}</td>
                            <td>${result.annotation_source_name || ''}</td>
                            <td>${result.annotation_accession || ''}</td>
                            <td>${result.annotation_description || ''}</td>
                        </tr>`;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>`;
                
                expandedContainer.innerHTML = html;
                
                // Now initialize the table after DOM insertion
                setTimeout(() => {
                    const $table = $('#' + tableId);
                    initializeResultsTable('#' + tableId, selectIdClean, false);
                }, 50);
            }
            simpleContainer.style.display = 'none';
            expandedContainer.style.display = 'block';
            toggleBtn.innerHTML = '<i class="fa fa-compress"></i> Show Simple View';
        } else {
            // Switch to simple view
            simpleContainer.style.display = 'block';
            expandedContainer.style.display = 'none';
            toggleBtn.innerHTML = '<i class="fa fa-expand"></i> Expand All Matches';
        }
    });
}
