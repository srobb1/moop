<?php
/**
 * JBROWSE MANAGEMENT - Content File
 * 
 * Pure display content - no HTML structure, scripts, or styling.
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $site (site name)
 * - $organisms (array of organism => [assemblies])
 * - $track_stats (track statistics)
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
        
        <p><strong>What You Can Do:</strong></p>
        <ul>
          <li>Register Google Sheets as track sources for organism/assemblies</li>
          <li>View and search all tracks across all assemblies</li>
          <li>Validate track URLs and access levels</li>
          <li>Generate JBrowse configuration files</li>
          <li>Manage track metadata and access control</li>
        </ul>
        
        <p><strong>Workflow:</strong></p>
        <ol>
          <li>Register a Google Sheet URL for an organism/assembly</li>
          <li>System validates sheet and extracts track metadata</li>
          <li>Browse/search tracks in the listing below</li>
          <li>Generate configs to make tracks available in JBrowse</li>
        </ol>
      </div>
    </div>
  </div>
  
  <!-- Quick Stats Dashboard -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h3 class="text-primary"><?php echo count($organisms); ?></h3>
          <p class="mb-0">Organisms</p>
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
  
  <!-- Sync Tracks -->
  <div class="card mb-4 border-success">
    <div class="card-header bg-success bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#syncTracks">
      <h5 class="mb-0">
        <i class="fa fa-sync"></i> Sync Tracks from Google Sheets
        <i class="fa fa-chevron-down float-end"></i>
      </h5>
    </div>
    <div class="collapse show" id="syncTracks">
      <div class="card-body">
        <div class="alert alert-warning">
          <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> 
          After editing your Google Sheet (changing access levels, track names, etc.), 
          you must sync tracks here for changes to appear in JBrowse and the dashboard.
        </div>
        <p class="text-muted">
          <i class="fa fa-info-circle"></i> 
          This syncs track metadata from registered Google Sheets. Track configs are then automatically 
          generated per-user when they load JBrowse.
        </p>
        <form id="syncTracksForm">
          <div class="mb-3">
            <label class="form-label">Sync Mode</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="syncMode" id="syncModeAll" value="all">
              <label class="form-check-label" for="syncModeAll">
                Sync All Registered Sheets (all assemblies with registered sheets)
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
                <?php foreach ($organisms as $org => $assemblies): ?>
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
              <label class="form-check-label" for="forceRegenerate">
                Force regenerate all tracks (ignore existing)
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="dryRun" name="dryRun">
              <label class="form-check-label" for="dryRun">
                Dry run (preview changes without saving)
              </label>
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
  
  <!-- Detailed Stats -->
  <?php if ($track_stats['total'] > 0): ?>
  <div class="card mb-4">
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <h6>Tracks by Type</h6>
          <ul class="list-unstyled">
            <?php foreach ($track_stats['by_type'] as $type => $count): ?>
            <li><span class="badge bg-secondary"><?php echo $count; ?></span> <?php echo $type; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="col-md-6">
          <h6>Tracks by Access Level</h6>
          <ul class="list-unstyled">
            <?php 
            $accessColors = [
              'PUBLIC' => 'success',
              'COLLABORATOR' => 'primary',
              'IP_IN_RANGE' => 'warning',
              'ADMIN' => 'danger'
            ];
            foreach ($track_stats['by_access'] as $access => $count): 
              $color = $accessColors[$access] ?? 'secondary';
            ?>
            <li><span class="badge bg-<?php echo $color; ?>"><?php echo $count; ?></span> <?php echo $access; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Google Sheets Registration -->
  <div class="card mb-4">
    <div class="card-header" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#sheetRegistration">
      <h5 class="mb-0">
        <i class="fa fa-table"></i> Register Google Sheets Track Source
        <i class="fa fa-chevron-down float-end"></i>
      </h5>
    </div>
    <div class="collapse" id="sheetRegistration">
      <div class="card-body">
        <form id="registerSheetForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="organism" class="form-label">Organism <span class="text-danger">*</span></label>
              <select class="form-select" id="organism" name="organism" required>
                <option value="">Select organism...</option>
                <?php foreach ($organisms as $org => $assemblies): ?>
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
            <label class="form-check-label" for="autoSync">
              Auto-sync tracks when generating configs
            </label>
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
  
  <!-- Track Listing -->
  <div class="card mb-4">
    <div class="card-header">
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
