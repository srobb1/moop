/**
 * Reusable Annotation Search Module
 * Consolidates shared search logic for annotation searches across organisms
 * 
 * Usage:
 *   const search = new AnnotationSearch({
 *     formSelector: '#groupSearchForm',
 *     organismsVar: groupOrganisms,
 *     totalVar: totalOrganisms,
 *     hideSections: ['#groupDescription'],
 *     scrollToResults: false,
 *     extraAjaxParams: {group: groupName},
 *     noReadMoreButton: false
 *   });
 *   search.init();
 */

class AnnotationSearch {
    constructor(config) {
        this.config = {
            formSelector: config.formSelector,
            organismsVar: config.organismsVar || [],
            totalVar: config.totalVar || config.organismsVar.length,
            hideSections: config.hideSections || [],
            scrollToResults: config.scrollToResults || false,
            extraAjaxParams: config.extraAjaxParams || {},
            noReadMoreButton: config.noReadMoreButton || false,
            sitePath: config.sitePath || window.sitePath || '/moop'
        };
        
        this.allResults = [];
        this.searchedOrganisms = 0;
        this.currentKeywords = '';  // Store keywords for highlighting results
    }
    
    init() {
        $(this.config.formSelector).on('submit', (e) => {
            e.preventDefault();
            this.handleSearch();
        });
        
        // Add advanced filter button if not already present
        this.addFilterButton();
    }
    
    /**
     * Add advanced search filter button and clear button to form
     */
    addFilterButton() {
        const form = $(this.config.formSelector);
        if (form.find('.search-controls').length === 0) {
            const submitBtn = form.find('button[type="submit"]');
            
            // Create a new button group container
            const buttonGroup = `
                <div class="search-controls d-flex gap-2 align-items-center ms-2">
                    <!-- Filter button with icon only -->
                    <button type="button" class="btn btn-icon btn-advanced-filter" 
                            title="Advanced Filtering" 
                            data-bs-toggle="tooltip" data-bs-placement="bottom">
                        <i class="fa fa-sliders-h"></i>
                    </button>
                    
                    <!-- Clear filters button (hidden initially) -->
                    <button type="button" class="btn btn-sm btn-link text-danger btn-clear-filters" 
                            style="display: none;">
                        <i class="fa fa-times-circle"></i> Clear Filters
                    </button>
                </div>
            `;
            
            submitBtn.after(buttonGroup);
            
            // Attach event handlers
            form.find('.btn-advanced-filter').on('click', () => this.showFilterModal());
            form.find('.btn-clear-filters').on('click', () => this.clearFilters());
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(form.find('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }
    }
    
    /**
     * Clear all applied filters
     */
    clearFilters() {
        this.selectedSources = null;
        this.updateFilterButtonState();
        alert('Filters cleared');
    }
    
    /**
     * Update filter button visual state based on applied filters
     */
    updateFilterButtonState() {
        const form = $(this.config.formSelector);
        const filterBtn = form.find('.btn-advanced-filter');
        const clearBtn = form.find('.btn-clear-filters');
        const submitBtn = form.find('button[type="submit"]');
        
        if (this.selectedSources && this.selectedSources.length > 0) {
            // Filters applied
            filterBtn.addClass('filtered');
            filterBtn.html('<i class="fa fa-sliders-h"></i> <span class="badge badge-sm bg-primary text-white">' + this.selectedSources.length + '</span>');
            clearBtn.show();
            submitBtn.addClass('btn-search-active');
        } else {
            // No filters
            filterBtn.removeClass('filtered');
            filterBtn.html('<i class="fa fa-sliders-h"></i>');
            clearBtn.hide();
            submitBtn.removeClass('btn-search-active');
        }
    }
    
    /**
     * Show advanced search filter modal
     */
    showFilterModal() {
        // Get first organism for filter modal (they're all the same database)
        const organism = this.config.organismsVar[0];
        
        const filter = new AdvancedSearchFilter({
            organism: organism,
            sitePath: this.config.sitePath,
            onApply: (selectedSources) => {
                this.selectedSources = selectedSources;
                // Update button states
                this.updateFilterButtonState();
                // Show confirmation toast/message
                const message = selectedSources.length === 0 
                    ? 'No sources selected' 
                    : `Filtering to ${selectedSources.length} source(s)`;
                console.log('Filter applied:', message);
                alert(message);
            }
        });
        
        filter.show();
    }
    
    handleSearch() {
        const keywords = $('#searchKeywords').val().trim();
        this.currentKeywords = keywords;  // Store for highlighting results
        
        // Validate input
        if (keywords.length < 3) {
            alert('Please enter at least 3 characters to search');
            return;
        }
        
        // Check for quoted search
        const quotedSearch = /^".+"$/.test(keywords);
        
        // Calculate actual search terms (for multi-term searches, filter out < 3 char terms)
        let searchExplanation = '';
        if (!quotedSearch) {
            const terms = keywords.trim().split(/\s+/).filter(t => t.length >= 3);
            const shortTerms = keywords.trim().split(/\s+/).filter(t => t.length < 3);
            
            if (shortTerms.length > 0) {
                searchExplanation = `<br><small class="text-muted">Searching for: <strong>${terms.join(', ')}</strong> (terms with fewer than 3 characters like "${shortTerms.join('", "')}" are ignored)</small>`;
            }
            if (quotedSearch === false && terms.length > 1) {
                searchExplanation += `<br><small class="text-muted">Tip: Use quotes like <code>"exact phrase"</code> to search for exact phrase instead of individual terms.</small>`;
            }
        }
        
        // Hide specified sections on first search
        if ($('#searchResults').is(':hidden')) {
            this.config.hideSections.forEach(selector => {
                $(selector).slideUp();
            });
        }
        
        // Reset and show results section
        this.allResults = [];
        this.searchedOrganisms = 0;
        this.warnings = [];  // Collect warnings from each organism
        // NOTE: Do NOT reset this.selectedSources - it should persist across searches
        $('#searchResults').show();
        $('#resultsContainer').html('');
        
        // Build search info with filters
        let searchInfo = `Searching for: <strong>${keywords}</strong> across ${this.config.totalVar} organisms${searchExplanation}`;
        if (this.selectedSources && this.selectedSources.length > 0) {
            searchInfo += `<br><small class="text-muted">Limited to: ${this.selectedSources.join(', ')}</small>`;
        }
        $('#searchInfo').html(searchInfo);
        
        // Show progress bar
        $('#searchProgress').html(`
            <div class="search-progress-bar">
                <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
            </div>
            <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
        `);
        
        // Disable search button
        $('#searchBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Searching...');
        
        // Search each organism sequentially
        this.searchNextOrganism(keywords, quotedSearch, 0);
    }
    
    searchNextOrganism(keywords, quotedSearch, index) {
        if (index >= this.config.totalVar) {
            // All searches complete
            this.finishSearch();
            return;
        }
        
        const organism = this.config.organismsVar[index];
        $('#progressText').html(`Searching ${organism}... (${index + 1}/${this.config.totalVar})`);
        
        // Build AJAX data
        const ajaxData = {
            search_keywords: keywords,
            organism: organism,
            quoted: quotedSearch ? '1' : '0',
            ...this.config.extraAjaxParams
        };
        
        // Add source filter if selected
        if (this.selectedSources && this.selectedSources.length > 0) {
            ajaxData.source_names = this.selectedSources.join(',');
            console.log('Applying source filter:', ajaxData.source_names);
        } else {
            console.log('No source filter applied');
        }
        
        console.log('AJAX data:', ajaxData);
        
        $.ajax({
            url: this.config.sitePath + '/tools/annotation_search_ajax.php',
            method: 'GET',
            data: ajaxData,
            dataType: 'json',
            success: (data) => {
                if (data.results && data.results.length > 0) {
                    this.allResults = this.allResults.concat(data.results);
                    this.displayOrganismResults(data);
                }
                
                // Collect warning if present
                if (data.warning) {
                    this.warnings.push({
                        organism: data.organism,
                        warning: data.warning
                    });
                }
                
                this.searchedOrganisms++;
                const progress = Math.round((this.searchedOrganisms / this.config.totalVar) * 100);
                $('#progressFill').css('width', progress + '%').text(progress + '%');
                
                // Search next organism
                this.searchNextOrganism(keywords, quotedSearch, index + 1);
            },
            error: (xhr, status, error) => {
                console.error('Search error for ' + organism + ':', error);
                console.error('Response text:', xhr.responseText.substring(0, 500));
                this.searchedOrganisms++;
                this.searchNextOrganism(keywords, quotedSearch, index + 1);
            }
        });
    }
    
    displayOrganismResults(data) {
        const organism = data.organism;
        const results = data.results;
        const imageUrl = data.organism_image_path || '';
        
        // Create results table using shared function
        let tableHtml = createOrganismResultsTable(organism, results, this.config.sitePath, 'tools/parent_display.php', imageUrl, this.currentKeywords);
        
        // Add "Read More" button if configured
        if (!this.config.noReadMoreButton) {
            const readMoreUrl = this.config.sitePath + '/tools/organism_display.php?organism=' + encodeURIComponent(organism);
            const readMoreBtn = `<a href="${readMoreUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary ms-2 font-size-small">
                            <i class="fa fa-info-circle"></i> Read More
                        </a>`;
            tableHtml = tableHtml.replace(/(<span class="badge bg-primary">.*?<\/span>)/, `$1\n                ${readMoreBtn}`);
        }
        
        $('#resultsContainer').append(tableHtml);
        
        // Initialize table functionality
        const tableId = '#resultsTable-' + organism.replace(/\s+/g, '-');
        const selectId = '#selectCheckbox-' + organism.replace(/\s+/g, '-');
        initializeResultsTable(tableId, selectId, true);
    }
    
    finishSearch() {
        $('#searchBtn').prop('disabled', false).html('<i class="fa fa-search"></i> Search');
        
        // Build warnings section if any warnings exist
        let warningsHtml = '';
        if (this.warnings.length > 0) {
            warningsHtml = '<div class="alert alert-warning mb-3">';
            this.warnings.forEach((item, idx) => {
                warningsHtml += '<strong>' + item.organism + ':</strong> ' + item.warning;
                if (idx < this.warnings.length - 1) warningsHtml += '<br>';
            });
            warningsHtml += '</div>';
        }
        
        if (this.allResults.length === 0) {
            $('#searchProgress').html(warningsHtml + '<div class="alert alert-warning">No results found. Try different search terms.</div>');
        } else {
            // Build jump-to navigation
            let jumpToHtml = '<div class="alert alert-info mb-3"><strong>Jump to results for:</strong> ';
            const organismCounts = {};
            this.allResults.forEach(r => {
                if (!organismCounts[r.organism]) {
                    organismCounts[r.organism] = 0;
                }
                organismCounts[r.organism]++;
            });
            
            Object.keys(organismCounts).forEach((org, idx) => {
                const anchorId = 'results-' + org.replace(/[^a-zA-Z0-9]/g, '_');
                const genus = this.allResults.find(r => r.organism === org)?.genus || '';
                const species = this.allResults.find(r => r.organism === org)?.species || '';
                if (idx > 0) jumpToHtml += ' | ';
                jumpToHtml += `<a href="#${anchorId}" class="jump-link"><em>${genus} ${species}</em> <span class="badge bg-secondary">${organismCounts[org]}</span></a>`;
            });
            jumpToHtml += '</div>';
            
            // Generate unique ID for collapse
            const collapseId = 'searchHintsCollapse-' + Date.now();
            
            $('#searchProgress').html(`
                ${warningsHtml}
                <div class="alert alert-success mb-3">
                    <button class="btn btn-link text-start p-0 w-100 text-decoration-none collapsed" 
                            type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}"
                            style="text-align: left; color: inherit;">
                        <i class="fa fa-check-circle"></i> <strong>Search complete!</strong> Found ${this.allResults.length} total result${this.allResults.length !== 1 ? 's' : ''} across ${this.searchedOrganisms} organisms.
                        <span style="float: right; margin-top: 2px;">
                            <i class="fa fa-chevron-down"></i>
                        </span>
                    </button>
                    <div class="collapse mt-3" id="${collapseId}">
                        <small>
                            <strong>How to use results:</strong><br>
                            • <strong>Filter:</strong> Use the input boxes above each column header to filter results.<br>
                            • <strong>Sort:</strong> Click column headers to sort ascending/descending.<br>
                            • <strong>Export:</strong> Select rows with checkboxes, then click export buttons (Copy, CSV, Excel, PDF, Print).<br>
                            • <strong>Columns:</strong> Use "Column Visibility" button to show/hide columns.
                        </small>
                    </div>
                </div>
                ${jumpToHtml}
            `);
        }
        
        // Optional: scroll to results
        if (this.config.scrollToResults) {
            $('html, body').animate({ scrollTop: $('#searchResults').offset().top - 100 }, 'smooth');
        }
    }
}

// Make AnnotationSearch available globally for non-module scripts
window.AnnotationSearch = AnnotationSearch;
