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
        this.cappedOrganisms = [];  // Track organisms with capped results
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
                    <button type="button" class="btn btn-icon btn-clear-filters btn-outline-secondary" 
                            title="Clear Filters"
                            data-bs-toggle="tooltip" data-bs-placement="bottom"
                            style="display: none;">
                        <i class="fa fa-times"></i>
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
            filterBtn.removeClass('btn-outline-secondary').addClass('btn-primary');
            filterBtn.html('<i class="fa fa-sliders-h"></i><span class="badge badge-filter">' + this.selectedSources.length + '</span>');
            clearBtn.show();
            submitBtn.addClass('btn-success');
        } else {
            // No filters
            filterBtn.removeClass('btn-primary').addClass('btn-outline-secondary');
            filterBtn.html('<i class="fa fa-sliders-h"></i>');
            clearBtn.hide();
            submitBtn.removeClass('btn-success');
        }
    }
    
    /**
     * Show advanced search filter modal
     */
    showFilterModal() {
        // Always pass organisms as array - works for single or multiple organisms
        // Single organism page will have 1 item in array, multi-organism pages will have many
        const organisms = this.config.organismsVar;
        
        // Convert selectedSources array to object for modal
        const selectedSourcesObj = {};
        if (this.selectedSources && this.selectedSources.length > 0) {
            this.selectedSources.forEach(source => {
                selectedSourcesObj[source] = true;
            });
        }
        
        const filter = new AdvancedSearchFilter({
            organisms: organisms,
            sitePath: this.config.sitePath,
            selectedSources: selectedSourcesObj,
            onApply: (selectedSources) => {
                this.selectedSources = selectedSources;
                this.updateFilterButtonState();
                const message = selectedSources.length === 0 
                    ? 'No sources selected' 
                    : `Filtering to ${selectedSources.length} source(s)`;
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
        let displayKeywords = keywords;
        
        if (!quotedSearch) {
            const terms = keywords.trim().split(/\s+/).filter(t => t.length >= 3);
            const shortTerms = keywords.trim().split(/\s+/).filter(t => t.length < 3);
            
            // Format terms with bold for display
            const boldTerms = terms.map(t => `<strong>${t}</strong>`).join(', ');
            
            if (shortTerms.length > 0) {
                searchExplanation = `<br><small class="text-muted">Searching with: ${boldTerms} (terms with fewer than 3 characters like "${shortTerms.join('", "')}" are ignored)</small>`;
            } else if (terms.length > 1) {
                // Multi-word search without short terms - show the formatted terms
                searchExplanation = `<br><small class="text-muted">Searching with: ${boldTerms}</small>`;
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
        
        // Build search info with description
        let searchInfo = `Searched for any record containing <strong>${keywords}</strong>`;
        if (this.selectedSources && this.selectedSources.length > 0) {
            searchInfo += ` (limited to ${this.selectedSources.join(', ')})`;
        }
        if (searchExplanation) {
            searchInfo += searchExplanation;
        }
        $('#searchInfo').html(searchInfo);
        
        // Show progress bar
        $('#searchProgress').html(`
            <div class="search-progress-bar">
                <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
            </div>
            <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
        `);
        
        // Disable search button and add flashing animation
        $('#searchBtn').prop('disabled', true).addClass('btn-searching').html('<i class="fa fa-search"></i>');
        
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
                
                // Track if results were capped for this organism
                if (data.capped) {
                    this.cappedOrganisms.push(data.organism);
                }
                
                this.searchedOrganisms++;
                const progress = Math.round((this.searchedOrganisms / this.config.totalVar) * 100);
                $('#progressFill').css('width', progress + '%').text(progress + '%');
                
                // Search next organism
                this.searchNextOrganism(keywords, quotedSearch, index + 1);
            },
            error: (xhr, status, error) => {
                this.searchedOrganisms++;
                this.searchNextOrganism(keywords, quotedSearch, index + 1);
            }
        });
    }
    
    displayOrganismResults(data) {
        const organism = data.organism;
        const results = data.results;
        const imageUrl = data.organism_image_path || '';
        const isUniquenameSearch = data.search_type === 'Gene/Transcript ID';
        
        // Use simple view for keyword searches, full view for uniquename searches
        let tableHtml;
        if (!isUniquenameSearch && results.length > 0) {
            tableHtml = createSimpleResultsTable(organism, results, this.config.sitePath, 'tools/parent.php', imageUrl, this.currentKeywords);
        } else {
            tableHtml = createOrganismResultsTable(organism, results, this.config.sitePath, 'tools/parent.php', imageUrl, this.currentKeywords);
        }
        
        $('#resultsContainer').append(tableHtml);
        
        // Only initialize full DataTable for uniquename searches
        if (isUniquenameSearch) {
            const tableId = '#resultsTable-' + organism.replace(/\s+/g, '-');
            const selectId = '#selectCheckbox-' + organism.replace(/\s+/g, '-');
            initializeResultsTable(tableId, selectId, true);
        }
    }
    
    finishSearch() {
        $('#searchBtn').prop('disabled', false).removeClass('btn-searching').html('<i class="fa fa-search"></i>');
        
        // Build compact cap message if any organisms were capped
        let capMessageHtml = '';
        if (this.cappedOrganisms.length > 0) {
            const cappedList = this.cappedOrganisms.join(', ');
            capMessageHtml = `<div class="alert alert-warning mb-3">
                <strong>Search results are capped at 2,500.</strong> Use Advanced Filter or add more search terms to refine. 
                The following organism searches were capped: <em>${cappedList}</em>
            </div>`;
        }
        
        // Build warnings section if any other warnings exist (excluding cap warnings)
        let warningsHtml = '';
        const otherWarnings = this.warnings.filter(w => !w.warning.includes('2,500') && !w.warning.includes('capped'));
        if (otherWarnings.length > 0) {
            warningsHtml = '<div class="alert alert-warning mb-3">';
            otherWarnings.forEach((item, idx) => {
                warningsHtml += '<strong>' + item.organism + ':</strong> ' + item.warning;
                if (idx < otherWarnings.length - 1) warningsHtml += '<br>';
            });
            warningsHtml += '</div>';
        }
        
        if (this.allResults.length === 0) {
            $('#searchProgress').html(capMessageHtml + warningsHtml + '<div class="alert alert-warning">No results found. Try different search terms.</div>');
        } else {
            // Build jump-to navigation combined with results summary
            const organismCounts = {};
            this.allResults.forEach(r => {
                if (!organismCounts[r.organism]) {
                    organismCounts[r.organism] = 0;
                }
                organismCounts[r.organism]++;
            });
            
            let jumpToHtml = '<div class="alert alert-info mb-3">';
            jumpToHtml += `<strong>Found ${this.allResults.length} matching annotation${this.allResults.length !== 1 ? 's' : ''} across ${Object.keys(organismCounts).length} feature${Object.keys(organismCounts).length !== 1 ? 's' : ''}:</strong> `;
            
            Object.keys(organismCounts).forEach((org, idx) => {
                const anchorId = 'results-' + org.replace(/[^a-zA-Z0-9]/g, '_');
                const genus = this.allResults.find(r => r.organism === org)?.genus || '';
                const species = this.allResults.find(r => r.organism === org)?.species || '';
                if (idx > 0) jumpToHtml += ' | ';
                jumpToHtml += `<a href="#${anchorId}" class="jump-link"><em>${genus} ${species}</em> <span class="badge bg-secondary">${organismCounts[org]}</span></a>`;
            });
            jumpToHtml += '</div>';
            
            $('#searchProgress').html(`
                ${capMessageHtml}
                ${warningsHtml}
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
