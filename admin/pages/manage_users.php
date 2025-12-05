

<div class="container mt-5">
  <?php
  ?>
  
  <h2><i class="fa fa-users"></i> Manage Users</h2>

  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutUserAccessControl">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About User Access Control <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutUserAccessControl">
      <div class="card-body">
        <p><strong>Purpose:</strong> Create and manage user accounts, controlling access to organism data.</p>
        
        <p><strong>Why It Matters:</strong> The system has two access models:</p>
        <ol>
          <li><strong>Internal Network Users (IP-based):</strong> All users from your institution's IP range automatically have FULL access to ALL organisms - no login needed</li>
          <li><strong>Collaborators (Account-based):</strong> External users you invite with access to Public group + custom organism assemblies you assign</li>
        </ol>
        
        <p><strong>How It Works:</strong></p>
        <ul>
          <li>Internal users are recognized by IP address (configured in site settings)</li>
          <li>Collaborators create accounts and log in</li>
          <li>Each Collaborator gets access to Public organisms + any groups you assign them</li>
          <li>Update Collaborator access</li>
          <li>To configure Public organisms and assemblies use the manage groups page</li>
        </ul>
        
        <p class="mb-0"><strong>What You Can Do:</strong></p>
        <ul class="mb-0">
          <li>Add new user accounts for external collaborators</li>
          <li>Manage login credentials and permissions</li>
          <li>Edit existing accounts and update access levels</li>
          <li>Remove users when collaboration ends</li>
          <li>Create Admin users</li>
        </ul>
      </div>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  
  <?php if ($file_write_error): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h4><i class="fa fa-exclamation-circle"></i> File Permission Issue Detected</h4>
      <p><strong>Problem:</strong> The file <code>includes/users.json</code> is not writable by the web server.</p>
      
      <p><strong>Current Status:</strong></p>
      <ul class="mb-3">
        <li>File owner: <code><?= htmlspecialchars($file_write_error['owner']) ?></code></li>
        <li>Current permissions: <code><?= $file_write_error['perms'] ?></code></li>
        <li>Web server user: <code><?= htmlspecialchars($file_write_error['web_user']) ?></code></li>
        <?php if ($file_write_error['web_group']): ?>
        <li>Web server group: <code><?= htmlspecialchars($file_write_error['web_group']) ?></code></li>
        <?php endif; ?>
      </ul>
      
      <p><strong>To Fix:</strong> Run this command on the server:</p>
      <div style="margin: 10px 0; background: #f0f0f0; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
        <code style="word-break: break-all; display: block; font-size: 0.9em;">
          <?= htmlspecialchars($file_write_error['command']) ?>
        </code>
      </div>
      
      <p><small class="text-muted">After running the command, refresh this page.</small></p>
    </div>
  <?php endif; ?>

  <!-- Create New User Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0"><i class="fa fa-user-plus"></i> Create New User</h4>
        </div>
        <div class="card-body">
          <form method="post" id="createUserForm">
            <input type="hidden" name="create_user" value="1">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" id="username" class="form-control" required>
              </div>

              <div class="col-md-6 mb-3">
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" id="password" class="form-control" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control">
              </div>

              <div class="col-md-6 mb-3">
                <label for="account_host" class="form-label">Account Host</label>
                <input type="text" name="account_host" id="account_host" class="form-control" placeholder="Lab or person requesting access">
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

            <div class="mb-3 form-check">
              <input type="checkbox" name="isAdmin" id="isAdmin" class="form-check-input">
              <label class="form-check-label" for="isAdmin"><strong>Is Admin</strong></label>
            </div>

            <div class="mb-3" id="groups-section">
              <label class="form-label">Access Groups</label>
              <small class="text-muted d-block mb-2">Filter and click organism assemblies to grant access (not applicable for admins)</small>
              <div class="mb-2 d-flex gap-2">
                <input type="text" id="organism-filter" class="form-control form-control-sm" placeholder="Filter organisms...">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-all-btn" title="Expand/Collapse all">
                  <i class="fa fa-plus"></i> Expand All
                </button>
              </div>
              <div class="border rounded" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa;" id="create-access-container">
                <?php foreach ($organisms as $organism => $assemblies): ?>
                  <div class="organism-group" data-organism-name="<?= strtolower(htmlspecialchars($organism)) ?>" style="border-bottom: 1px solid #dee2e6; padding: 8px 10px;">
                    <div style="font-weight: 600; font-size: 12px; margin-bottom: 6px; cursor: pointer;" class="organism-toggle" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'; this.querySelector('i').classList.toggle('fa-chevron-right'); this.querySelector('i').classList.toggle('fa-chevron-down');">
                      <i class="fa fa-chevron-right"></i> <?= htmlspecialchars($organism) ?>
                    </div>
                    <div style="display: none;">
                      <?php foreach ($assemblies as $assembly): ?>
                        <span class="tag-chip-selector create-chip" 
                              data-organism="<?= htmlspecialchars($organism) ?>" 
                              data-assembly="<?= htmlspecialchars($assembly) ?>"
                              style="font-size: 11px; padding: 3px 8px; margin: 2px;">
                          <?= htmlspecialchars($assembly) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div id="create-selected-hidden"></div>
            </div>

            <button type="submit" class="btn btn-success" <?= $file_write_error ? 'disabled' : '' ?>>
              <i class="fa fa-user-plus"></i> Create User
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Legend for Assembly Status -->
  <div class="alert alert-info mb-4">
    <strong><i class="fa fa-info-circle"></i> Assembly Status Legend:</strong>
    <div style="margin-top: 10px;">
      <span class="tag-chip">Active Assembly</span>
      <span style="margin-left: 10px; margin-right: 10px;">— Assembly exists and is available (filled with color)</span>
    </div>
    <div style="margin-top: 8px;">
      <span class="tag-chip tag-chip-stale">Stale Assembly</span>
      <span style="margin-left: 10px;">— Assembly was deleted or moved (red outline, no fill). Admin should remove from user access.</span>
    </div>
  </div>
  
  <!-- Existing Users Table -->
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
          <h4 class="mb-0"><i class="fa fa-users"></i> Existing Users</h4>
        </div>
        <div class="card-body">
          <table id="usersTable" class="table table-striped table-hover">
            <thead>
              <tr>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Account Host</th>
                <th>Role</th>
                <th>Access</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user => $userData): ?>
                <tr data-username="<?= htmlspecialchars($user) ?>">
                  <td><?= htmlspecialchars($user) ?></td>
                  <td>
                    <?php 
                    $name = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
                    echo htmlspecialchars($name ?: '-');
                    ?>
                  </td>
                  <td>
                    <?php if (!empty($userData['email'])): ?>
                      <a href="mailto:<?= htmlspecialchars($userData['email']) ?>"><?= htmlspecialchars($userData['email']) ?></a>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($userData['account_host'] ?? '-') ?></td>
                  <td>
                    <?php if (isset($userData['role']) && $userData['role'] === 'admin'): ?>
                      <span class="badge bg-danger">Admin</span>
                    <?php else: ?>
                      <span class="badge bg-primary">Collaborator</span>
                    <?php endif; ?>
                  </td>
                  <td class="access-display">
                    <?php 
                    if (isset($userData['role']) && $userData['role'] === 'admin') {
                        echo '<span class="text-muted">All Access</span>';
                    } elseif (isset($userData['access']) && is_array($userData['access']) && !empty($userData['access'])) {
                        foreach ($userData['access'] as $org => $assemblies) {
                            if (is_array($assemblies)) {
                                foreach ($assemblies as $asm) {
                                    // Check if assembly exists in filesystem
                                    $exists = isset($organisms[$org]) && in_array($asm, $organisms[$org]);
                                    $class = $exists ? 'tag-chip' : 'tag-chip tag-chip-stale';
                                    $title = $exists ? '' : ' title="Assembly no longer exists - please remove from user access"';
                                    echo '<span class="' . $class . '" data-organism="'.htmlspecialchars($org).'" data-assembly="'.htmlspecialchars($asm).'"' . $title . '>' . htmlspecialchars($org) . ': ' . htmlspecialchars($asm) . '</span>';
                                }
                            }
                        }
                    } else {
                        echo '<span class="text-muted">No access</span>';
                    }
                    ?>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-warning edit-user-btn" data-username="<?= htmlspecialchars($user) ?>">
                      <i class="fa fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger delete-user-btn" data-username="<?= htmlspecialchars($user) ?>">
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
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit User: <span id="editUsername"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="editUserForm">
        <input type="hidden" name="update_user" value="1">
        <input type="hidden" name="username" id="editUsernameInput">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="editEmail" class="form-label">Email Address</label>
              <input type="email" name="email" id="editEmail" class="form-control">
            </div>
            
            <div class="col-md-6 mb-3">
              <label for="editAccountHost" class="form-label">Account Host</label>
              <input type="text" name="account_host" id="editAccountHost" class="form-control">
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="editFirstName" class="form-label">First Name</label>
              <input type="text" name="first_name" id="editFirstName" class="form-control">
            </div>
            
            <div class="col-md-6 mb-3">
              <label for="editLastName" class="form-label">Last Name</label>
              <input type="text" name="last_name" id="editLastName" class="form-control">
            </div>
          </div>
          
          <div class="mb-3">
            <label for="editPassword" class="form-label">New Password (leave blank to keep current)</label>
            <input type="password" name="new_password" id="editPassword" class="form-control">
          </div>
          
          <div class="mb-3 form-check">
            <input type="checkbox" name="isAdmin" id="editIsAdmin" class="form-check-input">
            <label class="form-check-label" for="editIsAdmin"><strong>Is Admin</strong></label>
          </div>
          
          <!-- Stale Assemblies Section -->
          <div class="mb-3" id="edit-stale-section" style="display: none;">
            <div class="alert alert-warning">
              <strong><i class="fa fa-exclamation-circle"></i> Stale Assemblies</strong>
              <p class="mb-2 small">These assemblies were deleted or moved. Remove them from user access:</p>
              <div id="edit-stale-list" style="margin-bottom: 10px;"></div>
              <button type="button" class="btn btn-sm btn-warning" id="remove-all-stale-btn">
                <i class="fa fa-trash"></i> Remove All Stale
              </button>
            </div>
          </div>
          
          <div class="mb-3" id="edit-groups-section">
            <label class="form-label">Access Groups</label>
            <div id="edit-access-container"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" <?= $file_write_error ? 'disabled' : '' ?>>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .tag-chip {
    display: inline-block;
    padding: 5px 12px;
    margin: 3px;
    border-radius: 15px;
    font-size: 12px;
    background: #007bff;
    color: white;
    border: 2px solid #0056b3;
  }
  .tag-chip.removable {
    cursor: pointer;
  }
  .tag-chip.tag-chip-stale {
    background: transparent;
    color: #dc3545;
    border: 2px solid #dc3545;
  }
  .tag-chip .remove {
    margin-left: 5px;
    font-weight: bold;
  }
  .tag-chip-selector {
    display: inline-block;
    padding: 5px 12px;
    margin: 3px;
    border-radius: 15px;
    font-size: 12px;
    cursor: pointer;
    background: #e9ecef;
    color: #495057;
    border: 2px solid #dee2e6;
    transition: all 0.2s;
  }
  .tag-chip-selector:hover {
    background: #d3d6da;
    border-color: #adb5bd;
  }
  .tag-chip-selector.selected {
    background: #007bff;
    color: white;
    border-color: #0056b3;
  }
</style>









