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
 * - $orphaned_registrations array of ['organism','assembly','reason','detail'] — registered
 *                           in JBrowse but the source data under organisms/ is gone
 * - $track_stats            ['total','by_type','by_access','warnings']
 * - $assembly_rows          one entry per registered assembly (gene sets + sheet status);
 *                           also passed to JS as jbrowseAssemblyRows, which renders the table
 */
?>

<div class="container mt-5">
  <h2><i class="fa fa-dna"></i> JBrowse Track Management</h2>

  <!-- About Section -->
  <div class="card mb-4">
    <div class="card-header adm-head" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutJBrowse">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About JBrowse Management <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutJBrowse">
      <div class="card-body">
        <p><strong>Purpose:</strong> Centralized management for JBrowse assemblies, tracks, and configurations.</p>
        <p><strong>Workflow:</strong></p>
        <ol>
          <li>Register an assembly in JBrowse (prepares genome files and the gene annotation track)</li>
          <li>If it has other tracks, press <strong>Add sheet</strong> on its row and paste the Google Sheet link — saving also creates the tracks</li>
          <li>After editing a sheet later, press <strong>Sync</strong> on its row</li>
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

  <!-- Broken registrations — source data gone. Opens expanded: this is live breakage. -->
  <?php if (!empty($orphaned_registrations)): ?>
  <div class="card mb-4 border-danger" id="orphaned-registrations">
    <div class="card-header adm-head-danger">
      <h5 class="mb-0">
        <i class="fa fa-unlink text-danger"></i> Broken Registrations
        <span class="badge bg-danger ms-2"><?= count($orphaned_registrations) ?></span>
      </h5>
    </div>
    <div class="card-body">
      <?php if (!empty($orphans_systemic)): ?>
      <div class="alert alert-danger">
        <strong><i class="fa fa-plug"></i> Do not unregister these yet.</strong>
        Every registration is reporting missing source data at the same time, which points at the
        <code>organisms/</code> directory being unavailable — an unmounted share or a wrong
        <code>organism_data</code> path — rather than at these assemblies individually. They are
        probably fine. Unregistering would mean rebuilding all of them once the data is back, so
        the buttons below are disabled until the data directory is readable again.
      </div>
      <?php endif; ?>
      <p class="text-muted">
        <i class="fa fa-exclamation-triangle text-danger"></i>
        These assemblies are registered in JBrowse, but the source data they were built from
        is gone — renamed or removed outside MOOP. The genome browser still lists them and
        their reference sequence returns <strong>404</strong> for every user who opens them.
        <br>
        Unregistering removes only what registration created (the <code>data/genomes/</code>
        link directory and the registry entry). <strong>Nothing under <code>organisms/</code>
        is touched</strong>, and any existing track configuration is kept — so if the data
        still exists under a different name, register that name below and the tracks remain.
      </p>
      <table class="table table-sm table-hover mb-0">
        <thead>
          <tr>
            <th>Organism</th>
            <th>Registered as</th>
            <th>Problem</th>
            <th style="width:130px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orphaned_registrations as $o): ?>
          <tr id="orphan-<?= htmlspecialchars($o['organism'] . '_' . $o['assembly']) ?>">
            <td class="small"><?= htmlspecialchars($o['organism']) ?></td>
            <td class="small"><code><?= htmlspecialchars($o['assembly']) ?></code></td>
            <td class="small text-muted"><?= htmlspecialchars($o['detail']) ?></td>
            <td>
              <button class="btn btn-sm btn-outline-danger unregister-assembly-btn"
                      data-organism="<?= htmlspecialchars($o['organism']) ?>"
                      data-assembly="<?= htmlspecialchars($o['assembly']) ?>"
                      <?= !empty($orphans_systemic) ? 'disabled title="Disabled while the organism data directory looks unavailable"' : '' ?>>
                <i class="fa fa-unlink"></i> Unregister
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Register Assemblies (collapsible, starts closed) -->
  <?php if (!empty($unregistered_assemblies)): ?>
  <div class="card mb-4">
    <div class="card-header adm-head" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#registerAssemblies">
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
          After registering you are asked for that assembly's track sheet, in case it has other tracks.
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

  <!-- Assemblies & Tracks: one row per registered assembly. Rendered by js/jbrowse-admin.js
       from jbrowseAssemblyRows, so a row is redrawn in place after a sync or a sheet change. -->
  <div class="card adm-card mb-4" id="assemblyTracks">
    <div class="card-header adm-head d-flex align-items-center flex-wrap gap-2">
      <h5 class="mb-0 me-auto">
        <i class="fa fa-table"></i> Assemblies &amp; Tracks
        <span class="badge bg-secondary ms-2" id="asmRowCount"></span>
      </h5>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#syncOptions">
        <i class="fa fa-sliders-h"></i> Sync options
      </button>
      <button type="button" class="btn btn-sm btn-outline-primary" id="syncAllBtn">
        <i class="fa fa-sync"></i> Sync all sheets
      </button>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-2">
        Every assembly registered in JBrowse, with its gene annotation track and the Google Sheet
        that supplies its other tracks (RNA-seq, alignments…). <strong>Add sheet</strong> saves the
        link and creates the tracks in one step. Press <strong>Sync</strong> after editing a sheet.
      </p>

      <div class="collapse mb-3" id="syncOptions">
        <div class="border rounded p-3 bg-light">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="optRewrite" checked>
            <label class="form-check-label" for="optRewrite">Rewrite existing tracks from the sheet (picks up edited rows)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="optRemove">
            <label class="form-check-label" for="optRemove">Remove tracks that are no longer in the sheet</label>
            <small class="text-muted d-block">Only tracks that came from a sheet. The gene annotation track is never removed by a sync.</small>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="optDryRun">
            <label class="form-check-label" for="optDryRun">Dry run (report what would change, change nothing)</label>
          </div>
        </div>
      </div>

      <div class="row g-2 align-items-center mb-2">
        <div class="col-md-5">
          <input type="search" class="form-control form-control-sm" id="asmFilter" placeholder="Filter by organism or assembly…">
        </div>
        <div class="col-md-7">
          <div class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="checkbox" id="asmOnlySheet">
            <label class="form-check-label small" for="asmOnlySheet">Has a sheet</label>
          </div>
          <div class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="checkbox" id="asmOnlyAttention">
            <label class="form-check-label small" for="asmOnlyAttention">Gene track needs attention</label>
          </div>
        </div>
      </div>

      <div id="asmLog" class="mb-3" style="display:none;">
        <div class="alert py-2 mb-2" id="asmLogStatus"></div>
        <pre class="border rounded p-3 bg-light small mb-0" id="asmLogOutput"
             style="max-height:260px;overflow-y:auto;white-space:pre-wrap;"></pre>
      </div>

      <div class="table-responsive" style="max-height:520px;overflow-y:auto;">
        <table class="table table-sm table-hover align-middle mb-0" id="asmTable">
          <thead class="sticky-top bg-white">
            <tr>
              <th>Organism</th>
              <th>Assembly</th>
              <th>Gene track</th>
              <th>Track sheet</th>
              <th></th>
            </tr>
          </thead>
          <tbody><!-- rendered by jbrowse-admin.js --></tbody>
        </table>
      </div>
      <p class="text-muted small mt-2 mb-0" id="asmEmpty" style="display:none;">No assemblies match.</p>
    </div>
  </div>

  <!-- Tracks Server Configuration -->
  <div class="card mb-4">
    <div class="card-header adm-head" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#tracksServerConfig">
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
                     placeholder="https://tracks.yourlab.edu/moop">
              <small class="text-muted">
                Include the full path prefix if the server is deployed under a subdirectory (e.g. <code>https://tracks.yourlab.edu/moop</code>, not just <code>https://tracks.yourlab.edu</code>).
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
  <div class="card mb-4">
    <div class="card-header adm-head">
      <h5 class="mb-0"><i class="fa fa-list"></i> Track Listing</h5>
    </div>
    <div class="card-body">
      <!-- Filters -->
      <div class="row mb-3">
        <div class="col-md-3">
          <label for="filterOrganism" class="form-label">Organism</label>
          <select class="form-select" id="filterOrganism">
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
        <button class="btn btn-sm btn-outline-danger" disabled id="bulkDeleteBtn">
          <i class="fa fa-trash"></i> Delete Selected
        </button>
        <span id="selectedCount" class="ms-2 text-muted">0 selected</span>
      </div>
    </div>
  </div>

</div>

<!-- Track details modal (View Details in the track listing) -->
<div class="modal fade" id="trackDetailsModal" tabindex="-1" aria-labelledby="trackDetailsTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header adm-head">
        <h5 class="modal-title" id="trackDetailsTitle">Track details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="trackDetailsBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Track sheet modal (Add sheet / Edit sheet) -->
<div class="modal fade" id="sheetModal" tabindex="-1" aria-labelledby="sheetModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header adm-head">
        <h5 class="modal-title" id="sheetModalTitle">Track sheet</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="sheetForm">
        <div class="modal-body">
          <div class="alert alert-success py-2" id="sheetModalIntro" style="display:none;"></div>
          <div class="mb-3">
            <label for="sheetUrl" class="form-label">Google Sheet link <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="sheetUrl" required
                   placeholder="https://docs.google.com/spreadsheets/d/…/edit#gid=…">
            <small class="text-muted">Paste the link with the right tab open — the tab is read from it.</small>
          </div>
          <div class="mb-3" style="max-width:220px;">
            <label for="sheetGid" class="form-label small text-muted mb-1">Tab id (gid), if not in the link</label>
            <input type="text" class="form-control form-control-sm" id="sheetGid" value="0" inputmode="numeric" pattern="[0-9]+">
          </div>
          <div id="sheetModalResult" style="display:none;">
            <div class="alert py-2 mb-2" id="sheetModalStatus"></div>
            <pre class="border rounded p-3 bg-light small mb-0" id="sheetModalLog"
                 style="display:none;max-height:240px;overflow-y:auto;white-space:pre-wrap;"></pre>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary me-auto" id="sheetTestBtn">
            <i class="fa fa-check-circle"></i> Test link
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="sheetSaveBtn">
            <i class="fa fa-save"></i> Save &amp; sync tracks
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- GFF Action Modal (Rebuild / Index Names) -->
<div class="modal fade" id="gffActionModal" tabindex="-1" aria-labelledby="gffActionModalTitle" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="gffActionModalTitle">GFF Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="gffActionModalDesc" class="text-muted mb-3"></p>

        <div id="gffActionAttrsGroup">
          <label for="gffActionAttrs" class="form-label fw-semibold">
            Text-index attributes
            <small class="text-muted fw-normal">(leave blank to skip indexing)</small>
          </label>
          <input type="text" class="form-control" id="gffActionAttrs"
                 value="Name,ID" placeholder="e.g. Name,ID,gene_id">
          <div class="form-text">Comma-separated GFF attribute names to index for feature name search.</div>
        </div>

        <div id="gffActionResult" class="mt-3" style="display:none;">
          <hr class="my-2">
          <pre id="gffActionLog"
               class="bg-light border rounded p-3 small mb-0"
               style="max-height:220px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;"></pre>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="gffActionCancelBtn">Cancel</button>
        <button type="button" class="btn btn-primary" id="gffActionBtn">Run</button>
      </div>
    </div>
  </div>
</div>
