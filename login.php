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
include_once __DIR__ . '/lib/functions_login_protection.php';

$config = ConfigManager::getInstance();
$usersFile = $config->getPath('users_file');
$users = json_decode(file_get_contents($usersFile), true);
$siteTitle = $config->getString('siteTitle');

$error = "";

// Read and validate optional return URL (used by auth_gateway.php after session expiry)
$raw_return = $_GET['return'] ?? $_POST['return_url'] ?? '';
$return_url = '';
if ($raw_return !== '') {
    $parsed = parse_url($raw_return);
    $same_host = isset($parsed['host']) && $parsed['host'] === $_SERVER['HTTP_HOST'];
    $relative  = !isset($parsed['host']) && str_starts_with($raw_return, '/');
    if ($same_host || $relative) {
        $return_url = $raw_return;
    }
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_protect();

    $username   = $_POST["username"] ?? '';
    $password   = $_POST["password"] ?? '';
    $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Check for lockout / rate-limiting before touching the password
    $lockout = check_login_lockout($username, $visitor_ip);
    if ($lockout['locked']) {
        $error = $lockout['message'];
    } elseif (isset($users[$username]) && password_verify($password, $users[$username]["password"])) {
        // Successful login: clear failure counters and regenerate session
        reset_login_failures($username, $visitor_ip);
        session_regenerate_id(true);
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

        header("Location: " . ($return_url ?: "index.php"));
        exit;
    } else {
        record_login_failure($username, $visitor_ip);
        $error = "Invalid username or password.";
    }
}

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/login.php',
    [
        'siteTitle'  => $siteTitle,
        'error'      => $error,
        'return_url' => $return_url,
    ],
    'Login - ' . $siteTitle
);
?>
