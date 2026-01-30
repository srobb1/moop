/**
 * Advanced Search Filter Modal
 * Allows users to select/deselect annotation sources for search filtering
 * 
 * Usage:
 *   const filter = new AdvancedSearchFilter({
 *       organism: 'Anoura_caudifer',
 *       sitePath: '/moop',
 *       onApply: (selectedSources) => { ... }
 *   });
 *   filter.show();
 */

class AdvancedSearchFilter {
    constructor(config) {
        // Accept either organisms array or single organism for backwards compatibility
        this.organisms = config.organisms || (config.organism ? [config.organism] : []);
        this.sitePath = config.sitePath || '/moop';
        this.onApply = config.onApply || (() => {});
        this.sourceTypes = {};
        // Initialize selectedSources from config or empty
        this.selectedSources = config.selectedSources ? {...config.selectedSources} : {};
    }
    
    /**
     * Load sources from server and display modal
     */
    show() {
        this.fetchSources();
    }
    
    /**
     * Fetch grouped sources from server
     */
    fetchSources() {
        // Build organisms parameter - comma-separated list
        const organismsParam = this.organisms.join(',');
        
        $.ajax({
            url: this.sitePath + '/tools/get_annotation_sources_grouped.php',
            data: { organisms: organismsParam },
            method: 'GET',
            dataType: 'json',
            success: (data) => {
                this.sourceTypes = data.source_types;
                this.initializeSelectedSources();
                this.showModal();
            },
            error: (err) => {
                alert('Error loading annotation sources. Please try again.');
            }
        });
    }
    
    /**
     * Initialize selected sources - if passed in config, use those; otherwise select all
     */
    initializeSelectedSources() {
        // If selectedSources already has values (from config), keep them as-is
        if (Object.keys(this.selectedSources).length > 0) {
            // Already initialized from config, don't change
            return;
        }
        
        // Otherwise, select all sources by default
        for (const type in this.sourceTypes) {
            const typeData = this.sourceTypes[type];
            const sources = typeData.sources || typeData; // Handle both new format with color and old format
            for (const source of sources) {
                this.selectedSources[source.name] = true;
            }
        }
    }
    
    /**
     * Create and show the modal
     */
    showModal() {
        const modalHtml = this.buildModalHtml();
        
        // Remove existing modal if present
        $('#advancedSearchFilterModal').remove();
        
        // Add modal to body
        $('body').append(modalHtml);
        
        // Attach event handlers
        this.attachEventHandlers();
        
        // Show modal with Bootstrap
        const modalElement = document.getElementById('advancedSearchFilterModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: true
            });
            modal.show();
        }
    }
    
    /**
     * Build modal HTML
     */
    buildModalHtml() {
        let sourceTypesHtml = '';
        
        for (const type in this.sourceTypes) {
            const typeData = this.sourceTypes[type];
            const sources = typeData.sources || typeData; // Handle both new format with color and old format
            const color = typeData.color || 'secondary'; // Get color from new format
            const description = typeData.description || ''; // Get description
            const typeId = this.sanitizeId(type);
            const sourcesHtml = sources.map(source => {
                const isChecked = this.selectedSources[source.name] ? 'checked' : '';
                return `
                <div class="form-check ms-3">
                    <input class="form-check-input source-checkbox" type="checkbox" 
                           id="source-${this.sanitizeId(source.name)}"
                           data-source="${source.name}"
                           data-type="${type}"
                           ${isChecked}>
                    <label class="form-check-label small" for="source-${this.sanitizeId(source.name)}">
                        ${source.name} <span class="badge bg-secondary">${source.count.toLocaleString()}</span>
                    </label>
                </div>
            `;
            }).join('');
            
            const infoIcon = description ? `<i class="fa fa-info-circle text-muted info-icon-trigger" style="cursor: pointer; margin-left: 0.5rem;" data-description="${escapeHtml(description)}" data-type="${escapeHtml(type)}"></i>` : '';
            
            sourceTypesHtml += `
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-${color} fs-6">${type}</span>
                            ${infoIcon}
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary select-type" 
                                    data-type="${type}">
                                Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary deselect-type" 
                                    data-type="${type}">
                                Deselect All
                            </button>
                        </div>
                    </div>
                    <div class="source-list">
                        ${sourcesHtml}
                    </div>
                </div>
            `;
        }
        
        return `
            <div class="modal fade" id="advancedSearchFilterModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Advanced Search Filters</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                Select annotation sources to include in your search. 
                                All sources are selected by default. The numbers shown are total 
                                annotations per source in these organisms' databases.
                            </p>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-primary select-all-global">
                                    Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary deselect-all-global">
                                    Deselect All
                                </button>
                            </div>
                            
                            <hr>
                            
                            <div class="sources-container">
                                ${sourceTypesHtml}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="button" class="btn btn-primary apply-filter">
                                Apply Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Attach event handlers to modal elements
     */
    attachEventHandlers() {
        // Use document for event delegation to handle dynamically added elements
        
        // Global select/deselect
        $(document).off('click', '.select-all-global').on('click', '.select-all-global', () => this.selectAllGlobal());
        $(document).off('click', '.deselect-all-global').on('click', '.deselect-all-global', () => this.deselectAllGlobal());
        
        // Type-level select/deselect
        $(document).off('click', '.select-type').on('click', '.select-type', (e) => this.selectType($(e.target).data('type')));
        $(document).off('click', '.deselect-type').on('click', '.deselect-type', (e) => this.deselectType($(e.target).data('type')));
        
        // Individual source checkbox
        $(document).off('change', '.source-checkbox').on('change', '.source-checkbox', (e) => {
            const source = $(e.target).data('source');
            this.selectedSources[source] = e.target.checked;
        });
        
        // Info icon click handler
        $(document).off('click', '.info-icon-trigger').on('click', '.info-icon-trigger', (e) => {
            e.stopPropagation();
            const description = $(e.target).data('description');
            const type = $(e.target).data('type');
            this.showInfoModal(type, description);
        });
        
        // Apply filter button
        $(document).off('click', '.apply-filter').on('click', '.apply-filter', () => this.apply());
    }
    
    /**
     * Select all sources globally
     */
    selectAllGlobal() {
        $('.source-checkbox').prop('checked', true);
        for (const source in this.selectedSources) {
            this.selectedSources[source] = true;
        }
    }
    
    /**
     * Deselect all sources globally
     */
    deselectAllGlobal() {
        $('.source-checkbox').prop('checked', false);
        for (const source in this.selectedSources) {
            this.selectedSources[source] = false;
        }
    }
    
    /**
     * Select all sources of a specific type
     */
    selectType(type) {
        $(`.source-checkbox[data-type="${type}"]`).prop('checked', true).each((idx, el) => {
            this.selectedSources[$(el).data('source')] = true;
        });
    }
    
    /**
     * Deselect all sources of a specific type
     */
    deselectType(type) {
        $(`.source-checkbox[data-type="${type}"]`).prop('checked', false).each((idx, el) => {
            this.selectedSources[$(el).data('source')] = false;
        });
    }
    
    /**
     * Get selected source names
     */
    getSelectedSources() {
        return Object.keys(this.selectedSources).filter(source => this.selectedSources[source]);
    }
    
    /**
     * Apply filter and close modal
     */
    apply() {
        const selected = this.getSelectedSources();
        
        // Close modal using Bootstrap
        const modalElement = document.getElementById('advancedSearchFilterModal');
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
        
        // Call callback
        this.onApply(selected);
    }
    
    /**
     * Sanitize string for use in HTML IDs
     */
    sanitizeId(str) {
        return str.replace(/[^a-zA-Z0-9-_]/g, '_');
    }
    
    /**
     * Show info modal for an annotation type
     */
    showInfoModal(type, description) {
        const modalHtml = `
            <div class="modal fade" id="infoModal-${this.sanitizeId(type)}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${escapeHtml(type)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${description}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        $(`#infoModal-${this.sanitizeId(type)}`).remove();
        
        // Add and show modal
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById(`infoModal-${this.sanitizeId(type)}`));
        modal.show();
    }
}

// Make available globally
window.AdvancedSearchFilter = AdvancedSearchFilter;
