<?php

include_once __DIR__ . '/../site_config.php';

$usersFile = $users_file;
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
}

$logged_in = $_SESSION["logged_in"] ?? false;
$username  = $_SESSION["username"] ?? "";

$is_admin = false;
if ($logged_in && isset($users[$username]) && isset($users[$username]['role']) && $users[$username]['role'] === 'admin') {
    $is_admin = true;
}

if (!$is_admin) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>403 Forbidden</h1>";
    echo "You don't have permission to access this page.";
    exit;
}
