<div class="container mt-4">

  <!-- Back to Admin Dashboard -->
  <div class="mb-3">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>

  <!-- Messages -->
  <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- File permission warning — render through the shared helper (as manage_groups does).
       getFileWriteError() returns an ARRAY (or null), never a string, so the old
       htmlspecialchars($file_write_error) was a TypeError → 500 the moment the users
       file became non-writable by the web server. generatePermissionAlert() returns ''
       when the file is readable+writable, so the healthy case renders nothing as before. -->
  <?php
    echo generatePermissionAlert(
        $config->getPath('users_file'),
        'Cannot Save User Changes',
        'The users file is not writable by the web server, so creating, editing, and deleting users is disabled until this is fixed.',
        'file'
    );
  ?>

  <!-- ══════════════════════════════════════════════════════
       SECTION 1: EXISTING USERS TABLE
  ══════════════════════════════════════════════════════ -->
  <div class="card shadow-sm mb-4">
    <div class="card-header adm-head d-flex justify-content-between align-items-center">
      <h4 class="mb-0"><i class="fa fa-users"></i> User Accounts</h4>
      <span class="badge bg-light text-dark fs-6"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($users)): ?>
        <div class="p-4 text-muted text-center">No users yet. Create one below.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="usersTable">
          <thead class="table-light">
            <tr>
              <th>Username</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Assemblies</th>
              <th>Stale</th>
              <th style="width:130px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $username => $userData): ?>
              <?php
                $assemblyCount = 0;
                $staleCount    = 0;
                if (isset($userData['access']) && is_array($userData['access'])) {
                    foreach ($userData['access'] as $org => $asm_data) {
                        if (!is_array($asm_data)) continue;
                        $asm_keys = array_is_list($asm_data) ? $asm_data : array_keys($asm_data);
                        foreach ($asm_keys as $asm) {
                            $assemblyCount++;
                            if (!isset($organisms[$org]) || !in_array($asm, $organisms[$org])) {
                                $staleCount++;
                            }
                        }
                    }
                }
                $isAdmin = ($userData['role'] ?? '') === 'admin';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($username) ?></strong></td>
                <td><?= htmlspecialchars(trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''))) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($userData['email'] ?? '') ?></td>
                <td>
                  <?php if ($isAdmin): ?>
                    <span class="badge bg-danger">Admin</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Collaborator</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($isAdmin): ?>
                    <span class="text-muted small">All (admin)</span>
                  <?php else: ?>
                    <?= $assemblyCount ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($staleCount > 0): ?>
                    <span class="badge bg-warning text-dark" title="Stale assembly references">
                      <i class="fa fa-exclamation-triangle"></i> <?= $staleCount ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn btn-sm btn-outline-primary edit-user-btn"
                          data-username="<?= htmlspecialchars($username) ?>"
                          data-bs-toggle="modal" data-bs-target="#editUserModal"
                          title="Edit <?= htmlspecialchars($username) ?>">
                    <i class="fa fa-edit"></i> Edit
                  </button>
                  <button class="btn btn-sm btn-outline-danger delete-user-btn ms-1"
                          data-username="<?= htmlspecialchars($username) ?>"
                          title="Delete <?= htmlspecialchars($username) ?>">
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION 2: CREATE NEW USER FORM
  ══════════════════════════════════════════════════════ -->
  <div class="card shadow-sm mb-4">
    <div class="card-header adm-head">
      <h4 class="mb-0"><i class="fa fa-user-plus"></i> Create New User</h4>
    </div>
    <div class="card-body">
      <form method="post" id="createUserForm">
        <?= csrf_input_field() ?>
        <input type="hidden" name="create_or_update_user" value="1">
        <input type="hidden" name="is_create" value="1">
        <input type="hidden" name="original_username" value="">

        <!-- Row 1: Username + Email -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" id="create-username" class="form-control" placeholder="Unique login name" required autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" name="email" id="create-email" class="form-control" placeholder="user@example.com">
          </div>
        </div>

        <!-- Row 2: Name fields -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">First Name</label>
            <input type="text" name="first_name" id="create-first-name" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Last Name</label>
            <input type="text" name="last_name" id="create-last-name" class="form-control">
          </div>
        </div>

        <!-- Row 3: Host + Password -->
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Institution / Lab</label>
            <input type="text" name="account_host" id="create-account-host" class="form-control" placeholder="e.g. Stanford University">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
            <input type="password" name="new_password" id="create-password" class="form-control" autocomplete="new-password" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="new_password_confirm" id="create-password-confirm" class="form-control" autocomplete="new-password">
            <div id="create-pw-match" class="small mt-1" style="display:none;"></div>
          </div>
        </div>

        <!-- Admin checkbox -->
        <div class="mb-3 p-3 bg-light rounded border">
          <div class="form-check">
            <input type="checkbox" name="isAdmin" id="create-isAdmin" class="form-check-input">
            <label class="form-check-label" for="create-isAdmin">
              <strong><i class="fa fa-shield-alt text-danger"></i> Admin User</strong>
              <span class="text-muted ms-2">— Grants full access to all organisms and the admin panel</span>
            </label>
          </div>
        </div>

        <!-- Assembly access selector -->
        <div id="create-access-section" class="mb-3">
          <label class="form-label fw-semibold">
            Organism / Assembly Access <span class="text-danger" id="create-access-required">*</span>
          </label>
          <p class="text-muted small mb-2">Select at least one assembly. Not required for admin users.</p>

          <div class="mb-2 d-flex align-items-center gap-2">
            <label class="form-label mb-0 text-muted small fw-semibold text-nowrap">Copy from user:</label>
            <select id="create-copy-from-user" class="form-select form-select-sm" style="max-width:260px;">
              <option value="">— select user —</option>
            </select>
          </div>

          <div class="d-flex gap-2 mb-2 flex-wrap">
            <input type="text" id="create-organism-filter" class="form-control form-control-sm" placeholder="Filter organisms or groups…" style="max-width:280px;">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="create-toggle-all-btn">
              <i class="fa fa-plus"></i> Expand All
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" id="create-select-all-btn">
              <i class="fa fa-check-square"></i> Select All
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="create-clear-all-btn">
              <i class="fa fa-times"></i> Clear All
            </button>
          </div>

          <div class="border rounded" style="max-height:320px; overflow-y:auto; background:#f8f9fa;" id="create-access-container">
            <!-- populated by JS -->
          </div>
          <div id="create-selected-assemblies-hidden"></div>
        </div>

        <!-- Selected assemblies preview -->
        <div class="mb-4" id="create-preview-section">
          <label class="form-label fw-semibold">Selected Assemblies</label>
          <div id="create-selected-preview" class="p-3 border rounded bg-light" style="min-height:44px;">
            <span class="text-muted small">Select assemblies above to see them here</span>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-success btn-lg">
            <i class="fa fa-user-plus"></i> Create User
          </button>
          <button type="reset" class="btn btn-outline-secondary btn-lg" onclick="resetCreateForm()">
            <i class="fa fa-times"></i> Clear Form
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       SECTION 3: STALE ASSEMBLY AUDIT
  ══════════════════════════════════════════════════════ -->
  <?php if (!empty($stale_entries_audit)): ?>
  <div class="card shadow-sm mb-4 border-warning">
    <div class="card-header adm-head-warn" style="cursor:pointer;" data-bs-target="#stale-audit" id="stale-audit-header">
      <h5 class="mb-0">
        <i class="fa fa-exclamation-triangle"></i> Stale Assembly References
        <span class="badge bg-danger ms-2"><?= count($stale_entries_audit) ?></span>
        <i class="fa fa-chevron-down float-end mt-1"></i>
      </h5>
    </div>
    <div id="stale-audit" style="display:none;">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <p class="text-muted small mb-0">These assemblies are still assigned to users but no longer exist on disk.</p>
          <form method="post" onsubmit="return confirm('Remove ALL <?= count($stale_entries_audit) ?> stale reference(s) from all users?');">
            <?= csrf_input_field() ?>
            <input type="hidden" name="remove_all_stale" value="1">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Remove All Stale</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr><th>User</th><th>Email</th><th>Organism</th><th>Assembly</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($stale_entries_audit as $stale): ?>
              <tr class="table-warning">
                <td><strong><?= htmlspecialchars($stale['username']) ?></strong></td>
                <td><?= htmlspecialchars($stale['email']) ?></td>
                <td><?= htmlspecialchars($stale['organism']) ?></td>
                <td><span class="tag-chip tag-chip-stale"><?= htmlspecialchars($stale['assembly']) ?></span></td>
                <td>
                  <form method="post" style="display:inline;">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="remove_stale_assembly" value="1">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($stale['username']) ?>">
                    <input type="hidden" name="organism" value="<?= htmlspecialchars($stale['organism']) ?>">
                    <input type="hidden" name="assembly" value="<?= htmlspecialchars($stale['assembly']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                  </form>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Remove from ALL users?');">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="remove_stale_from_all" value="1">
                    <input type="hidden" name="organism" value="<?= htmlspecialchars($stale['organism']) ?>">
                    <input type="hidden" name="assembly" value="<?= htmlspecialchars($stale['assembly']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-warning ms-1">Remove from All</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="mb-5">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>

</div><!-- /container -->


<!-- ══════════════════════════════════════════════════════════════════
     EDIT USER MODAL
     Opens when clicking "Edit" in the users table.
     Clearly separated from the create form above.
══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <!-- Modal header — amber to distinguish from the green create form -->
      <div class="modal-header" style="background:#f0ad4e; border-bottom:3px solid #d08020;">
        <div>
          <h4 class="modal-title mb-0" id="editUserModalLabel">
            <i class="fa fa-user-edit"></i> Editing User:
            <span id="modal-username-display" class="fw-bold ms-1" style="font-family:monospace;"></span>
          </h4>
          <small class="text-dark opacity-75">Changes take effect immediately on save.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="post" id="editUserForm">
        <div class="modal-body">
          <?= csrf_input_field() ?>
          <input type="hidden" name="create_or_update_user" value="1">
          <input type="hidden" name="is_create" value="0">
          <input type="hidden" name="original_username" id="modal-original-username" value="">

          <!-- Stale assemblies alert (shown if user has stale entries) -->
          <div class="alert alert-warning d-none" id="modal-stale-alert">
            <strong><i class="fa fa-exclamation-circle"></i> Stale Assemblies on this Account</strong>
            <p class="mb-1 small">These assemblies no longer exist. Click × to remove them from this user.</p>
            <div id="modal-stale-items"></div>
          </div>

          <!-- Basic info -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Username</label>
              <input type="text" name="username" id="modal-username" class="form-control bg-light" readonly>
              <small class="text-muted">Username cannot be changed.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email</label>
              <input type="email" name="email" id="modal-email" class="form-control">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">First Name</label>
              <input type="text" name="first_name" id="modal-first-name" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Last Name</label>
              <input type="text" name="last_name" id="modal-last-name" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Institution / Lab</label>
              <input type="text" name="account_host" id="modal-account-host" class="form-control">
            </div>
          </div>

          <!-- Password change (optional) -->
          <div class="card bg-light border mb-3">
            <div class="card-header adm-head py-2">
              <i class="fa fa-lock"></i> <strong>Change Password</strong>
              <span class="text-muted ms-2 fw-normal small">— leave both fields blank to keep the current password</span>
            </div>
            <div class="card-body pb-2">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">New Password</label>
                  <input type="password" name="new_password" id="modal-password" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Confirm New Password</label>
                  <input type="password" name="new_password_confirm" id="modal-password-confirm" class="form-control" autocomplete="new-password">
                  <div id="modal-pw-match" class="small mt-1" style="display:none;"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Admin toggle -->
          <div class="mb-3 p-3 rounded border" style="background:#fff3cd;">
            <div class="form-check">
              <input type="checkbox" name="isAdmin" id="modal-isAdmin" class="form-check-input">
              <label class="form-check-label" for="modal-isAdmin">
                <strong><i class="fa fa-shield-alt text-danger"></i> Admin User</strong>
                <span class="text-muted ms-2">— Full access to all organisms and admin panel</span>
              </label>
            </div>
          </div>

          <!-- Assembly selector -->
          <div id="modal-access-section">
            <label class="form-label fw-semibold">
              Organism / Assembly Access <span class="text-danger" id="modal-access-required">*</span>
            </label>
            <p class="text-muted small mb-2">Select assemblies this user can access. Not required for admins.</p>

            <div class="mb-2 d-flex align-items-center gap-2">
              <label class="form-label mb-0 text-muted small fw-semibold text-nowrap">Copy from user:</label>
              <select id="modal-copy-from-user" class="form-select form-select-sm" style="max-width:260px;">
                <option value="">— select user —</option>
              </select>
            </div>

            <div class="d-flex gap-2 mb-2 flex-wrap">
              <input type="text" id="modal-organism-filter" class="form-control form-control-sm" placeholder="Filter organisms or groups…" style="max-width:280px;">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="modal-toggle-all-btn">
                <i class="fa fa-plus"></i> Expand All
              </button>
              <button type="button" class="btn btn-sm btn-outline-success" id="modal-select-all-btn">
                <i class="fa fa-check-square"></i> Select All
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger" id="modal-clear-all-btn">
                <i class="fa fa-times"></i> Clear All
              </button>
            </div>

            <div class="border rounded" style="max-height:300px; overflow-y:auto; background:#f8f9fa;" id="modal-access-container">
              <!-- populated by JS -->
            </div>
            <div id="modal-selected-assemblies-hidden"></div>
          </div>

          <!-- Selected preview -->
          <div class="mt-3">
            <label class="form-label fw-semibold">Selected Assemblies</label>
            <div id="modal-selected-preview" class="p-3 border rounded bg-light" style="min-height:44px;">
              <span class="text-muted small">No assemblies selected</span>
            </div>
          </div>

        </div><!-- /modal-body -->

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times"></i> Cancel
          </button>
          <button type="submit" class="btn btn-warning btn-lg px-4">
            <i class="fa fa-save"></i> Update User
          </button>
        </div>
      </form>

    </div>
  </div>
</div><!-- /editUserModal -->


<style>
  /* Group-level rows */
  .group-section { border-bottom: 1px solid #dee2e6; }
  .group-header   { padding: 6px 10px; background: #e9ecef; cursor: pointer; user-select: none; }
  .group-header:hover { background: #dde1e7; }

  /* Organism-level rows */
  .org-section  { border-top: 1px solid #f0f0f0; }
  .org-header   { padding: 4px 10px 4px 28px; cursor: pointer; user-select: none; }
  .org-header:hover { background: #f8f9fa; }

  /* Dim the access section when admin is checked */
  .access-disabled { opacity: 0.45; pointer-events: none; }

  /* Gene set chips inside expanded assembly panel */
  .tag-chip-gs {
    font-size: 10px;
    padding: 1px 7px;
    cursor: pointer;
    border-radius: 4px;
    border: 1.5px solid;
    user-select: none;
    transition: background 0.1s, color 0.1s, opacity 0.1s;
  }
  .tag-chip-gs:hover { opacity: 1 !important; filter: brightness(0.93); }
</style>

<script>
(function () {
  'use strict';

  // ── State ─────────────────────────────────────────────────────────────
  let createSelectedAccess = {};
  let editSelectedAccess   = {};

  // ── Colour helpers ────────────────────────────────────────────────────
  const COLORS = ['#007bff','#28a745','#17a2b8','#ffc107','#dc3545','#6f42c1','#fd7e14','#20c997','#e83e8c','#6610f2'];
  const colorMap = {};
  let colorIdx = 0;
  function orgColor(org) {
    if (!colorMap[org]) colorMap[org] = COLORS[colorIdx++ % COLORS.length];
    return colorMap[org];
  }

  // ── Build one org row (chips + per-organism checkbox + count) ─────────
  // assembliesDict: {assembly: [gene_sets]} — e.g. {HIv3: ['v1'], GCA_1: ['v1','v2']}
  // selectedAccess: {org: {assembly: ['*'] | [gene_sets]}}
  // asmRefreshRegistry: optional map keyed 'org::asm' → refreshFn, filled here
  function buildOrgSection(organism, assembliesDict, selectedAccess, onGroupRefresh, onUpdate, asmRefreshRegistry) {
    const assemblies = Object.keys(assembliesDict);

    const orgSection = document.createElement('div');
    orgSection.className = 'org-section';
    orgSection.dataset.org = organism;

    const orgHeader = document.createElement('div');
    orgHeader.className = 'org-header d-flex align-items-center gap-2';

    const orgCb = document.createElement('input');
    orgCb.type = 'checkbox';
    orgCb.className = 'form-check-input flex-shrink-0';
    orgCb.style.cursor = 'pointer';

    const orgChevron = document.createElement('i');
    orgChevron.className = 'fa fa-chevron-right fa-fw text-muted';

    const orgLabel = document.createElement('span');
    orgLabel.className = 'flex-grow-1 fst-italic';
    orgLabel.style.fontSize = '12px';
    orgLabel.textContent = organism;

    const orgCount = document.createElement('span');
    orgCount.style.fontSize = '11px';

    const assemblyWrap = document.createElement('div');
    assemblyWrap.className = 'assembly-wrap pb-1';
    assemblyWrap.style.cssText = 'display:none; padding-left:44px;';

    function refreshOrg() {
      const sel   = assemblies.filter(a => selectedAccess[organism]?.[a] !== undefined).length;
      const total = assemblies.length;
      orgCount.textContent = `${sel}/${total}`;
      orgCount.className = sel === 0     ? 'badge bg-secondary'
                         : sel === total ? 'badge bg-success'
                         :                 'badge bg-warning text-dark';
      orgCb.checked       = sel === total && total > 0;
      orgCb.indeterminate = sel > 0 && sel < total;
      // Refresh each assembly chip (updates gene set badges too)
      assemblies.forEach(a => asmRefreshRegistry?.[`${organism}::${a}`]?.());
    }

    assemblies.forEach(assembly => {
      const geneSets  = assembliesDict[assembly] || ['v1'];
      const hasGeneSets = geneSets.length >= 1;
      const c             = orgColor(organism);

      // Outer wrapper: chip row on top, gene set panel below (hidden by default)
      const asmEntry = document.createElement('div');
      asmEntry.style.cssText = 'display:inline-flex; flex-direction:column; vertical-align:top; margin:2px;';

      const chipRow = document.createElement('div');
      chipRow.className = 'd-flex align-items-center gap-1';

      const chip = document.createElement('span');
      chip.className = 'tag-chip-selector';
      chip.dataset.organism = organism;
      chip.dataset.assembly = assembly;
      chip.style.cssText = `font-size:11px; padding:2px 8px; cursor:pointer; background:${c}; border-color:${c}; color:white; border:2px solid ${c};`;
      chip.textContent = assembly;

      // Badge showing gene set selection state (only visible when >1 gs)
      const gsBadge = document.createElement('span');
      gsBadge.style.cssText = 'font-size:10px;';

      // Gene set chips panel (expands when customize is clicked)
      const gsPanel = document.createElement('div');
      gsPanel.style.cssText = 'display:none; padding-top:3px;';

      function getCurrentGs() { return selectedAccess[organism]?.[assembly]; }

      function refreshAsmChip() {
        const gs    = getCurrentGs();
        const isSel = gs !== undefined;
        chip.style.opacity = isSel ? '1' : '0.35';
        chip.classList.toggle('selected', isSel);

        if (!isSel) {
          gsBadge.textContent = ''; gsBadge.className = '';
        } else if (gs[0] === '*') {
          gsBadge.textContent = 'all';
          gsBadge.className   = 'badge bg-success ms-1';
        } else {
          gsBadge.textContent = `${gs.length}/${geneSets.length}`;
          gsBadge.className   = 'badge bg-warning text-dark ms-1';
        }

        // Keep gs chip visuals in sync if panel is open
        if (gsPanel.style.display !== 'none') {
          gsPanel.querySelectorAll('.tag-chip-gs').forEach(gsChip => {
            const gsName = gsChip.dataset.gs;
            const sel    = gs === undefined ? false : (gs[0] === '*' ? true : gs.includes(gsName));
            gsChip.style.opacity    = sel ? '1' : '0.45';
            gsChip.style.background = sel ? c : 'white';
            gsChip.style.color      = sel ? 'white' : c;
            gsChip.classList.toggle('selected', sel);
          });
        }
      }

      if (asmRefreshRegistry) asmRefreshRegistry[`${organism}::${assembly}`] = refreshAsmChip;

      // Assembly chip click → toggle all gene sets (or upgrade partial → all)
      chip.addEventListener('click', function () {
        const gs = getCurrentGs();
        if (gs === undefined) {
          if (!selectedAccess[organism]) selectedAccess[organism] = {};
          selectedAccess[organism][assembly] = ['*'];
        } else if (gs[0] === '*') {
          // Already all — deselect
          delete selectedAccess[organism][assembly];
          if (!Object.keys(selectedAccess[organism]).length) delete selectedAccess[organism];
        } else {
          // Partial → upgrade to all
          selectedAccess[organism][assembly] = ['*'];
        }
        refreshAsmChip();
        refreshOrg();
        onGroupRefresh();
        onUpdate();
      });

      chipRow.appendChild(chip);
      chipRow.appendChild(gsBadge);

      // Customize button + individual gene set chips (only when assembly has >1 gene set)
      if (hasGeneSets) {
        const customBtn = document.createElement('button');
        customBtn.type  = 'button';
        customBtn.title = 'Customize gene sets';
        customBtn.style.cssText = 'background:none; border:none; padding:0 2px; cursor:pointer; font-size:11px; color:#6c757d; line-height:1;';
        customBtn.innerHTML = '<i class="fa fa-sliders-h"></i>';

        customBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          const isOpen = gsPanel.style.display !== 'none';
          gsPanel.style.display = isOpen ? 'none' : 'block';
          this.innerHTML = isOpen
            ? '<i class="fa fa-sliders-h"></i>'
            : '<i class="fa fa-times-circle" style="color:#dc3545;"></i>';
          if (!isOpen) refreshAsmChip(); // sync gs chip state when opening
        });

        chipRow.appendChild(customBtn);

        geneSets.forEach(gs => {
          const gsChip = document.createElement('span');
          gsChip.className     = 'tag-chip-gs me-1';
          gsChip.dataset.gs    = gs;
          gsChip.style.borderColor = c;

          const initGs  = getCurrentGs();
          const initSel = initGs === undefined ? false : (initGs[0] === '*' ? true : initGs.includes(gs));
          gsChip.style.background = initSel ? c : 'white';
          gsChip.style.color      = initSel ? 'white' : c;
          gsChip.style.opacity    = initSel ? '1' : '0.45';
          gsChip.classList.toggle('selected', initSel);
          gsChip.textContent = gs;

          gsChip.addEventListener('click', function (e) {
            e.stopPropagation();
            let current = getCurrentGs();

            if (current === undefined) {
              // Assembly not yet selected — select just this gs
              if (!selectedAccess[organism]) selectedAccess[organism] = {};
              selectedAccess[organism][assembly] = [gs];
            } else if (current[0] === '*') {
              // All selected — remove this one
              const remaining = geneSets.filter(g => g !== gs);
              if (remaining.length === 0) {
                delete selectedAccess[organism][assembly];
                if (!Object.keys(selectedAccess[organism]).length) delete selectedAccess[organism];
              } else {
                selectedAccess[organism][assembly] = remaining;
              }
            } else {
              // Specific list — toggle
              if (current.includes(gs)) {
                const remaining = current.filter(g => g !== gs);
                if (remaining.length === 0) {
                  delete selectedAccess[organism][assembly];
                  if (!Object.keys(selectedAccess[organism]).length) delete selectedAccess[organism];
                } else {
                  selectedAccess[organism][assembly] = remaining;
                }
              } else {
                const newList = [...current, gs];
                // Upgrade to ['*'] if all gene sets are now selected
                selectedAccess[organism][assembly] = newList.length === geneSets.length ? ['*'] : newList;
              }
            }

            refreshAsmChip();
            refreshOrg();
            onGroupRefresh();
            onUpdate();
          });

          gsPanel.appendChild(gsChip);
        });
      }

      asmEntry.appendChild(chipRow);
      if (hasGeneSets) asmEntry.appendChild(gsPanel);
      assemblyWrap.appendChild(asmEntry);
      refreshAsmChip();
    });

    orgCb.addEventListener('click', function (e) {
      e.stopPropagation();
      if (this.checked) {
        selectedAccess[organism] = {};
        assemblies.forEach(a => { selectedAccess[organism][a] = ['*']; });
      } else {
        delete selectedAccess[organism];
      }
      if (this.checked && assemblyWrap.style.display === 'none') {
        assemblyWrap.style.display = 'block';
        orgChevron.className = 'fa fa-chevron-down fa-fw text-muted';
      }
      refreshOrg(); // refreshOrg calls asmRefreshRegistry for each asm
      onGroupRefresh();
      onUpdate();
    });

    orgHeader.addEventListener('click', function (e) {
      if (e.target === orgCb) return;
      const hidden = assemblyWrap.style.display === 'none';
      assemblyWrap.style.display = hidden ? 'block' : 'none';
      orgChevron.className = hidden ? 'fa fa-chevron-down fa-fw text-muted' : 'fa fa-chevron-right fa-fw text-muted';
    });

    orgHeader.appendChild(orgCb);
    orgHeader.appendChild(orgChevron);
    orgHeader.appendChild(orgLabel);
    orgHeader.appendChild(orgCount);
    orgSection.appendChild(orgHeader);
    orgSection.appendChild(assemblyWrap);

    refreshOrg();
    return orgSection;
  }

  // ── Render full selector grouped by group ─────────────────────────────
  function renderAssemblySelector(containerEl, selectedAccess, onUpdate) {
    containerEl.innerHTML = '';
    const asmRefreshRegistry = {}; // 'org::asm' → refreshFn, populated by buildOrgSection

    Object.keys(allOrganismsByGroup).sort().forEach(group => {
      const groupOrgs = allOrganismsByGroup[group];

      const groupSection = document.createElement('div');
      groupSection.className = 'group-section';
      groupSection.dataset.group = group;

      const groupHeader = document.createElement('div');
      groupHeader.className = 'group-header d-flex align-items-center gap-2';

      const groupCb = document.createElement('input');
      groupCb.type = 'checkbox';
      groupCb.className = 'form-check-input flex-shrink-0';
      groupCb.style.cursor = 'pointer';

      const groupChevron = document.createElement('i');
      groupChevron.className = 'fa fa-chevron-right fa-fw';

      const groupLabel = document.createElement('span');
      groupLabel.className = 'fw-bold flex-grow-1';
      groupLabel.textContent = group;

      const groupCount = document.createElement('span');

      const groupBody = document.createElement('div');
      groupBody.className = 'group-body';
      groupBody.style.display = 'none';

      function refreshGroup() {
        let total = 0, sel = 0;
        // groupOrgs is {org: {assembly: [gene_sets]}}
        Object.entries(groupOrgs).forEach(([org, asmsDict]) => {
          Object.keys(asmsDict).forEach(a => {
            total++;
            if (selectedAccess[org]?.[a] !== undefined) sel++;
          });
        });
        groupCount.textContent = `${sel}/${total}`;
        groupCount.className = sel === 0     ? 'badge bg-secondary ms-1'
                             : sel === total ? 'badge bg-success ms-1'
                             :                 'badge bg-warning text-dark ms-1';
        groupCb.checked       = sel === total && total > 0;
        groupCb.indeterminate = sel > 0 && sel < total;
      }

      groupCb.addEventListener('click', function (e) {
        e.stopPropagation();
        const selectAll = this.checked;
        Object.entries(groupOrgs).forEach(([org, asmsDict]) => {
          if (selectAll) {
            if (!selectedAccess[org]) selectedAccess[org] = {};
            Object.keys(asmsDict).forEach(a => { selectedAccess[org][a] = ['*']; });
          } else {
            if (selectedAccess[org]) {
              Object.keys(asmsDict).forEach(a => { delete selectedAccess[org][a]; });
              if (!Object.keys(selectedAccess[org]).length) delete selectedAccess[org];
            }
          }
        });
        if (selectAll && groupBody.style.display === 'none') {
          groupBody.style.display = 'block';
          groupChevron.className = 'fa fa-chevron-down fa-fw';
        }
        // Update org rows via registry (handles gene set badges too)
        groupBody.querySelectorAll('.org-section').forEach(orgSection => {
          const org      = orgSection.dataset.org;
          const asmsDict = groupOrgs[org] || {};
          const asmKeys  = Object.keys(asmsDict);
          const orgCbEl  = orgSection.querySelector('input[type=checkbox]');
          const orgCntEl = orgSection.querySelector('.badge');
          const sel   = asmKeys.filter(a => selectedAccess[org]?.[a] !== undefined).length;
          const total = asmKeys.length;
          if (orgCntEl) {
            orgCntEl.textContent = `${sel}/${total}`;
            orgCntEl.className = sel === 0     ? 'badge bg-secondary'
                               : sel === total ? 'badge bg-success'
                               :                 'badge bg-warning text-dark';
          }
          if (orgCbEl) { orgCbEl.checked = sel === total && total > 0; orgCbEl.indeterminate = sel > 0 && sel < total; }
          asmKeys.forEach(a => asmRefreshRegistry[`${org}::${a}`]?.());
        });
        refreshGroup();
        onUpdate();
      });

      groupHeader.addEventListener('click', function (e) {
        if (e.target === groupCb) return;
        const hidden = groupBody.style.display === 'none';
        groupBody.style.display = hidden ? 'block' : 'none';
        groupChevron.className = hidden ? 'fa fa-chevron-down fa-fw' : 'fa fa-chevron-right fa-fw';
      });

      Object.keys(groupOrgs).sort().forEach(org => {
        // groupOrgs[org] is {assembly: [gene_sets]}
        groupBody.appendChild(buildOrgSection(org, groupOrgs[org], selectedAccess, refreshGroup, onUpdate, asmRefreshRegistry));
      });

      groupHeader.appendChild(groupCb);
      groupHeader.appendChild(groupChevron);
      groupHeader.appendChild(groupLabel);
      groupHeader.appendChild(groupCount);
      groupSection.appendChild(groupHeader);
      groupSection.appendChild(groupBody);
      containerEl.appendChild(groupSection);

      refreshGroup();
    });
  }

  // ── Sync hidden inputs + preview badges ───────────────────────────────
  function syncSelection(hiddenId, previewId, selectedAccess) {
    const hiddenEl  = document.getElementById(hiddenId);
    const previewEl = document.getElementById(previewId);
    if (!hiddenEl || !previewEl) return;

    hiddenEl.innerHTML  = '';
    previewEl.innerHTML = '';
    let total = 0;

    // selectedAccess: {org: {assembly: [gene_sets]}}
    Object.keys(selectedAccess).sort().forEach(org => {
      Object.keys(selectedAccess[org] || {}).sort().forEach(asm => {
        const geneSets = selectedAccess[org][asm] || ['*'];
        total++;

        // Emit one hidden input per gene_set value
        geneSets.forEach(gs => {
          const inp = document.createElement('input');
          inp.type = 'hidden'; inp.name = `access[${org}][${asm}][]`; inp.value = gs;
          hiddenEl.appendChild(inp);
        });

        // Resolve ['*'] to actual gene set names for display
        const displayGs = (geneSets.length === 1 && geneSets[0] === '*')
          ? (allOrganisms[org]?.[asm] || ['v1'])
          : geneSets;

        const row = document.createElement('div');
        row.className = 'd-flex align-items-start gap-1 mb-1';

        const badge = document.createElement('span');
        badge.className = 'tag-chip flex-shrink-0';
        badge.style.background  = orgColor(org);
        badge.style.borderColor = orgColor(org);
        badge.textContent = `${org}: ${asm}`;

        const rm = document.createElement('i');
        rm.className = 'fa fa-times ms-2';
        rm.style.cssText = 'cursor:pointer;opacity:0.8;';
        rm.addEventListener('click', function (e) {
          e.stopPropagation();
          delete selectedAccess[org]?.[asm];
          if (selectedAccess[org] && !Object.keys(selectedAccess[org]).length) delete selectedAccess[org];
          syncSelection(hiddenId, previewId, selectedAccess);
          const containerEl = document.getElementById(
            hiddenId === 'create-selected-assemblies-hidden' ? 'create-access-container' : 'modal-access-container'
          );
          if (containerEl) renderAssemblySelector(containerEl, selectedAccess, () => syncSelection(hiddenId, previewId, selectedAccess));
        });
        badge.appendChild(rm);

        const gsWrap = document.createElement('span');
        gsWrap.className = 'd-flex flex-wrap gap-1 align-items-center';
        displayGs.forEach(gs => {
          const allGs = allOrganisms[org]?.[asm] || ['v1'];
          const gsChip = document.createElement('span');
          gsChip.className = 'd-inline-flex align-items-center gap-1 badge rounded-pill';
          gsChip.style.cssText = `background:${orgColor(org)}; opacity:0.82; font-size:10px; font-weight:normal; padding:3px 7px;`;
          const gsLabel = document.createElement('span');
          gsLabel.textContent = gs;
          const gsRm = document.createElement('i');
          gsRm.className = 'fa fa-times';
          gsRm.style.cssText = 'cursor:pointer; opacity:0.7; font-size:9px;';
          gsRm.title = `Remove ${gs}`;
          gsRm.addEventListener('click', function (e) {
            e.stopPropagation();
            const current = selectedAccess[org]?.[asm];
            const fullList = (current && current.length === 1 && current[0] === '*') ? allGs : (current || allGs);
            const remaining = fullList.filter(g => g !== gs);
            if (remaining.length === 0) {
              delete selectedAccess[org][asm];
              if (!Object.keys(selectedAccess[org]).length) delete selectedAccess[org];
            } else {
              if (!selectedAccess[org]) selectedAccess[org] = {};
              selectedAccess[org][asm] = remaining.length === allGs.length ? ['*'] : remaining;
            }
            syncSelection(hiddenId, previewId, selectedAccess);
            const containerEl = document.getElementById(
              hiddenId === 'create-selected-assemblies-hidden' ? 'create-access-container' : 'modal-access-container'
            );
            if (containerEl) renderAssemblySelector(containerEl, selectedAccess, () => syncSelection(hiddenId, previewId, selectedAccess));
          });
          gsChip.appendChild(gsLabel);
          gsChip.appendChild(gsRm);
          gsWrap.appendChild(gsChip);
        });

        row.appendChild(badge);
        row.appendChild(gsWrap);
        previewEl.appendChild(row);
      });
    });

    if (total === 0) previewEl.innerHTML = '<span class="text-muted small">No assemblies selected</span>';
  }

  // ── Password match ────────────────────────────────────────────────────
  function watchPasswordMatch(pwId, confirmId, msgId) {
    const pw = document.getElementById(pwId), pw2 = document.getElementById(confirmId), msg = document.getElementById(msgId);
    if (!pw || !pw2 || !msg) return;
    function check() {
      if (!pw.value || !pw2.value) { msg.style.display = 'none'; return; }
      if (pw.value === pw2.value) { msg.textContent = '✓ Passwords match'; msg.style.color = '#198754'; msg.style.display = ''; }
      else { msg.textContent = '✗ Passwords do not match'; msg.style.color = '#dc3545'; msg.style.display = ''; }
    }
    pw.addEventListener('input', check);
    pw2.addEventListener('input', check);
  }

  // ── Admin toggle ──────────────────────────────────────────────────────
  function toggleAccess(checkboxId, sectionId, requiredId) {
    const cb = document.getElementById(checkboxId), sec = document.getElementById(sectionId), req = document.getElementById(requiredId);
    if (!cb || !sec) return;
    sec.classList.toggle('access-disabled', cb.checked);
    if (req) req.style.display = cb.checked ? 'none' : '';
  }

  // ── Expand / Collapse All ─────────────────────────────────────────────
  function setupExpandAll(btnId, containerId, stateRef) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      stateRef.expanded = !stateRef.expanded;
      const wrap = document.getElementById(containerId);
      if (!wrap) return;
      wrap.querySelectorAll('.group-body, .assembly-wrap').forEach(el => {
        el.style.display = stateRef.expanded ? 'block' : 'none';
      });
      wrap.querySelectorAll('.group-header i.fa, .org-header i.fa').forEach(i => {
        i.className = i.className.replace('chevron-right', 'chevron-down').replace('chevron-down', stateRef.expanded ? 'chevron-down' : 'chevron-right');
      });
      btn.innerHTML = stateRef.expanded ? '<i class="fa fa-minus"></i> Collapse All' : '<i class="fa fa-plus"></i> Expand All';
    });
  }

  // ── Select All / Clear All ────────────────────────────────────────────
  function setupSelectAll(selectBtnId, clearBtnId, getAccess, containerEl, onUpdate) {
    document.getElementById(selectBtnId)?.addEventListener('click', function () {
      const acc = getAccess();
      // allOrganisms: {org: {assembly: [gene_sets]}}
      Object.keys(allOrganisms).forEach(org => {
        acc[org] = {};
        Object.keys(allOrganisms[org]).forEach(asm => { acc[org][asm] = ['*']; });
      });
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
    });
    document.getElementById(clearBtnId)?.addEventListener('click', function () {
      const acc = getAccess();
      Object.keys(acc).forEach(k => delete acc[k]);
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
    });
  }

  // ── Filter (groups + organisms) ───────────────────────────────────────
  function setupFilter(filterId, containerId) {
    const inp = document.getElementById(filterId);
    if (!inp) return;
    inp.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      const container = document.getElementById(containerId);
      if (!container) return;
      container.querySelectorAll('.group-section').forEach(groupSection => {
        const groupName = (groupSection.dataset.group || '').toLowerCase();
        const groupMatchesQuery = !q || groupName.includes(q);
        let anyOrgVisible = false;

        groupSection.querySelectorAll('.org-section').forEach(orgSection => {
          const orgName = (orgSection.dataset.org || '').toLowerCase();
          const show = groupMatchesQuery || orgName.includes(q);
          orgSection.style.display = show ? '' : 'none';
          if (show) anyOrgVisible = true;
        });

        groupSection.style.display = anyOrgVisible ? '' : 'none';
        if (anyOrgVisible && q) {
          const body = groupSection.querySelector('.group-body');
          const chev = groupSection.querySelector('.group-header i.fa');
          if (body) body.style.display = 'block';
          if (chev) chev.className = chev.className.replace('chevron-right', 'chevron-down');
        }
      });
    });
  }

  // ── Copy from user ────────────────────────────────────────────────────
  function setupCopyFromUser(selectId, excludeUser, getAccess, containerEl, onUpdate) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    Object.keys(allUsers).sort().forEach(uname => {
      if (uname === excludeUser) return;
      const u = allUsers[uname];
      if (!u.access || !Object.keys(u.access).length) return;
      const opt = document.createElement('option');
      opt.value = uname;
      opt.textContent = uname + (u.email ? ` (${u.email})` : '');
      sel.appendChild(opt);
    });
    sel.addEventListener('change', function () {
      if (!this.value) return;
      const src = allUsers[this.value];
      if (!src?.access) return;
      const acc = getAccess();
      Object.keys(acc).forEach(k => delete acc[k]);
      // Handle both old {org: [asms]} and new {org: {asm: [gene_sets]}} formats
      Object.keys(src.access).forEach(org => {
        const d = src.access[org];
        if (Array.isArray(d)) {
          // Old format — convert
          acc[org] = {};
          d.forEach(asm => { acc[org][asm] = ['*']; });
        } else if (d && typeof d === 'object') {
          acc[org] = { ...d };
        }
      });
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
      this.value = '';
    });
  }

  // ── CREATE FORM ───────────────────────────────────────────────────────
  function initCreateForm() {
    const containerEl = document.getElementById('create-access-container');
    if (!containerEl) return;

    function onUpdate() { syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess); }
    renderAssemblySelector(containerEl, createSelectedAccess, onUpdate);

    watchPasswordMatch('create-password', 'create-password-confirm', 'create-pw-match');

    document.getElementById('create-isAdmin')?.addEventListener('change', function () {
      toggleAccess('create-isAdmin', 'create-access-section', 'create-access-required');
      if (this.checked) {
        createSelectedAccess = {};
        syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess);
        renderAssemblySelector(containerEl, createSelectedAccess, onUpdate);
      }
    });

    setupExpandAll('create-toggle-all-btn', 'create-access-container', { expanded: false });
    setupFilter('create-organism-filter', 'create-access-container');
    setupSelectAll('create-select-all-btn', 'create-clear-all-btn', () => createSelectedAccess, containerEl, onUpdate);
    setupCopyFromUser('create-copy-from-user', null, () => createSelectedAccess, containerEl, onUpdate);

    document.getElementById('createUserForm')?.addEventListener('submit', function (e) {
      const pw = document.getElementById('create-password').value;
      const pw2 = document.getElementById('create-password-confirm').value;
      const adm = document.getElementById('create-isAdmin').checked;
      const hasAsm = Object.keys(createSelectedAccess).some(org => Object.keys(createSelectedAccess[org] || {}).length > 0);
      if (!pw) { e.preventDefault(); alert('Password is required for new users.'); document.getElementById('create-password').focus(); return; }
      if (pw !== pw2) { e.preventDefault(); alert('Passwords do not match.'); document.getElementById('create-password-confirm').focus(); return; }
      if (!adm && !hasAsm) { e.preventDefault(); alert('Select at least one assembly, or check Admin for full access.'); }
    });
  }

  // ── EDIT MODAL ────────────────────────────────────────────────────────
  function initEditModal() {
    const containerEl = document.getElementById('modal-access-container');
    if (!containerEl) return;

    let currentEditUser = null;

    function onUpdate() { syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess); }

    watchPasswordMatch('modal-password', 'modal-password-confirm', 'modal-pw-match');

    document.getElementById('modal-isAdmin')?.addEventListener('change', function () {
      toggleAccess('modal-isAdmin', 'modal-access-section', 'modal-access-required');
      if (this.checked) {
        editSelectedAccess = {};
        syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);
        renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
      }
    });

    setupExpandAll('modal-toggle-all-btn', 'modal-access-container', { expanded: false });
    setupFilter('modal-organism-filter', 'modal-access-container');
    setupSelectAll('modal-select-all-btn', 'modal-clear-all-btn', () => editSelectedAccess, containerEl, onUpdate);

    // Copy-from-user is re-populated each time the modal opens to exclude the current user
    const copyFromSel = document.getElementById('modal-copy-from-user');

    document.addEventListener('click', function (event) {
      const btn = event.target.closest('.edit-user-btn');
      if (!btn) return;
      const username = btn.getAttribute('data-username');
      if (!username) return;
      const userData = allUsers[username];
      if (!userData) return;

      currentEditUser = username;

      document.getElementById('modal-username-display').textContent = username;
      document.getElementById('modal-original-username').value = username;
      document.getElementById('modal-username').value      = username;
      document.getElementById('modal-email').value         = userData.email        || '';
      document.getElementById('modal-first-name').value   = userData.first_name   || '';
      document.getElementById('modal-last-name').value    = userData.last_name    || '';
      document.getElementById('modal-account-host').value = userData.account_host || '';
      document.getElementById('modal-password').value         = '';
      document.getElementById('modal-password-confirm').value = '';
      document.getElementById('modal-pw-match').style.display = 'none';

      const isAdmin = (userData.role === 'admin');
      document.getElementById('modal-isAdmin').checked = isAdmin;
      toggleAccess('modal-isAdmin', 'modal-access-section', 'modal-access-required');

      editSelectedAccess = {};
      if (userData.access && typeof userData.access === 'object') {
        Object.keys(userData.access).forEach(org => {
          const d = userData.access[org];
          if (Array.isArray(d)) {
            // Old format — convert to new
            editSelectedAccess[org] = {};
            d.forEach(asm => { editSelectedAccess[org][asm] = ['*']; });
          } else if (d && typeof d === 'object') {
            editSelectedAccess[org] = { ...d };
          }
        });
      }

      renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
      syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);

      // Rebuild copy-from-user options excluding this user
      if (copyFromSel) {
        copyFromSel.innerHTML = '<option value="">— select user —</option>';
        Object.keys(allUsers).sort().forEach(uname => {
          if (uname === username) return;
          const u = allUsers[uname];
          if (!u.access || !Object.keys(u.access).length) return;
          const opt = document.createElement('option');
          opt.value = uname;
          opt.textContent = uname + (u.email ? ` (${u.email})` : '');
          copyFromSel.appendChild(opt);
        });
      }

      // Stale assemblies
      const staleAlert = document.getElementById('modal-stale-alert');
      const staleItems = document.getElementById('modal-stale-items');
      staleItems.innerHTML = '';
      const staleList = [];
      Object.keys(userData.access || {}).forEach(org => {
        const d = userData.access[org];
        const asmKeys = Array.isArray(d) ? d : Object.keys(d || {});
        asmKeys.forEach(asm => {
          if (!Object.prototype.hasOwnProperty.call(allOrganisms[org] || {}, asm)) staleList.push({ org, asm });
        });
      });
      if (staleList.length > 0) {
        staleAlert.classList.remove('d-none');
        staleList.forEach(({ org, asm }) => {
          const chip = document.createElement('span');
          chip.className = 'tag-chip tag-chip-stale me-1';
          chip.textContent = `${org}: ${asm}`;
          const rm = document.createElement('i');
          rm.className = 'fa fa-times ms-1'; rm.style.cursor = 'pointer';
          rm.addEventListener('click', function () {
            delete editSelectedAccess[org]?.[asm];
            if (editSelectedAccess[org] && !Object.keys(editSelectedAccess[org]).length) delete editSelectedAccess[org];
            chip.remove();
            if (!staleItems.children.length) staleAlert.classList.add('d-none');
            renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
            syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);
          });
          chip.appendChild(rm);
          staleItems.appendChild(chip);
        });
      } else {
        staleAlert.classList.add('d-none');
      }
    });

    if (copyFromSel) {
      copyFromSel.addEventListener('change', function () {
        if (!this.value) return;
        const src = allUsers[this.value];
        if (!src?.access) return;
        Object.keys(editSelectedAccess).forEach(k => delete editSelectedAccess[k]);
        Object.keys(src.access).forEach(org => {
          const d = src.access[org];
          if (Array.isArray(d)) {
            editSelectedAccess[org] = {};
            d.forEach(asm => { editSelectedAccess[org][asm] = ['*']; });
          } else if (d && typeof d === 'object') {
            editSelectedAccess[org] = { ...d };
          }
        });
        renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
        onUpdate();
        this.value = '';
      });
    }

    document.getElementById('editUserForm')?.addEventListener('submit', function (e) {
      const pw = document.getElementById('modal-password').value;
      const pw2 = document.getElementById('modal-password-confirm').value;
      const adm = document.getElementById('modal-isAdmin').checked;
      const hasAsm = Object.keys(editSelectedAccess).some(org => Object.keys(editSelectedAccess[org] || {}).length > 0);
      if (pw && pw !== pw2) { e.preventDefault(); alert('Passwords do not match.'); document.getElementById('modal-password-confirm').focus(); return; }
      if (!adm && !hasAsm) { e.preventDefault(); alert('Select at least one assembly, or check Admin for full access.'); }
    });
  }

  // ── DELETE ────────────────────────────────────────────────────────────
  function initDeleteButtons() {
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const username = this.getAttribute('data-username');
        if (!confirm(`Permanently delete user "${username}"? This cannot be undone.`)) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="csrf_token" value="${token}"><input type="hidden" name="delete_user" value="1"><input type="hidden" name="username" value="${username}">`;
        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  // ── RESET CREATE FORM ─────────────────────────────────────────────────
  window.resetCreateForm = function () {
    createSelectedAccess = {};
    const containerEl = document.getElementById('create-access-container');
    if (containerEl) renderAssemblySelector(containerEl, createSelectedAccess, () => syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess));
    syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess);
    document.getElementById('create-pw-match').style.display = 'none';
    document.getElementById('create-access-section')?.classList.remove('access-disabled');
    const req = document.getElementById('create-access-required');
    if (req) req.style.display = '';
  };

  // ── STALE AUDIT TOGGLE ────────────────────────────────────────────────
  function initStaleAudit() {
    const header = document.getElementById('stale-audit-header');
    const panel  = document.getElementById('stale-audit');
    if (!header || !panel) return;
    header.addEventListener('click', function () {
      const hidden = panel.style.display === 'none' || !panel.style.display;
      panel.style.display = hidden ? 'block' : 'none';
      const ic = header.querySelector('.fa-chevron-down, .fa-chevron-up');
      if (ic) ic.className = hidden ? 'fa fa-chevron-up float-end mt-1' : 'fa fa-chevron-down float-end mt-1';
    });
  }

  // ── DataTable ─────────────────────────────────────────────────────────
  function initDataTable() {
    if (typeof $ !== 'undefined' && $.fn?.DataTable) {
      $('#usersTable').DataTable({ pageLength: 25, order: [[0, 'asc']], columnDefs: [{ targets: 6, orderable: false }] });
    }
  }

  // ── Boot ──────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    Object.keys(allOrganisms).forEach(org => orgColor(org));
    initCreateForm();
    initEditModal();
    initDeleteButtons();
    initStaleAudit();
    initDataTable();
  });

})();
</script>
