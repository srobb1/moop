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

// CLI-only: never expose install diagnostics (paths, OS user, tool inventory) over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

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

    // Look for non-root worker processes. PHP-FPM is checked first because
    // it's the process that actually runs PHP — its user is what matters for
    // file permissions, even if a different user runs the nginx/httpd frontend.
    $detect_cmds = [
        "ps -eo user,comm --no-headers | awk '\$2 ~ /php-fpm/ && \$1 != \"root\" {print \$1; exit}'",
        "ps -eo user,comm --no-headers | awk '\$2 ~ /apache2|httpd/ && \$1 != \"root\" {print \$1; exit}'",
        "ps -eo user,comm --no-headers | awk '\$2 ~ /nginx/ && \$1 != \"root\" {print \$1; exit}'",
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
            } else {
                // Fallback without posix: parse `id` command output
                $id_output = [];
                @exec("id " . escapeshellarg($user) . " 2>/dev/null", $id_output);
                if (!empty($id_output[0]) && preg_match('/gid=\d+\(([^)]+)\)/', $id_output[0], $m)) {
                    $group = $m[1];
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
/**
 * Which web server is actually running? MOOP supports both Apache and nginx, so no
 * check may assume one. Detected from running processes (same approach as
 * detectWebUser), falling back to which config tree exists on disk.
 *
 * @return string 'nginx' | 'apache' | 'unknown'
 */
function detectWebServer() {
    static $flavor = null;
    if ($flavor !== null) return $flavor;

    $out = [];
    @exec("ps -eo comm --no-headers 2>/dev/null | sort -u", $out);
    $procs = implode(' ', $out);
    if (preg_match('/\bnginx\b/', $procs))          return $flavor = 'nginx';
    if (preg_match('/\b(apache2|httpd)\b/', $procs)) return $flavor = 'apache';

    // Nothing running (fresh install, or checking before starting the server).
    if (is_dir('/etc/nginx'))                        return $flavor = 'nginx';
    if (is_dir('/etc/httpd') || is_dir('/etc/apache2')) return $flavor = 'apache';
    return $flavor = 'unknown';
}

/**
 * Numeric uid/gid for the web server account. Cached; resolved without posix.
 *
 * The posix extension is NOT loaded in this PHP CLI (verified 2026-07-16), so
 * posix_getpwnam() and friends are unavailable here even though the same functions
 * work under php-fpm. Fall back to id/getent.
 *
 * @return array{uid:?int, gid:?int}
 */
function webUserIds($web_user, $web_group) {
    static $ids = null;
    if ($ids !== null) return $ids;

    $uid = $gid = null;
    if (function_exists('posix_getpwnam')) {
        $p = @posix_getpwnam($web_user);
        if (is_array($p)) $uid = (int) $p['uid'];
        $g = @posix_getgrnam($web_group);
        if (is_array($g)) $gid = (int) $g['gid'];
    }
    if ($uid === null) {
        $o = [];
        @exec('id -u ' . escapeshellarg($web_user) . ' 2>/dev/null', $o);
        if (!empty($o[0]) && ctype_digit(trim($o[0]))) $uid = (int) trim($o[0]);
    }
    if ($gid === null) {
        $o = [];
        @exec('getent group ' . escapeshellarg($web_group) . ' 2>/dev/null', $o);
        if (!empty($o[0])) {
            $parts = explode(':', $o[0]);
            if (isset($parts[2]) && ctype_digit($parts[2])) $gid = (int) $parts[2];
        }
    }
    return $ids = ['uid' => $uid, 'gid' => $gid];
}

/**
 * Can the WEB SERVER write here? — not "can whoever ran this script write here".
 *
 * is_writable() answers for the current process. Run as the deploy user (the normal case
 * for a preflight) it is owner-biased and wrong in BOTH directions: it says "no" for a
 * directory apache owns and can write (observed on archived_gene_sets, apache:apache 755)
 * and "yes" for one only the deploy user can write. Predict apache's DAC view from the
 * raw mode bits and numeric ids instead.
 *
 * DAC only. On an enforcing host the SELinux label can still block a write that passes
 * here — that is what the SELinux section checks, and it is the more common failure.
 */
function webCanWrite($path, $web_user, $web_group) {
    $st = @stat($path);
    if ($st === false) return false;

    $mode = $st['mode'];
    $ids  = webUserIds($web_user, $web_group);

    if ($ids['uid'] !== null && $st['uid'] === $ids['uid'] && ($mode & 0200)) return true; // owner
    if ($ids['gid'] !== null && $st['gid'] === $ids['gid'] && ($mode & 0020)) return true; // group
    if ($mode & 0002) return true;                                                         // world
    // Ids unresolvable (no posix, no id/getent): fall back rather than cry wolf.
    if ($ids['uid'] === null && $ids['gid'] === null) return is_writable($path);
    return false;
}

function toolExists($tool) {
    $output = [];
    $ret = 1;
    @exec("which " . escapeshellarg($tool) . " 2>/dev/null", $output, $ret);
    return $ret === 0;
}

// ── Load Configuration ──────────────────────────────────────────────────────

$base = __DIR__;

require_once "$base/lib/distro_detect.php";
$distro = detectDistroFamily();
$pkg = $distro['pkg_cmd'];
$family = $distro['family'];

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

// ── Which installation are we actually reporting on? ───────────────────────
//
// Nearly every check below resolves its target from $config, not from $base. If the
// config describes a DIFFERENT deployment, this script inspects THAT one and reports
// PASS for directories, keys and users files that do not exist in the tree it was run
// from. Observed 2026-07-22 from a fresh clone on a host with a live MOOP: 29 checks
// passed — organisms/, certs/, both JWT keys, users file, data/tracks/.htaccess — all
// belonging to the OTHER install. A preflight that green-lights the wrong deployment is
// worse than no preflight, so say plainly which one is being examined.
$sc_configured = rtrim($config['root_path'] ?? '', '/') . '/' . trim($config['site'] ?? '', '/');
$sc_conf_real  = realpath($sc_configured);
$sc_base_real  = realpath($base);
$sc_mismatch   = ($sc_conf_real === false || $sc_base_real === false || $sc_conf_real !== $sc_base_real);

echo "Inspecting install: " . ($sc_conf_real ?: $sc_configured) . "\n";
if ($sc_mismatch) {
    echo "\n" . C_RED . "WARNING: config points at a DIFFERENT directory than this script."
        . C_RESET . "\n";
    echo "  Script running in : " . ($sc_base_real ?: $base) . "\n";
    echo "  Config points at  : " . ($sc_conf_real ?: $sc_configured) . "\n";
    echo "  Results below describe the CONFIGURED install, not this tree — passes here do\n";
    echo "  NOT mean this copy is ready. config/site_config.php derives the location from\n";
    echo "  its own path, so check config/site_paths.php or MOOP_ROOT_PATH / MOOP_SITE.\n";
    // Counted as a failure so it cannot be lost in a wall of passes, and so the exit
    // status is non-zero for anything scripting this.
    $fails++;
}

// Merge the admin-editable overrides, exactly as ConfigManager does at runtime
// (includes/ConfigManager.php:112 — override only when set and non-empty, so an empty
// value falls back to the shipped default).
//
// Without this, the preflight validates the SHIPPED DEFAULTS rather than the live
// config, and reports on paths the site does not use. Observed 2026-07-16: it looked
// for site_data_path at /var/www/html/moop-site-data (the stale default) while the
// real, admin-configured directory is /var/www/moop-site-data — a confident FAIL about
// a directory that is not supposed to exist. CLAUDE.md's rule ("never read
// site_config.php directly") exists for exactly this reason.
$editable_file = "$base/config/config_editable.json";
if (is_readable($editable_file)) {
    $editable = json_decode((string) @file_get_contents($editable_file), true);
    if (is_array($editable)) {
        foreach ($editable as $key => $value) {
            if ($value === '' || $value === null) continue;
            if (in_array($key, ['sequence_types', 'jbrowse2'], true)
                && is_array($value) && is_array($config[$key] ?? null)) {
                foreach ($value as $sub_key => $sub_value) {
                    if ($key === 'sequence_types' && isset($config[$key][$sub_key]) && is_array($sub_value)) {
                        $config[$key][$sub_key] = array_merge($config[$key][$sub_key], $sub_value);
                    } else {
                        $config[$key][$sub_key] = $sub_value;
                    }
                }
            } else {
                $config[$key] = $value;
            }
        }
    }
}

$web = detectWebUser();
$web_user  = $web['user'];
$web_group = $web['group'];
echo "Web server user: $web_user:$web_group\n";
echo "Distro family: {$distro['family']} (package manager: {$distro['pkg_cmd']})\n";

// ── Section 1: PHP Environment ──────────────────────────────────────────────

section("PHP Environment");

// PHP version
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    pass("PHP " . PHP_VERSION . " (7.4+ required)");
} else {
    fail("PHP " . PHP_VERSION . " — version 7.4+ required",
         "sudo $pkg php");
}

// Required extensions
$required_extensions = [
    'sqlite3'  => "sudo $pkg " . distroPackage('php-sqlite3', 'php-pdo', $family),
    'json'     => $family === 'rhel'
                    ? '(bundled with php on RHEL 8+)'
                    : "sudo $pkg php-json",
    'openssl'  => "sudo $pkg " . distroPackage('php-xml', 'php-xml', $family) . ' (openssl is usually bundled)',
    'curl'     => "sudo $pkg php-curl",
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
         "Optional — install with: sudo $pkg " . distroPackage('php-process', 'php-posix', $family) .
         "\n         Used for accurate file ownership detection in permission management");
}

// ── Section 2: CLI Tools ────────────────────────────────────────────────────

section("CLI Tools");

// On RHEL, check for EPEL repository first — several tools require it
if ($family === 'rhel') {
    $epelOutput = [];
    $epelRet = 1;
    @exec("rpm -q epel-release 2>/dev/null", $epelOutput, $epelRet);
    if ($epelRet === 0) {
        pass("EPEL repository enabled");
    } else {
        // Detect major version for the correct EPEL URL
        $rhelVer = PHP_MAJOR_VERSION >= 8 ? '9' : '8';  // safe default
        if (file_exists('/etc/os-release')) {
            $osrel = file_get_contents('/etc/os-release');
            if (preg_match('/^VERSION_ID=["\']?(\d+)/m', $osrel, $vm)) {
                $rhelVer = $vm[1];
            }
        }
        fail("EPEL repository not enabled — required for BLAST+, samtools, and other tools",
             "sudo dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-$rhelVer.noarch.rpm");
    }
}

$blast_install_fix = $family === 'rhel'
    ? "BLAST+ is not in RHEL/EPEL repos. Install manually from NCBI:\n"
      . "         curl -O https://ftp.ncbi.nlm.nih.gov/blast/executables/blast+/LATEST/ncbi-blast-2.17.0+-x64-linux.tar.gz\n"
      . "         tar xzf ncbi-blast-*.tar.gz && sudo cp ncbi-blast-*/bin/* /usr/local/bin/"
    : "sudo $pkg ncbi-blast+";

$required_tools = [
    'blastn'          => $blast_install_fix,
    'blast_formatter' => $blast_install_fix,
    'blastdbcmd'      => $blast_install_fix,
    'samtools' => $family === 'rhel'
                    ? "Not in EPEL for RHEL 9. Install from source:\n"
                      . "         sudo dnf install -y gcc make zlib-devel bzip2-devel xz-devel curl-devel openssl-devel ncurses-devel\n"
                      . "         curl -LO https://github.com/samtools/samtools/releases/download/1.21/samtools-1.21.tar.bz2\n"
                      . "         tar xjf samtools-1.21.tar.bz2 && cd samtools-1.21 && ./configure && make && sudo make install"
                    : "sudo $pkg samtools",
    'tabix'    => $family === 'rhel'
                    ? "Not in EPEL for RHEL 9. Install htslib from source (provides tabix and bgzip):\n"
                      . "         curl -LO https://github.com/samtools/htslib/releases/download/1.21/htslib-1.21.tar.bz2\n"
                      . "         tar xjf htslib-1.21.tar.bz2 && cd htslib-1.21 && ./configure && make && sudo make install"
                    : "sudo $pkg tabix",
    'bgzip'    => $family === 'rhel'
                    ? "Installed with htslib (see tabix fix above)"
                    : "sudo $pkg tabix",
    'sqlite3'  => "sudo $pkg " . distroPackage('sqlite3', 'sqlite', $family),
];

foreach ($required_tools as $tool => $fix) {
    if (toolExists($tool)) {
        pass($tool);
    } else {
        fail("$tool not found in PATH", $fix);
    }
}

// Optional tools
if (toolExists('bigWigSummary')) {
    pass("bigWigSummary");
} else {
    warn("bigWigSummary not found in PATH",
         "Optional — required for Expression Explorer. Install with:\n"
         . "         sudo wget -q https://hgdownload.soe.ucsc.edu/admin/exe/linux.x86_64/bigWigSummary -O /usr/local/bin/bigWigSummary && sudo chmod +x /usr/local/bin/bigWigSummary");
}

if (toolExists('jq')) {
    pass("jq");
} else {
    warn("jq not found in PATH", "Optional — install with: sudo $pkg jq");
}

// jbrowse CLI — needed for text-index (gene name search) and sort-gff
$jbrowse_local = "$base/tools/jbrowse-cli/node_modules/.bin/jbrowse";
if (toolExists('jbrowse') || file_exists($jbrowse_local)) {
    pass("jbrowse CLI" . (file_exists($jbrowse_local) ? " (local install)" : ""));
} else {
    warn("jbrowse CLI not found",
         "Optional — required for JBrowse text-index (gene name search) and sort-gff.\n"
         . "         mkdir -p tools/jbrowse-cli && cd tools/jbrowse-cli && npm install @jbrowse/cli");
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

// ── Section 3b: Front-end vendor assets ─────────────────────────────────────
//
// These are committed, so this normally passes. It exists because it did NOT for a long time:
// js/vendor/ and css/fontawesome/ were gitignored with nothing to fetch them, so a fresh clone
// had no jQuery, Bootstrap, DataTables or icons — every page broken — and this script happily
// reported all-clear, because the check above only covers Composer's PHP packages. A missing
// file here is invisible server-side: PHP runs fine and the page 200s, it just cannot work in
// a browser.

section("Front-end Vendor Assets");

$vendor_assets = [
    'js/vendor/jquery.min.js',
    'js/vendor/jquery-ui.min.js',
    'js/vendor/bootstrap.bundle.min.js',
    'js/vendor/jquery.dataTables.min.js',
    'js/vendor/dataTables.bootstrap5.min.js',
    'js/vendor/dataTables.buttons.min.js',
    'js/vendor/buttons.bootstrap5.min.js',
    'js/vendor/buttons.html5.min.js',
    'js/vendor/buttons.print.min.js',
    'js/vendor/buttons.colVis.min.js',
    'js/vendor/dataTables.colReorder.min.js',
    'js/vendor/jszip.min.js',
    'css/bootstrap.min.css',
    'css/datatables/dataTables.bootstrap5.min.css',
    'css/datatables/buttons.bootstrap5.min.css',
    'css/datatables/colReorder.dataTables.min.css',
    'css/fontawesome/all.css',
    'css/fontawesome/webfonts/fa-solid-900.woff2',
    'css/fontawesome/webfonts/fa-regular-400.woff2',
    'css/fontawesome/webfonts/fa-brands-400.woff2',
];

$missing_assets = [];
foreach ($vendor_assets as $rel) {
    if (!file_exists("$base/$rel") || filesize("$base/$rel") === 0) {
        $missing_assets[] = $rel;
    }
}

if (empty($missing_assets)) {
    pass(count($vendor_assets) . " front-end vendor assets present");
} else {
    fail(count($missing_assets) . " front-end vendor asset(s) missing or empty — "
         . "pages will render but jQuery/Bootstrap/DataTables/icons will not load: "
         . implode(', ', array_slice($missing_assets, 0, 4))
         . (count($missing_assets) > 4 ? ' …' : ''),
         'scripts/fetch_vendor_assets.sh');
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

// Directories php-fpm must be able to WRITE. Mirrors the table in
// docs/SELINUX_AND_HARDENING.md §55 and RW_DIRS in scripts/fix_moop_selinux.sh — keep
// the three in step. Do NOT add a directory here just because it must exist: this list
// drives both a chmod 2775 recommendation and the SELinux label check below, so a wrong
// entry tells the admin to loosen something that should stay locked.
$writable_dirs = [
    'logs'                      => "$base/logs",
    'data/genomes'              => $config['jbrowse2']['genomes_directory'] ?? "$base/data/genomes",
    'images'                    => "$base/images",
    'metadata'                  => $config['metadata_path'] ?? "$base/metadata",
    'metadata/change_log'       => ($config['metadata_path'] ?? "$base/metadata") . '/change_log',
    'metadata/jbrowse2-configs' => ($config['metadata_path'] ?? "$base/metadata") . '/jbrowse2-configs',
    'config'                    => "$base/config",
    'organisms'                 => $config['organism_data'] ?? "$base/organisms",
    'archived_gene_sets'        => "$base/archived_gene_sets",
];
if (!empty($config['cache_path'])) {
    $writable_dirs['cache_path'] = $config['cache_path'];
}
if (!empty($config['site_data_path'])) {
    $writable_dirs['site_data_path'] = $config['site_data_path'];
}

// Directories that must EXIST but are read-only to the web server. Verified 2026-07-16:
// neither contains a single apache-owned file, the §55 writable table lists neither, and
// permission_check.php classifies them 'data' and 'secret'. They were previously in the
// writable list, which produced two bad recommendations — most alarmingly `chmod 2775`
// on the directory holding the JWT private key.
$readonly_dirs = [
    'data/tracks' => $config['jbrowse2']['tracks_directory'] ?? "$base/data/tracks",
    'certs'       => $config['jbrowse2']['certs_directory'] ?? "$base/certs",
];

// Normalize trailing slashes from config values
foreach ($writable_dirs as $label => &$path) {
    $path = rtrim($path, '/');
}
unset($path);
foreach ($readonly_dirs as $label => &$path) {
    $path = rtrim($path, '/');
}
unset($path);

foreach ($writable_dirs as $label => $path) {
    if (!is_dir($path)) {
        fail("$label/ does not exist ($path)",
             "mkdir -p " . escapeshellarg($path) .
             " && sudo chown $web_user:$web_group " . escapeshellarg($path) .
             " && sudo chmod 2775 " . escapeshellarg($path));
    } elseif (!webCanWrite($path, $web_user, $web_group)) {
        fail("$label/ exists but $web_user cannot write to it",
             "sudo chgrp $web_group " . escapeshellarg($path) .
             " && sudo chmod 2775 " . escapeshellarg($path));
    } else {
        pass("$label/ writable by $web_user");
    }
}

// Read-only directories: they must exist and be readable, but must NOT be loosened.
foreach ($readonly_dirs as $label => $path) {
    if (!is_dir($path)) {
        fail("$label/ does not exist ($path)", "mkdir -p " . escapeshellarg($path));
    } elseif (!is_readable($path)) {
        fail("$label/ exists but is not readable",
             "sudo chgrp $web_group " . escapeshellarg($path) . " && sudo chmod g+rX " . escapeshellarg($path));
    } else {
        pass("$label/ present (read-only — the web server only reads here)");
    }
}

// ── Section 5b: Data-tree Execution Guard ───────────────────────────────────
//
// Applies to EVERY install — any OS, any web server, SELinux or not. MOOP writes into
// directories that are also served over HTTP, which is safe only while the web server
// refuses to execute .php inside them. Without that, one file-write bug in the app
// becomes a persistent webshell.
//
// Do not assume nginx: MOOP supports Apache too (the README lists it first).

section("Data-tree Execution Guard");

$server_flavor = detectWebServer();

// [deployed path, canonical file in the repo, reload command]
$guard_targets = [
    'nginx'  => [
        ['/etc/nginx/default.d/moop-security.conf'],
        "$base/docs/nginx/moop-security.conf",
        'sudo nginx -t && sudo systemctl reload nginx',
    ],
    'apache' => [
        ['/etc/httpd/conf.d/moop-security.conf', '/etc/apache2/conf-available/moop-security.conf'],
        "$base/docs/apache/moop-security.conf",
        'sudo apachectl configtest && sudo systemctl reload httpd   # Debian: a2enconf moop-security && systemctl reload apache2',
    ],
];

if ($server_flavor === 'unknown') {
    warn("Could not detect the web server — cannot verify the execution guard",
         "Deny .php under organisms/, data/, images/, jbrowse2/ and archived_gene_sets/.\n" .
         "         References: docs/nginx/moop-security.conf, docs/apache/moop-security.conf");
} else {
    [$deploy_paths, $canonical, $reload] = $guard_targets[$server_flavor];
    $deployed = null;
    foreach ($deploy_paths as $p) {
        if (file_exists($p)) { $deployed = $p; break; }
    }
    $target = $deploy_paths[0];

    if (!file_exists($canonical)) {
        warn("No canonical guard shipped for $server_flavor ($canonical)");
    } elseif ($deployed === null) {
        fail("Execution guard is not deployed ($server_flavor)",
             "sudo cp $canonical $target && sudo chmod 644 $target\n" .
             "         $reload\n" .
             "         (Without it, an uploaded .php in a served data tree will execute.)");
    } elseif (md5_file($deployed) !== md5_file($canonical)) {
        warn("Deployed guard differs from " . str_replace("$base/", '', $canonical),
             "It may predate a rule that closes a hole. Re-deploy:\n" .
             "         sudo cp $canonical $deployed && $reload");
    } else {
        pass("Execution guard deployed and current ($server_flavor)");
    }

    if ($server_flavor === 'apache' && file_exists($canonical)) {
        warn("The Apache guard has not been verified on a live Apache host",
             "Confirm it actually blocks execution — a 404 on a missing path proves nothing:\n" .
             "         see the VERIFY block at the bottom of docs/apache/moop-security.conf");
    }
}

// ── Section 5c: SELinux ─────────────────────────────────────────────────────
//
// This is how an admin finds out fix_moop_selinux.sh exists. The admin dashboard
// points at it too, but that pointer is useless in the case that matters most: a
// hardening run has just relabelled the docroot, the site is throwing 500s, and you
// cannot load the dashboard to read its advice. This check runs from a shell, so it
// still works when the web server does not.
//
// It also corrects a false-green directly above: is_writable() sees only DAC. Run as
// the owner it returns true for a directory php-fpm CANNOT write, because on an
// enforcing host the SELinux label — not the Unix mode — is the real gate. Three MOOP
// features (banner upload, organism image upload, JBrowse text-index) died silently
// that way in July 2026 and nothing reported it for three days.

$selinux_enforcing = is_readable('/sys/fs/selinux/enforce')
    && trim((string) @file_get_contents('/sys/fs/selinux/enforce')) === '1';

if ($selinux_enforcing) {
    section("SELinux (Enforcing)");

    $selinux_type = function ($path) {
        $out = [];
        @exec('ls -dZ ' . escapeshellarg($path) . ' 2>/dev/null', $out);
        if (empty($out[0]) || !preg_match('/:([a-z_]+_t):/', $out[0], $m)) return null;
        return $m[1];
    };

    // Every directory the web server writes needs the read-write type. Report the bad
    // ones as ONE finding — a list of ten identical failures is noise, and the remedy
    // is a single command regardless of how many paths are wrong.
    $mislabelled = [];
    foreach ($writable_dirs as $label => $path) {
        if (!is_dir($path)) continue;
        $type = $selinux_type($path);
        if ($type !== null && $type !== 'httpd_sys_rw_content_t') {
            $mislabelled[] = "$label ($type)";
        }
    }

    if ($mislabelled) {
        fail("SELinux label blocks writes to: " . implode(', ', $mislabelled),
             "sudo $base/scripts/fix_moop_selinux.sh\n" .
             "         (These directories look writable to `ls` but php-fpm cannot write them.\n" .
             "          The label is the real gate — chmod will NOT fix this.)");
    } else {
        pass("Writable directories carry httpd_sys_rw_content_t");
    }

    if (toolExists('getsebool')) {
        $out = [];
        @exec('getsebool httpd_can_network_connect 2>/dev/null', $out);
        if (!empty($out[0]) && strpos($out[0], 'on') !== false) {
            pass("httpd_can_network_connect is on");
        } else {
            fail("httpd_can_network_connect is off — php-fpm cannot make outbound connections",
                 "sudo setsebool -P httpd_can_network_connect on\n" .
                 "         (Blocks the Google Sheets track sync with errors that look nothing\n" .
                 "          like a permissions problem.)");
        }
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

// Detect which web server is installed
$has_apache = toolExists('apache2') || toolExists('httpd') || toolExists('apachectl');
$has_nginx  = toolExists('nginx');

if (file_exists($htaccess)) {
    pass(".htaccess exists in data/tracks/");
} else {
    fail(".htaccess missing from data/tracks/ — track files are accessible without authentication",
         "See README.md Step 6 \"Set up the tracks security file\" for the required content");
}

if ($has_nginx && !$has_apache) {
    $site = $config['site'] ?? 'moop';
    warn("Nginx detected — .htaccess files have no effect on Nginx",
         "Add this to your Nginx server block to protect track files:\n" .
         "         location ~ ^/$site/data/tracks/ { deny all; return 403; }");
} elseif ($has_nginx && $has_apache) {
    $site = $config['site'] ?? 'moop';
    warn("Both Apache and Nginx detected — if using Nginx, .htaccess has no effect",
         "Add this to your Nginx server block to protect track files:\n" .
         "         location ~ ^/$site/data/tracks/ { deny all; return 403; }");
}

// ── Section 8: Users File ───────────────────────────────────────────────────

section("Users File");

$users_file = $config['users_file'] ?? "$base/../users.json";

if (file_exists($users_file)) {
    if (is_readable($users_file)) {
        pass("Users file: $users_file");
    } else {
        // File exists but current user can't read it — check if owned by web server user
        // (that's correct: www-data owns it with 600 so the web app can manage users)
        $file_owner = 'unknown';
        $file_uid = fileowner($users_file);
        if (function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid($file_uid);
            $file_owner = $pw['name'] ?? 'unknown';
        } else {
            // Fallback: match UID against known web server user
            $id_output = [];
            @exec("id -u " . escapeshellarg($web_user) . " 2>/dev/null", $id_output);
            if (!empty($id_output[0]) && (int)$id_output[0] === $file_uid) {
                $file_owner = $web_user;
            }
        }

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
    $site = $config['site'] ?? 'moop';
    echo C_GREEN . "All checks passed! MOOP is ready to run." . C_RESET . "\n\n";
    echo "Next steps:\n";
    echo "  1. Visit " . C_CYAN . "http://your-server-hostname/$site/" . C_RESET . " in your browser\n";
    echo "  2. Log in with your admin account\n";
    echo "  3. Go to Admin > Manage Site Configuration to customize your site\n\n";
    exit(0);
}
