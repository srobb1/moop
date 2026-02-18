/**
 * JBrowse Admin Dashboard - JavaScript Functions
 * 
 * Functions for managing JBrowse tracks via admin interface.
 * 
 * Expects global variables from inline_scripts:
 * - jbrowseOrganisms: object with organism => [assemblies]
 * - sitePath: site URL path
 */

// Store organisms data globally (populated by inline_scripts)
let organismsData = {};
let tracksTable = null;
let siteUrl = '';

/**
 * Initialize JBrowse admin dashboard
 */
function initJBrowseAdmin(organisms, site) {
    organismsData = organisms || window.jbrowseOrganisms || {};
    siteUrl = site || window.sitePath || '';
    
    console.log('Initializing JBrowse Admin with', Object.keys(organismsData).length, 'organisms');
    
    // Setup organism dropdowns
    setupOrganismDropdowns();
    
    // Initialize DataTable
    initTracksTable();
}

/**
 * Setup organism dropdown change handlers
 */
function setupOrganismDropdowns() {
    // Sheet registration dropdown
    const regOrganism = document.getElementById('organism');
    if (regOrganism) {
        regOrganism.addEventListener('change', function() {
            updateAssemblyDropdown(this.value, 'assembly');
        });
    }
    
    // Sheet registration assembly - load existing sheet when selected
    const regAssembly = document.getElementById('assembly');
    if (regAssembly) {
        regAssembly.addEventListener('change', function() {
            const organism = document.getElementById('organism').value;
            const assembly = this.value;
            if (organism && assembly) {
                loadExistingSheetConfig(organism, assembly);
            }
        });
    }
    
    // Track sync dropdown
    const syncOrganism = document.getElementById('syncOrganism');
    if (syncOrganism) {
        syncOrganism.addEventListener('change', function() {
            updateAssemblyDropdown(this.value, 'syncAssembly');
        });
    }
    
    // Filter dropdown
    const filterOrganism = document.getElementById('filterOrganism');
    if (filterOrganism) {
        filterOrganism.addEventListener('change', function() {
            updateAssemblyDropdown(this.value, 'filterAssembly');
            filterTracks();
        });
    }
}

/**
 * Update assembly dropdown based on selected organism
 */
function updateAssemblyDropdown(organism, assemblySelectId) {
    const assemblySelect = document.getElementById(assemblySelectId);
    if (!assemblySelect) return;
    
    const defaultText = assemblySelectId.startsWith('filter') ? 'All' : 'Select assembly...';
    assemblySelect.innerHTML = `<option value="">${defaultText}</option>`;
    
    if (organism && organismsData[organism]) {
        organismsData[organism].forEach(asm => {
            const option = document.createElement('option');
            option.value = asm;
            option.textContent = asm;
            assemblySelect.appendChild(option);
        });
        assemblySelect.disabled = false;
    } else {
        assemblySelect.disabled = true;
    }
}

/**
 * Initialize tracks DataTable
 */
function initTracksTable() {
    tracksTable = $('#tracksTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: `/${siteUrl}/api/jbrowse2/admin_list_tracks.php`,
            type: 'POST',
            data: function(d) {
                d.organism = $('#filterOrganism').val();
                d.assembly = $('#filterAssembly').val();
                d.type = $('#filterType').val();
                d.access = $('#filterAccess').val();
            }
        },
        columns: [
            { data: 'checkbox', orderable: false, searchable: false },
            { data: 'name' },
            { data: 'organism' },
            { data: 'assembly' },
            { data: 'type' },
            { data: 'access' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        pageLength: 20,
        order: [[1, 'asc']]
    });
}

/**
 * Reload tracks table with current filters
 */
function filterTracks() {
    if (tracksTable) {
        tracksTable.ajax.reload();
    }
}

/**
 * Toggle select all checkboxes
 */
function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('input[name="trackSelect"]').forEach(cb => {
        cb.checked = checked;
    });
    updateBulkButtons();
}

/**
 * Update bulk action buttons state
 */
function updateBulkButtons() {
    const selected = document.querySelectorAll('input[name="trackSelect"]:checked').length;
    document.getElementById('selectedCount').textContent = `${selected} selected`;
    document.getElementById('bulkGenerateBtn').disabled = selected === 0;
    document.getElementById('bulkDeleteBtn').disabled = selected === 0;
}

/**
 * Load existing sheet configuration for selected organism/assembly
 */
function loadExistingSheetConfig(organism, assembly) {
    fetch(`/${siteUrl}/api/jbrowse2/admin_get_sheet_config.php?organism=${encodeURIComponent(organism)}&assembly=${encodeURIComponent(assembly)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.config) {
                // Populate form with existing data
                const sheetId = data.config.SHEET_ID || '';
                const gid = data.config.GID || '0';
                const autoSync = data.config.AUTO_SYNC === 'true';
                
                document.getElementById('sheetUrl').value = sheetId;
                document.getElementById('gid').value = gid;
                document.getElementById('autoSync').checked = autoSync;
                
                // Show info message
                const resultDiv = document.getElementById('sheetValidationResult');
                resultDiv.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> <strong>Existing sheet found</strong>
                        <p class="mb-0 mt-2">Registered: ${data.config.REGISTERED_DATE || 'Unknown'}</p>
                        <p class="mb-0">You can update the sheet URL or test the current configuration.</p>
                    </div>
                `;
                resultDiv.style.display = 'block';
            } else {
                // No existing sheet
                document.getElementById('sheetUrl').value = '';
                document.getElementById('gid').value = '0';
                document.getElementById('autoSync').checked = true;
                document.getElementById('sheetValidationResult').style.display = 'none';
            }
        })
        .catch(error => {
            console.log('No existing sheet config found or error:', error);
        });
}

/**
 * Test Google Sheet connection
 */
function testSheet() {
    const form = document.getElementById('registerSheetForm');
    const formData = new FormData(form);
    formData.append('action', 'test');
    
    const resultDiv = document.getElementById('sheetValidationResult');
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Testing connection...</div>';
    resultDiv.style.display = 'block';
    
    fetch(`/${siteUrl}/api/jbrowse2/admin_register_sheet.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h6><i class="fa fa-check-circle"></i> Sheet Validated Successfully</h6>
                    <ul class="mb-0">
                        <li>✓ Sheet accessible</li>
                        <li>✓ Required columns found: ${data.columns.join(', ')}</li>
                        <li>✓ ${data.trackCount} tracks detected</li>
                    </ul>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h6><i class="fa fa-times-circle"></i> Validation Failed</h6>
                    <p class="mb-0">${data.error}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <h6><i class="fa fa-times-circle"></i> Error</h6>
                <p class="mb-0">${error.message}</p>
            </div>
        `;
    });
}

/**
 * Clear sheet registration form
 */
function clearSheetForm() {
    document.getElementById('registerSheetForm').reset();
    document.getElementById('sheetValidationResult').style.display = 'none';
    document.getElementById('assembly').disabled = true;
}

/**
 * View track details
 */
function viewTrack(trackId, organism, assembly) {
    alert(`View track details:\nTrack: ${trackId}\nOrganism: ${organism}\nAssembly: ${assembly}\n\n[Feature coming soon]`);
}

/**
 * Delete single track
 */
function deleteTrack(trackId, organism, assembly) {
    if (!confirm(`Delete track "${trackId}"?\n\nThis will remove the track metadata file. The track will no longer be visible in JBrowse.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('trackId', trackId);
    formData.append('organism', organism);
    formData.append('assembly', assembly);
    
    fetch(`/${siteUrl}/api/jbrowse2/admin_delete_tracks.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Track deleted successfully!');
            if (tracksTable) {
                tracksTable.ajax.reload();
            }
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

/**
 * Delete selected tracks
 */
function deleteSelected() {
    const selected = document.querySelectorAll('input[name="trackSelect"]:checked');
    if (selected.length === 0) return;
    
    if (!confirm(`Delete ${selected.length} selected track(s)?`)) {
        return;
    }
    
    let deleted = 0;
    let errors = 0;
    
    selected.forEach((checkbox, index) => {
        const trackId = checkbox.value;
        const organism = checkbox.dataset.organism;
        const assembly = checkbox.dataset.assembly;
        
        const formData = new FormData();
        formData.append('trackId', trackId);
        formData.append('organism', organism);
        formData.append('assembly', assembly);
        
        fetch(`/${siteUrl}/api/jbrowse2/admin_delete_tracks.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                deleted++;
            } else {
                errors++;
            }
            
            // After last one, show results
            if (deleted + errors === selected.length) {
                alert(`Deleted ${deleted} track(s). Errors: ${errors}`);
                if (tracksTable) {
                    tracksTable.ajax.reload();
                }
            }
        });
    });
}

/**
 * Generate configs for selected assemblies
 */
function generateSelectedConfigs() {
    alert('Feature coming soon: Generate configs for selected assemblies');
}

/**
 * Setup form submission handlers
 */
$(document).ready(function() {
    // Auto-initialize on page load using global variables
    if (typeof jbrowseOrganisms !== 'undefined' && typeof sitePath !== 'undefined') {
        initJBrowseAdmin(jbrowseOrganisms, sitePath);
    }
    
    // Sheet registration form
    $('#registerSheetForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'register');
        
        const resultDiv = document.getElementById('sheetValidationResult');
        resultDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Registering sheet...</div>';
        resultDiv.style.display = 'block';
        
        fetch(`/${siteUrl}/api/jbrowse2/admin_register_sheet.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> Sheet registered successfully!
                        ${data.message ? '<p class="mb-0 mt-2">' + data.message + '</p>' : ''}
                    </div>
                `;
                setTimeout(() => {
                    clearSheetForm();
                }, 3000);
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fa fa-times-circle"></i> ${data.error}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fa fa-times-circle"></i> ${error.message}
                </div>
            `;
        });
    });
    
    // Track sync form
    $('#syncTracksForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        const progressDiv = document.getElementById('syncProgress');
        const logOutput = document.getElementById('syncLogOutput');
        
        progressDiv.style.display = 'block';
        logOutput.textContent = 'Starting track sync...\n';
        
        fetch(`/${siteUrl}/api/jbrowse2/admin_sync_tracks.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                logOutput.textContent += '\n✓ Track sync complete!\n';
                logOutput.textContent += data.output;
                document.getElementById('syncProgressBar').style.width = '100%';
                
                // Reload track listing
                if (tracksTable) {
                    tracksTable.ajax.reload();
                }
                
                // Show success message with auto-refresh countdown
                logOutput.textContent += '\n\n🔄 Refreshing page in 3 seconds to update statistics...\n';
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                logOutput.textContent += '\n✗ Error: ' + data.error + '\n';
                if (data.output) {
                    logOutput.textContent += data.output;
                }
            }
        })
        .catch(error => {
            logOutput.textContent += '\n✗ Error: ' + error.message + '\n';
        });
    });
});
