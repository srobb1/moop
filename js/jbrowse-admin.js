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
    // Sheet registration dropdown — only registered assemblies
    const regOrganism = document.getElementById('organism');
    if (regOrganism) {
        regOrganism.addEventListener('change', function() {
            updateAssemblyDropdown(this.value, 'assembly', window.registeredOrganisms);
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
    
    // Track sync dropdown — only registered assemblies
    const syncOrganism = document.getElementById('syncOrganism');
    if (syncOrganism) {
        syncOrganism.addEventListener('change', function() {
            updateAssemblyDropdown(this.value, 'syncAssembly', window.registeredOrganisms);
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
function updateAssemblyDropdown(organism, assemblySelectId, dataSource) {
    const assemblySelect = document.getElementById(assemblySelectId);
    if (!assemblySelect) return;

    const source = dataSource || organismsData;
    const defaultText = assemblySelectId.startsWith('filter') ? 'All' : 'Select assembly...';
    assemblySelect.innerHTML = `<option value="">${defaultText}</option>`;

    if (organism && source[organism]) {
        source[organism].forEach(asm => {
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

// State for the shared GFF action modal
let _gffActionState = null;

/**
 * Open the shared modal to rebuild bgzip + tabix (+ optional text-index)
 * for a local GFF track.
 */
function rebuildGff(organism, assembly, buttonEl) {
    _openGffModal({
        title:       `Rebuild GFF — ${organism} / ${assembly}`,
        desc:        'Re-runs bgzip and tabix on the source genomic.gff. ' +
                     'Leave the attributes field blank to skip text-indexing.',
        btnLabel:    'Rebuild',
        btnClass:    'btn btn-warning',
        showAttrs:   true,
        triggerEl:   buttonEl,
        buildRequest: (attrs) => {
            const fd = new FormData();
            fd.append('organism', organism);
            fd.append('assembly', assembly);
            if (attrs) {
                fd.append('text_index', '1');
                fd.append('attributes', attrs);
            }
            return { url: `/${siteUrl}/api/jbrowse2/admin_reprep_gff.php`, formData: fd };
        },
        formatResult: (data) => {
            let log = data.output || '';
            if (data.text_index_result) {
                if (data.text_index_result.success) {
                    log += '\n\n✓ Text search index built.';
                } else if (data.text_index_result.no_cli) {
                    log += '\n\n⚠ Text index skipped: jbrowse CLI not installed.';
                } else {
                    log += '\n\n⚠ Text index: ' + data.text_index_result.error;
                }
            }
            return log;
        },
    });
}

/**
 * Open the shared modal to build (or rebuild) a jbrowse text-index
 * for a local GFF or BED track.
 */
function indexTrackNames(trackId, organism, assembly, buttonEl) {
    _openGffModal({
        title:       `Index Feature Names — ${trackId}`,
        desc:        'Builds a text search index so users can search by feature name, gene ID, etc. in JBrowse.',
        btnLabel:    'Build Index',
        btnClass:    'btn btn-primary',
        showAttrs:   true,
        requireAttrs: true,
        triggerEl:   buttonEl,
        buildRequest: (attrs) => {
            const fd = new FormData();
            fd.append('track_id',   trackId);
            fd.append('organism',   organism);
            fd.append('assembly',   assembly);
            fd.append('attributes', attrs);
            return { url: `/${siteUrl}/api/jbrowse2/admin_text_index.php`, formData: fd };
        },
        formatResult: (data) => {
            let log = `Attributes: ${data.attributes || ''}\n\n` + (data.output || '');
            if (data.no_cli) {
                log = 'jbrowse CLI not installed.\n\nInstall Node.js ≥18, then run:\n  npm install -g @jbrowse/cli';
            }
            return log;
        },
    });
}

/**
 * Internal: configure and show the #gffActionModal.
 * @param {object} opts
 *   title, desc, btnLabel, btnClass, showAttrs, requireAttrs,
 *   triggerEl, buildRequest(attrs), formatResult(data)
 */
function _openGffModal(opts) {
    _gffActionState = opts;

    document.getElementById('gffActionModalTitle').textContent = opts.title;
    document.getElementById('gffActionModalDesc').textContent  = opts.desc;
    document.getElementById('gffActionAttrsGroup').style.display = opts.showAttrs ? '' : 'none';
    document.getElementById('gffActionAttrs').value = 'Name,ID';
    document.getElementById('gffActionResult').style.display = 'none';
    document.getElementById('gffActionLog').textContent = '';

    const btn = document.getElementById('gffActionBtn');
    btn.textContent = opts.btnLabel;
    btn.className   = opts.btnClass;
    btn.disabled    = false;

    document.getElementById('gffActionCancelBtn').textContent = 'Cancel';

    const modalEl = document.getElementById('gffActionModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
}

/**
 * Called when the action button inside #gffActionModal is clicked.
 * Wired up once in $(document).ready().
 */
function _gffActionSubmit() {
    if (!_gffActionState) return;
    const opts  = _gffActionState;
    const attrs = document.getElementById('gffActionAttrs').value.trim();

    if (opts.requireAttrs && !attrs) {
        document.getElementById('gffActionAttrs').classList.add('is-invalid');
        return;
    }
    document.getElementById('gffActionAttrs').classList.remove('is-invalid');

    const { url, formData } = opts.buildRequest(attrs);

    // Update UI to running state
    const btn = document.getElementById('gffActionBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Running…';
    document.getElementById('gffActionCancelBtn').textContent = 'Close';

    const resultDiv = document.getElementById('gffActionResult');
    const logPre    = document.getElementById('gffActionLog');
    resultDiv.style.display = 'none';
    logPre.textContent = '';

    // Disable trigger button in the table row while running
    if (opts.triggerEl) {
        opts.triggerEl.disabled = true;
        opts._origHtml = opts.triggerEl.innerHTML;
        opts.triggerEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch(url, { method: 'POST', body: formData, headers: { 'X-CSRF-Token': csrfToken } })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                logPre.textContent = '✓ ' + opts.title + '\n\n' + opts.formatResult(data);
                logPre.className   = 'bg-success bg-opacity-10 border border-success rounded p-3 small mb-0';
            } else {
                const errMsg = data.error || 'Unknown error';
                const logLines = Array.isArray(data.log) ? data.log.join('\n') : (data.log || '');
                logPre.textContent = '✗ Error: ' + errMsg + (logLines ? '\n\n' + logLines : '');
                logPre.className   = 'bg-danger bg-opacity-10 border border-danger rounded p-3 small mb-0';
            }
            resultDiv.style.display = 'block';
        })
        .catch(err => {
            logPre.textContent = '✗ Network error: ' + err.message;
            logPre.className   = 'bg-danger bg-opacity-10 border border-danger rounded p-3 small mb-0';
            resultDiv.style.display = 'block';
        })
        .finally(() => {
            btn.disabled  = false;
            btn.textContent = opts.btnLabel;
            if (opts.triggerEl) {
                opts.triggerEl.disabled = false;
                opts.triggerEl.innerHTML = opts._origHtml;
            }
        });
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
 * Register an unregistered assembly in JBrowse.
 * Preps genome files and creates the assembly metadata JSON.
 */
function registerAssembly(organism, assembly, buttonEl) {
    const rowId = 'unregistered-row-' + organism + '_' + assembly;
    const logDiv = document.getElementById('registerLog');
    const logOutput = document.getElementById('registerLogOutput');

    buttonEl.disabled = true;
    buttonEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Registering...';

    logDiv.style.display = 'block';
    logOutput.textContent += `\n=== Registering ${organism} / ${assembly} ===\n`;

    const formData = new FormData();
    formData.append('organism', organism);
    formData.append('assembly', assembly);

    fetch(`/${siteUrl}/api/jbrowse2/admin_register_assembly.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            logOutput.textContent += data.output + '\n✓ Done\n';
            logOutput.scrollTop = logOutput.scrollHeight;

            // Remove the row and reload after a short delay so the user sees the log
            const row = document.getElementById(rowId);
            if (row) {
                row.style.opacity = '0.4';
                row.cells[row.cells.length - 1].innerHTML =
                    '<span class="text-success"><i class="fa fa-check"></i> Registered</span>';
            }
            setTimeout(() => window.location.reload(), 2000);
        } else {
            logOutput.textContent += '✗ Error: ' + data.error + '\n';
            logOutput.scrollTop = logOutput.scrollHeight;
            buttonEl.disabled = false;
            buttonEl.innerHTML = '<i class="fa fa-plus"></i> Register';
        }
    })
    .catch(error => {
        logOutput.textContent += '✗ ' + error.message + '\n';
        buttonEl.disabled = false;
        buttonEl.innerHTML = '<i class="fa fa-plus"></i> Register';
    });
}

// ============================================================
// Tracks Server Configuration
// ============================================================

let _jwtPublicKey = null;

/**
 * Load current tracks server config from server and populate form
 */
function loadTracksServerConfig() {
    const formData = new FormData();
    formData.append('action', 'get_config');

    fetch(`/${siteUrl}/api/jbrowse2/admin_tracks_server.php`, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { console.error('get_config failed:', data.error); return; }

            const cfg = data.tracks_server;
            document.getElementById('tracksServerEnabled').checked = cfg.enabled;
            document.getElementById('tracksServerUrl').value = cfg.url || '';

            _jwtPublicKey = data.jwt_public_key;

            const badge = document.getElementById('tracksServerBadge');
            if (cfg.enabled && cfg.url) {
                badge.textContent = 'Remote: ' + cfg.url;
                badge.className = 'badge bg-primary ms-2';
            } else {
                badge.textContent = 'Local';
                badge.className = 'badge bg-success ms-2';
            }

            if (data.jwt_key_exists) {
                document.getElementById('jwtStatusDisplay').innerHTML =
                    '<span class="text-success"><i class="fa fa-check-circle"></i> JWT key pair found</span>';
            } else {
                document.getElementById('jwtStatusDisplay').innerHTML =
                    '<span class="text-danger"><i class="fa fa-times-circle"></i> JWT keys missing — run: <code>openssl genrsa -out certs/jwt_private_key.pem 2048 && openssl rsa -in certs/jwt_private_key.pem -pubout -out certs/jwt_public_key.pem</code></span>';
            }
        })
        .catch(err => console.error('loadTracksServerConfig error:', err));
}

/**
 * Test JWT key pair
 */
function testJWT() {
    const formData = new FormData();
    formData.append('action', 'test_jwt');

    const statusDiv = document.getElementById('jwtStatusDisplay');
    statusDiv.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';

    fetch(`/${siteUrl}/api/jbrowse2/admin_tracks_server.php`, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = `
                    <span class="text-success"><i class="fa fa-check-circle"></i> ${data.message}</span>
                    <br><small class="text-muted">Scope: ${data.token_scope} | Expires: ${data.expires_in}</small>
                `;
            } else {
                statusDiv.innerHTML = `<span class="text-danger"><i class="fa fa-times-circle"></i> ${data.error}</span>`;
            }
        });
}

/**
 * Show JWT public key in the page
 */
function showJWTPublicKey() {
    const pre = document.getElementById('jwtPublicKeyDisplay');
    if (pre.style.display === 'none') {
        pre.style.display = 'block';
        pre.textContent = _jwtPublicKey || '(public key not loaded yet — click Reset to reload)';
    } else {
        pre.style.display = 'none';
    }
}

/**
 * Copy JWT public key to clipboard
 */
function copyJWTPublicKey() {
    if (!_jwtPublicKey) {
        alert('Public key not loaded. Click Reset to reload.');
        return;
    }
    navigator.clipboard.writeText(_jwtPublicKey).then(() => {
        alert('Public key copied to clipboard.');
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = _jwtPublicKey;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('Public key copied to clipboard.');
    });
}

/**
 * Setup form submission handlers
 */
$(document).ready(function() {
    // Auto-initialize on page load using global variables
    if (typeof jbrowseOrganisms !== 'undefined' && typeof sitePath !== 'undefined') {
        initJBrowseAdmin(jbrowseOrganisms, sitePath);
    }

    // Wire up the GFF action modal submit button (shared by Rebuild + Index Names)
    document.getElementById('gffActionBtn')?.addEventListener('click', _gffActionSubmit);

    // Load tracks server config when the card is expanded
    document.getElementById('tracksServerConfig')?.addEventListener('shown.bs.collapse', loadTracksServerConfig);
    // Also load on page ready so badge is correct
    if (typeof siteUrl !== 'undefined') { loadTracksServerConfig(); }

    // Tracks server form
    $('#tracksServerForm').on('submit', function(e) {
        e.preventDefault();
        const enabled = document.getElementById('tracksServerEnabled').checked;
        const url     = document.getElementById('tracksServerUrl').value.trim();

        const formData = new FormData();
        formData.append('action', 'save_config');
        formData.append('enabled', enabled ? '1' : '0');
        formData.append('url', url);

        const resultDiv = document.getElementById('tracksServerResult');
        resultDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Saving...</div>';
        resultDiv.style.display = 'block';

        fetch(`/${siteUrl}/api/jbrowse2/admin_tracks_server.php`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> ${data.message}</div>`;
                    loadTracksServerConfig();
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ${data.error}</div>`;
                }
            });
    });
    
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
