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
     * Add advanced search filter button to form
     */
    addFilterButton() {
        const form = $(this.config.formSelector);
        if (form.find('.btn-advanced-filter').length === 0) {
            const filterBtn = `
                <button type="button" class="btn btn-sm btn-outline-secondary btn-advanced-filter ms-2">
                    <i class="fa fa-sliders"></i> Advanced Filters
                </button>
            `;
            form.find('button[type="submit"]').after(filterBtn);
            
            $('.btn-advanced-filter').on('click', () => this.showFilterModal());
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
        
        // Validate input
        if (keywords.length < 3) {
            alert('Please enter at least 3 characters to search');
            return;
        }
        
        // Check for quoted search
        const quotedSearch = /^".+"$/.test(keywords);
        
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
        this.selectedSources = null;  // Selected annotation sources filter
        $('#searchResults').show();
        $('#resultsContainer').html('');
        $('#searchInfo').html(`Searching for: <strong>${keywords}</strong> across ${this.config.totalVar} organisms`);
        
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
        }
        
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
        let tableHtml = createOrganismResultsTable(organism, results, this.config.sitePath, 'tools/parent_display.php', imageUrl);
        
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
            
            $('#searchProgress').html(`
                ${warningsHtml}
                <div class="alert alert-success">
                    <i class="fa fa-check-circle"></i> <strong>Search complete!</strong> Found ${this.allResults.length} total result${this.allResults.length !== 1 ? 's' : ''} across ${this.searchedOrganisms} organisms.
                    <hr class="my-2">
                    <small>
                        <strong>Filter:</strong> Use the input boxes above each column header to filter results.<br>
                        <strong>Sort:</strong> Click column headers to sort ascending/descending.<br>
                        <strong>Export:</strong> Select rows with checkboxes, then click export buttons (Copy, CSV, Excel, PDF, Print) to download selected data.<br>
                        <strong>Columns:</strong> Use "Column Visibility" button to show/hide columns.
                    </small>
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
