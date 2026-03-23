<?php
/**
 * MOOP Web-Based Installer
 *
 * One-time setup wizard for new MOOP deployments. Replaces the manual
 * README steps with an interactive browser-based process.
 *
 * Self-disabling: refuses to run once config/config_editable.json exists.
 *
 * What it does:
 *   1. Checks PHP version, extensions, and CLI tools
 *   2. Creates required directories with correct permissions
 *   3. Copies .example config/metadata files
 *   4. Generates JWT key pair for JBrowse2 track authentication
 *   5. Creates data/tracks/.htaccess security file
 *   6. Creates admin user account (users.json)
 *   7. Runs composer install (if needed)
 *   8. Creates config_editable.json (this disables the installer)
 */

// ── Security Gate ───────────────────────────────────────────────────────────

$base = __DIR__;
$config_editable_path = "$base/config/config_editable.json";

if (file_exists($config_editable_path)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;">
        <h2>Setup Already Complete</h2>
        <p>The installer has been disabled because <code>config/config_editable.json</code> already exists.</p>
        <p>To re-run setup, delete that file first (this will reset your site configuration).</p>
        </body></html>');
}

// ── Token Gate ─────────────────────────────────────────────────────────────
// Prevent unauthorized access: require a server-generated token in the URL.
// The token file is created on first visit and must be read from the CLI.
$tokenFile = "$base/.setup-token";

if (!file_exists($tokenFile)) {
    @file_put_contents($tokenFile, bin2hex(random_bytes(16)));
    @chmod($tokenFile, 0600);
}

$expectedToken = trim(@file_get_contents($tokenFile) ?: '');
$providedToken = $_GET['token'] ?? '';

if ($providedToken !== $expectedToken || empty($expectedToken)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid or missing setup token.']);
        exit;
    }
    http_response_code(403);
    die('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>MOOP Setup</title>
        <style>body{font-family:sans-serif;padding:40px;max-width:600px;margin:0 auto;}
        pre{background:#f0f0f0;padding:12px;border-radius:4px;}</style></head>
        <body>
        <h2>Setup Token Required</h2>
        <p>For security, the setup wizard requires a one-time token.</p>
        <p>Run this command on the server to see your token:</p>
        <pre>cat ' . htmlspecialchars($base) . '/.setup-token</pre>
        <p>Then visit:</p>
        <pre>setup.php?token=<em>YOUR_TOKEN</em></pre>
        </body></html>');
}

// ── Load Configuration ──────────────────────────────────────────────────────

$config_file = "$base/config/site_config.php";
if (!file_exists($config_file)) {
    die('FATAL: config/site_config.php not found. This file must exist in a fresh clone.');
}

$config = @require $config_file;
if (!is_array($config)) {
    die('FATAL: config/site_config.php did not return a valid config array.');
}
require_once "$base/lib/distro_detect.php";

// ── Helper Functions ────────────────────────────────────────────────────────

/**
 * Detect web server user/group. Since this runs inside the web server,
 * posix_getuid() returns the correct web server identity.
 */
function getWebUser() {
    $user  = 'www-data';
    $group = 'www-data';

    if (function_exists('posix_getuid')) {
        $pwinfo = posix_getpwuid(posix_getuid());
        if ($pwinfo !== false) {
            $user = $pwinfo['name'];
        }
        $grinfo = posix_getgrgid(posix_getgid());
        if ($grinfo !== false) {
            $group = $grinfo['name'];
        }
    } else {
        // Fallback without posix: parse `id` command output
        $id_output = [];
        @exec("id 2>/dev/null", $id_output);
        if (!empty($id_output[0])) {
            if (preg_match('/uid=\d+\(([^)]+)\)/', $id_output[0], $m)) {
                $user = $m[1];
            }
            if (preg_match('/gid=\d+\(([^)]+)\)/', $id_output[0], $m)) {
                $group = $m[1];
            }
        }
    }

    return ['user' => $user, 'group' => $group];
}

function toolExists($tool) {
    $output = [];
    $ret = 1;
    @exec("which " . escapeshellarg($tool) . " 2>/dev/null", $output, $ret);
    return $ret === 0;
}

/**
 * Run all environment checks. Returns array of check results.
 */
function checkEnvironment($base, $config) {
    $checks = [];

    // PHP version
    $checks[] = [
        'label' => 'PHP ' . PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'pass' : 'fail',
        'detail' => 'Requires 7.4+',
        'category' => 'PHP',
    ];

    // Required extensions
    $extensions = ['sqlite3', 'json', 'openssl', 'curl'];
    foreach ($extensions as $ext) {
        $checks[] = [
            'label' => "Extension: $ext",
            'status' => extension_loaded($ext) ? 'pass' : 'fail',
            'category' => 'PHP',
        ];
    }

    // Optional extension
    $checks[] = [
        'label' => 'Extension: posix',
        'status' => extension_loaded('posix') ? 'pass' : 'warn',
        'detail' => 'Optional — permission detection uses fallback if missing',
        'category' => 'PHP',
    ];

    // CLI tools
    $tools = [
        'blastn'   => ['required' => true],
        'samtools' => ['required' => true],
        'tabix'    => ['required' => true],
        'bgzip'    => ['required' => true],
        'sqlite3'  => ['required' => true],
        'jq'       => ['required' => false],
    ];

    foreach ($tools as $tool => $info) {
        $exists = toolExists($tool);
        $checks[] = [
            'label' => $tool,
            'status' => $exists ? 'pass' : ($info['required'] ? 'fail' : 'warn'),
            'detail' => $exists ? '' : ($info['required'] ? 'Required' : 'Optional'),
            'category' => 'CLI Tools',
        ];
    }

    // Composer
    $hasComposer = toolExists('composer') || file_exists("$base/composer.phar");
    $checks[] = [
        'label' => 'composer',
        'status' => $hasComposer ? 'pass' : 'fail',
        'category' => 'CLI Tools',
    ];

    return $checks;
}

// ── POST Handler: Execute Setup ─────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    set_time_limit(120);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'error' => 'Invalid request body']);
        exit;
    }

    $siteTitle  = trim($input['site_title'] ?? 'MOOP');
    $adminEmail = trim($input['admin_email'] ?? 'admin@example.com');
    $username   = trim($input['username'] ?? '');
    $password   = $input['password'] ?? '';

    // Validate inputs
    if (empty($username) || !preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        echo json_encode(['success' => false, 'error' => 'Invalid username. Use only letters, numbers, underscores, hyphens.']);
        exit;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']);
        exit;
    }

    $steps = [];

    // ── Step 1: Create Directories ──────────────────────────────────────

    $dirResults = [];
    $created = 0;
    $existed = 0;
    $dirFailed = false;

    $dirs = [
        'logs'                => "$base/logs",
        'data/genomes'        => rtrim($config['jbrowse2']['genomes_directory'] ?? "$base/data/genomes", '/'),
        'data/tracks'         => rtrim($config['jbrowse2']['tracks_directory'] ?? "$base/data/tracks", '/'),
        'images'              => "$base/images",
        'metadata'            => rtrim($config['metadata_path'] ?? "$base/metadata", '/'),
        'metadata/change_log' => rtrim(($config['metadata_path'] ?? "$base/metadata") . '/change_log', '/'),
        'config'              => "$base/config",
        'certs'               => rtrim($config['jbrowse2']['certs_directory'] ?? "$base/certs", '/'),
        'organisms'           => rtrim($config['organism_data'] ?? "$base/organisms", '/'),
    ];

    foreach ($dirs as $label => $path) {
        if (is_dir($path) && is_writable($path)) {
            $existed++;
        } elseif (is_dir($path)) {
            // Exists but not writable — try to fix
            if (@chmod($path, 02775)) {
                $existed++;
            } else {
                $dirFailed = true;
                $dirResults[] = "Cannot write to $label/ ($path)";
            }
        } else {
            if (@mkdir($path, 02775, true)) {
                $created++;
            } else {
                $dirFailed = true;
                $dirResults[] = "Cannot create $label/ ($path)";
            }
        }
    }

    $steps['directories'] = [
        'success' => !$dirFailed,
        'message' => $dirFailed
            ? 'Failed: ' . implode('; ', $dirResults)
            : "Created $created directories, $existed already existed",
    ];

    // ── Step 2: Copy .example Files ─────────────────────────────────────

    $exampleFiles = [
        'metadata/annotation_config.json',
        'metadata/group_descriptions.json',
        'metadata/organism_assembly_groups.json',
        'metadata/taxonomy_tree_config.json',
    ];

    $copied = 0;
    $exSkipped = 0;
    $exErrors = [];

    foreach ($exampleFiles as $target) {
        $targetPath  = "$base/$target";
        $examplePath = "$targetPath.example";

        if (file_exists($targetPath)) {
            $exSkipped++;
        } elseif (file_exists($examplePath)) {
            if (@copy($examplePath, $targetPath)) {
                $copied++;
            } else {
                $exErrors[] = basename($target);
            }
        } else {
            $exErrors[] = basename($target) . ' (no .example found)';
        }
    }

    $steps['example_files'] = [
        'success' => empty($exErrors),
        'message' => empty($exErrors)
            ? "Copied $copied files, $exSkipped already existed"
            : 'Failed to copy: ' . implode(', ', $exErrors),
    ];

    // ── Step 3: Generate JWT Keys ───────────────────────────────────────

    $jwtPriv = $config['jbrowse2']['jwt_private_key'] ?? "$base/certs/jwt_private_key.pem";
    $jwtPub  = $config['jbrowse2']['jwt_public_key']  ?? "$base/certs/jwt_public_key.pem";

    if (file_exists($jwtPriv) && file_exists($jwtPub)) {
        $steps['jwt_keys'] = [
            'success' => true,
            'message' => 'Key pair already exists — skipped',
        ];
    } elseif (!extension_loaded('openssl')) {
        $steps['jwt_keys'] = [
            'success' => false,
            'message' => 'OpenSSL extension not loaded — cannot generate keys',
        ];
    } else {
        $keyConfig = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $key = @openssl_pkey_new($keyConfig);
        if ($key === false) {
            $steps['jwt_keys'] = [
                'success' => false,
                'message' => 'openssl_pkey_new() failed: ' . openssl_error_string(),
            ];
        } else {
            $privKeyPem = '';
            openssl_pkey_export($key, $privKeyPem);
            $pubKeyPem = openssl_pkey_get_details($key)['key'];

            $privOk = @file_put_contents($jwtPriv, $privKeyPem);
            $pubOk  = @file_put_contents($jwtPub, $pubKeyPem);

            if ($privOk !== false && $pubOk !== false) {
                @chmod($jwtPriv, 0640);
                @chmod($jwtPub, 0640);
                $steps['jwt_keys'] = [
                    'success' => true,
                    'message' => 'Generated 2048-bit RSA key pair',
                ];
            } else {
                $steps['jwt_keys'] = [
                    'success' => false,
                    'message' => 'Generated keys but failed to write files — check certs/ directory permissions',
                ];
            }
        }
    }

    // ── Step 4: Create data/tracks/.htaccess ────────────────────────────

    $tracksDir = rtrim($config['jbrowse2']['tracks_directory'] ?? "$base/data/tracks", '/');
    $htaccessPath = "$tracksDir/.htaccess";

    if (file_exists($htaccessPath)) {
        $steps['htaccess'] = [
            'success' => true,
            'message' => '.htaccess already exists — skipped',
        ];
    } else {
        $htaccessContent = <<<'HTACCESS'
# SECURITY: Block direct access to track files
# All track requests MUST go through /api/jbrowse2/tracks.php
# which validates JWT tokens before serving files

# Apache 2.2 style
<IfVersion < 2.4>
    Order Deny,Allow
    Deny from all
</IfVersion>

# Apache 2.4+ style
<IfVersion >= 2.4>
    Require all denied
</IfVersion>

ErrorDocument 403 "Access denied. Track files must be accessed through the API endpoint with valid JWT token."
HTACCESS;

        if (@file_put_contents($htaccessPath, $htaccessContent) !== false) {
            $steps['htaccess'] = [
                'success' => true,
                'message' => 'Created data/tracks/.htaccess',
            ];
        } else {
            $steps['htaccess'] = [
                'success' => false,
                'message' => 'Failed to write .htaccess — check data/tracks/ permissions',
            ];
        }
    }

    // Warn if Nginx is detected (where .htaccess has no effect)
    $ngxOutput = [];
    $ngxRet = 1;
    @exec("which nginx 2>/dev/null", $ngxOutput, $ngxRet);
    if ($ngxRet === 0 && ($steps['htaccess']['success'] ?? false)) {
        $siteName = $config['site'] ?? 'moop';
        $steps['htaccess']['message'] .= ". Note: Nginx detected — .htaccess has no effect on Nginx. "
            . "Add a 'deny all' location block for /$siteName/data/tracks/ in your Nginx server config.";
    }

    // ── Step 5: Create Admin User ───────────────────────────────────────

    $usersFile = $config['users_file'] ?? "$base/../users.json";

    if (file_exists($usersFile)) {
        $existingUsers = json_decode(file_get_contents($usersFile), true);
        if (is_array($existingUsers) && isset($existingUsers[$username]) && ($existingUsers[$username]['role'] ?? '') === 'admin') {
            $steps['admin_user'] = [
                'success' => true,
                'message' => "Admin user '$username' already exists — skipped",
            ];
        } else {
            // File exists but this user isn't admin — add them
            if (!is_array($existingUsers)) {
                $existingUsers = [];
            }
            $existingUsers[$username] = [
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'role'     => 'admin',
                'access'   => [],
            ];

            if (@file_put_contents($usersFile, json_encode($existingUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
                @chmod($usersFile, 0600);
                $steps['admin_user'] = [
                    'success' => true,
                    'message' => "Added admin user '$username' to existing users file",
                ];
            } else {
                $steps['admin_user'] = [
                    'success' => false,
                    'message' => "Failed to write to $usersFile — check parent directory permissions",
                ];
            }
        }
    } else {
        // Create new users.json
        $usersDir = dirname($usersFile);
        if (!is_dir($usersDir)) {
            @mkdir($usersDir, 0755, true);
        }

        $users = [
            $username => [
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'role'     => 'admin',
                'access'   => [],
            ],
        ];

        if (@file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
            @chmod($usersFile, 0600);
            $steps['admin_user'] = [
                'success' => true,
                'message' => "Created users file with admin user '$username'",
            ];
        } else {
            $steps['admin_user'] = [
                'success' => false,
                'message' => "Failed to create $usersFile — check that the web server can write to " . dirname($usersFile),
            ];
        }
    }

    // ── Step 6: Composer Install ────────────────────────────────────────

    if (file_exists("$base/vendor/autoload.php")) {
        $steps['composer'] = [
            'success' => true,
            'message' => 'Dependencies already installed — skipped',
        ];
    } else {
        $composerCmd = null;
        if (toolExists('composer')) {
            $composerCmd = 'composer';
        } elseif (file_exists("$base/composer.phar")) {
            $composerCmd = 'php ' . escapeshellarg("$base/composer.phar");
        }

        if ($composerCmd === null) {
            $steps['composer'] = [
                'success' => false,
                'message' => 'Composer not found — run "composer install" manually from the command line',
            ];
        } else {
            $output = [];
            $ret = 0;
            $fullCmd = "cd " . escapeshellarg($base) . " && $composerCmd install --no-dev --no-interaction 2>&1";
            @exec($fullCmd, $output, $ret);

            if ($ret === 0) {
                $steps['composer'] = [
                    'success' => true,
                    'message' => 'Dependencies installed',
                ];
            } else {
                $steps['composer'] = [
                    'success' => false,
                    'message' => 'Composer install failed (exit ' . $ret . '). Run "composer install" from CLI. Output: ' . implode("\n", array_slice($output, -5)),
                ];
            }
        }
    }

    // ── Step 7: Create config_editable.json (MUST BE LAST) ──────────────

    // Read the example file as a starting point
    $exampleConfig = "$base/config/config_editable.json.example";
    $newConfig = [];

    if (file_exists($exampleConfig)) {
        $newConfig = json_decode(file_get_contents($exampleConfig), true) ?? [];
    }

    // Apply user-provided values
    $newConfig['siteTitle']    = $siteTitle;
    $newConfig['admin_email']  = $adminEmail;

    $json = json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (@file_put_contents($config_editable_path, $json) !== false) {
        $steps['config'] = [
            'success' => true,
            'message' => 'Created config_editable.json — installer is now disabled',
        ];
        // Clean up: remove the setup token file
        @unlink($tokenFile);
    } else {
        $steps['config'] = [
            'success' => false,
            'message' => 'Failed to write config/config_editable.json — check config/ directory permissions',
        ];
    }

    // Overall success = config file was created (the self-disable trigger)
    $overallSuccess = $steps['config']['success'] ?? false;

    echo json_encode(['success' => $overallSuccess, 'steps' => $steps]);
    exit;
}

// ── GET Handler: Render Wizard Page ─────────────────────────────────────────

$checks = checkEnvironment($base, $config);
$webInfo = getWebUser();
$hasCriticalFail = false;
foreach ($checks as $c) {
    if ($c['status'] === 'fail') {
        $hasCriticalFail = true;
        break;
    }
}

$siteName = $config['site'] ?? 'moop';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MOOP Setup Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .setup-container { max-width: 720px; margin: 40px auto; }
        .check-pass { color: #198754; }
        .check-fail { color: #dc3545; font-weight: 600; }
        .check-warn { color: #ffc107; }
        .step-result { padding: 6px 12px; border-radius: 4px; margin-bottom: 4px; }
        .step-ok { background: #d1e7dd; }
        .step-err { background: #f8d7da; }
        .step-skip { background: #e2e3e5; }
    </style>
</head>
<body>
<div class="setup-container">
    <?php if (empty($_SERVER['HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https'
              && ($_SERVER['SERVER_PORT'] ?? 80) != 443): ?>
    <div class="alert alert-warning mb-4">
        <strong>Warning:</strong> You are accessing this page over HTTP.
        The admin password will be sent in plain text. Use HTTPS if possible.
    </div>
    <?php endif; ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body text-center">
            <h2 class="mb-1">MOOP Setup Wizard</h2>
            <p class="text-muted mb-0">Multi-Organism Omics Platform &mdash; First-Time Setup</p>
        </div>
    </div>

    <!-- Step 1: Environment Check -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Step 1:</strong> Environment Check</div>
        <div class="card-body">
            <p class="text-muted small">Verifying PHP, extensions, and CLI tools.</p>
            <table class="table table-sm mb-0">
                <tbody>
                <?php
                $currentCat = '';
                foreach ($checks as $c):
                    if ($c['category'] !== $currentCat):
                        $currentCat = $c['category'];
                ?>
                    <tr><td colspan="2" class="fw-bold pt-3 border-0"><?= htmlspecialchars($currentCat) ?></td></tr>
                <?php endif; ?>
                    <tr>
                        <td><?= htmlspecialchars($c['label']) ?></td>
                        <td class="text-end">
                            <?php if ($c['status'] === 'pass'): ?>
                                <span class="check-pass">PASS</span>
                            <?php elseif ($c['status'] === 'fail'): ?>
                                <span class="check-fail">FAIL</span>
                            <?php else: ?>
                                <span class="check-warn">WARN</span>
                            <?php endif; ?>
                            <?php if (!empty($c['detail'])): ?>
                                <small class="text-muted d-block"><?= htmlspecialchars($c['detail']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($hasCriticalFail): ?>
                <div class="alert alert-danger mt-3 mb-0">
                    <strong>Critical failures detected.</strong> Fix the items above before running setup.
                    Run <code>php setup-check.php</code> from the command line for detailed fix commands.
                </div>
            <?php endif; ?>

            <p class="text-muted small mt-3 mb-0">
                Web server running as: <strong><?= htmlspecialchars($webInfo['user']) ?>:<?= htmlspecialchars($webInfo['group']) ?></strong>
            </p>
        </div>
    </div>

    <!-- Step 2: Site Configuration -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Step 2:</strong> Site Configuration</div>
        <div class="card-body">
            <div class="mb-3">
                <label for="siteTitle" class="form-label">Site Title</label>
                <input type="text" class="form-control" id="siteTitle" value="MOOP" placeholder="e.g. My Genome Portal">
                <div class="form-text">Displayed in the browser tab and navigation bar.</div>
            </div>
            <div class="mb-3">
                <label for="adminEmail" class="form-label">Admin Email</label>
                <input type="email" class="form-control" id="adminEmail" value="admin@example.com" placeholder="admin@example.com">
            </div>
        </div>
    </div>

    <!-- Step 3: Admin Account -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Step 3:</strong> Create Admin Account</div>
        <div class="card-body">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" value="admin" pattern="[a-zA-Z0-9_-]+" required>
                <div class="form-text">Letters, numbers, underscores, hyphens only.</div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" minlength="8" required>
                <div class="form-text">Minimum 8 characters.</div>
            </div>
            <div class="mb-3">
                <label for="passwordConfirm" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="passwordConfirm" minlength="8" required>
                <div id="passwordError" class="invalid-feedback">Passwords do not match.</div>
            </div>
        </div>
    </div>

    <!-- Run Setup Button -->
    <div class="d-grid mb-4">
        <button id="runSetup" class="btn btn-primary btn-lg" <?= $hasCriticalFail ? 'disabled' : '' ?>>
            Run Setup
        </button>
    </div>

    <!-- Step 4: Results -->
    <div class="card shadow-sm mb-4" id="resultsCard" style="display:none;">
        <div class="card-header"><strong>Step 4:</strong> Results</div>
        <div class="card-body" id="resultsBody">
        </div>
    </div>

    <!-- Success -->
    <div class="card shadow-sm mb-4 border-success" id="successCard" style="display:none;">
        <div class="card-body text-center">
            <h4 class="text-success">Setup Complete!</h4>
            <p>Log in with your admin account to start configuring your site.</p>
            <a href="/<?= htmlspecialchars($siteName) ?>/" class="btn btn-success btn-lg">Go to MOOP</a>
        </div>
    </div>

</div>

<script>
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
document.getElementById('runSetup').addEventListener('click', async function() {
    const btn = this;
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('passwordConfirm').value;
    const username = document.getElementById('username').value.trim();

    // Client-side validation
    if (!username || !/^[a-zA-Z0-9_-]+$/.test(username)) {
        alert('Username is required and can only contain letters, numbers, underscores, hyphens.');
        return;
    }
    if (password.length < 8) {
        alert('Password must be at least 8 characters.');
        return;
    }
    if (password !== passwordConfirm) {
        document.getElementById('passwordConfirm').classList.add('is-invalid');
        return;
    }
    document.getElementById('passwordConfirm').classList.remove('is-invalid');

    // Disable button, show progress
    btn.disabled = true;
    btn.textContent = 'Running setup\u2026';

    const resultsCard = document.getElementById('resultsCard');
    const resultsBody = document.getElementById('resultsBody');
    resultsCard.style.display = 'block';
    resultsBody.innerHTML = '<p class="text-muted">Working\u2026 this may take a moment if installing Composer dependencies.</p>';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                site_title: document.getElementById('siteTitle').value.trim(),
                admin_email: document.getElementById('adminEmail').value.trim(),
                username: username,
                password: password,
            }),
        });

        const data = await response.json();

        // Render results
        const stepLabels = {
            directories: 'Create directories',
            example_files: 'Copy config templates',
            jwt_keys: 'Generate JWT keys',
            htaccess: 'Create tracks .htaccess',
            admin_user: 'Create admin account',
            composer: 'Install Composer dependencies',
            config: 'Create config_editable.json',
        };

        let html = '';
        for (const [key, result] of Object.entries(data.steps || {})) {
            const label = stepLabels[key] || key;
            const cls = result.success ? 'step-ok' : 'step-err';
            const icon = result.success ? '&#10003;' : '&#10007;';
            html += `<div class="step-result ${cls}"><strong>${icon}</strong> ${escapeHtml(label)} &mdash; ${escapeHtml(result.message)}</div>`;
        }
        resultsBody.innerHTML = html;

        if (data.success) {
            document.getElementById('successCard').style.display = 'block';
            btn.textContent = 'Setup Complete';
        } else {
            btn.disabled = false;
            btn.textContent = 'Retry Setup';

            const errorMsg = data.error
                ? `<div class="alert alert-danger mt-3">${escapeHtml(data.error)}</div>`
                : '<div class="alert alert-warning mt-3">Some steps failed. Fix the issues and click Retry.</div>';
            resultsBody.innerHTML += errorMsg;
        }
    } catch (err) {
        resultsBody.innerHTML = `<div class="alert alert-danger">Request failed: ${escapeHtml(err.message)}</div>`;
        btn.disabled = false;
        btn.textContent = 'Retry Setup';
    }
});

// Clear password mismatch on typing
document.getElementById('passwordConfirm').addEventListener('input', function() {
    this.classList.remove('is-invalid');
});
</script>
</body>
</html>
