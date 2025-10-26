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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
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
  <title>Create User</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2><i class="fa fa-user-plus"></i> Create User</h2>
  
  <div class="mb-3">
    <a href="index.php" class="btn btn-secondary">← Back to Admin Tools</a>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="card-title mb-4">User Information</h4>

          <form method="post">
            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input type="text" name="username" id="username" class="form-control" required>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="mb-3 form-check">
              <input type="checkbox" name="isAdmin" id="isAdmin" class="form-check-input">
              <label class="form-check-label" for="isAdmin">Is Admin</label>
            </div>

            <div class="mb-3" id="groups-section">
              <label class="form-label">Access Groups</label>
              <small class="text-muted d-block mb-2">Select which organism assemblies this user can access (not applicable for admins)</small>
              <div class="mb-2">
                <input type="text" id="organism-filter" class="form-control" placeholder="Filter organisms by name...">
              </div>
              <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa;">
                <?php foreach ($organisms as $organism => $assemblies): ?>
                  <div class="card mb-2 organism-card" data-organism-name="<?= strtolower(htmlspecialchars($organism)) ?>">
                    <div class="card-header py-2" style="background-color: #e9ecef;">
                      <div class="form-check mb-0">
                        <input type="checkbox" id="org-<?= htmlspecialchars($organism) ?>" class="form-check-input select-all">
                        <label class="form-check-label fw-bold" for="org-<?= htmlspecialchars($organism) ?>"><?= htmlspecialchars($organism) ?></label>
                      </div>
                    </div>
                    <div class="card-body py-2">
                      <div class="row">
                        <?php foreach ($assemblies as $assembly): ?>
                          <div class="col-md-6">
                            <div class="form-check">
                              <input type="checkbox" name="groups[<?= htmlspecialchars($organism) ?>][]" value="<?= htmlspecialchars($assembly) ?>" id="assembly-<?= htmlspecialchars($assembly) ?>" class="form-check-input assembly-checkbox" data-organism="<?= htmlspecialchars($organism) ?>">
                              <label class="form-check-label text-break" for="assembly-<?= htmlspecialchars($assembly) ?>"><?= htmlspecialchars($assembly) ?></label>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isAdminCheckbox = document.getElementById('isAdmin');
    const groupsSection = document.getElementById('groups-section');
    const organismFilter = document.getElementById('organism-filter');
    
    // Filter organisms
    organismFilter.addEventListener('input', function() {
        const filterValue = this.value.toLowerCase();
        const organismCards = document.querySelectorAll('.organism-card');
        
        organismCards.forEach(function(card) {
            const organismName = card.getAttribute('data-organism-name');
            if (organismName.includes(filterValue)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // Toggle groups section based on admin checkbox
    isAdminCheckbox.addEventListener('change', function() {
        if (this.checked) {
            groupsSection.style.opacity = '0.5';
            groupsSection.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
                checkbox.disabled = true;
            });
            organismFilter.disabled = true;
        } else {
            groupsSection.style.opacity = '1';
            groupsSection.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
                checkbox.disabled = false;
            });
            organismFilter.disabled = false;
        }
    });
    
    // Select all functionality
    document.querySelectorAll('.select-all').forEach(function(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            let organism = this.id.replace('org-', '');
            document.querySelectorAll('.assembly-checkbox[data-organism="' + organism + '"]').forEach(function(assemblyCheckbox) {
                assemblyCheckbox.checked = this.checked;
            }, this);
        });
    });
});
</script>

</body>
</html>

<?php
include_once '../footer.php';
?>