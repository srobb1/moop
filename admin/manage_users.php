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

// Build group → organism → {assembly: [gene_sets]} map for the JS selector
$group_data = getGroupData();
$organisms_by_group = [];
$grouped_orgs = [];
foreach ($group_data as $entry) {
    $org  = $entry['organism'];
    $asm  = $entry['assembly'];
    $gs   = $entry['gene_set'] ?? 'v1';
    $grps = $entry['groups'] ?? [];
    if (!isset($organisms[$org]) || !in_array($asm, $organisms[$org])) continue;
    foreach ($grps as $g) {
        if ($g === 'Public') continue;
        $organisms_by_group[$g][$org][$asm][] = $gs;
        $grouped_orgs[$org] = true;
    }
}
// Organisms not in any named group → "Other"
foreach ($organisms as $org => $assemblies) {
    if (!isset($grouped_orgs[$org])) {
        foreach ($assemblies as $asm) {
            $organisms_by_group['Other'][$org][$asm][] = 'v1';
        }
    }
}
ksort($organisms_by_group);

// Build {org: {assembly: [gene_sets]}} for allOrganisms JS variable
$orgs_with_gene_sets = [];
foreach ($group_data as $entry) {
    $org = $entry['organism'];
    $asm = $entry['assembly'];
    $gs  = $entry['gene_set'] ?? 'v1';
    if (!isset($organisms[$org]) || !in_array($asm, $organisms[$org])) continue;
    $orgs_with_gene_sets[$org][$asm][] = $gs;
}
foreach ($organisms as $org => $assemblies) {
    foreach ($assemblies as $asm) {
        if (!isset($orgs_with_gene_sets[$org][$asm])) {
            $orgs_with_gene_sets[$org][$asm] = ['v1'];
        }
    }
}

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
    csrf_protect();
    if ($file_write_error) {
        $message = "Users file is not writable. Please fix permissions first.";
        $messageType = "danger";
    }
    elseif (isset($_POST['create_or_update_user'])) {
        $username = trim($_POST['username'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $new_password_confirm = trim($_POST['new_password_confirm'] ?? '');
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
                if (empty($new_password)) {
                    $message = "Password is required for new users.";
                    $messageType = "danger";
                } elseif ($new_password !== $new_password_confirm) {
                    $message = "Passwords do not match.";
                    $messageType = "danger";
                } else {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
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
                    // Validate new password if provided
                    if (!empty($new_password) && $new_password !== $new_password_confirm) {
                        $message = "Passwords do not match.";
                        $messageType = "danger";
                    } else {
                        $users[$original_username]['email'] = $email;
                        $users[$original_username]['first_name'] = $first_name;
                        $users[$original_username]['last_name'] = $last_name;
                        $users[$original_username]['account_host'] = $account_host;
                        $users[$original_username]['access'] = $access;
                        $users[$original_username]['role'] = $is_admin ? 'admin' : 'user';

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

        if (isset($users[$username]['access'][$organism])) {
            $asm_data = $users[$username]['access'][$organism];
            if (is_array($asm_data) && array_is_list($asm_data)) {
                // Old format: [{asm, asm, ...}]
                $users[$username]['access'][$organism] = array_values(array_filter($asm_data, fn($a) => $a !== $assembly));
            } else {
                // New format: {asm: [gene_sets]}
                unset($users[$username]['access'][$organism][$assembly]);
            }
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                $message = "Stale assembly removed from user.";
                $messageType = "success";
            }
        }
    }
    elseif (isset($_POST['remove_all_stale'])) {
        // Remove every stale assembly from every user in one pass
        $removed_count = 0;
        foreach ($users as $user => $userData) {
            foreach ($userData['access'] ?? [] as $org => $asm_data) {
                if (!is_array($asm_data)) continue;
                if (array_is_list($asm_data)) {
                    $filtered = array_values(array_filter($asm_data, fn($a) => isset($organisms[$org]) && in_array($a, $organisms[$org])));
                    if (count($filtered) < count($asm_data)) {
                        $users[$user]['access'][$org] = $filtered;
                        $removed_count += count($asm_data) - count($filtered);
                    }
                } else {
                    foreach (array_keys($asm_data) as $asm) {
                        if (!isset($organisms[$org]) || !in_array($asm, $organisms[$org])) {
                            unset($users[$user]['access'][$org][$asm]);
                            $removed_count++;
                        }
                    }
                }
            }
        }
        if ($removed_count > 0 && file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
            $message = "Removed $removed_count stale assembly reference(s) across all users.";
            $messageType = "success";
        } elseif ($removed_count === 0) {
            $message = "No stale assemblies found.";
            $messageType = "info";
        }
    }
    elseif (isset($_POST['remove_stale_from_all'])) {
        $organism = $_POST['organism'];
        $assembly = $_POST['assembly'];
        $removed_count = 0;

        foreach ($users as $user => $userData) {
            if (!isset($userData['access'][$organism])) continue;
            $asm_data = $userData['access'][$organism];
            if (is_array($asm_data) && array_is_list($asm_data)) {
                $filtered = array_values(array_filter($asm_data, fn($a) => $a !== $assembly));
                if (count($filtered) < count($asm_data)) {
                    $users[$user]['access'][$organism] = $filtered;
                    $removed_count++;
                }
            } elseif (isset($asm_data[$assembly])) {
                unset($users[$user]['access'][$organism][$assembly]);
                $removed_count++;
            }
        }

        if ($removed_count > 0 && file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
            $message = "Stale assembly removed from $removed_count user(s).";
            $messageType = "success";
        }
    }
}

// Build stale assemblies list (all users, all stale entries)
// Handles both old {org: [asm,...]} and new {org: {asm: [gene_sets]}} formats
$stale_entries_audit = [];
foreach ($users as $username => $userData) {
    $userAccess = $userData['access'] ?? [];
    foreach ($userAccess as $organism => $asm_data) {
        if (!is_array($asm_data)) continue;
        $asm_keys = array_is_list($asm_data) ? $asm_data : array_keys($asm_data);
        foreach ($asm_keys as $assembly) {
            if (!isset($organisms[$organism]) || !in_array($assembly, $organisms[$organism])) {
                $stale_entries_audit[] = [
                    'username' => $username,
                    'organism' => $organism,
                    'assembly' => $assembly,
                    'email'    => $userData['email'] ?? '',
                ];
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
    ],
    'inline_scripts' => [
        "const allOrganisms = " . json_encode($orgs_with_gene_sets) . ";",
        "const allOrganismsByGroup = " . json_encode($organisms_by_group) . ";",
        "const allUsers = " . json_encode(array_map(function($u) { unset($u['password']); return $u; }, $users)) . ";",
    ]
];

// Render page using layout system
echo render_display_page(
    $display_config['content_file'],
    $data,
    $display_config['title']
);

?>
