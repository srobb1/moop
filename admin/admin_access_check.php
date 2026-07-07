<?php
include_once __DIR__ . '/../includes/access_control.php';
include_once __DIR__ . '/../lib/moop_functions.php';

// On an auth failure, requests that expect JSON (admin API endpoints + AJAX) get a JSON
// error instead of an HTML redirect. Without this, a fetch()/$.ajax call follows the
// redirect to the login / access-denied HTML page and the caller sees the cryptic
// "Unexpected token '<', <!DOCTYPE ... is not valid JSON". The detection heuristic is
// request_expects_json(), shared from access_control.php (included above).

// Admin session inactivity timeout — 8 hours
const ADMIN_SESSION_TIMEOUT = 28800;
if (isset($_SESSION['admin_last_active']) && (time() - $_SESSION['admin_last_active']) > ADMIN_SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    if (request_expects_json()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Your admin session expired — reload the page and log in again.']);
        exit;
    }
    header('Location: ../login.php?timeout=1');
    exit;
}
$_SESSION['admin_last_active'] = time();

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
    if (request_expects_json()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required — your session may have ended. Reload the page and log in again.']);
        exit;
    }
    header('Location: ../access_denied.php', true, 302);
    exit;
}
