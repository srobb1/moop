<?php
/**
 * JBROWSE MANAGEMENT - Content File
 *
 * Available variables (extracted from $data by render_display_page):
 * - $config, $site
 * - $organisms              array of ALL organism => [assemblies] on disk
 * - $registered_assemblies  array of organism => [assemblies] registered in JBrowse
 * - $registered_count       int
 * - $unregistered_assemblies array of ['organism','assembly','has_genome']
 * - $track_stats            ['total','by_type','by_access','warnings']
 */
?>

<div class="container mt-5">
  <h2><i class="fa fa-dna"></i> JBrowse Track Management</h2>

  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutJBrowse">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About JBrowse Management <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutJBrowse">
      <div class="card-body">
        <p><strong>Purpose:</strong> Centralized management for JBrowse assemblies, tracks, and configurations.</p>
        <p><strong>Workflow:</strong></p>
        <ol>
          <li>Register an assembly in JBrowse (prepares genome files)</li>
          <li>Register a Google Sheet URL as the track source for that assembly</li>
          <li>Sync tracks from the sheet to populate the track listing</li>
        </ol>
      </div>
    </div>
  </div>

  <!-- Quick Stats -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h3 class="text-primary"><?php echo $registered_count; ?></h3>
          <p class="mb-0">Registered Assemblies</p>
          <?php if (!empty($unregistered_assemblies)): ?>
          <small class="text-warning"><?php echo count($unregistered_assemblies); ?> unregistered</small>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h3 class="text-success"><?php echo $track_stats['total']; ?></h3>
          <p class="mb-0">Total Tracks</p>
          <small class="text-muted">Updated: <?php echo date('M d, Y H:i'); ?></small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h3 class="text-info"><?php echo count($track_stats['by_type']); ?></h3>
          <p class="mb-0">Track Types</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h3 class="<?php echo $track_stats['warnings'] > 0 ? 'text-warning' : 'text-muted'; ?>">
            <?php echo $track_stats['warnings']; ?>
          </h3>
          <p class="mb-0">Warnings</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Register Assemblies (collapsible, starts closed) -->
  <?php if (!empty($unregistered_assemblies)): ?>
  <div class="card mb-4 border-primary">
    <div class="card-header bg-primary bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#registerAssemblies">
      <h5 class="mb-0">
        <i class="fa fa-plus-circle"></i> Register Assemblies in JBrowse
        <span class="badge bg-primary ms-2"><?php echo count($unregistered_assemblies); ?></span>
        <i class="fa fa-chevron-down float-end"></i>
      </h5>
    </div>
    <div class="collapse" id="registerAssemblies">
      <div class="card-body">
        <p class="text-muted">
          <i class="fa fa-info-circle"></i>
          These assemblies exist on disk but are not yet registered in JBrowse.
          Registering prepares genome files (FASTA index, compressed GFF) and creates the assembly config.
          After registering, use <strong>Register Google Sheet</strong> below to add tracks.
        </p>
        <table class="table table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>Organism</th>
              <th>Assembly</th>
              <th>genome.fa</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($unregistered_assemblies as $item): ?>
            <tr id="unregistered-row-<?php echo htmlspecialchars($item['organism'] . '_' . $item['assembly']); ?>">
              <td><?php echo htmlspecialchars($item['organism']); ?></td>
              <td><?php echo htmlspecialchars($item['assembly']); ?></td>
              <td>
                <?php if ($item['has_genome']): ?>
                  <span class="text-success"><i class="fa fa-check"></i></span>
                <?php else: ?>
                  <span class="text-danger"><i class="fa fa-times"></i> missing</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($item['has_genome']): ?>
                <button class="btn btn-sm btn-primary"
                        data-organism="<?php echo htmlspecialchars($item['organism']); ?>"
                        data-assembly="<?php echo htmlspecialchars($item['assembly']); ?>"
                        onclick="registerAssembly(this.dataset.organism, this.dataset.assembly, this)">
                  <i class="fa fa-plus"></i> Register
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-secondary" disabled title="genome.fa required">Register</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div id="registerLog" class="mt-3" style="display:none;">
          <pre class="border rounded p-3 bg-light mb-0" id="registerLogOutput" style="max-height:200px; overflow-y:auto;"></pre>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Register Google Sheets Track Source (starts open) -->
  <div class="card mb-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#sheetRegistration">
      <h5 class="mb-0">
        <i class="fa fa-table"></i> Register Google Sheets Track Source
        <i class="fa fa-chevron-down float-end"></i>
      </h5>
    </div>
    <div class="collapse show" id="sheetRegistration">
      <div class="card-body">
        <div class="alert alert-info py-2">
          <i class="fa fa-lightbulb"></i>
          Only registered assemblies appear here. If an organism is missing, expand <strong>Register Assemblies in JBrowse</strong> above and register it first.
        </div>
        <form id="registerSheetForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="organism" class="form-label">Organism <span class="text-danger">*</span></label>
              <select class="form-select" id="organism" name="organism" required>
                <option value="">Select organism...</option>
                <?php foreach ($registered_assemblies as $org => $assemblies): ?>
                <option value="<?php echo htmlspecialchars($org); ?>"><?php echo htmlspecialchars($org); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="assembly" class="form-label">Assembly <span class="text-danger">*</span></label>
              <select class="form-select" id="assembly" name="assembly" required disabled>
                <option value="">Select organism first...</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label for="sheetUrl" class="form-label">Google Sheet URL or ID <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="sheetUrl" name="sheetUrl"
                   placeholder="https://docs.google.com/spreadsheets/d/SHEET_ID/... or just SHEET_ID" required>
            <small class="text-muted">Enter full URL or just the Sheet ID</small>
          </div>

          <div class="mb-3">
            <label for="gid" class="form-label">GID (Sheet Tab) <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="gid" name="gid" value="0" required>
            <small class="text-muted">Tab identifier (usually 0 for first tab, found in URL: gid=XXXXX)</small>
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="autoSync" name="autoSync" checked>
            <label class="form-check-label" for="autoSync">Auto-sync tracks when generating configs</label>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="testSheet()">
              <i class="fa fa-check-circle"></i> Test Connection
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-save"></i> Register Sheet
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="clearSheetForm()">
              <i class="fa fa-times"></i> Clear
            </button>
          </div>
        </form>

        <div id="sheetValidationResult" class="mt-3" style="display: none;"></div>
      </div>
    </div>
  </div>

  <!-- Sync Tracks (starts closed) -->
  <div class="card mb-4 border-success">
    <div class="card-header bg-success bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#syncTracks">
      <h5 class="mb-0">
        <i class="fa fa-sync"></i> Sync Tracks from Google Sheets
        <i class="fa fa-chevron-down float-end"></i>
      </h5>
    </div>
    <div class="collapse" id="syncTracks">
      <div class="card-body">
        <p class="text-muted">
          <i class="fa fa-info-circle"></i>
          After registering a Google Sheet or editing it, sync here to update the track listing.
          Configs are generated per-user automatically when they load JBrowse.
        </p>
        <form id="syncTracksForm">
          <div class="mb-3">
            <label class="form-label">Sync Mode</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="syncMode" id="syncModeAll" value="all">
              <label class="form-check-label" for="syncModeAll">
                Sync All Registered Sheets
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="syncMode" id="syncModeSingle" value="single" checked>
              <label class="form-check-label" for="syncModeSingle">
                Single Assembly
              </label>
            </div>
          </div>

          <div class="row mb-3" id="singleAssemblySelect">
            <div class="col-md-6">
              <label for="syncOrganism" class="form-label">Organism</label>
              <select class="form-select" id="syncOrganism" name="syncOrganism">
                <option value="">Select organism...</option>
                <?php foreach ($registered_assemblies as $org => $assemblies): ?>
                <option value="<?php echo htmlspecialchars($org); ?>"><?php echo htmlspecialchars($org); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="syncAssembly" class="form-label">Assembly</label>
              <select class="form-select" id="syncAssembly" name="syncAssembly" disabled>
                <option value="">Select organism first...</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Options</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="forceRegenerate" name="forceRegenerate" checked>
              <label class="form-check-label" for="forceRegenerate">Force regenerate all tracks (ignore existing)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="dryRun" name="dryRun">
              <label class="form-check-label" for="dryRun">Dry run (preview changes without saving)</label>
            </div>
          </div>

          <button type="submit" class="btn btn-success">
            <i class="fa fa-sync"></i> Sync Tracks
          </button>
        </form>

        <div id="syncProgress" class="mt-3" style="display: none;">
          <div class="progress">
            <div id="syncProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                 role="progressbar" style="width: 0%"></div>
          </div>
          <div id="syncLog" class="mt-2 border rounded p-3" style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">
            <pre class="mb-0" id="syncLogOutput"></pre>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tracks Server Configuration -->
  <div class="card mb-4 border-dark">
    <div class="card-header bg-dark bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#tracksServerConfig">
      <h5 class="mb-0">
        <i class="fa fa-server"></i> Tracks Server Configuration
        <span id="tracksServerBadge" class="badge bg-secondary ms-2">Loading...</span>
        <i class="fa fa-chevron-down float-end"></i>
      </h5>
    </div>
    <div class="collapse" id="tracksServerConfig">
      <div class="card-body">
        <p class="text-muted">
          <i class="fa fa-info-circle"></i>
          Track data files can be served from this MOOP server or a dedicated remote tracks server.
          All track requests are authenticated with short-lived JWT tokens signed by your private key.
          The remote server only needs the <strong>public key</strong> — never share the private key.
        </p>

        <div class="row mb-3">
          <div class="col-md-6">
            <div class="card border-light bg-light p-3 mb-3">
              <h6><i class="fa fa-lock"></i> JWT Status</h6>
              <div id="jwtStatusDisplay">
                <span class="text-muted"><i class="fa fa-spinner fa-spin"></i> Checking...</span>
              </div>
              <button class="btn btn-sm btn-outline-secondary mt-2" onclick="testJWT()">
                <i class="fa fa-check-circle"></i> Test JWT Key Pair
              </button>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-light bg-light p-3 mb-3">
              <h6><i class="fa fa-key"></i> JWT Public Key</h6>
              <p class="text-muted small mb-2">Copy this to your remote tracks server's <code>certs/jwt_public_key.pem</code></p>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="showJWTPublicKey()">
                  <i class="fa fa-eye"></i> Show Public Key
                </button>
                <button class="btn btn-sm btn-outline-success" onclick="copyJWTPublicKey()">
                  <i class="fa fa-copy"></i> Copy to Clipboard
                </button>
              </div>
              <pre id="jwtPublicKeyDisplay" class="mt-2 p-2 border rounded bg-white small" style="display:none; max-height:120px; overflow-y:auto; font-size:0.7rem; word-break:break-all;"></pre>
            </div>
          </div>
        </div>

        <form id="tracksServerForm">
          <div class="mb-3 form-check form-switch">
            <input class="form-check-input" type="checkbox" id="tracksServerEnabled" name="enabled">
            <label class="form-check-label" for="tracksServerEnabled">
              <strong>Use Remote Tracks Server</strong>
              <small class="text-muted d-block">When disabled, tracks are served from this machine via <code>api/jbrowse2/tracks.php</code></small>
            </label>
          </div>

          <div id="remoteServerFields">
            <div class="mb-3">
              <label for="tracksServerUrl" class="form-label">Remote Server URL</label>
              <input type="url" class="form-control" id="tracksServerUrl" name="url"
                     placeholder="https://tracks.yourlab.edu">
              <small class="text-muted">
                The remote server must have <code>api/jbrowse2/tracks.php</code> deployed with your JWT public key.
                Track data files go in <code>data/tracks/{organism}/{assembly}/{type}/</code>.
              </small>
            </div>

            <div class="alert alert-warning py-2">
              <i class="fa fa-exclamation-triangle"></i>
              <strong>Remote server deployment checklist:</strong>
              <ol class="mb-0 mt-2 small">
                <li>Copy <code>api/jbrowse2/tracks.php</code> and <code>lib/jbrowse/track_token.php</code> to remote server</li>
                <li>Copy <strong>only</strong> <code>certs/jwt_public_key.pem</code> (NOT the private key)</li>
                <li>Install Firebase JWT: <code>composer require firebase/php-jwt</code></li>
                <li>Place track data files in <code>data/tracks/{organism}/{assembly}/{type}/</code></li>
                <li>Add <code>data/tracks/.htaccess</code> to block direct file access</li>
                <li>Re-sync tracks from Google Sheet so URIs point to the remote server</li>
              </ol>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-dark">
              <i class="fa fa-save"></i> Save Configuration
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="loadTracksServerConfig()">
              <i class="fa fa-undo"></i> Reset
            </button>
          </div>
        </form>

        <div id="tracksServerResult" class="mt-3" style="display:none;"></div>
      </div>
    </div>
  </div>

  <!-- Track Listing -->
  <div class="card mb-4 border-secondary">
    <div class="card-header bg-secondary bg-opacity-10">
      <h5 class="mb-0"><i class="fa fa-list"></i> Track Listing</h5>
    </div>
    <div class="card-body">
      <!-- Filters -->
      <div class="row mb-3">
        <div class="col-md-3">
          <label for="filterOrganism" class="form-label">Organism</label>
          <select class="form-select" id="filterOrganism" onchange="filterTracks()">
            <option value="">All</option>
            <?php foreach ($organisms as $org => $assemblies): ?>
            <option value="<?php echo htmlspecialchars($org); ?>"><?php echo htmlspecialchars($org); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="filterAssembly" class="form-label">Assembly</label>
          <select class="form-select" id="filterAssembly" onchange="filterTracks()" disabled>
            <option value="">All</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="filterType" class="form-label">Track Type</label>
          <select class="form-select" id="filterType" onchange="filterTracks()">
            <option value="">All</option>
            <?php foreach ($track_stats['by_type'] as $type => $count): ?>
            <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?> (<?php echo $count; ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="filterAccess" class="form-label">Access Level</label>
          <select class="form-select" id="filterAccess" onchange="filterTracks()">
            <option value="">All</option>
            <?php foreach ($track_stats['by_access'] as $access => $count): ?>
            <option value="<?php echo htmlspecialchars($access); ?>"><?php echo htmlspecialchars($access); ?> (<?php echo $count; ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- DataTable -->
      <div class="table-responsive">
        <table id="tracksTable" class="table table-striped table-hover" style="width:100%">
          <thead>
            <tr>
              <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
              <th>Track Name</th>
              <th>Organism</th>
              <th>Assembly</th>
              <th>Type</th>
              <th>Access</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- Populated by DataTables -->
          </tbody>
        </table>
      </div>

      <!-- Bulk Actions -->
      <div class="mt-3">
        <button class="btn btn-sm btn-outline-primary" onclick="generateSelectedConfigs()" disabled id="bulkGenerateBtn">
          <i class="fa fa-cog"></i> Generate Configs for Selected
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteSelected()" disabled id="bulkDeleteBtn">
          <i class="fa fa-trash"></i> Delete Selected
        </button>
        <span id="selectedCount" class="ms-2 text-muted">0 selected</span>
      </div>
    </div>
  </div>

</div>
