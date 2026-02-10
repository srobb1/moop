<?php
/**
 * MOOP Login Page
 * 
 * Handles user authentication.
 * Processes form submissions before rendering page.
 */

session_start();

include_once __DIR__ . '/includes/config_init.php';
include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/includes/layout.php';

$config = ConfigManager::getInstance();
$usersFile = $config->getPath('users_file');
$users = json_decode(file_get_contents($usersFile), true);
$siteTitle = $config->getString('siteTitle');

$error = "";

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username]["password"])) {
        // Store login info in session
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $username;
        $_SESSION["access"]   = $users[$username]["access"];
        $_SESSION["role"]     = $users[$username]["role"] ?? null;
        // Set access level based on role
        if (isset($users[$username]["role"]) && $users[$username]["role"] === 'admin') {
            $_SESSION["access_level"] = 'ADMIN';
        } else {
            $_SESSION["access_level"] = 'COLLABORATOR';
        }

        // Redirect to index.php
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/login.php',
    [
        'siteTitle' => $siteTitle,
        'error' => $error,
    ],
    'Login - ' . $siteTitle
);
?>
