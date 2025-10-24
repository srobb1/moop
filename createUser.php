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
            "groups"   => $groups
        ];

        // Save back to JSON
	if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
          die("Error: Could not write to users.json");
        }

        $message = "User created successfully!";
    }
}
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
              <div class="form-check">
                <input type="checkbox" name="groups[]" value="bats" id="groupBats" class="form-check-input">
                <label class="form-check-label" for="groupBats">Bats</label>
              </div>
              <div class="form-check">
                <input type="checkbox" name="groups[]" value="corals" id="groupMice" class="form-check-input">
                <label class="form-check-label" for="groupCorals">Corals</label>
              </div>
              <div class="form-check">
                <input type="checkbox" name="groups[]" value="Public" id="groupHumans" class="form-check-input">
                <label class="form-check-label" for="groupHumans">Public</label>
              </div>
              <div class="form-check">
                <input type="checkbox" name="groups[]" value="all" id="groupAll" class="form-check-input">
                <label class="form-check-label" for="groupAll">All</label>
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100">Create Account</button>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>

