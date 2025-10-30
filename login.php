<?php
session_start();

include_once __DIR__ . '/site_config.php';
$usersFile = $users_file;
$users = json_decode(file_get_contents($usersFile), true);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username]["password"])) {
        // Store login info in session
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $username;
        $_SESSION["access"]   = $users[$username]["access"];
        // Set access level based on role
        if (isset($users[$username]["role"]) && $users[$username]["role"] === 'admin') {
            $_SESSION["access_level"] = 'Admin';
        } else {
            $_SESSION["access_level"] = 'Collaborator';
        }

        // Redirect to index.php
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">

  <h2>Login</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" class="mt-3">
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input type="text" class="form-control" id="username" name="username" required>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary">Login</button>
  </form>

</body>
</html>

