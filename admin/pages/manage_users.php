<div class="container mt-4">


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

  <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <h2 class="mb-0"><i class="fa fa-users"></i> Manage Users</h2>
    <?= help_modal_trigger('users-guide', 'How this page works') ?>
  </div>

  <?php if (!empty($stale_entries_audit)): $__ns = count($stale_entries_audit); $__nu = count(array_unique(array_column($stale_entries_audit, 'username'))); ?>
  <div class="alert alert-warning d-flex align-items-center gap-2 py-2" role="status">
    <i class="fa fa-exclamation-triangle"></i>
    <div><strong><?= $__ns ?> stale assignment<?= $__ns === 1 ? '' : 's' ?></strong> -- <?= $__nu ?> user<?= $__nu === 1 ? ' has' : 's have' ?> access to assemblies that no longer exist.
      <a href="#stale-audit-card" class="alert-link js-open-stale">See Stale Assembly References below</a>.</div>
  </div>
  <?php endif; ?>

  <p class="text-muted small">
    <i class="fa fa-info-circle me-1"></i>
    This page grants <strong>collaborators</strong> access to specific restricted organisms.
    To make a gene set visible to <strong>everyone</strong> instead, use the Visibility toggle in
    <a href="manage_groups.php">Manage Groups</a> — public data needs no user account at all.
  </p>

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
      <div class="table-responsive um-wrap">
        <?php // One row per account: who (name + email under the username), access, then activity. ?>
        <table class="table table-hover align-middle mb-0 um-table" id="usersTable">
          <thead>
            <tr>
              <th>User</th>
              <th>Access</th>
              <th title="When the account was made. ≈ = not recorded at the time: the date of the first site-data backup that has it">Created</th>
              <th title="The last time this person logged in with their password">Last login</th>
              <th title="Logins since counting began (2026-11-25); the last 30 days under it">Logins</th>
              <th class="text-end"><span class="visually-hidden">Actions</span></th>
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
                $isAdmin  = ($userData['role'] ?? '') === 'admin';
                $fullName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
                // Account activity (lib/user_activity.php). Dates as YYYY-MM-DD, the exact time on hover.
                $act     = $user_activity[$username] ?? [];
                $fmt_day = fn($ts) => '<span title="' . htmlspecialchars(date('Y-m-d H:i', (int) $ts)) . '">' . date('Y-m-d', (int) $ts) . '</span>';
                $ago     = function (int $ts): string {
                    $d = max(0, (int) floor((time() - $ts) / 86400));
                    return $d === 0 ? 'today' : ($d === 1 ? 'yesterday' : ($d < 60 ? "$d days ago" : round($d / 30) . ' months ago'));
                };
              ?>
              <tr>
                <td data-order="<?= htmlspecialchars(strtolower($username)) ?>">
                  <div class="um-username"><?= htmlspecialchars($username) ?></div>
                  <?php if ($fullName !== '' || !empty($userData['email'])): ?>
                  <div class="um-who"><?= htmlspecialchars($fullName) ?><?php if ($fullName !== '' && !empty($userData['email'])): ?> · <?php endif; ?><?php if (!empty($userData['email'])): ?><a href="mailto:<?= htmlspecialchars($userData['email']) ?>"><?= htmlspecialchars($userData['email']) ?></a><?php endif; ?></div>
                  <?php endif; ?>
                </td>
                <td data-order="<?= $isAdmin ? 1000000 : $assemblyCount ?>">
                  <?php if ($isAdmin): ?>
                    <span class="badge bg-danger">Admin</span> <span class="um-sub text-nowrap">all data</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Collaborator</span>
                    <span class="um-sub text-nowrap"><?= $assemblyCount ?> assembl<?= $assemblyCount === 1 ? 'y' : 'ies' ?></span>
                  <?php endif; ?>
                  <?php if ($staleCount > 0): ?>
                    <a href="#stale-audit-card" class="um-stale js-open-stale" title="Assigned assemblies that no longer exist -- see Stale Assembly References below">
                      <i class="fa fa-exclamation-triangle"></i> <?= $staleCount ?> stale</a>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap" data-order="<?= (int) ($act['created'] ?? 0) ?>">
                  <?php if (!empty($act['created'])): ?>
                    <?= $fmt_day($act['created']) ?><?php if (($act['created_how'] ?? '') === 'backup_history'): ?> <span class="um-sub" title="Not recorded when made: the date of the first site-data backup that has this account, so it may be a little older">≈</span><?php endif; ?>
                  <?php else: ?><span class="um-sub" title="Made before account dates were recorded">unknown</span><?php endif; ?>
                </td>
                <td class="text-nowrap" data-order="<?= (int) ($act['last_login'] ?? 0) ?>">
                  <?php if (!empty($act['last_login'])): ?>
                    <?= $fmt_day($act['last_login']) ?><div class="um-sub"><?= $ago((int) $act['last_login']) ?></div>
                  <?php else: ?><span class="um-sub" title="No login since logins were first counted (2026-11-25)">not yet</span><?php endif; ?>
                </td>
                <td class="text-nowrap" data-order="<?= (int) ($act['login_count'] ?? 0) ?>">
                  <?= (int) ($act['login_count'] ?? 0) ?><div class="um-sub"><?= moop_user_activity_recent($act, 30) ?> in 30 days</div>
                </td>
                <td class="text-end text-nowrap">
                  <button class="btn btn-sm btn-outline-primary edit-user-btn"
                          data-username="<?= htmlspecialchars($username) ?>"
                          data-bs-toggle="modal" data-bs-target="#editUserModal"
                          title="Edit <?= htmlspecialchars($username) ?>">
                    <i class="fa fa-edit"></i><span class="um-btn-label"> Edit</span>
                  </button>
                  <button class="btn btn-sm btn-outline-danger delete-user-btn ms-1"
                          data-username="<?= htmlspecialchars($username) ?>"
                          title="Delete <?= htmlspecialchars($username) ?>" aria-label="Delete <?= htmlspecialchars($username) ?>">
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
          <button type="reset" class="btn btn-outline-secondary btn-lg" id="resetCreateBtn">
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
  <div class="card shadow-sm mb-4 border-warning" id="stale-audit-card">
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
          <form method="post" data-confirm="Remove ALL <?= count($stale_entries_audit) ?> stale reference(s) from all users?">
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
                  <form method="post" style="display:inline;" data-confirm="Remove from ALL users?">
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
<?php include __DIR__ . '/guides/manage_users.php';   // the "How this page works" guide ?>
