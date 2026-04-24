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

  <!-- File permission warning -->
  <?php if ($file_write_error): ?>
    <div class="alert alert-danger">
      <i class="fa fa-exclamation-triangle"></i>
      <strong>Cannot save changes:</strong> <?= htmlspecialchars($file_write_error) ?>
    </div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════
       SECTION 1: EXISTING USERS TABLE
  ══════════════════════════════════════════════════════ -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
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
                    foreach ($userData['access'] as $org => $assemblies) {
                        if (is_array($assemblies)) {
                            foreach ($assemblies as $asm) {
                                $assemblyCount++;
                                if (!isset($organisms[$org]) || !in_array($asm, $organisms[$org])) {
                                    $staleCount++;
                                }
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
  <div class="card shadow-sm mb-4 border-success">
    <div class="card-header bg-success text-white">
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

          <div class="d-flex gap-2 mb-2 flex-wrap">
            <input type="text" id="create-organism-filter" class="form-control form-control-sm" placeholder="Filter organisms…" style="max-width:280px;">
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
    <div class="card-header bg-warning text-dark" style="cursor:pointer;" data-bs-target="#stale-audit" id="stale-audit-header">
      <h5 class="mb-0">
        <i class="fa fa-exclamation-triangle"></i> Stale Assembly References
        <span class="badge bg-danger ms-2"><?= count($stale_entries_audit) ?></span>
        <i class="fa fa-chevron-down float-end mt-1"></i>
      </h5>
    </div>
    <div id="stale-audit" style="display:none;">
      <div class="card-body">
        <p class="text-muted small">These assemblies are still assigned to users but no longer exist on disk.</p>
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
            <div class="card-header py-2">
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

            <div class="d-flex gap-2 mb-2 flex-wrap">
              <input type="text" id="modal-organism-filter" class="form-control form-control-sm" placeholder="Filter organisms…" style="max-width:280px;">
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
  /* Tighten up the organism list */
  .organism-group { border-bottom: 1px solid #dee2e6; padding: 6px 10px; }
  .organism-toggle { font-weight: 600; font-size: 12px; cursor: pointer; user-select: none; }
  .organism-toggle:hover { color: #0d6efd; }

  /* Dim the access section when admin is checked */
  .access-disabled { opacity: 0.45; pointer-events: none; }
</style>

<script>
(function() {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────
  let createSelectedAccess = {};
  let editSelectedAccess   = {};
  let createAllExpanded    = false;
  let modalAllExpanded     = false;

  // ── Colour helpers ─────────────────────────────────────────────────────
  const COLORS = ['#007bff','#28a745','#17a2b8','#ffc107','#dc3545','#6f42c1','#fd7e14','#20c997','#e83e8c','#6610f2'];
  const colorMap = {};
  let colorIdx = 0;
  function orgColor(org) {
    if (!colorMap[org]) { colorMap[org] = COLORS[colorIdx++ % COLORS.length]; }
    return colorMap[org];
  }

  // ── Assembly selector (shared, parameterised) ──────────────────────────
  /**
   * Render the organism/assembly chip list into `containerEl`.
   * `selectedAccess` is the state object to read from and write to.
   * `onUpdate` is called after any selection change.
   */
  function renderAssemblySelector(containerEl, selectedAccess, onUpdate) {
    containerEl.innerHTML = '';
    Object.keys(allOrganisms).sort().forEach(organism => {
      const orgDiv = document.createElement('div');
      orgDiv.className = 'organism-group';

      const header = document.createElement('div');
      header.className = 'organism-toggle d-flex align-items-center gap-1';
      const chevron = document.createElement('i');
      chevron.className = 'fa fa-chevron-right';
      header.appendChild(chevron);
      header.appendChild(document.createTextNode(' ' + organism));

      const assemblyWrap = document.createElement('div');
      assemblyWrap.style.display = 'none';
      assemblyWrap.className = 'assembly-wrap pt-1';

      allOrganisms[organism].forEach(assembly => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip-selector';
        chip.setAttribute('data-organism', organism);
        chip.setAttribute('data-assembly', assembly);
        chip.style.fontSize = '11px';
        chip.style.padding = '2px 8px';
        chip.style.margin = '2px';
        chip.style.background = orgColor(organism);
        chip.style.borderColor = orgColor(organism);
        chip.style.color = 'white';
        chip.style.border = '2px solid ' + orgColor(organism);
        chip.textContent = assembly;

        const isSelected = selectedAccess[organism] && selectedAccess[organism].includes(assembly);
        chip.style.opacity = isSelected ? '1' : '0.35';
        if (isSelected) chip.classList.add('selected');

        chip.addEventListener('click', function () {
          const sel = this.classList.toggle('selected');
          this.style.opacity = sel ? '1' : '0.35';
          if (sel) {
            if (!selectedAccess[organism]) selectedAccess[organism] = [];
            if (!selectedAccess[organism].includes(assembly)) selectedAccess[organism].push(assembly);
          } else {
            if (selectedAccess[organism]) {
              selectedAccess[organism] = selectedAccess[organism].filter(a => a !== assembly);
              if (!selectedAccess[organism].length) delete selectedAccess[organism];
            }
          }
          onUpdate();
        });

        assemblyWrap.appendChild(chip);
      });

      header.addEventListener('click', function () {
        const hidden = assemblyWrap.style.display === 'none';
        assemblyWrap.style.display = hidden ? 'block' : 'none';
        chevron.className = hidden ? 'fa fa-chevron-down' : 'fa fa-chevron-right';
      });

      orgDiv.appendChild(header);
      orgDiv.appendChild(assemblyWrap);
      containerEl.appendChild(orgDiv);
    });
  }

  /**
   * Sync the hidden inputs and preview badges from `selectedAccess`.
   */
  function syncSelection(hiddenContainerId, previewContainerId, selectedAccess) {
    const hiddenEl  = document.getElementById(hiddenContainerId);
    const previewEl = document.getElementById(previewContainerId);
    if (!hiddenEl || !previewEl) return;

    hiddenEl.innerHTML  = '';
    previewEl.innerHTML = '';

    let total = 0;
    Object.keys(selectedAccess).sort().forEach(org => {
      selectedAccess[org].forEach(asm => {
        total++;
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = `access[${org}][]`;
        inp.value = asm;
        hiddenEl.appendChild(inp);

        const badge = document.createElement('span');
        badge.className = 'tag-chip me-1 mb-1';
        badge.style.background   = orgColor(org);
        badge.style.borderColor  = orgColor(org);
        badge.textContent = `${org}: ${asm}`;

        const rm = document.createElement('i');
        rm.className = 'fa fa-times ms-2';
        rm.style.cssText = 'cursor:pointer;opacity:0.8;';
        rm.addEventListener('click', function (e) {
          e.stopPropagation();
          selectedAccess[org] = selectedAccess[org].filter(a => a !== asm);
          if (!selectedAccess[org].length) delete selectedAccess[org];
          syncSelection(hiddenContainerId, previewContainerId, selectedAccess);
          // Re-render the selector that owns this state
          const containerEl = document.getElementById(
            hiddenContainerId === 'create-selected-assemblies-hidden' ? 'create-access-container' : 'modal-access-container'
          );
          if (containerEl) renderAssemblySelector(containerEl, selectedAccess, function () {
            syncSelection(hiddenContainerId, previewContainerId, selectedAccess);
          });
        });
        badge.appendChild(rm);
        previewEl.appendChild(badge);
      });
    });

    if (total === 0) {
      previewEl.innerHTML = '<span class="text-muted small">No assemblies selected</span>';
    }
  }

  // ── Password match feedback ────────────────────────────────────────────
  function watchPasswordMatch(pwId, confirmId, msgId) {
    const pw  = document.getElementById(pwId);
    const pw2 = document.getElementById(confirmId);
    const msg = document.getElementById(msgId);
    if (!pw || !pw2 || !msg) return;
    function check() {
      const v1 = pw.value, v2 = pw2.value;
      if (!v1 && !v2) { msg.style.display = 'none'; return; }
      if (!v2)        { msg.style.display = 'none'; return; }
      if (v1 === v2) {
        msg.textContent    = '✓ Passwords match';
        msg.style.color    = '#198754';
        msg.style.display  = '';
      } else {
        msg.textContent    = '✗ Passwords do not match';
        msg.style.color    = '#dc3545';
        msg.style.display  = '';
      }
    }
    pw.addEventListener('input', check);
    pw2.addEventListener('input', check);
  }

  // ── Admin toggle (show/hide access section) ────────────────────────────
  function toggleAccess(checkboxId, sectionId, requiredId) {
    const cb  = document.getElementById(checkboxId);
    const sec = document.getElementById(sectionId);
    const req = document.getElementById(requiredId);
    if (!cb || !sec) return;
    if (cb.checked) {
      sec.classList.add('access-disabled');
      if (req) req.style.display = 'none';
    } else {
      sec.classList.remove('access-disabled');
      if (req) req.style.display = '';
    }
  }

  // ── Expand/Collapse All ────────────────────────────────────────────────
  function setupExpandAll(btnId, containerId, stateRef) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      stateRef.expanded = !stateRef.expanded;
      const wrap = document.getElementById(containerId);
      if (!wrap) return;
      wrap.querySelectorAll('.assembly-wrap').forEach(el => {
        el.style.display = stateRef.expanded ? 'block' : 'none';
      });
      wrap.querySelectorAll('.organism-toggle i').forEach(i => {
        i.className = stateRef.expanded ? 'fa fa-chevron-down' : 'fa fa-chevron-right';
      });
      btn.innerHTML = stateRef.expanded
        ? '<i class="fa fa-minus"></i> Collapse All'
        : '<i class="fa fa-plus"></i> Expand All';
    });
  }

  // ── Select All / Clear All ─────────────────────────────────────────────
  function setupSelectAll(selectBtnId, clearBtnId, getAccess, containerEl, onUpdate) {
    document.getElementById(selectBtnId)?.addEventListener('click', function () {
      const acc = getAccess();
      Object.keys(allOrganisms).forEach(org => { acc[org] = [...allOrganisms[org]]; });
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
    });
    document.getElementById(clearBtnId)?.addEventListener('click', function () {
      const acc = getAccess();
      Object.keys(acc).forEach(org => delete acc[org]);
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
    });
  }

  // ── Organism filter ────────────────────────────────────────────────────
  function setupFilter(filterId, containerId) {
    const inp = document.getElementById(filterId);
    if (!inp) return;
    inp.addEventListener('input', function () {
      const q = this.value.toLowerCase();
      document.getElementById(containerId)?.querySelectorAll('.organism-group').forEach(g => {
        const match = !q || g.textContent.toLowerCase().includes(q);
        g.style.display = match ? '' : 'none';
        if (match && q) {
          const wrap = g.querySelector('.assembly-wrap');
          if (wrap) {
            wrap.style.display = 'block';
            const ic = g.querySelector('.organism-toggle i');
            if (ic) ic.className = 'fa fa-chevron-down';
          }
        }
      });
    });
  }

  // ── CREATE FORM ────────────────────────────────────────────────────────
  function initCreateForm() {
    const containerEl = document.getElementById('create-access-container');
    if (!containerEl) return;

    function onUpdate() {
      syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess);
    }
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
    setupSelectAll('create-select-all-btn', 'create-clear-all-btn',
                   () => createSelectedAccess, containerEl, onUpdate);

    document.getElementById('createUserForm')?.addEventListener('submit', function (e) {
      const pw  = document.getElementById('create-password').value;
      const pw2 = document.getElementById('create-password-confirm').value;
      const adm = document.getElementById('create-isAdmin').checked;
      const hasAsm = Object.values(createSelectedAccess).some(a => a.length > 0);

      if (!pw) {
        e.preventDefault();
        alert('Password is required for new users.');
        document.getElementById('create-password').focus();
        return;
      }
      if (pw !== pw2) {
        e.preventDefault();
        alert('Passwords do not match.');
        document.getElementById('create-password-confirm').focus();
        return;
      }
      if (!adm && !hasAsm) {
        e.preventDefault();
        alert('Select at least one assembly, or check Admin for full access.');
      }
    });
  }

  // ── EDIT MODAL ─────────────────────────────────────────────────────────
  function initEditModal() {
    const containerEl = document.getElementById('modal-access-container');
    if (!containerEl) return;

    function onUpdate() {
      syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);
    }

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
    setupSelectAll('modal-select-all-btn', 'modal-clear-all-btn',
                   () => editSelectedAccess, containerEl, onUpdate);

    // Populate modal on click via document delegation.
    // Delegated so it works after DataTables reorders rows, and fires
    // synchronously before Bootstrap animates the modal open — so fields
    // are set before the user ever sees the modal.
    document.addEventListener('click', function (event) {
      const btn = event.target.closest('.edit-user-btn');
      if (!btn) return;
      const username = btn.getAttribute('data-username');
      if (!username) return;
      const userData = allUsers[username];
      if (!userData) return;

      // Header
      document.getElementById('modal-username-display').textContent = username;
      document.getElementById('modal-original-username').value = username;

      // Fields
      document.getElementById('modal-username').value      = username;
      document.getElementById('modal-email').value         = userData.email        || '';
      document.getElementById('modal-first-name').value   = userData.first_name   || '';
      document.getElementById('modal-last-name').value    = userData.last_name    || '';
      document.getElementById('modal-account-host').value = userData.account_host || '';
      document.getElementById('modal-password').value         = '';
      document.getElementById('modal-password-confirm').value = '';
      document.getElementById('modal-pw-match').style.display = 'none';

      // Admin checkbox — set BEFORE calling toggleAccess so it reads the correct state
      const isAdmin = (userData.role === 'admin');
      document.getElementById('modal-isAdmin').checked = isAdmin;
      toggleAccess('modal-isAdmin', 'modal-access-section', 'modal-access-required');

      // Build selected access
      editSelectedAccess = {};
      if (userData.access && typeof userData.access === 'object') {
        Object.keys(userData.access).forEach(org => {
          if (Array.isArray(userData.access[org])) {
            editSelectedAccess[org] = [...userData.access[org]];
          }
        });
      }

      // Render assembly selector and sync hidden inputs
      renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
      syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);

      // Stale assemblies
      const staleAlert = document.getElementById('modal-stale-alert');
      const staleItems = document.getElementById('modal-stale-items');
      staleItems.innerHTML = '';
      const staleList = [];
      Object.keys(userData.access || {}).forEach(org => {
        const available = allOrganisms[org] || [];
        (userData.access[org] || []).forEach(asm => {
          if (!available.includes(asm)) staleList.push({ org, asm });
        });
      });

      if (staleList.length > 0) {
        staleAlert.classList.remove('d-none');
        staleList.forEach(({ org, asm }) => {
          const chip = document.createElement('span');
          chip.className = 'tag-chip tag-chip-stale me-1';
          chip.textContent = `${org}: ${asm}`;
          const rm = document.createElement('i');
          rm.className = 'fa fa-times ms-1';
          rm.style.cursor = 'pointer';
          rm.addEventListener('click', function () {
            if (editSelectedAccess[org]) {
              editSelectedAccess[org] = editSelectedAccess[org].filter(a => a !== asm);
              if (!editSelectedAccess[org].length) delete editSelectedAccess[org];
            }
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
    }); // end delegated click → populate modal

    // Validate edit form on submit
    document.getElementById('editUserForm')?.addEventListener('submit', function (e) {
      const pw  = document.getElementById('modal-password').value;
      const pw2 = document.getElementById('modal-password-confirm').value;
      const adm = document.getElementById('modal-isAdmin').checked;
      const hasAsm = Object.values(editSelectedAccess).some(a => a.length > 0);

      if (pw && pw !== pw2) {
        e.preventDefault();
        alert('Passwords do not match.');
        document.getElementById('modal-password-confirm').focus();
        return;
      }
      if (!adm && !hasAsm) {
        e.preventDefault();
        alert('Select at least one assembly, or check Admin for full access.');
      }
    });
  }

  // ── DELETE ─────────────────────────────────────────────────────────────
  function initDeleteButtons() {
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const username = this.getAttribute('data-username');
        if (!confirm(`Permanently delete user "${username}"? This cannot be undone.`)) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML =
          `<input type="hidden" name="csrf_token"  value="${token}">` +
          `<input type="hidden" name="delete_user" value="1">` +
          `<input type="hidden" name="username"    value="${username}">`;
        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  // ── RESET CREATE FORM ──────────────────────────────────────────────────
  window.resetCreateForm = function () {
    createSelectedAccess = {};
    const containerEl = document.getElementById('create-access-container');
    if (containerEl) {
      renderAssemblySelector(containerEl, createSelectedAccess, function () {
        syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess);
      });
    }
    syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess);
    document.getElementById('create-pw-match').style.display = 'none';
    document.getElementById('create-access-section')?.classList.remove('access-disabled');
    document.getElementById('create-access-required').style.display = '';
  };

  // ── STALE AUDIT TOGGLE ─────────────────────────────────────────────────
  function initStaleAudit() {
    const header = document.getElementById('stale-audit-header');
    const panel  = document.getElementById('stale-audit');
    if (!header || !panel) return;
    header.style.cursor = 'pointer';
    header.addEventListener('click', function () {
      const hidden = panel.style.display === 'none' || !panel.style.display;
      panel.style.display = hidden ? 'block' : 'none';
      const ic = header.querySelector('.fa-chevron-down, .fa-chevron-up');
      if (ic) ic.className = hidden ? 'fa fa-chevron-up float-end mt-1' : 'fa fa-chevron-down float-end mt-1';
    });
  }

  // ── DataTable ──────────────────────────────────────────────────────────
  function initDataTable() {
    if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
      $('#usersTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [{ targets: 6, orderable: false }]
      });
    }
  }

  // ── Boot ───────────────────────────────────────────────────────────────
  // allOrganisms/allUsers are defined by inline_scripts which layout.php
  // outputs AFTER this content file, so they're only safe to access inside
  // DOMContentLoaded (by which point all scripts have been parsed).
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
