<?php
include_once __DIR__ . '/../includes/access_control.php';
include_once __DIR__ . '/../lib/moop_functions.php';

// Load users file from ConfigManager
$config = ConfigManager::getInstance();
$usersFile = $config->getPath('users_file');
$users = loadJsonFile($usersFile, []);

$is_admin = false;
if (is_logged_in() && isset($users[get_username()]) && isset($users[get_username()]['role']) && $users[get_username()]['role'] === 'admin') {
    $is_admin = true;
}

// Only allow ADMIN access level (not IP_IN_RANGE, as IP users shouldn't have admin panel access)
if (!$is_admin || get_access_level() !== 'ADMIN') {
    header('Location: ../access_denied.php', true, 302);
    exit;
}
