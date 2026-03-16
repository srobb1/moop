<?php
/**
 * Housekeeping — lightweight maintenance tasks that run once per admin session.
 *
 * HOW IT WORKS:
 *   admin_init.php calls run_housekeeping() after auth succeeds.
 *   The function checks a session flag so it only runs once per session,
 *   then iterates through a list of small, fast tasks.
 *
 * ADDING A NEW TASK:
 *   1. Write a function below (keep it fast — no network calls, no heavy I/O).
 *   2. Add it to the $tasks array in run_housekeeping().
 *   That's it. It will run automatically on the next admin login.
 */

/**
 * Run all housekeeping tasks (once per session).
 */
function run_housekeeping() {
    if (!empty($_SESSION['housekeeping_done'])) {
        return;
    }
    $_SESSION['housekeeping_done'] = true;

    // ── Task registry ────────────────────────────────────────
    // Each entry: ['name' => string, 'fn' => callable]
    // Tasks should be fast and safe to skip on failure.
    $tasks = [
        ['name' => 'clean_temp_files',    'fn' => 'housekeeping_clean_temp_files'],
        ['name' => 'snapshot_site_data',  'fn' => 'housekeeping_snapshot_site_data'],
        ['name' => 'environment_check',   'fn' => 'housekeeping_environment_check'],
    ];

    foreach ($tasks as $task) {
        try {
            call_user_func($task['fn']);
        } catch (\Throwable $e) {
            error_log('MOOP housekeeping task "' . $task['name'] . '" failed: ' . $e->getMessage());
        }
    }
}

// =====================================================================
// Individual tasks
// =====================================================================

/**
 * Delete stale temp files created by BLAST, MAFFT, and other tools.
 *
 * Targets files older than 24 hours with known prefixes in the system
 * temp directory. These accumulate when PHP processes crash mid-run.
 */
function housekeeping_clean_temp_files() {
    $tmp_dir = sys_get_temp_dir();
    $max_age = 86400; // 24 hours in seconds
    $now     = time();
    $deleted = 0;

    // Prefixes used by tempnam() calls throughout the codebase
    $prefixes = [
        'blast_',         // blast_functions.php — archive output
        'blast_xml_',     // blast_functions.php — XML conversion
        'blast_pairwise_',// blast_functions.php — pairwise conversion
        'blastdb_',       // blast_functions.php — batch sequence extraction
        'mafft_',         // galaxy/mafft.php    — MAFFT alignment input
        'galaxy_seqs_',   // galaxy testing
    ];

    foreach ($prefixes as $prefix) {
        $pattern = $tmp_dir . '/' . $prefix . '*';
        $files = glob($pattern);
        if (!is_array($files)) {
            continue;
        }
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $age = $now - filemtime($file);
            if ($age > $max_age) {
                @unlink($file);
                $deleted++;
            }
        }
    }

    if ($deleted > 0) {
        error_log("MOOP housekeeping: cleaned up $deleted stale temp file(s)");
    }
}

/**
 * Snapshot site-specific files to the site-data backup directory.
 *
 * Copies config, metadata, and user files to a separate git repo for
 * version history. If the directory doesn't exist yet, this task is
 * silently skipped — the admin dashboard will show a setup prompt.
 *
 * On first successful run, creates a README explaining the repo.
 * On each run, copies files and auto-commits if anything changed.
 */
function housekeeping_snapshot_site_data() {
    $config = ConfigManager::getInstance();
    $site_data_path = $config->getPath('site_data_path');

    // Disabled or not configured
    if (empty($site_data_path)) {
        return;
    }

    // Directory doesn't exist yet — skip silently, admin dashboard will prompt
    if (!is_dir($site_data_path)) {
        return;
    }

    // Not a git repo yet — skip silently
    if (!is_dir($site_data_path . '/.git')) {
        return;
    }

    $site_path = $config->getPath('site_path');
    $users_file = $config->getPath('users_file');

    // Files to snapshot: [source_path => destination_relative_path]
    $files = [
        $site_path . '/config/config_editable.json' => 'config/config_editable.json',
        $site_path . '/config/secrets.php'           => 'config/secrets.php',
        $site_path . '/metadata/annotation_config.json'        => 'metadata/annotation_config.json',
        $site_path . '/metadata/group_descriptions.json'       => 'metadata/group_descriptions.json',
        $site_path . '/metadata/organism_assembly_groups.json'  => 'metadata/organism_assembly_groups.json',
        $site_path . '/metadata/taxonomy_tree_config.json'      => 'metadata/taxonomy_tree_config.json',
        $users_file                                             => 'users.json',
    ];

    // Create README on first run
    $readme_path = $site_data_path . '/README.md';
    if (!file_exists($readme_path)) {
        $readme = <<<'README'
# MOOP Site Data Backup

This repository is automatically maintained by the MOOP housekeeping system.
It snapshots site-specific configuration and metadata on each admin login,
giving you version history of changes made through the admin UI.

**KEEP THIS REPO PRIVATE** — it contains user accounts, API keys, and
access control configuration.

## Files tracked

| File | Purpose |
|------|---------|
| `config/config_editable.json` | Admin-edited site settings (title, branding, etc.) |
| `config/secrets.php` | API keys and credentials |
| `metadata/annotation_config.json` | Annotation display configuration |
| `metadata/group_descriptions.json` | Organism group definitions |
| `metadata/organism_assembly_groups.json` | Which organisms belong to which groups |
| `metadata/taxonomy_tree_config.json` | Taxonomy tree structure |
| `users.json` | User accounts and access levels |

## What is NOT tracked here

- Genome sequences (`.fa`, `.fasta`) — too large for git
- SQLite databases (`.sqlite`) — regenerated from source data
- BLAST indexes — regenerated via admin panel
- JBrowse2 track data — managed separately
- Log files — ephemeral

## How it works

The MOOP housekeeping system (`lib/housekeeping.php`) runs once per admin
session. It copies the files listed above into this directory and commits
any changes automatically. The commit message includes the admin username
and a timestamp.

To restore from a previous version:
```bash
git log --oneline                    # find the commit
git show <commit>:config/config_editable.json  # view old version
git checkout <commit> -- <file>      # restore a specific file
```
README;
        @file_put_contents($readme_path, $readme);
    }

    // Copy files
    $changed = false;
    foreach ($files as $source => $dest_relative) {
        if (!file_exists($source)) {
            continue;
        }

        $dest = $site_data_path . '/' . $dest_relative;
        $dest_dir = dirname($dest);

        // Create subdirectory if needed
        if (!is_dir($dest_dir)) {
            @mkdir($dest_dir, 0750, true);
        }

        // Only copy if content differs
        $source_content = @file_get_contents($source);
        $dest_content = @file_get_contents($dest);
        if ($source_content !== false && $source_content !== $dest_content) {
            @file_put_contents($dest, $source_content);
            $changed = true;
        }
    }

    if (!$changed) {
        return;
    }

    // Auto-commit changes
    $username = $_SESSION['username'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    $message = "Auto-snapshot by $username at $timestamp";

    $cwd = getcwd();
    chdir($site_data_path);
    exec('git add -A 2>&1');
    exec('git diff --cached --quiet 2>&1', $output, $has_staged_changes);
    if ($has_staged_changes !== 0) {
        exec('git commit -m ' . escapeshellarg($message) . ' 2>&1');
        error_log("MOOP housekeeping: site data snapshot committed ($message)");
    }
    chdir($cwd);
}

/**
 * Check environment health and store warnings in session.
 *
 * Detects degraded requirements that could cause silent failures:
 * missing PHP extensions, missing JWT keys, unwritable directories,
 * missing CLI tools, missing composer dependencies.
 *
 * Results are stored in $_SESSION['env_warnings'] as an array of
 * ['level' => 'danger'|'warning', 'message' => string] entries.
 * The admin dashboard reads this to display alerts.
 */
function housekeeping_environment_check() {
    $warnings = [];
    $config = ConfigManager::getInstance();
    $site_path = $config->getPath('site_path');

    // 1. Required PHP extensions
    $required_extensions = [
        'pdo_sqlite' => 'SQLite database access',
        'openssl'    => 'JWT key generation and verification',
        'mbstring'   => 'Multi-byte string handling',
        'curl'       => 'External API calls (Wikipedia, Galaxy)',
    ];
    foreach ($required_extensions as $ext => $purpose) {
        if (!extension_loaded($ext)) {
            $warnings[] = [
                'level' => 'danger',
                'message' => "PHP extension <code>$ext</code> is not loaded ($purpose). Install it: <code>sudo apt install php-$ext && sudo systemctl restart apache2</code>",
            ];
        }
    }

    // 2. JWT keys exist and readable
    $certs_dir = $site_path . '/certs';
    $private_key = $certs_dir . '/jwt_private.pem';
    $public_key = $certs_dir . '/jwt_public.pem';
    if (!file_exists($private_key) || !file_exists($public_key)) {
        $warnings[] = [
            'level' => 'danger',
            'message' => 'JWT keys missing in <code>certs/</code> — JBrowse2 track authentication will not work. Generate with: <code>openssl genrsa -out certs/jwt_private.pem 2048 && openssl rsa -in certs/jwt_private.pem -pubout -out certs/jwt_public.pem</code>',
        ];
    } elseif (!is_readable($private_key) || !is_readable($public_key)) {
        $warnings[] = [
            'level' => 'warning',
            'message' => 'JWT keys in <code>certs/</code> are not readable by the web server — JBrowse2 track authentication will fail. Check file permissions.',
        ];
    }

    // 3. Critical directories writable
    $writable_dirs = [
        'logs'     => $site_path . '/logs',
        'metadata' => $config->getPath('metadata_path'),
        'config'   => $site_path . '/config',
    ];
    foreach ($writable_dirs as $label => $dir) {
        if (is_dir($dir) && !is_writable($dir)) {
            $warnings[] = [
                'level' => 'warning',
                'message' => "Directory <code>$label/</code> is not writable — admin changes may not save. See <a href=\"manage_filesystem_permissions.php\">Filesystem Permissions</a>.",
            ];
        }
    }

    // 4. CLI tools available
    $cli_tools = [
        'blastn'     => 'BLAST searches will not work',
        'samtools'   => 'Sequence retrieval will not work',
        'makeblastdb'=> 'BLAST index building will not work',
    ];
    foreach ($cli_tools as $tool => $impact) {
        $path = trim(shell_exec("which $tool 2>/dev/null") ?? '');
        if (empty($path)) {
            $warnings[] = [
                'level' => 'warning',
                'message' => "CLI tool <code>$tool</code> not found in PATH — $impact.",
            ];
        }
    }

    // 5. Composer dependencies installed
    if (!is_dir($site_path . '/vendor')) {
        $warnings[] = [
            'level' => 'danger',
            'message' => 'Composer dependencies not installed (<code>vendor/</code> missing). Run: <code>cd ' . htmlspecialchars($site_path) . ' && composer install --no-dev</code>',
        ];
    }

    // 6. .htaccess for track protection
    $tracks_htaccess = $site_path . '/data/tracks/.htaccess';
    if (is_dir($site_path . '/data/tracks') && !file_exists($tracks_htaccess)) {
        $warnings[] = [
            'level' => 'danger',
            'message' => 'Missing <code>data/tracks/.htaccess</code> — track files may be accessible without authentication.',
        ];
    }

    $_SESSION['env_warnings'] = $warnings;
}
