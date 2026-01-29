<div class="container mt-5">

  <!-- Back to Admin Dashboard Link -->
  <div class="mb-4">
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

  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutUserAccessControl">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About User Access Control <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutUserAccessControl">
      <div class="card-body">
        <p><strong>Purpose:</strong> Create and manage user accounts, controlling access to organism assemblies.</p>
        
        <p><strong>Why It Matters:</strong> The system has two access models:</p>
        <ol>
          <li><strong>Internal Network Users (IP-based):</strong> All users from your institution's IP range automatically have FULL access to ALL organisms - no login needed</li>
          <li><strong>Collaborators (Account-based):</strong> External users you invite with accounts and specific assembly access</li>
          <li><strong>Admin Users:</strong> Full system access for managing organisms, groups, users, and configuration</li>
        </ol>
        
        <p><strong>How It Works:</strong></p>
        <ul>
          <li>Internal users are recognized by IP address (configured in site settings)</li>
          <li>Collaborators create accounts and log in</li>
          <li>Each Collaborator gets access to assemblies you assign them (can span multiple organisms)</li>
          <li>Admin users have automatic full access to everything</li>
          <li>Regular users MUST have at least 1 assembly assigned (unless promoted to admin)</li>
        </ul>
        
        <p><strong>Access Management:</strong></p>
        <ul>
          <li><strong>Create User:</strong> Add new account with assemblies user can access</li>
          <li><strong>Edit User:</strong> Modify account details, add/remove assemblies, promote to admin</li>
          <li><strong>Delete User:</strong> Remove account and all access</li>
          <li><strong>Stale Assemblies:</strong> Automatically tracked - appears when directories are deleted but user still has access</li>
          <li><strong>Preview:</strong> See selected assemblies in real-time as you add them</li>
        </ul>
        
        <p class="mb-0"><strong>What You Can Do:</strong></p>
        <ul class="mb-0">
          <li>Add new user accounts for external collaborators</li>
          <li>Assign specific organism assemblies to each user</li>
          <li>Search through large organism lists with the filter</li>
          <li>Edit existing accounts and update assembly access</li>
          <li>Remove users when collaboration ends</li>
          <li>Create Admin users with full system access</li>
          <li>View and remove stale assembly references</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- CREATE/EDIT USER FORM -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-success text-white">
      <h4 class="mb-0"><i class="fa fa-user-plus"></i> <span id="form-title">Create New User</span></h4>
    </div>
    <div class="card-body">
      <form method="post" id="userForm">
        <input type="hidden" name="create_or_update_user" value="1">
        <input type="hidden" name="is_create" id="is_create" value="1">
        <input type="hidden" name="original_username" id="original_username" value="">

        <!-- Basic Info Row -->
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" id="username" class="form-control" placeholder="Unique username">
          </div>
          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" name="email" id="email" class="form-control">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input type="text" name="first_name" id="first_name" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input type="text" name="last_name" id="last_name" class="form-control">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="account_host" class="form-label">Account Host</label>
            <input type="text" name="account_host" id="account_host" class="form-control" placeholder="Lab or institution">
          </div>
          <div class="col-md-6 mb-3">
            <label for="password" class="form-label" id="password_label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" id="password" class="form-control">
            <small class="text-muted" id="password_help">Leave blank to keep current password when editing</small>
          </div>
        </div>

        <!-- Admin Checkbox -->
        <div class="mb-3 form-check">
          <input type="checkbox" name="isAdmin" id="isAdmin" class="form-check-input">
          <label class="form-check-label" for="isAdmin">
            <strong>Admin User</strong> - Grants automatic full access to all organisms
          </label>
        </div>

        <!-- STALE ASSEMBLIES ALERT (shown when editing user with stale entries) -->
        <div class="alert alert-warning" id="stale-alert" style="display: none;">
          <strong><i class="fa fa-exclamation-circle"></i> Stale Assemblies</strong>
          <p class="mb-2 small">This user has access to assemblies that no longer exist. Click X to remove:</p>
          <div id="stale-items" style="margin-bottom: 10px;"></div>
        </div>

        <!-- ACCESS GROUPS (hidden for admins) -->
        <div class="mb-3" id="access-section">
          <label class="form-label">Assign Organism Access <span class="text-danger" id="required-badge">*</span></label>
          <small class="text-muted d-block mb-2">Select at least one assembly to grant user access (not applicable for admins)</small>
          
          <div class="mb-2 d-flex gap-2">
            <input type="text" id="organism-filter" class="form-control form-control-sm" placeholder="Filter organisms...">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-all-btn">
              <i class="fa fa-plus"></i> Expand All
            </button>
          </div>

          <div class="border rounded" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa;" id="access-container">
            <!-- Organisms populated by JS -->
          </div>

          <div id="selected-assemblies-hidden"></div>
        </div>

        <!-- SELECTED ASSEMBLIES PREVIEW -->
        <div class="mb-3" id="preview-section">
          <label class="form-label">Selected Assemblies</label>
          <div id="selected-preview" class="p-3 border rounded" style="background-color: #f8f9fa; min-height: 50px;">
            <span class="text-muted small"><i class="fa fa-check-circle"></i> Select assemblies above to see them here</span>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-4">
          <button type="submit" class="btn btn-success" id="submit-btn">
            <i class="fa fa-user-plus"></i> <span id="submit-text">Create User</span>
          </button>
          <button type="button" class="btn btn-secondary" id="cancel-btn" onclick="resetForm()">
            <i class="fa fa-times"></i> Clear Form
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- EXISTING USERS TABLE -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
      <h4 class="mb-0"><i class="fa fa-users"></i> Existing Users</h4>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-hover" id="usersTable">
          <thead>
            <tr>
              <th>Username</th>
              <th>Email</th>
              <th>Name</th>
              <th># Assemblies</th>
              <th>Stale ⚠️</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $username => $userData): ?>
              <?php 
                $assemblyCount = 0;
                $staleCount = 0;
                
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
              ?>
              <tr data-username="<?= htmlspecialchars($username) ?>">
                <td><strong><?= htmlspecialchars($username) ?></strong></td>
                <td><?= htmlspecialchars($userData['email'] ?? '') ?></td>
                <td><?= htmlspecialchars(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')) ?></td>
                <td><?= $userData['role'] === 'admin' ? '<span class="badge bg-success">Admin (All)</span>' : $assemblyCount ?></td>
                <td>
                  <?php if ($staleCount > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $staleCount ?></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn btn-sm btn-warning edit-user-btn" type="button" data-username="<?= htmlspecialchars($username) ?>">
                    <i class="fa fa-edit"></i> Edit
                  </button>
                  <button class="btn btn-sm btn-danger delete-user-btn" type="button" data-username="<?= htmlspecialchars($username) ?>">
                    <i class="fa fa-trash"></i> Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- STALE ASSEMBLIES AUDIT SECTION -->
  <?php if (!empty($stale_entries_audit)): ?>
    <div class="card shadow-sm mb-4 border-warning">
      <div class="card-header bg-warning text-dark" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#stale-audit">
        <h4 class="mb-0"><i class="fa fa-exclamation-triangle"></i> Stale Assemblies Audit 
          <span class="badge bg-danger"><?= count($stale_entries_audit) ?></span>
          <i class="fa fa-chevron-down float-end"></i>
        </h4>
      </div>
      <div class="collapse" id="stale-audit">
        <div class="card-body">
          <p class="text-muted">Assemblies that have been deleted or moved but are still assigned to users. Click Remove to clean up individual entries or Remove from All to remove from all users at once.</p>
          
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Email</th>
                  <th>Organism</th>
                  <th>Assembly</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($stale_entries_audit as $stale): ?>
                  <tr style="background-color: #fff3cd;">
                    <td>
                      <strong><?= htmlspecialchars($stale['username']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($stale['email']) ?></td>
                    <td><?= htmlspecialchars($stale['organism']) ?></td>
                    <td>
                      <span class="tag-chip tag-chip-stale">
                        <?= htmlspecialchars($stale['assembly']) ?>
                      </span>
                    </td>
                    <td>
                      <form method="post" style="display: inline;">
                        <input type="hidden" name="remove_stale_assembly" value="1">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($stale['username']) ?>">
                        <input type="hidden" name="organism" value="<?= htmlspecialchars($stale['organism']) ?>">
                        <input type="hidden" name="assembly" value="<?= htmlspecialchars($stale['assembly']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                      </form>
                      <form method="post" style="display: inline;" onclick="return confirm('Remove this stale assembly from ALL users?');">
                        <input type="hidden" name="remove_stale_from_all" value="1">
                        <input type="hidden" name="organism" value="<?= htmlspecialchars($stale['organism']) ?>">
                        <input type="hidden" name="assembly" value="<?= htmlspecialchars($stale['assembly']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning">Remove from All</button>
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

  <!-- Back to Admin Dashboard Link (Bottom) -->
  <div class="mt-5 mb-4">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>

</div>

<!-- Temporary: Use Bootstrap collapse for audit section instead of manual handler -->
<script>
  // Add manual collapse handler for About section
  (function() {
    document.addEventListener('DOMContentLoaded', function() {
      const aboutHeader = document.querySelector('[data-bs-target="#aboutUserAccessControl"]');
      if (aboutHeader) {
        aboutHeader.removeAttribute('data-bs-toggle');
        aboutHeader.addEventListener('click', function(e) {
          e.preventDefault();
          const content = document.querySelector('#aboutUserAccessControl');
          content.classList.toggle('show');
          this.querySelector('i').classList.toggle('fa-chevron-down');
          this.querySelector('i').classList.toggle('fa-chevron-up');
        }, true);
      }

      const staleHeader = document.querySelector('[data-bs-target="#stale-audit"]');
      if (staleHeader) {
        staleHeader.removeAttribute('data-bs-toggle');
        staleHeader.addEventListener('click', function(e) {
          e.preventDefault();
          const content = document.querySelector('#stale-audit');
          content.classList.toggle('show');
          this.querySelector('i').classList.toggle('fa-chevron-down');
          this.querySelector('i').classList.toggle('fa-chevron-up');
        }, true);
      }
    });
  })();
</script>
