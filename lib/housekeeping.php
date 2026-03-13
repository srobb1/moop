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
