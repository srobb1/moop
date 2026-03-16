#!/usr/bin/env php
<?php

/**
 * MOOP Setup Preflight Check
 *
 * Validates that all prerequisites for a working MOOP installation are met.
 * Run after cloning the repo or after system changes (PHP upgrades, etc.).
 *
 * Usage: php setup-check.php
 *
 * Exit codes:
 *   0 = all checks passed
 *   1 = one or more checks failed
 */

// ── ANSI Colors ──────────────────────────────────────────────────────────────

const C_GREEN  = "\033[0;32m";
const C_RED    = "\033[0;31m";
const C_YELLOW = "\033[1;33m";
const C_CYAN   = "\033[1;36m";
const C_BOLD   = "\033[1m";
const C_RESET  = "\033[0m";

// ── Counters ─────────────────────────────────────────────────────────────────

$passes = 0;
$fails  = 0;
$warns  = 0;

// ── Utility Functions ────────────────────────────────────────────────────────

function pass($label) {
    global $passes;
    $passes++;
    echo "  " . C_GREEN . "[PASS]" . C_RESET . " $label\n";
}

function fail($label, $fix = '') {
    global $fails;
    $fails++;
    echo "  " . C_RED . "[FAIL]" . C_RESET . " $label\n";
    if ($fix) {
        echo "         " . C_YELLOW . "Fix: " . C_RESET . "$fix\n";
    }
}

function warn($label, $note = '') {
    global $warns;
    $warns++;
    echo "  " . C_YELLOW . "[WARN]" . C_RESET . " $label\n";
    if ($note) {
        echo "         $note\n";
    }
}

function section($title) {
    echo "\n" . C_CYAN . "── $title " . str_repeat('─', max(1, 56 - strlen($title))) . C_RESET . "\n\n";
}

/**
 * Detect the web server user/group from running processes.
 *
 * When this script runs from CLI, posix_getuid() returns the CLI user (e.g.
 * ubuntu), not the web server. Instead we inspect running apache2/httpd/nginx/
 * php-fpm worker processes (skipping root, which owns the parent process) to
 * find the actual web server identity. Falls back to 'www-data' if no web
 * server process is detected.
 */
function detectWebUser() {
    $user  = 'www-data';
    $group = 'www-data';

    // Look for non-root worker processes of common web servers
    $detect_cmds = [
        "ps -eo user,comm --no-headers | awk '\$2 ~ /apache2|httpd/ && \$1 != \"root\" {print \$1; exit}'",
        "ps -eo user,comm --no-headers | awk '\$2 ~ /nginx/ && \$1 != \"root\" {print \$1; exit}'",
        "ps -eo user,comm --no-headers | awk '\$2 ~ /php-fpm/ && \$1 != \"root\" {print \$1; exit}'",
    ];

    foreach ($detect_cmds as $cmd) {
        $output = [];
        @exec($cmd, $output);
        $detected = trim($output[0] ?? '');
        if (!empty($detected)) {
            $user = $detected;
            // Resolve primary group for this user
            if (function_exists('posix_getpwnam')) {
                $pwinfo = posix_getpwnam($user);
                if ($pwinfo !== false) {
                    $grinfo = posix_getgrgid($pwinfo['gid']);
                    if ($grinfo !== false) {
                        $group = $grinfo['name'];
                    }
                }
            }
            break;
        }
    }

    return ['user' => $user, 'group' => $group];
}

/**
 * Check if a CLI tool is available via `which`.
 */
function toolExists($tool) {
    $output = [];
    $ret = 1;
    @exec("which " . escapeshellarg($tool) . " 2>/dev/null", $output, $ret);
    return $ret === 0;
}

// ── Load Configuration ──────────────────────────────────────────────────────

$base = __DIR__;

echo "\n" . C_BOLD . "MOOP Setup Preflight Check" . C_RESET . "\n";
echo str_repeat('=', 40) . "\n";
echo "Base directory: $base\n";

// Load site_config.php for paths
$config = null;
$config_file = "$base/config/site_config.php";
if (!file_exists($config_file)) {
    echo "\n" . C_RED . "FATAL: config/site_config.php not found." . C_RESET . "\n";
    echo "This file should exist in a freshly cloned repo.\n";
    exit(1);
}

$config = @require $config_file;
if (!is_array($config)) {
    echo "\n" . C_RED . "FATAL: config/site_config.php did not return a valid config array." . C_RESET . "\n";
    exit(1);
}

$web = detectWebUser();
$web_user  = $web['user'];
$web_group = $web['group'];
echo "Web server user: $web_user:$web_group\n";

// ── Section 1: PHP Environment ──────────────────────────────────────────────

section("PHP Environment");

// PHP version
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    pass("PHP " . PHP_VERSION . " (7.4+ required)");
} else {
    fail("PHP " . PHP_VERSION . " — version 7.4+ required",
         "sudo apt-get install php");
}

// Required extensions
$required_extensions = [
    'sqlite3'  => 'sudo apt-get install php-sqlite3',
    'json'     => 'sudo apt-get install php-json',
    'openssl'  => 'sudo apt-get install php-xml (openssl is usually bundled)',
    'curl'     => 'sudo apt-get install php-curl',
];

foreach ($required_extensions as $ext => $fix) {
    if (extension_loaded($ext)) {
        pass("Extension: $ext");
    } else {
        fail("Extension: $ext not loaded", $fix);
    }
}

// posix is optional (app handles gracefully if missing)
if (extension_loaded('posix')) {
    pass("Extension: posix");
} else {
    warn("Extension: posix not loaded",
         "Optional — file permission detection will use fallback defaults");
}

// ── Section 2: CLI Tools ────────────────────────────────────────────────────

section("CLI Tools");

$required_tools = [
    'blastn'    => 'sudo apt-get install ncbi-blast+',
    'samtools'  => 'sudo apt-get install samtools',
    'tabix'     => 'sudo apt-get install tabix',
    'bgzip'     => 'sudo apt-get install tabix',
    'sqlite3'   => 'sudo apt-get install sqlite3',
];

foreach ($required_tools as $tool => $fix) {
    if (toolExists($tool)) {
        pass($tool);
    } else {
        fail("$tool not found in PATH", $fix);
    }
}

// Optional tools
if (toolExists('jq')) {
    pass("jq");
} else {
    warn("jq not found in PATH", "Optional — install with: sudo apt-get install jq");
}

// Composer — check PATH and local composer.phar
$has_composer = toolExists('composer');
$has_composer_phar = file_exists("$base/composer.phar");

if ($has_composer) {
    pass("composer");
} elseif ($has_composer_phar) {
    pass("composer (via local composer.phar)");
} else {
    fail("composer not found",
         "curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer");
}

// ── Section 3: Composer Dependencies ────────────────────────────────────────

section("Composer Dependencies");

if (file_exists("$base/vendor/autoload.php")) {
    pass("vendor/autoload.php exists");
} else {
    $composer_cmd = $has_composer ? 'composer install' : 'php composer.phar install';
    fail("vendor/autoload.php missing — dependencies not installed",
         $composer_cmd);
}

// ── Section 4: Configuration Files ──────────────────────────────────────────

section("Configuration Files");

$example_files = [
    'config/config_editable.json',
    'metadata/annotation_config.json',
    'metadata/group_descriptions.json',
    'metadata/organism_assembly_groups.json',
    'metadata/taxonomy_tree_config.json',
];

foreach ($example_files as $target) {
    $target_path  = "$base/$target";
    $example_path = "$target_path.example";

    if (file_exists($target_path)) {
        pass($target);
    } elseif (file_exists($example_path)) {
        fail("$target missing",
             "cp $target.example $target");
    } else {
        fail("$target missing (no .example template found either)");
    }
}

// ── Section 5: Directory Structure & Permissions ────────────────────────────

section("Directory Structure & Permissions");

$writable_dirs = [
    'logs'              => "$base/logs",
    'data/genomes'      => $config['jbrowse2']['genomes_directory'] ?? "$base/data/genomes",
    'data/tracks'       => $config['jbrowse2']['tracks_directory'] ?? "$base/data/tracks",
    'images'            => "$base/images",
    'metadata'          => $config['metadata_path'] ?? "$base/metadata",
    'metadata/change_log' => ($config['metadata_path'] ?? "$base/metadata") . '/change_log',
    'config'            => "$base/config",
    'certs'             => $config['jbrowse2']['certs_directory'] ?? "$base/certs",
    'organisms'         => $config['organism_data'] ?? "$base/organisms",
];

// Normalize trailing slashes from config values
foreach ($writable_dirs as $label => &$path) {
    $path = rtrim($path, '/');
}
unset($path);

foreach ($writable_dirs as $label => $path) {
    if (!is_dir($path)) {
        fail("$label/ does not exist ($path)",
             "mkdir -p " . escapeshellarg($path) .
             " && sudo chown $web_user:$web_group " . escapeshellarg($path) .
             " && sudo chmod 2775 " . escapeshellarg($path));
    } elseif (!is_writable($path)) {
        fail("$label/ exists but is not writable",
             "sudo chown $web_user:$web_group " . escapeshellarg($path) .
             " && sudo chmod 2775 " . escapeshellarg($path));
    } else {
        pass("$label/ writable");
    }
}

// ── Section 6: JWT Authentication Keys ──────────────────────────────────────

section("JWT Authentication Keys");

$jwt_priv = $config['jbrowse2']['jwt_private_key'] ?? "$base/certs/jwt_private_key.pem";
$jwt_pub  = $config['jbrowse2']['jwt_public_key']  ?? "$base/certs/jwt_public_key.pem";

$jwt_gen_cmd = "mkdir -p certs && openssl genrsa -out certs/jwt_private_key.pem 2048 " .
               "&& openssl rsa -in certs/jwt_private_key.pem -pubout -out certs/jwt_public_key.pem " .
               "&& sudo chmod 640 certs/*.pem";

$priv_ok = false;
$pub_ok  = false;

// JWT keys are typically owned by the deploy user (e.g. ubuntu) with group set to
// the web server group (e.g. www-data), perms 640. The web server reads via group.
// When running this script as a different user, the keys may not be directly readable
// but that's fine as long as the group is correct.
foreach ([['Private', $jwt_priv], ['Public', $jwt_pub]] as [$key_label, $key_path]) {
    if (!file_exists($key_path)) {
        continue; // handled below in the "missing keys" check
    }
    if (is_readable($key_path)) {
        pass("$key_label key: " . basename($key_path));
    } else {
        // Not readable by CLI user — check if web server group has access
        $file_group = function_exists('posix_getgrgid')
            ? (posix_getgrgid(filegroup($key_path))['name'] ?? 'unknown')
            : 'unknown';
        if ($file_group === $web_group) {
            pass("$key_label key: " . basename($key_path) . " (group $web_group has read access)");
        } else {
            fail("$key_label key not readable by web server (group is $file_group, expected $web_group)",
                 "sudo chgrp $web_group " . escapeshellarg($key_path) . " && sudo chmod 640 " . escapeshellarg($key_path));
        }
    }
}

// If either key is completely missing, show the generation command once
if (!file_exists($jwt_priv) || !file_exists($jwt_pub)) {
    $missing = [];
    if (!file_exists($jwt_priv)) $missing[] = basename($jwt_priv);
    if (!file_exists($jwt_pub))  $missing[] = basename($jwt_pub);
    fail("Missing JWT key(s): " . implode(', ', $missing), $jwt_gen_cmd);
}

// ── Section 7: Tracks Security ──────────────────────────────────────────────

section("Tracks Security");

$tracks_dir = rtrim($config['jbrowse2']['tracks_directory'] ?? "$base/data/tracks", '/');
$htaccess   = "$tracks_dir/.htaccess";

if (file_exists($htaccess)) {
    pass(".htaccess exists in data/tracks/");
} else {
    fail(".htaccess missing from data/tracks/ — track files are accessible without authentication",
         "See README.md Step 6 \"Set up the tracks security file\" for the required content");
}

// ── Section 8: Users File ───────────────────────────────────────────────────

section("Users File");

$users_file = $config['users_file'] ?? '/var/www/html/users.json';

if (file_exists($users_file)) {
    if (is_readable($users_file)) {
        pass("Users file: $users_file");
    } else {
        // File exists but current user can't read it — check if owned by web server user
        // (that's correct: www-data owns it with 600 so the web app can manage users)
        $file_owner = function_exists('posix_getpwuid')
            ? (posix_getpwuid(fileowner($users_file))['name'] ?? 'unknown')
            : 'unknown';

        if ($file_owner === $web_user) {
            // Owned by web server — this is the expected production state
            pass("Users file: $users_file (owned by $web_user, not readable by CLI user — correct)");
        } else {
            fail("Users file exists but is not readable and not owned by web server user: $users_file",
                 "sudo chown $web_user " . escapeshellarg($users_file) .
                 " && sudo chmod 600 " . escapeshellarg($users_file));
        }
    }
} else {
    fail("Users file not found: $users_file",
         "sudo php setup-admin.php");
}

// ── Summary ─────────────────────────────────────────────────────────────────

echo "\n" . str_repeat('=', 40) . "\n";

$total = $passes + $fails + $warns;
echo C_GREEN  . "  $passes passed" . C_RESET;
if ($fails > 0) {
    echo "  " . C_RED . "$fails failed" . C_RESET;
}
if ($warns > 0) {
    echo "  " . C_YELLOW . "$warns warnings" . C_RESET;
}
echo "\n" . str_repeat('=', 40) . "\n\n";

if ($fails > 0) {
    echo C_RED . "Some checks failed. Fix the issues above and re-run:" . C_RESET . "\n";
    echo "  php setup-check.php\n\n";
    exit(1);
} else {
    echo C_GREEN . "All checks passed! MOOP is ready to run." . C_RESET . "\n\n";
    exit(0);
}
