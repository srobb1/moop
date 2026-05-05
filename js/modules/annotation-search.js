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
        this.zeroResultOrganisms = [];
        this.currentKeywords = '';
        this.cappedOrganisms = [];
    }

    init() {
        $(this.config.formSelector).on('submit', (e) => {
            e.preventDefault();
            this.handleSearch();
        });

        this.addFilterButton();
        this.updateOrganismNote();
    }

    /**
     * Add advanced search filter button and clear button to form
     */
    addFilterButton() {
        const form = $(this.config.formSelector);
        if (form.find('.search-controls').length === 0) {
            const submitBtn = form.find('button[type="submit"]');

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

            form.find('.btn-advanced-filter').on('click', () => this.showFilterModal());
            form.find('.btn-clear-filters').on('click', () => this.clearFilters());

            const tooltipTriggerList = [].slice.call(form.find('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }
    }

    /**
     * Render or update the "Searching across N organisms" note below the search form.
     * Called on init and whenever the organism selection changes.
     */
    updateOrganismNote() {
        const form = $(this.config.formSelector);
        const parent = form.parent();
        let note = parent.find('.search-org-note');
        if (note.length === 0) {
            note = $('<div class="search-org-note text-muted small mt-2"></div>');
            form.after(note);
        }
        const n = this.config.totalVar;
        if (n > 1) {
            note.html(`Searching across <strong>${n}</strong> organism${n !== 1 ? 's' : ''}. <a href="#organismsSection">Select or deselect</a> to customize.`);
        } else {
            note.html('');
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
            filterBtn.removeClass('btn-outline-secondary').addClass('btn-primary');
            filterBtn.html('<i class="fa fa-sliders-h"></i><span class="badge badge-filter">' + this.selectedSources.length + '</span>');
            clearBtn.show();
            submitBtn.addClass('btn-success');
        } else {
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
        const organisms = this.config.organismsVar;

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
            }
        });

        filter.show();
    }

    handleSearch() {
        const keywords = $('#searchKeywords').val().trim();
        this.currentKeywords = keywords;

        if (keywords.length < 3) {
            alert('Please enter at least 3 characters to search');
            return;
        }

        const quotedSearch = /^".+"$/.test(keywords);

        let searchExplanation = '';

        if (!quotedSearch) {
            const terms = keywords.trim().split(/\s+/).filter(t => t.length >= 3);
            const shortTerms = keywords.trim().split(/\s+/).filter(t => t.length < 3);

            const boldTerms = terms.map(t => `<strong>${t}</strong>`).join(', ');

            if (shortTerms.length > 0) {
                searchExplanation = `<br><small class="text-muted">Searching with: ${boldTerms} (terms with fewer than 3 characters like "${shortTerms.join('", "')}" are ignored)</small>`;
            } else if (terms.length > 1) {
                searchExplanation = `<br><small class="text-muted">Searching with: ${boldTerms}</small>`;
            }

            if (quotedSearch === false && terms.length > 1) {
                searchExplanation += `<br><small class="text-muted">Tip: Use quotes like <code>"exact phrase"</code> to search for exact phrase instead of individual terms.</small>`;
            }
        }

        if ($('#searchResults').is(':hidden')) {
            this.config.hideSections.forEach(selector => {
                $(selector).slideUp();
            });
        }

        this.allResults = [];
        this.zeroResultOrganisms = [];
        this.warnings = [];
        this.cappedOrganisms = [];
        $('#searchResults').show();
        $('#resultsContainer').html('');

        let searchInfo = `Searched for any record containing <strong>${keywords}</strong>`;
        if (this.selectedSources && this.selectedSources.length > 0) {
            searchInfo += ` (limited to ${this.selectedSources.join(', ')})`;
        }
        if (searchExplanation) {
            searchInfo += searchExplanation;
        }
        $('#searchInfo').html(searchInfo);

        $('#searchProgress').html(`
            <div class="search-progress-bar">
                <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
            </div>
            <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
        `);

        $('#searchBtn').prop('disabled', true).addClass('btn-searching').html('<i class="fa fa-search"></i>');

        this.searchAllOrganisms(keywords, quotedSearch);
    }

    /**
     * Search all organisms in parallel with a concurrency limit of 5.
     */
    searchAllOrganisms(keywords, quotedSearch) {
        const total = this.config.totalVar;
        const organisms = this.config.organismsVar;
        let nextIndex = 0;
        let completed = 0;

        const launchNext = () => {
            if (nextIndex >= total) return;
            const index = nextIndex++;
            const organism = organisms[index];

            const ajaxData = {
                search_keywords: keywords,
                organism: organism,
                quoted: quotedSearch ? '1' : '0',
                ...this.config.extraAjaxParams
            };
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
                    } else {
                        this.zeroResultOrganisms.push({
                            organism: organism,
                            genus: data.genus || '',
                            species: data.species || ''
                        });
                    }
                    if (data.warning) {
                        this.warnings.push({ organism: data.organism, warning: data.warning });
                    }
                    if (data.capped) {
                        this.cappedOrganisms.push(data.organism);
                    }
                    completed++;
                    const progress = Math.round((completed / total) * 100);
                    $('#progressFill').css('width', progress + '%').text(progress + '%');
                    $('#progressText').html(`Searching... (${completed}/${total} complete)`);
                    if (completed >= total) {
                        this.finishSearch();
                    } else {
                        launchNext();
                    }
                },
                error: () => {
                    completed++;
                    const progress = Math.round((completed / total) * 100);
                    $('#progressFill').css('width', progress + '%').text(progress + '%');
                    if (completed >= total) {
                        this.finishSearch();
                    } else {
                        launchNext();
                    }
                }
            });
        };

        const concurrency = Math.min(5, total);
        for (let i = 0; i < concurrency; i++) {
            launchNext();
        }
    }

    displayOrganismResults(data) {
        const organism = data.organism;
        const results = data.results;
        const imageUrl = data.organism_image_path || '';
        const isUniquenameSearch = data.search_type === 'Gene/Transcript ID';

        let tableHtml;
        if (!isUniquenameSearch && results.length > 0) {
            tableHtml = createSimpleResultsTable(organism, results, this.config.sitePath, 'tools/parent.php', imageUrl, this.currentKeywords);
        } else {
            tableHtml = createOrganismResultsTable(organism, results, this.config.sitePath, 'tools/parent.php', imageUrl, this.currentKeywords);
        }

        $('#resultsContainer').append(tableHtml);

        if (isUniquenameSearch) {
            const tableId = '#resultsTable-' + organism.replace(/\s+/g, '-');
            const selectId = '#selectCheckbox-' + organism.replace(/\s+/g, '-');
            initializeResultsTable(tableId, selectId, true);
        }
    }

    finishSearch() {
        $('#searchBtn').prop('disabled', false).removeClass('btn-searching').html('<i class="fa fa-search"></i>');

        let capMessageHtml = '';
        if (this.cappedOrganisms.length > 0) {
            const cappedList = this.cappedOrganisms.join(', ');
            capMessageHtml = `<div class="alert alert-warning mb-3">
                <strong>Search results are capped at 2,500.</strong> Use Advanced Filter or add more search terms to refine.
                The following organism searches were capped: <em>${cappedList}</em>
            </div>`;
        }

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
            const organismCounts = {};
            const uniqueFeatures = new Set();
            this.allResults.forEach(r => {
                if (!organismCounts[r.organism]) {
                    organismCounts[r.organism] = 0;
                }
                organismCounts[r.organism]++;
                uniqueFeatures.add(r.feature_uniquename);
            });

            let jumpToHtml = '<div class="alert alert-info mb-3 d-flex justify-content-between align-items-start gap-3">';
            jumpToHtml += '<div>';
            jumpToHtml += `<strong>Found ${this.allResults.length} matching annotation${this.allResults.length !== 1 ? 's' : ''} across ${uniqueFeatures.size} feature${uniqueFeatures.size !== 1 ? 's' : ''}:</strong> `;

            Object.keys(organismCounts).forEach((org, idx) => {
                const anchorId = 'results-' + org.replace(/[^a-zA-Z0-9]/g, '_');
                const genus = this.allResults.find(r => r.organism === org)?.genus || '';
                const species = this.allResults.find(r => r.organism === org)?.species || '';
                if (idx > 0) jumpToHtml += ' | ';
                jumpToHtml += `<a href="#${anchorId}" class="jump-link"><em>${genus} ${species}</em> <span class="badge bg-secondary">${organismCounts[org]}</span></a>`;
            });

            this.zeroResultOrganisms.forEach((orgInfo) => {
                jumpToHtml += ` | <em>${orgInfo.genus} ${orgInfo.species}</em> <span class="badge bg-secondary">0</span>`;
            });

            jumpToHtml += '</div>';
            jumpToHtml += `<div class="btn-group flex-shrink-0">
                <button class="btn btn-sm btn-outline-success download-all-results" title="Download all annotation results as CSV"><i class="fa fa-table"></i> Table CSV</button>
                <button class="btn btn-sm btn-outline-primary download-fasta-results" title="Download FASTA sequences for all features found"><i class="fa fa-dna"></i> FASTA</button>
            </div>`;
            jumpToHtml += '</div>';

            $('#searchProgress').html(`
                ${capMessageHtml}
                ${warningsHtml}
                ${jumpToHtml}
            `);
            $('#searchProgress').find('.download-all-results').on('click', () => this.downloadResults());
            $('#searchProgress').find('.download-fasta-results').on('click', () => this.downloadFasta());
        }

        if (this.config.scrollToResults) {
            $('html, body').animate({ scrollTop: $('#searchResults').offset().top - 100 }, 'smooth');
        }
    }

    downloadResults() {
        const decodeHtml = (html) => {
            const txt = document.createElement('textarea');
            txt.innerHTML = html;
            return txt.value;
        };
        const escape = (val) => {
            const s = decodeHtml(String(val ?? ''));
            return (s.includes(',') || s.includes('"') || s.includes('\n'))
                ? '"' + s.replace(/"/g, '""') + '"'
                : s;
        };

        const headers = ['Organism', 'Genus', 'Species', 'Feature ID', 'Feature Type', 'Feature Name', 'Feature Description', 'Annotation Source', 'Annotation ID', 'Annotation Description', 'Score'];
        const rows = [headers.join(',')];
        this.allResults.forEach(r => {
            rows.push([
                r.organism, r.genus, r.species,
                r.feature_uniquename, r.feature_type, r.feature_name, r.feature_description,
                r.annotation_source_name, r.annotation_accession, r.annotation_description, r.score
            ].map(escape).join(','));
        });

        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'annotation_search_results.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    downloadFasta() {
        // Group unique feature IDs by organism
        const byOrganism = {};
        this.allResults.forEach(r => {
            if (!byOrganism[r.organism]) byOrganism[r.organism] = new Set();
            byOrganism[r.organism].add(r.feature_uniquename);
        });

        const features = {};
        Object.keys(byOrganism).forEach(org => {
            features[org] = [...byOrganism[org]];
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = this.config.sitePath + '/api/download_search_fasta.php';
        form.style.display = 'none';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        const featuresInput = document.createElement('input');
        featuresInput.type = 'hidden';
        featuresInput.name = 'features';
        featuresInput.value = JSON.stringify(features);
        form.appendChild(featuresInput);

        const labelInput = document.createElement('input');
        labelInput.type = 'hidden';
        labelInput.name = 'label';
        labelInput.value = this.currentKeywords;
        form.appendChild(labelInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
}

// Make AnnotationSearch available globally for non-module scripts
window.AnnotationSearch = AnnotationSearch;
