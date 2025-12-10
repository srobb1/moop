<?php
/**
 * MANAGE USERS - Wrapper
 * 
 * Redesigned for form-based create/edit UI with:
 * - Single form handles both create and edit
 * - Inline stale assemblies alert when editing
 * - Separate stale audit section for bulk operations
 * - Assembly selection validation (min 1 unless admin)
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

// Get config
$siteTitle = $config->getString('siteTitle');
$site = $config->getString('site');

// Load page-specific config
$usersFile = $config->getPath('users_file');
$organism_data_path = $config->getPath('organism_data');

// Handle standard AJAX fix permissions request
handleAdminAjax();

// Load organisms for access control
$organisms = getOrganismsWithAssemblies($organism_data_path);

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
$edit_mode = false;
$edit_username = null;

// Handle form submission (create or update)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($file_write_error) {
        $message = "Users file is not writable. Please fix permissions first.";
        $messageType = "danger";
    }
    elseif (isset($_POST['create_or_update_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $account_host = trim($_POST['account_host'] ?? '');
        $access = $_POST['access'] ?? [];
        $is_admin = isset($_POST['isAdmin']);

        // Validation
        if (empty($username)) {
            $message = "Username is required.";
            $messageType = "danger";
        } 
        elseif ($_POST['is_create'] === '1' && isset($users[$username])) {
            $message = "Username already exists.";
            $messageType = "warning";
        }
        elseif (!$is_admin && empty($access)) {
            $message = "Must select at least one assembly (or check Admin for full access).";
            $messageType = "danger";
        }
        else {
            // Determine if create or update
            $is_create = $_POST['is_create'] === '1';
            $original_username = $_POST['original_username'] ?? $username;

            if ($is_create) {
                // Create new user
                if (empty($password)) {
                    $message = "Password is required for new users.";
                    $messageType = "danger";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $users[$username] = [
                        'password' => $hashedPassword,
                        'email' => $email,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'account_host' => $account_host,
                        'access' => $access,
                        'role' => $is_admin ? 'admin' : 'user'
                    ];

                    if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                        $message = "User $username created successfully!";
                        $messageType = "success";
                    } else {
                        $message = "Error saving user. Check file permissions.";
                        $messageType = "danger";
                    }
                }
            } else {
                // Update existing user
                if (isset($users[$original_username])) {
                    $users[$original_username]['email'] = $email;
                    $users[$original_username]['first_name'] = $first_name;
                    $users[$original_username]['last_name'] = $last_name;
                    $users[$original_username]['account_host'] = $account_host;
                    $users[$original_username]['access'] = $access;
                    $users[$original_username]['role'] = $is_admin ? 'admin' : 'user';

                    // Update password only if provided
                    if (!empty($new_password)) {
                        $users[$original_username]['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                    }

                    if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                        $message = "User $original_username updated successfully!";
                        $messageType = "success";
                    } else {
                        $message = "Error saving user changes. Check file permissions.";
                        $messageType = "danger";
                    }
                }
            }
        }
    }
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
    elseif (isset($_POST['remove_stale_assembly'])) {
        $username = $_POST['username'];
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];

        if (isset($users[$username]) && isset($users[$username]['access'][$organism])) {
            $key = array_search($assembly, $users[$username]['access'][$organism]);
            if ($key !== false) {
                unset($users[$username]['access'][$organism][$key]);
                $users[$username]['access'][$organism] = array_values($users[$username]['access'][$organism]);
                
                if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                    $message = "Stale assembly removed from user.";
                    $messageType = "success";
                }
            }
        }
    }
    elseif (isset($_POST['remove_stale_from_all'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $removed_count = 0;

        foreach ($users as $user => $userData) {
            if (isset($userData['access'][$organism])) {
                $key = array_search($assembly, $userData['access'][$organism]);
                if ($key !== false) {
                    unset($users[$user]['access'][$organism][$key]);
                    $users[$user]['access'][$organism] = array_values($users[$user]['access'][$organism]);
                    $removed_count++;
                }
            }
        }

        if ($removed_count > 0 && file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
            $message = "Stale assembly removed from $removed_count user(s).";
            $messageType = "success";
        }
    }
}

// Build stale assemblies list (all users, all stale entries)
$stale_entries_audit = [];
foreach ($users as $username => $userData) {
    $userAccess = $userData['access'] ?? [];
    foreach ($userAccess as $organism => $assemblies) {
        if (is_array($assemblies)) {
            foreach ($assemblies as $assembly) {
                // Check if assembly exists in filesystem
                if (!isset($organisms[$organism]) || !in_array($assembly, $organisms[$organism])) {
                    $stale_entries_audit[] = [
                        'username' => $username,
                        'organism' => $organism,
                        'assembly' => $assembly,
                        'email' => $userData['email'] ?? ''
                    ];
                }
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
    'organisms' => $organisms,
    'file_write_error' => $file_write_error,
    'message' => $message,
    'messageType' => $messageType,
    'stale_entries_audit' => $stale_entries_audit,
    'config' => $config,
    'page_styles' => [
        '/' . $site . '/css/manage-users.css'
    ],
    'page_script' => [
        '/' . $site . '/js/admin-utilities.js',
        '/' . $site . '/js/modules/manage-users.js'
    ],
    'inline_scripts' => [
        "const allOrganisms = " . json_encode($organisms) . ";",
        "const allUsers = " . json_encode($users) . ";",
        "const colors = ['#007bff', '#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6610f2'];",
        "const organismColorMap = {};",
        "let nextColorIndex = 0;",
        "function getColorForOrganism(organism) {",
        "  if (!organismColorMap[organism]) {",
        "    organismColorMap[organism] = colors[nextColorIndex % colors.length];",
        "    nextColorIndex++;",
        "  }",
        "  return organismColorMap[organism];",
        "}"
    ]
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
