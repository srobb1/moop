<?php
include_once __DIR__ . '/../access_control.php';

$usersFile = $users_file;
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
}

$is_admin = false;
if ($logged_in && isset($users[$username]) && isset($users[$username]['role']) && $users[$username]['role'] === 'admin') {
    $is_admin = true;
}

// Only allow Admin access level (not ALL, as IP users shouldn't have admin panel access)
if (!$is_admin || $access_level !== 'Admin') {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>403 Forbidden</h1>";
    echo "You don't have permission to access this page.";
    exit;
}
