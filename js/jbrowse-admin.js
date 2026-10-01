/**
 * JBrowse Admin Dashboard - JavaScript Functions
 * 
 * Functions for managing JBrowse tracks via admin interface.
 * 
 * Expects global variables from inline_scripts:
 * - jbrowseOrganisms: object with organism => [assemblies]
 * - jbrowseAssemblyRows: one entry per registered assembly (gene sets + sheet status)
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
        const nameMap = (typeof jbrowseAssemblyNames !== 'undefined' && jbrowseAssemblyNames[organism]) ? jbrowseAssemblyNames[organism] : {};
        source[organism].forEach(asm => {
            const option = document.createElement('option');
            option.value = asm;
            option.textContent = nameMap[asm] ? nameMap[asm] : asm;
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
            url: `/${siteUrl}/admin/api/jbrowse_list_tracks.php`,
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
        pageLength: 25,
        order: [[1, 'asc']]
    });

    // Delegated: the rows are redrawn on every page/filter change.
    $('#tracksTable tbody').on('click', 'button[data-action="view-track"]', function () {
        viewTrack(tracksTable.row($(this).closest('tr')).data().details);
    });
    $('#tracksTable tbody').on('click', 'button[data-action="delete-track"]', function () {
        deleteTracks([tracksTable.row($(this).closest('tr')).data().details]);
    });
    // Selection is read from THIS table's rows, not from the document.
    $('#bulkDeleteBtn').on('click', function () {
        deleteTracks($(tracksTable.rows().nodes()).filter(':has(input[name="trackSelect"]:checked)')
            .map(function () { return tracksTable.row(this).data().details; }).get());
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
    document.getElementById('bulkDeleteBtn').disabled = selected === 0;
}

/**
 * View track details: what the track's config file actually holds — where its data lives,
 * who can see it, where it came from, and every value carried over from the sheet row.
 * `details` comes from the listing row itself (jbrowse_list_tracks.php), not a second request.
 */
function viewTrack(details) {
    const track = details.track || {};
    const meta = track.metadata || {};
    const node = (tag, className, text) => {
        const n = document.createElement(tag);
        if (className) n.className = className;
        if (text !== undefined) n.textContent = text;
        return n;
    };

    // Every data/index location in the adapter, whatever the track type calls it.
    const uris = [];
    (function collect(value) {
        if (!value || typeof value !== 'object') return;
        Object.entries(value).forEach(([key, v]) => {
            if (key === 'uri' && typeof v === 'string') { if (!uris.includes(v)) uris.push(v); }
            else collect(v);
        });
    })(track.adapter);

    const list = node('dl', 'row small mb-3');
    const add = (label, value, asCode) => {
        if (value === undefined || value === null || value === '') return;
        list.append(node('dt', 'col-sm-3 text-muted fw-normal', label));
        const dd = node('dd', 'col-sm-9 mb-1 text-break');
        (Array.isArray(value) ? value : [value]).forEach(v => dd.append(node(asCode ? 'code' : 'span', asCode ? 'd-block' : '', String(v))));
        list.append(dd);
    };
    add('Organism / assembly', `${details.organism.replace(/_/g, ' ')} / ${details.assembly}`);
    add('Came from', details.origin);
    add('Access', meta.access_level || 'PUBLIC (not set)');
    add('Category', (track.category || []).join(' › '));
    add('Track type', [track.type, (track.adapter || {}).type].filter(Boolean).join(' · '));
    add('Description', meta.description);
    add('Added', meta.added_date);
    add('File size', typeof meta.file_size === 'number' ? meta.file_size.toLocaleString() + ' bytes' : meta.file_size);
    add('Reads', meta.total_reads !== undefined ? `${Number(meta.total_reads).toLocaleString()} total, ${Number(meta.mapped_reads || 0).toLocaleString()} mapped` : '');
    add(uris.length === 1 ? 'Data file' : `Data files (${uris.length})`, uris, true);
    add('JBrowse track id', track.trackId, true);
    add('Config file', details.config_file, true);

    const body = document.getElementById('trackDetailsBody');
    body.replaceChildren(list);

    const sheet = meta.google_sheets_metadata;
    if (sheet && Object.keys(sheet).length) {
        body.append(node('h6', 'mt-2', 'From the sheet row'));
        const table = node('table', 'table table-sm small mb-3');
        const tbody = node('tbody');
        Object.entries(sheet).forEach(([key, value]) => {
            const tr = node('tr');
            tr.append(node('th', 'text-muted fw-normal text-nowrap', key.replace(/_/g, ' ')),
                      node('td', 'text-break', typeof value === 'object' ? JSON.stringify(value) : String(value)));
            tbody.append(tr);
        });
        table.append(tbody);
        body.append(table);
    }

    const raw = node('details', 'small');
    raw.append(node('summary', 'text-muted', 'Full config (JSON)'));
    const pre = node('pre', 'border rounded p-2 bg-light mt-2 mb-0', JSON.stringify(track, null, 2));
    pre.style.cssText = 'max-height:300px;overflow:auto;';
    raw.append(pre);
    body.append(raw);

    document.getElementById('trackDetailsTitle').textContent = track.name || track.trackId || 'Track details';
    const modalEl = document.getElementById('trackDetailsModal');
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
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
        desc:        'Re-runs bgzip and tabix on the source genes.gff. ' +
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
            return { url: `/${siteUrl}/admin/api/jbrowse_reprep_gff.php`, formData: fd };
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
            return { url: `/${siteUrl}/admin/api/jbrowse_text_index.php`, formData: fd };
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
 * Delete track configs. Each is identified by the file it lives in (details.type_dir +
 * details.file_id, from the listing row), never by the JBrowse trackId — the two differ
 * for every track type but combo, which is why Delete used to find nothing.
 *
 * @param {Array} items details objects from the listing rows
 */
function deleteTracks(items) {
    if (!items.length) return;
    const what = items.length === 1 ? `track "${items[0].track.name || items[0].file_id}"` : `${items.length} selected tracks`;
    if (!confirm(`Delete ${what}?\n\nThis removes the track's config file, so it no longer appears in JBrowse. ` +
                 `The data file itself is not touched. If the track is still in its sheet, the next sync brings it back.`)) {
        return;
    }

    const requests = items.map(details => {
        const formData = new FormData();
        formData.append('organism', details.organism);
        formData.append('assembly', details.assembly);
        formData.append('type', details.type_dir);
        formData.append('fileId', details.file_id);
        return fetch(`/${siteUrl}/admin/api/jbrowse_delete_tracks.php`, { method: 'POST', body: formData })
            .then(response => response.json())
            .catch(error => ({ success: false, error: error.message }));
    });

    Promise.all(requests).then(results => {
        const failed = results.filter(r => !r.success);
        const deleted = results.length - failed.length;
        let message = `Deleted ${deleted} track${deleted === 1 ? '' : 's'}.`;
        if (failed.length) {
            message += `\n\n${failed.length} could not be deleted:\n` + [...new Set(failed.map(r => r.error))].slice(0, 5).join('\n');
        }
        alert(message);
        document.getElementById('selectAll').checked = false;
        if (tracksTable) tracksTable.ajax.reload(updateBulkButtons, false);
    });
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

    fetch(`/${siteUrl}/admin/api/jbrowse_register_assembly.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(t => { throw new Error(`HTTP ${response.status}: ${t.substring(0, 200)}`); });
        }
        return response.json();
    })
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
            // Land on the sheet form with this assembly already chosen, so the next step
            // is one paste away rather than three cards and two dropdowns away.
            const next = new URL(window.location.href);
            next.searchParams.set('sheet_for', organism + '/' + assembly);
            next.hash = 'assemblyTracks';
            setTimeout(() => window.location.assign(next.toString()), 2000);
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

    fetch(`/${siteUrl}/admin/api/jbrowse_tracks_server.php`, { method: 'POST', body: formData })
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

    fetch(`/${siteUrl}/admin/api/jbrowse_tracks_server.php`, { method: 'POST', body: formData })
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

        fetch(`/${siteUrl}/admin/api/jbrowse_tracks_server.php`, { method: 'POST', body: formData })
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
});

// ---------------------------------------------------------------------------
// Unregister a broken JBrowse registration (source data renamed/removed).
//
// The X-CSRF-Token header here is belt-and-braces: js/modules/csrf.js wraps window.fetch
// globally and adds the token to every non-GET request, and it skips any request that
// already carries one. Setting it explicitly keeps this call working even if that module
// fails to load.
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.unregister-assembly-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const organism = btn.dataset.organism;
            const assembly = btn.dataset.assembly;

            if (!confirm(
                `Unregister ${organism} / ${assembly} from JBrowse?\n\n` +
                `This removes only what registration created (the data/genomes link ` +
                `directory and the registry entry).\n\n` +
                `Nothing under organisms/ is touched, and existing track configuration is kept.`
            )) return;

            const row = document.getElementById('orphan-' + organism + '_' + assembly);
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Removing…';

            const formData = new FormData();
            formData.append('organism', organism);
            formData.append('assembly', assembly);

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            fetch(`/${siteUrl}/admin/api/unregister_assembly.php`, {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': csrfToken }
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(t => {
                        throw new Error(`HTTP ${response.status}: ${t.substring(0, 200)}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const removed = (data.removed || []).join(', ') || 'nothing';
                    const kept = (data.kept || []).length ? ` — kept: ${data.kept.join(', ')}` : '';
                    if (row) {
                        row.style.opacity = '0.5';
                        row.cells[row.cells.length - 1].innerHTML =
                            '<span class="text-success"><i class="fa fa-check"></i> Unregistered</span>';
                        row.cells[2].innerHTML =
                            `<small class="text-success">Removed: ${removed}${kept}</small>`;
                    }
                    setTimeout(() => window.location.reload(), 2500);
                } else {
                    alert('Could not unregister: ' + (data.error || data.message || 'unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            })
            .catch(error => {
                alert('Request failed: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = original;
            });
        });
    });
});

// ---------------------------------------------------------------------------
// Assemblies & Tracks table
//
// One row per registered assembly: its gene annotation track and its track sheet. This
// replaced three cards (gene sets, register sheet, sync) that each asked for the organism
// and assembly again. Rows are rendered here from jbrowseAssemblyRows, and every action
// updates the row OBJECT and redraws it — nothing reads or writes a cell by position.
// ---------------------------------------------------------------------------
(function () {
    const rows = (typeof jbrowseAssemblyRows !== 'undefined') ? jbrowseAssemblyRows : [];
    const api = name => `/${siteUrl || sitePath}/admin/api/${name}`;
    const rowKey = row => row.organism + '/' + row.assembly;
    const findRow = key => rows.find(row => rowKey(row) === key);

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function iconButton(className, icon, label, action, title) {
        const btn = el('button', className);
        btn.type = 'button';
        btn.dataset.action = action;
        if (title) btn.title = title;
        btn.append(el('i', 'fa ' + icon), ' ' + label);
        return btn;
    }

    function post(endpoint, fields) {
        const body = new FormData();
        Object.entries(fields).forEach(([name, value]) => body.append(name, value));
        return fetch(api(endpoint), { method: 'POST', body }).then(response => {
            if (!response.ok) {
                return response.text().then(t => { throw new Error(`HTTP ${response.status}: ${t.substring(0, 200)}`); });
            }
            return response.json();
        });
    }

    const geneSetNeedsAttention = gs => !gs.is_registered || !gs.gff_prepped;
    const hasSheet = row => !!row.sheet.sheet_id;
    const sheetLink = sheet => `https://docs.google.com/spreadsheets/d/${encodeURIComponent(sheet.sheet_id)}/edit#gid=${encodeURIComponent(sheet.gid || '0')}`;

    // ── rendering ───────────────────────────────────────────────────────────

    function geneTrackCell(row) {
        const td = el('td');
        if (!row.gene_sets.length) {
            td.append(el('span', 'text-muted small', 'no gene set'));
            return td;
        }
        row.gene_sets.forEach(gs => {
            const line = el('div', 'd-flex align-items-center gap-2 text-nowrap small');
            line.dataset.geneSet = gs.gene_set;
            if (!gs.is_registered) {
                line.append(el('i', 'fa fa-exclamation-circle text-warning'), el('code', '', gs.gene_set),
                            el('span', 'small text-warning', 'not registered'));
                const btn = iconButton('btn btn-sm btn-primary py-0', 'fa-plus', 'Register', 'gs-register');
                if (gs.gff_size === 0) { btn.disabled = true; btn.title = 'GFF is empty'; }
                line.append(btn);
            } else {
                line.append(el('i', 'fa ' + (gs.gff_prepped ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-warning')),
                            el('code', '', gs.gene_set));
                if (!gs.gff_prepped) line.append(el('span', 'small text-warning', 'not prepped'));
                line.append(iconButton('btn btn-sm btn-link p-0 small text-decoration-none', 'fa-redo', 'Re-prep', 'gs-reprep',
                                       'Rebuild the compressed, indexed GFF from the current genes.gff'));
            }
            td.append(line);
        });
        return td;
    }

    function sheetCell(row) {
        const td = el('td', 'small');
        const tracks = row.sheet.track_count;
        const trackText = tracks.toLocaleString() + (tracks === 1 ? ' track' : ' tracks');
        if (!hasSheet(row)) {
            td.append(el('span', 'text-muted', tracks > 0 ? `no sheet · ${trackText}` : 'none'));
            return td;
        }
        const link = el('a', '', 'sheet');
        link.href = sheetLink(row.sheet);
        link.target = '_blank';
        link.rel = 'noopener';
        link.title = 'Open the Google Sheet';
        link.append(' ', el('i', 'fa fa-external-link-alt'));
        const synced = row.sheet.last_sync ? `synced ${row.sheet.last_sync.slice(0, 10)}`
                     : tracks > 0 ? `added ${(row.sheet.registered || '').slice(0, 10)}`
                     : 'not synced yet';
        td.append(link, ` · ${trackText} · `, el('span', row.sheet.last_sync || tracks > 0 ? 'text-muted' : 'text-warning', synced));
        return td;
    }

    function actionCell(row) {
        const td = el('td', 'text-end text-nowrap');
        if (hasSheet(row)) {
            td.append(iconButton('btn btn-sm btn-outline-primary', 'fa-sync', 'Sync', 'sync'), ' ',
                      iconButton('btn btn-sm btn-outline-secondary', 'fa-pen', 'Edit sheet', 'sheet'));
        } else {
            td.append(iconButton('btn btn-sm btn-outline-secondary', 'fa-plus', 'Add sheet', 'sheet'));
        }
        return td;
    }

    function renderRow(row) {
        const tr = el('tr');
        tr.dataset.key = rowKey(row);
        const asm = el('td', 'small');
        asm.append(el('code', '', row.assembly));
        // The display name is shown only when it adds something; most are just
        // "Organism (assembly)", which the two columns already say.
        const generic = `${row.organism.replace(/_/g, ' ')} (${row.assembly})`;
        if (row.name && row.name !== row.assembly && row.name !== generic) asm.append(el('div', 'text-muted', row.name));
        tr.append(el('td', 'small', row.organism.replace(/_/g, ' ')), asm, geneTrackCell(row), sheetCell(row), actionCell(row));
        return tr;
    }

    function redrawRow(row) {
        const tbody = document.querySelector('#asmTable tbody');
        const old = [...tbody.rows].find(tr => tr.dataset.key === rowKey(row));
        const fresh = renderRow(row);
        if (old) old.replaceWith(fresh); else tbody.append(fresh);
        applyFilter();
    }

    function rowMatches(row, text, onlySheet, onlyAttention) {
        if (onlySheet && !hasSheet(row)) return false;
        if (onlyAttention && !row.gene_sets.some(geneSetNeedsAttention)) return false;
        return !text || (row.organism + ' ' + row.assembly + ' ' + (row.name || '')).toLowerCase().replace(/_/g, ' ')
            .includes(text.toLowerCase().replace(/_/g, ' '));
    }

    function applyFilter() {
        const text = document.getElementById('asmFilter').value.trim();
        const onlySheet = document.getElementById('asmOnlySheet').checked;
        const onlyAttention = document.getElementById('asmOnlyAttention').checked;
        let shown = 0;
        document.querySelectorAll('#asmTable tbody tr').forEach(tr => {
            const visible = rowMatches(findRow(tr.dataset.key), text, onlySheet, onlyAttention);
            tr.hidden = !visible;
            if (visible) shown++;
        });
        document.getElementById('asmRowCount').textContent = shown === rows.length ? rows.length : `${shown} of ${rows.length}`;
        document.getElementById('asmEmpty').style.display = shown ? 'none' : '';
    }

    // ── shared log panel ────────────────────────────────────────────────────

    function showLog(kind, message, output) {
        const icons = { info: 'fa-spinner fa-spin', success: 'fa-check-circle', warning: 'fa-exclamation-triangle', danger: 'fa-times-circle' };
        const status = document.getElementById('asmLogStatus');
        status.className = `alert alert-${kind} py-2 mb-2`;
        status.replaceChildren(el('i', 'fa ' + icons[kind]), ' ' + message);
        const pre = document.getElementById('asmLogOutput');
        pre.textContent = output || '';
        pre.style.display = output ? '' : 'none';
        pre.scrollTop = pre.scrollHeight;
        document.getElementById('asmLog').style.display = '';
    }

    function busy(btn, on) {
        if (on) {
            btn._html = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        } else if (btn.isConnected) {
            btn.disabled = false;
            btn.innerHTML = btn._html;
        }
    }

    // ── sync ────────────────────────────────────────────────────────────────

    function syncFields(overrides) {
        const fields = {};
        if (document.getElementById('optRewrite').checked) fields.forceRegenerate = 'on';
        if (document.getElementById('optRemove').checked) fields.removeMissing = 'on';
        if (document.getElementById('optDryRun').checked) fields.dryRun = 'on';
        return Object.assign(fields, overrides);
    }

    // Apply what the server now reports for each assembly it touched, then redraw those rows.
    function applySyncResults(data) {
        (data.results || []).forEach(result => {
            const row = findRow(result.organism + '/' + result.assembly);
            if (row && result.sheet) { row.sheet = result.sheet; redrawRow(row); }
        });
        if (tracksTable) tracksTable.ajax.reload();
    }

    function runSync(label, fields, btn) {
        const dry = !!fields.dryRun;
        busy(btn, true);
        showLog('info', `${dry ? 'Dry run' : 'Syncing'} ${label} — a large sheet can take a minute…`);
        return post('jbrowse_sync_tracks.php', fields)
            .then(data => {
                applySyncResults(data);
                if (data.success) {
                    showLog('success', dry ? `Dry run for ${label} finished — nothing was changed.` : `Tracks synced for ${label}.`, data.output);
                } else {
                    showLog('warning', `Sync of ${label} reported a problem: ${data.error || (data.errors || []).join('; ')}`, data.output);
                }
                return data;
            })
            .catch(error => showLog('danger', `Sync of ${label} failed: ${error.message}`))
            .finally(() => busy(btn, false));
    }

    // ── gene set register / re-prep ─────────────────────────────────────────

    function geneSetAction(row, geneSetName, endpoint, btn) {
        const gs = row.gene_sets.find(g => g.gene_set === geneSetName);
        const label = `${row.organism} / ${row.assembly} / ${geneSetName}`;
        busy(btn, true);
        showLog('info', `Preparing the gene track for ${label}…`);
        post(endpoint, { organism: row.organism, assembly: row.assembly, gene_set: geneSetName, text_index: '0' })
            .then(data => {
                if (data.success) {
                    gs.is_registered = true;
                    gs.gff_prepped = true;
                    showLog('success', `Gene track ready for ${label}.`, data.output);
                    redrawRow(row);
                } else {
                    showLog('danger', `Gene track for ${label} failed: ${data.error || 'unknown error'}`, data.output);
                    busy(btn, false);
                }
            })
            .catch(error => { showLog('danger', `Request failed: ${error.message}`); busy(btn, false); });
    }

    // ── sheet modal ─────────────────────────────────────────────────────────

    let modalRow = null;

    function modalStatus(kind, message, output) {
        const icons = { info: 'fa-spinner fa-spin', success: 'fa-check-circle', warning: 'fa-exclamation-triangle', danger: 'fa-times-circle' };
        const status = document.getElementById('sheetModalStatus');
        status.className = `alert alert-${kind} py-2 mb-2`;
        status.replaceChildren(el('i', 'fa ' + icons[kind]), ' ' + message);
        const pre = document.getElementById('sheetModalLog');
        pre.textContent = output || '';
        pre.style.display = output ? '' : 'none';
        pre.scrollTop = pre.scrollHeight;
        document.getElementById('sheetModalResult').style.display = '';
    }

    function openSheetModal(row, intro) {
        modalRow = row;
        document.getElementById('sheetModalTitle').textContent =
            `${hasSheet(row) ? 'Edit' : 'Add'} track sheet — ${row.organism.replace(/_/g, ' ')} / ${row.assembly}`;
        document.getElementById('sheetUrl').value = hasSheet(row) ? sheetLink(row.sheet) : '';
        document.getElementById('sheetGid').value = row.sheet.gid || '0';
        document.getElementById('sheetModalResult').style.display = 'none';
        const introDiv = document.getElementById('sheetModalIntro');
        introDiv.textContent = intro || '';
        introDiv.style.display = intro ? '' : 'none';

        const modalEl = document.getElementById('sheetModal');
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }

    function sheetFields(action) {
        return {
            action,
            organism: modalRow.organism,
            assembly: modalRow.assembly,
            sheetUrl: document.getElementById('sheetUrl').value.trim(),
            gid: document.getElementById('sheetGid').value.trim() || '0',
        };
    }

    function testSheetLink() {
        const btn = document.getElementById('sheetTestBtn');
        if (!document.getElementById('sheetForm').reportValidity()) return;
        busy(btn, true);
        modalStatus('info', 'Checking the sheet…');
        post('jbrowse_register_sheet.php', sheetFields('test'))
            .then(data => data.success
                ? modalStatus('success', `Sheet is readable, has the required columns, and lists ${data.trackCount} rows. Nothing has been saved yet.`)
                : modalStatus('danger', data.error || 'The sheet could not be read.'))
            .catch(error => modalStatus('danger', error.message))
            .finally(() => busy(btn, false));
    }

    // Saving a sheet also syncs it: a saved-but-never-synced sheet does nothing at all.
    function saveSheetAndSync(event) {
        event.preventDefault();
        const row = modalRow;
        const label = `${row.organism} / ${row.assembly}`;
        const btn = document.getElementById('sheetSaveBtn');
        busy(btn, true);
        modalStatus('info', 'Step 1 of 2: checking and saving the sheet…');

        post('jbrowse_register_sheet.php', sheetFields('register'))
            .then(data => {
                if (!data.success) throw new Error(data.error || 'The sheet could not be saved.');
                row.sheet = data.sheet;
                redrawRow(row);
                modalStatus('info', `Sheet saved (${data.trackCount} rows). Step 2 of 2: creating tracks — a large sheet can take a minute…`);
                return post('jbrowse_sync_tracks.php', {
                    syncMode: 'single', syncOrganism: row.organism, syncAssembly: row.assembly, forceRegenerate: 'on',
                });
            })
            .then(sync => {
                applySyncResults(sync);
                if (sync.success) {
                    modalStatus('success', `Sheet saved and tracks synced for ${label}.`, sync.output);
                } else {
                    modalStatus('warning', 'The sheet was saved, but the sync reported a problem: ' +
                        (sync.error || (sync.errors || []).join('; ')), sync.output);
                }
            })
            .catch(error => modalStatus('danger', error.message))
            .finally(() => busy(btn, false));
    }

    // ── wiring ──────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById('asmTable');
        if (!table) return;

        table.querySelector('tbody').append(...rows.map(renderRow));
        ['asmFilter', 'asmOnlySheet', 'asmOnlyAttention'].forEach(id =>
            document.getElementById(id).addEventListener('input', applyFilter));
        applyFilter();

        // One delegated handler: rows are redrawn, so per-button listeners would be lost.
        table.addEventListener('click', function (event) {
            const btn = event.target.closest('button[data-action]');
            if (!btn) return;
            const row = findRow(btn.closest('tr').dataset.key);
            const label = `${row.organism} / ${row.assembly}`;
            switch (btn.dataset.action) {
                case 'sheet':
                    openSheetModal(row);
                    break;
                case 'sync':
                    runSync(label, syncFields({ syncMode: 'single', syncOrganism: row.organism, syncAssembly: row.assembly }), btn);
                    break;
                case 'gs-register':
                    geneSetAction(row, btn.closest('[data-gene-set]').dataset.geneSet, 'jbrowse_register_gene_set.php', btn);
                    break;
                case 'gs-reprep':
                    geneSetAction(row, btn.closest('[data-gene-set]').dataset.geneSet, 'jbrowse_reprep_gff.php', btn);
                    break;
            }
        });

        document.getElementById('syncAllBtn').addEventListener('click', function () {
            const withSheet = rows.filter(hasSheet).length;
            if (!withSheet) { showLog('warning', 'No assembly has a track sheet yet.'); return; }
            const fields = syncFields({ syncMode: 'all' });
            if (!fields.dryRun && !confirm(`Sync all ${withSheet} track sheets now?` +
                (fields.removeMissing ? '\n\nTracks no longer in their sheet will be removed.' : ''))) return;
            runSync(`all ${withSheet} sheets`, fields, this);
        });

        document.getElementById('sheetForm').addEventListener('submit', saveSheetAndSync);
        document.getElementById('sheetTestBtn').addEventListener('click', testSheetLink);

        // Arriving from an assembly registration (?sheet_for=Organism/Assembly): ask for its
        // sheet straight away — the only step left.
        const sheetFor = new URLSearchParams(window.location.search).get('sheet_for');
        const arrived = sheetFor && findRow(sheetFor);
        if (arrived) {
            document.getElementById('asmFilter').value = arrived.organism.replace(/_/g, ' ');
            applyFilter();
            document.getElementById('assemblyTracks').scrollIntoView({ block: 'start' });
            openSheetModal(arrived, `${arrived.organism.replace(/_/g, ' ')} / ${arrived.assembly} is registered and its gene track is in place. ` +
                'If it has other tracks (RNA-seq, alignments…), paste the Google Sheet link and press Save & sync tracks. If not, close this — you are done.');
            document.getElementById('sheetModal').addEventListener('shown.bs.modal',
                () => document.getElementById('sheetUrl').focus(), { once: true });
        }
    });
})();
