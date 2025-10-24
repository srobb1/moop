<?php
session_start();

$usersFile = "/var/www/html/users.json";
$users = [];

if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
    if ($users === null && json_last_error() !== JSON_ERROR_NONE) {
      die("Error reading users.json: " . json_last_error_msg());
    }
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $groups   = $_POST['groups'] ?? [];

    if (empty($username) || empty($password)) {
        $message = "Username and password are required.";
    } elseif (isset($users[$username])) {
        $message = "That username already exists.";
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Add to users array
        $users[$username] = [
            "password" => $hashedPassword,
            "access"   => $groups
        ];

        // Save back to JSON
	if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
          die("Error: Could not write to users.json");
        }

        $message = "User created successfully!";
    }
}

function getOrganisms() {
    $orgs = [];
    $path = 'organisms';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Account</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="card-title mb-4 text-center">Create Account</h4>

          <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
          <?php endif; ?>

          <form method="post">
            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input type="text" name="username" id="username" class="form-control" required>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Groups</label>
              <?php foreach ($organisms as $organism => $assemblies): ?>
                <div class="form-check">
                  <input type="checkbox" id="org-<?= htmlspecialchars($organism) ?>" class="form-check-input select-all">
                  <label class="form-check-label" for="org-<?= htmlspecialchars($organism) ?>"><b><?= htmlspecialchars($organism) ?></b></label>
                </div>
                <div class="ms-4">
                  <?php foreach ($assemblies as $assembly): ?>
                    <div class="form-check">
                      <input type="checkbox" name="groups[<?= htmlspecialchars($organism) ?>][]" value="<?= htmlspecialchars($assembly) ?>" id="assembly-<?= htmlspecialchars($assembly) ?>" class="form-check-input assembly-checkbox" data-organism="<?= htmlspecialchars($organism) ?>">
                      <label class="form-check-label" for="assembly-<?= htmlspecialchars($assembly) ?>"><?= htmlspecialchars($assembly) ?></label>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-success w-100">Create Account</button>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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