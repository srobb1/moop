<?php
/**
 * MANAGE USERS - Wrapper
 * 
 * Handles admin access verification and renders user management
 * using clean architecture layout system.
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Get config
$siteTitle = $config->getString('siteTitle');
$site = $config->getString('site');

// Load page-specific config
$usersFile = $config->getPath('users_file');

// Handle standard AJAX fix permissions request
handleAdminAjax();

$users = [];
$file_write_error = null;

if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
    if ($users === null && json_last_error() !== JSON_ERROR_NONE) {
      die("Error reading users.json: " . json_last_error_msg());
    }
}

// Check if users file is writable
$file_write_error = getFileWriteError($usersFile);

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($file_write_error) {
        $message = "Users file is not writable. Please fix permissions first.";
        $messageType = "danger";
    }
    // Handle user creation
    elseif (isset($_POST['create_user'])) {
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
            
            $users[$username] = [
                'password' => $hashedPassword,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'account_host' => $account_host,
                'groups' => $groups,
                'is_admin' => $is_admin
            ];

            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $message = "User $username created successfully!";
                $messageType = "success";
            } else {
                $message = "Error saving user. Check file permissions.";
                $messageType = "danger";
            }
        }
    }
    // Handle user deletion
    elseif (isset($_POST['delete_user'])) {
        $username = $_POST['username'];
        
        if (isset($users[$username])) {
            unset($users[$username]);
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $message = "User $username deleted successfully!";
                $messageType = "success";
            } else {
                $message = "Error saving user changes. Check file permissions.";
                $messageType = "danger";
            }
        }
    }
    // Handle user update
    elseif (isset($_POST['update_user'])) {
        $username = $_POST['username'];
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $account_host = trim($_POST['account_host'] ?? '');
        $groups = $_POST['groups'] ?? [];
        $is_admin = isset($_POST['isAdmin']);

        if (isset($users[$username])) {
            $users[$username]['email'] = $email;
            $users[$username]['first_name'] = $first_name;
            $users[$username]['last_name'] = $last_name;
            $users[$username]['account_host'] = $account_host;
            $users[$username]['groups'] = $groups;
            $users[$username]['is_admin'] = $is_admin;

            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $message = "User $username updated successfully!";
                $messageType = "success";
            } else {
                $message = "Error saving user changes. Check file permissions.";
                $messageType = "danger";
            }
        }
    }
    // Handle password change
    elseif (isset($_POST['change_password'])) {
        $username = $_POST['username'];
        $new_password = $_POST['new_password'] ?? '';

        if (empty($new_password)) {
            $message = "New password is required.";
            $messageType = "danger";
        } elseif (isset($users[$username])) {
            $users[$username]['password'] = password_hash($new_password, PASSWORD_DEFAULT);

            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $message = "Password for user $username changed successfully!";
                $messageType = "success";
            } else {
                $message = "Error saving password change. Check file permissions.";
                $messageType = "danger";
            }
        }
    }
}

// Configure display
$display_config = [
    'title' => 'Manage Users - ' . $siteTitle,
    'content_file' => __DIR__ . '/pages/manage_users.php',
];

// Prepare data for content file
$data = [
    'users' => $users,
    'file_write_error' => $file_write_error,
    'message' => $message,
    'messageType' => $messageType,
    'config' => $config,
    'page_script' => '/' . $site . '/js/modules/manage-users.js',
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
