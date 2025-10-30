<?php
session_start();
$access_group = 'Admin';
include_once 'admin_header.php';

$usersFile = $users_file;
$users = [];

if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
    if ($users === null && json_last_error() !== JSON_ERROR_NONE) {
      die("Error reading users.json: " . json_last_error_msg());
    }
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Handle user creation
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $account_host = trim($_POST['account_host'] ?? '');
        $groups   = $_POST['groups'] ?? [];
        $is_admin = isset($_POST['isAdmin']);

        if (empty($username) || empty($password)) {
            $message = "Username and password are required.";
            $messageType = "danger";
        } elseif (isset($users[$username])) {
            $message = "That username already exists.";
            $messageType = "warning";
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $userData = [
                "password" => $hashedPassword,
                "email" => $email,
                "first_name" => $first_name,
                "last_name" => $last_name,
                "account_host" => $account_host,
            ];

            if ($is_admin) {
                $userData['role'] = 'admin';
                $userData['access'] = new stdClass(); // Empty object for admin
            } else {
                $userData['access'] = $groups;
            }

            $users[$username] = $userData;

            // Save back to JSON
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
              die("Error: Could not write to users.json");
            }

            $message = "User created successfully!";
            $messageType = "success";
        }
    }
    
    // Handle user update
    if (isset($_POST['update_user'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $account_host = trim($_POST['account_host'] ?? '');
        $groups   = $_POST['groups'] ?? [];
        $is_admin = isset($_POST['isAdmin']);
        $new_password = $_POST['new_password'] ?? '';

        if (isset($users[$username])) {
            // Update user info
            $users[$username]['email'] = $email;
            $users[$username]['first_name'] = $first_name;
            $users[$username]['last_name'] = $last_name;
            $users[$username]['account_host'] = $account_host;
            
            if ($is_admin) {
                $users[$username]['role'] = 'admin';
                $users[$username]['access'] = new stdClass();
            } else {
                unset($users[$username]['role']);
                $users[$username]['access'] = $groups;
            }
            
            // Update password if provided
            if (!empty($new_password)) {
                $users[$username]['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                die("Error: Could not write to users.json");
            }

            $message = "User updated successfully!";
            $messageType = "success";
        }
    }
    
    // Handle user deletion
    if (isset($_POST['delete_user'])) {
        $username = trim($_POST['username'] ?? '');
        if (isset($users[$username])) {
            unset($users[$username]);
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                die("Error: Could not write to users.json");
            }
            $message = "User deleted successfully!";
            $messageType = "success";
        }
    }
}

function getOrganisms() {
    $orgs = [];
    $path = '../organisms';
    if (!is_dir($path)) {
        return $orgs;
    }
    $organisms = scandir($path);
    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$path/$organism")) {
            continue;
        }
        $assemblies = [];
        $assemblyPath = "$path/$organism";
        $files = scandir($assemblyPath);
        foreach ($files as $file) {
            if ($file[0] === '.' || !is_dir("$assemblyPath/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        $orgs[$organism] = $assemblies;
    }
    return $orgs;
}

$organisms = getOrganisms();

include_once '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">

<div class="container-fluid mt-5">
  <h2><i class="fa fa-users"></i> Manage Users</h2>
  
  <div class="mb-3">
    <a href="index.php" class="btn btn-secondary">← Back to Admin Tools</a>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
              <small class="text-muted d-block mb-2">Click on organism assemblies to grant access (not applicable for admins)</small>
              <div class="mb-2">
                <input type="text" id="organism-filter" class="form-control" placeholder="Filter organisms by name...">
              </div>
              <div class="border rounded p-3" style="max-height: 500px; overflow-y: auto; background-color: #f8f9fa;" id="create-access-container">
                <?php foreach ($organisms as $organism => $assemblies): ?>
                  <div class="organism-group mb-3" data-organism-name="<?= strtolower(htmlspecialchars($organism)) ?>">
                    <h6><?= htmlspecialchars($organism) ?></h6>
                    <div>
                      <?php foreach ($assemblies as $assembly): ?>
                        <span class="tag-chip-selector create-chip" 
                              data-organism="<?= htmlspecialchars($organism) ?>" 
                              data-assembly="<?= htmlspecialchars($assembly) ?>">
                          <?= htmlspecialchars($assembly) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div id="create-selected-hidden"></div>
            </div>

            <button type="submit" class="btn btn-success">
              <i class="fa fa-user-plus"></i> Create User
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
          </form>
        </div>
      </div>
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
                                    echo '<span class="tag-chip" data-organism="'.htmlspecialchars($org).'" data-assembly="'.htmlspecialchars($asm).'">' . htmlspecialchars($org) . ': ' . htmlspecialchars($asm) . '</span>';
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
          
          <div class="mb-3" id="edit-groups-section">
            <label class="form-label">Access Groups</label>
            <div id="edit-access-container"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
const allOrganisms = <?= json_encode($organisms) ?>;
const allUsers = <?= json_encode($users) ?>;
const colors = [
  '#007bff', '#28a745', '#17a2b8', '#ffc107', '#dc3545', 
  '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6610f2'
];

// Color map for consistent organism colors
const organismColorMap = {};
let nextColorIndex = 0;

function getColorForOrganism(organism) {
  if (!organismColorMap[organism]) {
    organismColorMap[organism] = colors[nextColorIndex % colors.length];
    nextColorIndex++;
  }
  return organismColorMap[organism];
}

// Pre-assign colors to existing organisms
Object.keys(allOrganisms).forEach(org => getColorForOrganism(org));

// Apply colors to existing chips
document.querySelectorAll('.tag-chip').forEach(chip => {
  const organism = chip.getAttribute('data-organism');
  if (organism) {
    chip.style.background = getColorForOrganism(organism);
    chip.style.borderColor = getColorForOrganism(organism);
  }
});

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#usersTable').DataTable({
        pageLength: 10,
        order: [[0, 'asc']]
    });
    
    const isAdminCheckbox = document.getElementById('isAdmin');
    const groupsSection = document.getElementById('groups-section');
    const organismFilter = document.getElementById('organism-filter');
    const createAccessContainer = document.getElementById('create-access-container');
    
    // Apply colors to create chips and add click handlers
    document.querySelectorAll('.create-chip').forEach(chip => {
        const organism = chip.getAttribute('data-organism');
        chip.style.background = getColorForOrganism(organism);
        chip.style.borderColor = getColorForOrganism(organism);
        chip.style.color = 'white';
        chip.style.opacity = '0.5';
        
        chip.addEventListener('click', function() {
            if (!isAdminCheckbox.checked) {
                this.classList.toggle('selected');
                if (this.classList.contains('selected')) {
                    this.style.opacity = '1';
                } else {
                    this.style.opacity = '0.5';
                }
                updateCreateHiddenInputs();
            }
        });
    });
    
    // Function to update hidden inputs for form submission
    function updateCreateHiddenInputs() {
        const hiddenContainer = document.getElementById('create-selected-hidden');
        hiddenContainer.innerHTML = '';
        
        document.querySelectorAll('.create-chip.selected').forEach(chip => {
            const organism = chip.getAttribute('data-organism');
            const assembly = chip.getAttribute('data-assembly');
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `groups[${organism}][]`;
            input.value = assembly;
            hiddenContainer.appendChild(input);
        });
    }
    
    // Filter organisms
    organismFilter.addEventListener('input', function() {
        const filterValue = this.value.toLowerCase();
        const organismGroups = document.querySelectorAll('.organism-group');
        
        organismGroups.forEach(function(group) {
            const organismName = group.getAttribute('data-organism-name');
            if (organismName.includes(filterValue)) {
                group.style.display = '';
            } else {
                group.style.display = 'none';
            }
        });
    });
    
    // Toggle groups section based on admin checkbox
    isAdminCheckbox.addEventListener('change', function() {
        if (this.checked) {
            groupsSection.style.opacity = '0.5';
            createAccessContainer.style.pointerEvents = 'none';
            organismFilter.disabled = true;
        } else {
            groupsSection.style.opacity = '1';
            createAccessContainer.style.pointerEvents = 'auto';
            organismFilter.disabled = false;
        }
    });
    
    // Edit user button
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const username = this.getAttribute('data-username');
            const userData = allUsers[username];
            
            document.getElementById('editUsername').textContent = username;
            document.getElementById('editUsernameInput').value = username;
            document.getElementById('editPassword').value = '';
            document.getElementById('editEmail').value = userData.email || '';
            document.getElementById('editFirstName').value = userData.first_name || '';
            document.getElementById('editLastName').value = userData.last_name || '';
            document.getElementById('editAccountHost').value = userData.account_host || '';
            
            const isAdmin = userData.role === 'admin';
            document.getElementById('editIsAdmin').checked = isAdmin;
            
            // Build access selector
            const container = document.getElementById('edit-access-container');
            container.innerHTML = '';
            
            if (!isAdmin) {
                // Get current user access
                const currentAccess = userData.access || {};
                
                Object.keys(allOrganisms).forEach(organism => {
                    const organismDiv = document.createElement('div');
                    organismDiv.className = 'mb-3';
                    
                    const organismLabel = document.createElement('h6');
                    organismLabel.textContent = organism;
                    organismDiv.appendChild(organismLabel);
                    
                    const chipsDiv = document.createElement('div');
                    
                    allOrganisms[organism].forEach(assembly => {
                        const chip = document.createElement('span');
                        chip.className = 'tag-chip-selector';
                        chip.textContent = assembly;
                        chip.style.background = getColorForOrganism(organism);
                        chip.style.borderColor = getColorForOrganism(organism);
                        chip.style.color = 'white';
                        chip.style.opacity = '0.5';
                        
                        // Check if user has this access
                        if (currentAccess[organism] && currentAccess[organism].includes(assembly)) {
                            chip.classList.add('selected');
                            chip.style.opacity = '1';
                        }
                        
                        chip.setAttribute('data-organism', organism);
                        chip.setAttribute('data-assembly', assembly);
                        
                        chip.addEventListener('click', function() {
                            this.classList.toggle('selected');
                            if (this.classList.contains('selected')) {
                                this.style.opacity = '1';
                            } else {
                                this.style.opacity = '0.5';
                            }
                        });
                        
                        chipsDiv.appendChild(chip);
                    });
                    
                    organismDiv.appendChild(chipsDiv);
                    container.appendChild(organismDiv);
                });
            } else {
                container.innerHTML = '<p class="text-muted">Admin users have access to all organisms.</p>';
            }
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        });
    });
    
    // Handle admin checkbox in edit modal
    document.getElementById('editIsAdmin').addEventListener('change', function() {
        const container = document.getElementById('edit-access-container');
        if (this.checked) {
            container.innerHTML = '<p class="text-muted">Admin users have access to all organisms.</p>';
        } else {
            // Rebuild access selector
            container.innerHTML = '';
            Object.keys(allOrganisms).forEach(organism => {
                const organismDiv = document.createElement('div');
                organismDiv.className = 'mb-3';
                
                const organismLabel = document.createElement('h6');
                organismLabel.textContent = organism;
                organismDiv.appendChild(organismLabel);
                
                const chipsDiv = document.createElement('div');
                
                allOrganisms[organism].forEach(assembly => {
                    const chip = document.createElement('span');
                    chip.className = 'tag-chip-selector';
                    chip.textContent = assembly;
                    chip.style.background = getColorForOrganism(organism);
                    chip.style.borderColor = getColorForOrganism(organism);
                    chip.style.color = 'white';
                    chip.style.opacity = '0.5';
                    chip.setAttribute('data-organism', organism);
                    chip.setAttribute('data-assembly', assembly);
                    
                    chip.addEventListener('click', function() {
                        this.classList.toggle('selected');
                        if (this.classList.contains('selected')) {
                            this.style.opacity = '1';
                        } else {
                            this.style.opacity = '0.5';
                        }
                    });
                    
                    chipsDiv.appendChild(chip);
                });
                
                organismDiv.appendChild(chipsDiv);
                container.appendChild(organismDiv);
            });
        }
    });
    
    // Handle edit form submission
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        if (!document.getElementById('editIsAdmin').checked) {
            // Collect selected assemblies
            const selectedChips = document.querySelectorAll('#edit-access-container .tag-chip-selector.selected');
            const accessMap = {};
            
            selectedChips.forEach(chip => {
                const organism = chip.getAttribute('data-organism');
                const assembly = chip.getAttribute('data-assembly');
                
                if (!accessMap[organism]) {
                    accessMap[organism] = [];
                }
                accessMap[organism].push(assembly);
            });
            
            // Add hidden inputs for the access
            Object.keys(accessMap).forEach(organism => {
                accessMap[organism].forEach(assembly => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `groups[${organism}][]`;
                    input.value = assembly;
                    this.appendChild(input);
                });
            });
        }
    });
    
    // Delete user button
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const username = this.getAttribute('data-username');
            if (confirm(`Are you sure you want to delete user "${username}"?`)) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="delete_user" value="1">
                    <input type="hidden" name="username" value="${username}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});
</script>

</body>
</html>

<?php
include_once '../footer.php';
?>