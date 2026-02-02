#!/usr/bin/env php
<?php

/**
 * MOOP Admin User Setup Script
 * 
 * This script creates the users.json file with an admin account.
 * Run this once during initial MOOP installation to set up your admin user.
 * 
 * Usage: php setup-admin.php
 */

// ANSI color codes
const COLOR_GREEN = "\033[0;32m";
const COLOR_YELLOW = "\033[1;33m";
const COLOR_RED = "\033[0;31m";
const COLOR_RESET = "\033[0m";

// Load configuration to get the correct path
include_once __DIR__ . '/config/site_config.php';
$config_data = require __DIR__ . '/config/site_config.php';
$root_path = $config_data['root_path'];
$users_file = $root_path . '/users.json';

echo COLOR_GREEN . "========================================" . COLOR_RESET . "\n";
echo COLOR_GREEN . "MOOP Admin User Setup" . COLOR_RESET . "\n";
echo COLOR_GREEN . "========================================" . COLOR_RESET . "\n\n";

// Check if users.json already exists
if (file_exists($users_file)) {
    echo COLOR_RED . "Warning: users.json already exists!" . COLOR_RESET . "\n";
    echo "This file contains all current users and their permissions.\n\n";
    echo "Options:\n";
    echo "  1. Add a new admin user (preserves existing users)\n";
    echo "  2. Overwrite with new admin user only (DELETES all other users)\n";
    echo "  3. Cancel setup\n\n";
    echo "Choose an option (1/2/3): ";
    
    $handle = fopen('php://stdin', 'r');
    $option = trim(fgets($handle));
    fclose($handle);
    
    echo "\n";
    
    if ($option === '2') {
        echo COLOR_RED . "WARNING: This will delete all existing users!" . COLOR_RESET . "\n";
        echo "Type 'yes, delete all' to confirm: ";
        $handle = fopen('php://stdin', 'r');
        $confirm = trim(fgets($handle));
        fclose($handle);
        
        if ($confirm !== 'yes, delete all') {
            echo "Setup cancelled.\n";
            exit(0);
        }
        echo "\n";
        $mode = 'overwrite';
    } elseif ($option === '1') {
        $mode = 'add_admin';
    } else {
        echo "Setup cancelled.\n";
        exit(0);
    }
} else {
    $mode = 'create';
}

// Check if we have write permission to the users file location
$users_dir = dirname($users_file);
if (!is_writable($users_dir)) {
    echo COLOR_RED . "Error: Cannot write to directory: $users_dir" . COLOR_RESET . "\n";
    echo "Please run this script as a user with write permission to that directory.\n";
    echo "Current user: " . get_current_user() . "\n";
    echo "Directory owner: " . posix_getpwuid(fileowner($users_dir))['name'] . "\n\n";
    echo "Try running with sudo:\n";
    echo "  sudo php setup-admin.php\n";
    exit(1);
}

// Step 1: Get admin username
echo COLOR_RED . "Step 1: Choose an admin username" . COLOR_RESET . "\n";
echo "Admin username (default: admin): ";

$handle = fopen('php://stdin', 'r');
$admin_username = trim(fgets($handle));
fclose($handle);

if (empty($admin_username)) {
    $admin_username = 'admin';
}

// Validate username
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $admin_username)) {
    echo COLOR_RED . "Error: Username can only contain letters, numbers, underscores, and hyphens" . COLOR_RESET . "\n";
    exit(1);
}

// Check if this username already exists in the current users.json
$username_exists = false;
if ($mode === 'add_admin' && file_exists($users_file)) {
    $existing_users = json_decode(file_get_contents($users_file), true);
    if ($existing_users && isset($existing_users[$admin_username])) {
        $username_exists = true;
        echo "\n";
        echo COLOR_RED . "⚠ Warning: User '$admin_username' already exists!" . COLOR_RESET . "\n";
        echo "Existing user details:\n";
        echo "  - Role: " . ($existing_users[$admin_username]['role'] ?? 'unknown') . "\n";
        echo "  - Access: " . (count($existing_users[$admin_username]['access'] ?? []) . " organisms") . "\n";
        echo "\n";
        echo "Do you want to overwrite this user with a new password and give them admin access? (yes/no): ";
        
        $handle = fopen('php://stdin', 'r');
        $overwrite_confirm = trim(fgets($handle));
        fclose($handle);
        
        if ($overwrite_confirm !== 'yes') {
            echo "Setup cancelled.\n";
            exit(0);
        }
        echo "\n";
    }
}

echo "\n";

// Step 2: Get admin password
echo COLOR_RED . "Step 2: Choose a strong admin password" . COLOR_RESET . "\n";
echo "Password requirements:\n";
echo "  - At least 12 characters recommended\n";
echo "  - Mix of uppercase, lowercase, numbers, and symbols\n\n";

$admin_password = '';
while (true) {
    echo "Enter password: ";
    $admin_password = getPasswordInput();
    
    if (strlen($admin_password) < 8) {
        echo COLOR_RED . "Error: Password must be at least 8 characters" . COLOR_RESET . "\n";
        continue;
    }
    
    echo "Confirm password: ";
    $admin_password_confirm = getPasswordInput();
    
    if ($admin_password !== $admin_password_confirm) {
        echo COLOR_RED . "Error: Passwords do not match" . COLOR_RESET . "\n";
        continue;
    }
    
    break;
}

echo "\n";
echo COLOR_RED . "Step 3: Creating/Updating users.json" . COLOR_RESET . "\n\n";

// Generate password hash
$password_hash = password_hash($admin_password, PASSWORD_BCRYPT);

// Create new user entry
$new_user = [
    'password' => $password_hash,
    'role' => 'admin',
    'access' => []
];

// Determine which users to save
if ($mode === 'add_admin') {
    // Load existing users and add/update the admin user
    $existing_users = json_decode(file_get_contents($users_file), true);
    if ($existing_users === null) {
        echo COLOR_RED . "Error: Could not read existing users.json" . COLOR_RESET . "\n";
        exit(1);
    }
    
    // Create backup
    $backup_file = $users_file . '.backup-' . date('Y-m-d-H-i-s');
    if (copy($users_file, $backup_file)) {
        echo COLOR_GREEN . "✓ Backup created: " . basename($backup_file) . COLOR_RESET . "\n";
    }
    
    $existing_users[$admin_username] = $new_user;
    $users = $existing_users;
    
    // Report what happened
    if ($username_exists) {
        echo COLOR_RED . "✓ Overwriting existing user '$admin_username' with new password and admin access" . COLOR_RESET . "\n";
    } else {
        echo COLOR_GREEN . "✓ Adding new admin user to existing users.json" . COLOR_RESET . "\n";
    }
} else {
    // Create new users array with just the admin
    $users = [
        $admin_username => $new_user
    ];
    echo COLOR_GREEN . "Creating new users.json" . COLOR_RESET . "\n";
}

echo "\n";

// Write users.json
$json_content = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($json_content === false) {
    echo COLOR_RED . "Error: Failed to encode JSON" . COLOR_RESET . "\n";
    exit(1);
}

if (file_put_contents($users_file, $json_content) === false) {
    echo COLOR_RED . "Error: Failed to write users.json" . COLOR_RESET . "\n";
    exit(1);
}

// Set restrictive permissions
if (!chmod($users_file, 0600)) {
    echo COLOR_RED . "Warning: Could not set file permissions to 600" . COLOR_RESET . "\n";
    echo "Please run: chmod 600 $users_file\n\n";
}

echo COLOR_GREEN . "✓ users.json created successfully" . COLOR_RESET . "\n";
echo COLOR_GREEN . "✓ File permissions set to 600 (secure)" . COLOR_RESET . "\n\n";

echo COLOR_GREEN . "========================================" . COLOR_RESET . "\n";
echo COLOR_GREEN . "Setup Complete!" . COLOR_RESET . "\n";
echo COLOR_GREEN . "========================================" . COLOR_RESET . "\n\n";

echo "Admin credentials:\n";
echo "  Username: " . COLOR_RED . $admin_username . COLOR_RESET . "\n";
echo "  Password: " . COLOR_RED . "(the password you just entered)" . COLOR_RESET . "\n\n";

echo "Next steps:\n";
echo "  1. Set filesystem permissions (see README.md)\n";
echo "  2. Visit http://your-site/moop/ and login\n";
echo "  3. Go to Admin > Manage Site Configuration\n";
echo "  4. Update your site title, email, and other settings\n\n";

exit(0);

/**
 * Get password input without echoing to terminal
 * Works on Unix/Linux. Windows requires PHP 7.1+ with stream_select support.
 */
function getPasswordInput() {
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows doesn't support stty, so we echo the input
        $handle = fopen('php://stdin', 'r');
        $password = trim(fgets($handle));
        fclose($handle);
        echo "\n";
        return $password;
    } else {
        // Unix/Linux: disable terminal echo
        system('stty -echo');
        $handle = fopen('php://stdin', 'r');
        $password = trim(fgets($handle));
        fclose($handle);
        system('stty echo');
        echo "\n";
        return $password;
    }
}
?>
