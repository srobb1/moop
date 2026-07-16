<?php
/**
 * Housekeeping — lightweight maintenance tasks that run at most once per interval.
 *
 * HOW IT WORKS:
 *   admin_init.php calls run_housekeeping() after auth succeeds.
 *   Gating is a disk-based timestamp marker (logs/.housekeeping_last_run), NOT a
 *   PHP session flag. Session lifetime here is unreliable as a "run once" signal:
 *   PHP session GC is probabilistic (session.gc_probability/gc_divisor = 1/1000 by
 *   default) and cookie_lifetime=0 relies on the browser actually closing, so an
 *   admin's session can silently outlive the intended housekeeping interval by
 *   days — during which nothing here would ever run again. The marker file is
 *   shared across all admins/sessions and just asks "has it been long enough
 *   since the last run?", independent of any one login's lifetime.
 *
 * ADDING A NEW TASK:
 *   1. Write a function below (keep it fast — no blocking network calls, no heavy I/O).
 *      If a periodic network operation is needed, launch it as a background process
 *      (see housekeeping_check_ncbi_taxonomy_update for the pattern).
 *   2. Add it to the $tasks array in run_housekeeping().
 *   That's it. It will run automatically the next time any admin loads a page,
 *   at most once per HOUSEKEEPING_MIN_INTERVAL.
 */

define('HOUSEKEEPING_MIN_INTERVAL', 4 * 3600); // re-run at most every 4 hours

// environment_check and permission_check call getWebServerUser(). That function lives in
// functions_system.php, which nothing here loaded — it happened to be in scope only
// because admin_init.php includes moop_functions.php (which requires it) a few lines
// earlier. Depend on it explicitly: if that ordering ever changed, both tasks would throw,
// the catch below would swallow it into the log, and the dashboard cards would simply
// VANISH — identical to "everything is clean". That is the silent-failure shape this file
// is supposed to detect, not exhibit.
require_once __DIR__ . '/functions_system.php';

/**
 * Two tasks below (site data snapshot, environment check) drive dashboard widgets
 * that used to live only in $_SESSION. Now that tasks run at most once per interval
 * instead of once per session, most sessions would never be the one that happens to
 * trigger a run — their $_SESSION would just never get those keys set, and the
 * widgets would silently vanish. So those tasks also persist their result to this
 * small status file, and every request (cheap: one file read, no throttle) hydrates
 * its own $_SESSION from it — independent of whether this request is the one
 * actually running the underlying task.
 */
function housekeeping_status_file(): string {
    $config = ConfigManager::getInstance();
    return $config->getPath('site_path') . '/logs/.housekeeping_status.json';
}

function housekeeping_persist_status(string $key, $value): void {
    $file = housekeeping_status_file();
    $all  = loadJsonFile($file, []);
    $all[$key] = $value;
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT));
}

function housekeeping_hydrate_session_from_status(): void {
    $file = housekeeping_status_file();
    if (!file_exists($file)) return;
    $all = loadJsonFile($file, []);
    if (isset($all['site_data_backup'])) $_SESSION['site_data_backup'] = $all['site_data_backup'];
    if (isset($all['env_warnings']))     $_SESSION['env_warnings']     = $all['env_warnings'];
    if (isset($all['perm_summary']))     $_SESSION['perm_summary']     = $all['perm_summary'];
}

/**
 * The housekeeping task registry — the ONE list, used both to RUN the tasks and to
 * DESCRIBE them on the admin dashboard.
 *
 * Descriptions live here, beside the callable, on purpose. A separate list of
 * descriptions maintained next to the runner is the two-sources-of-truth shape that has
 * repeatedly bitten this codebase (CLAUDE.md §10): the two drift, and the copy nobody
 * runs is the one that lies. Add a task here and it documents itself.
 *
 * 'desc' is ADMIN-FACING UI text: say what the task does, in terms of things the admin
 * can see or act on. Rationale, history, and "why this exists" belong in the individual
 * task docblocks below, not here — the reader of this string is looking at a dashboard,
 * not the source.
 *
 * @return list<array{name:string, fn:string, label:string, desc:string}>
 */
function housekeeping_task_registry(): array {
    return [
        [
            'name'  => 'clean_temp_files',
            'fn'    => 'housekeeping_clean_temp_files',
            'label' => 'Clean temp files',
            'desc'  => 'Deletes BLAST and MAFFT scratch files older than 24 hours from the system temp directory.',
        ],
        [
            'name'  => 'ensure_cache_dir',
            'fn'    => 'housekeeping_ensure_cache_dir',
            'label' => 'Ensure cache directory',
            'desc'  => 'Creates the cache directory if it is missing and repairs its ownership and permissions.',
        ],
        [
            'name'  => 'snapshot_site_data',
            'fn'    => 'housekeeping_snapshot_site_data',
            'label' => 'Snapshot site data',
            'desc'  => 'Copies changed site data (config_editable.json, secrets.php, metadata, organism.json files, users.json) to the backup directory. It does NOT commit: if that directory is a git repo, MOOP only reads its state for the dashboard badge — committing and pushing stay manual, by design.',
        ],
        [
            'name'  => 'environment_check',
            'fn'    => 'housekeeping_environment_check',
            'label' => 'Environment check',
            'desc'  => 'Detects degraded requirements that cause silent failures: missing PHP extensions, missing JWT keys, unwritable directories, absent CLI tools (blastn, samtools, makeblastdb).',
        ],
        [
            'name'  => 'permission_check',
            'fn'    => 'housekeeping_permission_check',
            'label' => 'Filesystem permission check',
            'desc'  => 'Sweeps the filesystem for permission and SELinux problems by impact, storing only the counts. This is the slow one (~4s of the ~4.5s total) because it walks the whole organism tree — which is precisely why it is cached rather than run on every dashboard load.',
        ],
        [
            'name'  => 'refresh_annotation_caches',
            'fn'    => 'housekeeping_refresh_annotation_caches',
            'label' => 'Refresh annotation caches',
            'desc'  => "Rebuilds each organism's annotation_sources_cache.json when it is missing or older than that organism's SQLite database.",
        ],
        [
            'name'  => 'refresh_organism_cache',
            'fn'    => 'housekeeping_refresh_organism_cache_if_stale',
            'label' => 'Refresh organism cache',
            'desc'  => 'Rebuilds the organism cache in the background when the data it was built from has actually changed — it compares per-organism and config fingerprints rather than watching a clock, so an unchanged site costs nothing. Without it, the drift checks that read this cache would keep reporting a clean bill of health that is days old.',
        ],
        [
            'name'  => 'ncbi_taxonomy_update',
            'fn'    => 'housekeeping_check_ncbi_taxonomy_update',
            'label' => 'NCBI taxonomy update',
            'desc'  => 'Syncs the local NCBI taxonomy dump if it is more than 30 days old. Reads a local timestamp first, so no network call happens unless an update is actually due.',
        ],
    ];
}

/**
 * Run all housekeeping tasks (at most once per HOUSEKEEPING_MIN_INTERVAL).
 *
 * @param bool $force Skip the interval throttle and run now. Used by the admin
 *                    dashboard's "Run now" button, for when you have just fixed
 *                    something and do not want to wait up to 4h to see the card
 *                    clear. The LOCK is still honoured even when forcing — a forced
 *                    run must not stampede a run already in flight.
 * @return array{ran:bool, reason:?string, tasks:list<array{name:string,ok:bool,ms:int,error:?string}>}
 *         Per-task results so callers can report what actually happened rather than
 *         just spinning. Existing callers may ignore the return value.
 */
function run_housekeeping(bool $force = false): array {
    $result = ['ran' => false, 'reason' => null, 'tasks' => []];

    $config      = ConfigManager::getInstance();
    $logs_dir    = $config->getPath('site_path') . '/logs';
    $marker_file = "$logs_dir/.housekeeping_last_run";
    $lock_file   = "$logs_dir/.housekeeping_lock";

    // Cheap, unthrottled: every request's session gets the latest known status,
    // regardless of whether the tasks below actually run on this tick.
    housekeeping_hydrate_session_from_status();

    $last_run = @filemtime($marker_file) ?: 0;
    if (!$force && (time() - $last_run) < HOUSEKEEPING_MIN_INTERVAL) {
        $result['reason'] = 'throttled';
        return $result;
    }

    // Claim the run so concurrent admin requests don't all fire it at once.
    if (file_exists($lock_file)) {
        $pid = (int)trim(@file_get_contents($lock_file));
        if ($pid > 0 && file_exists("/proc/$pid")) {
            $result['reason'] = 'already_running';
            return $result; // another request is already running housekeeping
        }
        // stale lock left by a crashed request — reclaim it
    }
    if (!is_dir($logs_dir)) @mkdir($logs_dir, 0755, true);
    @file_put_contents($lock_file, (string)getmypid());
    // Touch the marker before running tasks so a slow task can't cause a pile-up
    // of concurrent housekeeping runs triggered by other requests mid-flight.
    @touch($marker_file);

    $tasks = housekeeping_task_registry();

    foreach ($tasks as $task) {
        $t0 = microtime(true);
        try {
            call_user_func($task['fn']);
            $result['tasks'][] = [
                'name'  => $task['name'],
                'ok'    => true,
                'ms'    => (int) round((microtime(true) - $t0) * 1000),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            error_log('MOOP housekeeping task "' . $task['name'] . '" failed: ' . $e->getMessage());
            $result['tasks'][] = [
                'name'  => $task['name'],
                'ok'    => false,
                'ms'    => (int) round((microtime(true) - $t0) * 1000),
                'error' => $e->getMessage(),
            ];
        }
    }

    @unlink($lock_file);
    $result['ran'] = true;
    return $result;
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
 * Copies config, metadata, and user files to a backup directory.
 * Auto-creates the directory if it doesn't exist. Git is NOT required —
 * plain file copies are the baseline, and they are the whole mechanism.
 *
 * This function does NOT commit anything. If the directory happens to be a git repo,
 * MOOP only READS its state (housekeeping_git_status()) to show a badge on the
 * dashboard; committing and pushing stay manual, deliberately, so the admin controls
 * when credentials-bearing snapshots get versioned. The README this writes into the
 * backup directory tells them exactly that.
 *
 * Status is stored in $_SESSION['site_data_backup'] for the dashboard, and persisted
 * to logs/.housekeeping_status.json so every session can be hydrated with it — see
 * housekeeping_hydrate_session_from_status().
 */
/**
 * Ensure the configured cache directory exists.
 *
 * When 'cache_path' points outside the organisms/ tree, create it once here so the
 * first request doesn't pay the mkdir and so a broken configuration surfaces as a
 * logged error rather than silently disabling caching. When 'cache_path' is empty
 * the caches live in the organisms/ tree (which already exists) — nothing to do.
 */
function housekeeping_ensure_cache_dir() {
    $config     = ConfigManager::getInstance();
    $cache_path = $config->getPath('cache_path');

    if (empty($cache_path)) {
        return; // legacy in-tree caching; no dedicated directory to create
    }

    if (!is_dir($cache_path) && !@mkdir($cache_path, 0775, true) && !is_dir($cache_path)) {
        error_log("MOOP housekeeping: could not create cache dir: $cache_path");
    }
}

/**
 * Summarise the git state of the site-data backup repo for the dashboard.
 *
 * All commands are local and read-only (no fetch/push) so this stays fast and needs
 * no network. 'ahead' counts commits not yet pushed to the tracked upstream, using the
 * last-known upstream ref — it does not fetch, so it reflects state as of the last pull/push.
 *
 * @return array{uncommitted:int, has_upstream:bool, ahead:int, commits:int, last_commit:string, clean:bool}
 */
function housekeeping_git_status(string $dir): array {
    $run = function (string $args) use ($dir): string {
        $out = @shell_exec('git -C ' . escapeshellarg($dir) . ' ' . $args . ' 2>/dev/null');
        return $out === null ? '' : trim($out);
    };
    $porcelain     = $run('status --porcelain');
    $uncommitted   = $porcelain === '' ? 0 : count(explode("\n", $porcelain));
    $has_upstream  = $run('rev-parse --abbrev-ref --symbolic-full-name "@{u}"') !== '';
    $ahead         = $has_upstream ? (int) $run('rev-list --count "@{u}"..HEAD') : -1;
    return [
        'uncommitted'  => $uncommitted,
        'has_upstream' => $has_upstream,
        'ahead'        => $ahead,
        'commits'      => (int) $run('rev-list --count HEAD'),
        'last_commit'  => $run('log -1 --format=%cr') ?: 'no commits yet',
        'clean'        => $uncommitted === 0 && $ahead <= 0,
    ];
}

function housekeeping_snapshot_site_data() {
    $config = ConfigManager::getInstance();
    $site_data_path = $config->getPath('site_data_path');

    // Not configured — nothing to do
    if (empty($site_data_path)) {
        return;
    }

    // Auto-create directory if missing
    if (!is_dir($site_data_path)) {
        if (!@mkdir($site_data_path, 0750, true)) {
            error_log("MOOP housekeeping: could not create site data dir: $site_data_path");
            $status = [
                'status' => 'error',
                'message' => "Could not create directory <code>" . htmlspecialchars($site_data_path) . "</code> — check permissions on the parent directory.",
                'path' => $site_data_path,
            ];
            $_SESSION['site_data_backup'] = $status;
            housekeeping_persist_status('site_data_backup', $status);
            return;
        }
        error_log("MOOP housekeeping: created site data backup directory: $site_data_path");
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

    // Dynamically add all organism.json files from the organisms directory
    $organisms_dir = $config->getPath('organism_data');
    if (!empty($organisms_dir) && is_dir($organisms_dir)) {
        foreach (glob($organisms_dir . '/*/organism.json') as $organism_json) {
            $organism_name = basename(dirname($organism_json));
            $files[$organism_json] = 'organisms/' . $organism_name . '/organism.json';
        }
    }

    // Create README on first run
    $readme_path = $site_data_path . '/README.md';
    if (!file_exists($readme_path)) {
        $readme = <<<'README'
# MOOP Site Data Backup

This directory is automatically maintained by the MOOP housekeeping system.
It snapshots site-specific configuration and metadata on each admin login,
keeping a copy of your settings separate from the application code.

**KEEP THIS DIRECTORY PRIVATE** — it contains user accounts, API keys, and
access control configuration.

## Files backed up

| File | Purpose |
|------|---------|
| `config/config_editable.json` | Admin-edited site settings (title, branding, etc.) |
| `config/secrets.php` | API keys and credentials |
| `metadata/annotation_config.json` | Annotation display configuration |
| `metadata/group_descriptions.json` | Organism group definitions |
| `metadata/organism_assembly_groups.json` | Which organisms belong to which groups |
| `metadata/taxonomy_tree_config.json` | Taxonomy tree structure |
| `users.json` | User accounts and access levels |
| `organisms/{name}/organism.json` | Per-organism metadata (one file per organism) |

## What is NOT backed up here

- Genome sequences (`.fa`, `.fasta`) — too large
- SQLite databases (`.sqlite`) — regenerated from source data
- BLAST indexes — regenerated via admin panel
- JBrowse2 track data — managed separately
- Log files — ephemeral

## Optional: Git version history

If you initialize this directory as a git repo, MOOP will detect it and show
a "Git available" badge on the Admin Dashboard. Run git commands manually to
commit and push changes:

    cd /path/to/this/directory
    git init -b main
    git add -A && git commit -m "Initial snapshot"

After each admin login MOOP copies changed files here. Run the commands above
(or a push) whenever you want to version the snapshot.
README;
        @file_put_contents($readme_path, $readme);
    }

    // Copy files
    $changed = false;
    $copied_count = 0;
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
            $copied_count++;
        }
    }

    $is_git = is_dir($site_data_path . '/.git');

    if ($changed) {
        error_log("MOOP housekeeping: backed up $copied_count file(s) to $site_data_path");
    }

    // Store status for the dashboard
    $status = [
        'status' => 'ok',
        'is_git' => $is_git,
        'git' => $is_git ? housekeeping_git_status($site_data_path) : null,
        'last_run' => date('Y-m-d H:i:s'),
        'files_copied' => $copied_count,
        'path' => $site_data_path,
    ];
    $_SESSION['site_data_backup'] = $status;
    housekeeping_persist_status('site_data_backup', $status);
}

/**
 * Check environment health and store warnings in session.
 *
 * Detects degraded requirements that could cause silent failures:
 * missing PHP extensions, missing JWT keys, unwritable directories,
 * missing CLI tools, missing composer dependencies.
 *
 * Results are stored in $_SESSION['env_warnings'] as an array of
 * ['level' => 'danger'|'warning', 'message' => string] entries, and persisted to
 * logs/.housekeeping_status.json so every session can be hydrated with it — see
 * housekeeping_hydrate_session_from_status().
 * The admin dashboard reads this to display alerts.
 */
function housekeeping_environment_check() {
    $warnings = [];
    $config = ConfigManager::getInstance();
    $site_path = $config->getPath('site_path');

    // Detect distro for fix commands
    $distro_helper = $site_path . '/lib/distro_detect.php';
    if (file_exists($distro_helper)) {
        require_once $distro_helper;
        $distro = detectDistroFamily();
        $pkg = $distro['pkg_cmd'];
        $restart_cmd = ($distro['family'] === 'rhel') ? 'sudo systemctl restart httpd' : 'sudo systemctl restart apache2';
    } else {
        $pkg = 'apt-get install -y';
        $restart_cmd = 'sudo systemctl restart apache2';
    }

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
                'message' => "PHP extension <code>$ext</code> is not loaded ($purpose). Install it: <code>sudo $pkg php-$ext && $restart_cmd</code>",
            ];
        }
    }

    // 2. JWT keys exist and readable (use config paths, fall back to defaults)
    $private_key = $config->getPath('jbrowse2.jwt_private_key') ?: $site_path . '/certs/jwt_private_key.pem';
    $public_key  = $config->getPath('jbrowse2.jwt_public_key') ?: $site_path . '/certs/jwt_public_key.pem';
    $priv_basename = basename($private_key);
    $pub_basename  = basename($public_key);
    if (!file_exists($private_key) || !file_exists($public_key)) {
        $warnings[] = [
            'level' => 'danger',
            'message' => "JWT keys missing in <code>certs/</code> — JBrowse2 track authentication will not work. Generate with: <code>openssl genrsa -out certs/$priv_basename 2048 && openssl rsa -in certs/$priv_basename -pubout -out certs/$pub_basename</code>",
        ];
    } elseif (!is_readable($private_key) || !is_readable($public_key)) {
        $warnings[] = [
            'level' => 'warning',
            'message' => 'JWT keys in <code>certs/</code> are not readable by the web server — JBrowse2 track authentication will fail. Fix with: <code>sudo chgrp ' . htmlspecialchars(getWebServerUser()['group']) . ' certs/*.pem && sudo chmod 640 certs/*.pem</code>',
        ];
    }

    // 3. Critical directories writable
    $writable_dirs = [
        'logs'     => $site_path . '/logs',
        'metadata' => $config->getPath('metadata_path'),
        'config'   => $site_path . '/config',
    ];
    $site_data_path = $config->getPath('site_data_path');
    if (!empty($site_data_path)) {
        $writable_dirs['site data backup'] = $site_data_path;
    }
    $web_info = getWebServerUser();
    foreach ($writable_dirs as $label => $dir) {
        if (is_dir($dir) && !is_writable($dir)) {
            $fix_cmd = "sudo chgrp " . $web_info['group'] . " " . htmlspecialchars($dir) . " && sudo chmod 2775 " . htmlspecialchars($dir);
            $warnings[] = [
                'level' => 'warning',
                'message' => "Directory <code>$label/</code> is not writable — admin changes may not save. Fix with: <code>$fix_cmd</code>",
            ];
        }
    }

    // 4. CLI tools available
    // PHP-FPM may have a restricted PATH, so also check /usr/local/bin explicitly
    $cli_tools = [
        'blastn'          => 'BLAST searches will not work',
        'blast_formatter' => 'BLAST result display and downloads will not work',
        'blastdbcmd'      => 'BLAST sequence retrieval will not work',
        'samtools'        => 'BAM/CRAM tracks and FAI indexing will not work',
        'makeblastdb'     => 'BLAST index building will not work',
    ];
    foreach ($cli_tools as $tool => $impact) {
        $path = trim(shell_exec("which " . escapeshellarg($tool) . " 2>/dev/null") ?? '');
        if (empty($path) && file_exists("/usr/local/bin/$tool")) {
            $path = "/usr/local/bin/$tool";
        }
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
    housekeeping_persist_status('env_warnings', $warnings);
}

/**
 * Aggregate the filesystem-permission checks into a small severity summary for the
 * dashboard pointer card (PAGE_BY_PAGE_AUDIT_PLAN §N).
 *
 * The underlying scan stat()s the whole organism tree, so it must NOT run on every
 * dashboard load — this runs it at most once per HOUSEKEEPING_MIN_INTERVAL and stores
 * only the counts. The dashboard reads the cached summary (hydrated into $_SESSION)
 * and links to the full Filesystem Permissions page for detail. Uses the SAME shared
 * collector the detail page uses, so the numbers always agree.
 *
 * Persisted to logs/.housekeeping_status.json (next to env_warnings / site_data_backup),
 * NOT to cache_path — this is dashboard status, not a regenerable data cache.
 *
 * NOTE (§O): as of Phase 1 organisms/ is writable again, so a plain permission check
 * is accurate. If Phase 2 re-tightens organisms/ to read-only for httpd_t, revisit so
 * this doesn't false-alarm on an intentionally read-only tree.
 */
function housekeeping_permission_check() {
    $config = ConfigManager::getInstance();
    require_once $config->getPath('site_path') . '/lib/permission_check.php';

    $summary = moop_permission_issue_summary($config);

    $_SESSION['perm_summary'] = $summary;
    housekeeping_persist_status('perm_summary', $summary);
}

/**
 * Refresh per-organism annotation source caches.
 *
 * For each organism that has a SQLite database, checks whether
 * annotation_sources_cache.json is missing or older than organism.sqlite.
 * Regenerates only the stale entries so get_annotation_sources_grouped.php
 * can serve the advanced search filter modal from flat files instead of
 * running COUNT aggregate queries against every database on each open.
 *
 * In steady state (no databases rebuilt) this is a no-op — just stat() calls.
 */
function housekeeping_refresh_annotation_caches() {
    $config = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');

    if (empty($organism_data) || !is_dir($organism_data)) {
        return;
    }

    // database_queries.php is not loaded by admin_init — include lazily
    $db_queries = dirname(__DIR__) . '/lib/database_queries.php';
    if (file_exists($db_queries)) {
        include_once $db_queries;
    }
    if (!function_exists('getAnnotationSourcesByType')) {
        return;
    }

    $refreshed = 0;
    $entries = scandir($organism_data);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $db         = "$organism_data/$entry/organism.sqlite";
        $cache_file = moop_annotation_sources_cache_file($entry);

        if (!file_exists($db)) {
            continue;
        }

        // Skip if cache is current
        if (file_exists($cache_file) && filemtime($cache_file) >= filemtime($db)) {
            continue;
        }

        $source_types = getAnnotationSourcesByType($db);
        file_put_contents($cache_file, json_encode($source_types));
        $refreshed++;
    }

    if ($refreshed > 0) {
        error_log("MOOP housekeeping: refreshed annotation source caches for $refreshed organism(s)");
    }
}

/**
 * Keep organisms/.organism_cache.json from going stale indefinitely.
 *
 * Until this task existed, nothing refreshed this cache automatically — only a
 * manual "Update Cache" click on the admin dashboard did. That's the same
 * silent-staleness trap as PHP sessions: if no admin happens to click it, the
 * DB/filesystem drift checks that read from this cache (e.g. gene_set
 * directories orphaned by a DB rebuild — see validateAssemblyDirectories())
 * never get re-evaluated, so the dashboard can show a clean bill of health
 * that's actually days old.
 *
 * Launches the same background scanner the manual button uses (see
 * admin/api/refresh_organism_cache.php) — non-blocking, returns immediately.
 * The scan itself only re-examines organisms whose fingerprint changed, so
 * this stays cheap even with many organisms.
 *
 * TRIGGER IS FINGERPRINT-BASED, NOT TIME-BASED. It fires when the per-organism
 * fingerprints or the config fingerprint no longer match what the cache was built from —
 * i.e. when something ACTUALLY changed — and skips when a scan is already running (lock).
 * There is no interval check here despite the function name. (This docblock previously
 * claimed "only fires if the cache is older than the refresh interval", and the log line
 * below said "12h interval"; both were leftovers from an older time-based version and
 * described a gate that does not exist. Corrected 2026-07-16.)
 */
function housekeeping_refresh_organism_cache_if_stale() {
    $config        = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    $cache_file    = moop_organism_cache_file();
    $lock_file     = moop_organism_cache_lock_file();
    $script_path   = realpath(dirname(__DIR__) . '/scripts/warm_organism_cache.php');

    if (!$script_path || !file_exists($script_path)) return;

    // Already running (same PID-liveness check as refresh_organism_cache.php)
    if (file_exists($lock_file)) {
        $pid = (int)trim(@file_get_contents($lock_file));
        if ($pid > 0 && file_exists("/proc/$pid")) return;
        @unlink($lock_file); // stale lock
    }

    // Only refresh when the underlying data actually changed — a DB rebuilt/copied over,
    // groups or taxonomy edited — detected via the same fingerprints the warm script and
    // dashboard use, rather than on a fixed timer. Keeps refreshes out of the admin's way:
    // the only slow case is a big rebuild, and it runs in the background below.
    if (file_exists($cache_file)) {
        $raw = loadJsonFile($cache_file, []);
        if ($raw && isset($raw['org_fingerprints'], $raw['config_fingerprint'])
            && function_exists('buildPerOrganismFingerprints') && function_exists('buildConfigFingerprint')) {
            $metadata_path      = $config->getPath('metadata_path');
            $taxonomy_tree_file = "$metadata_path/taxonomy_tree_config.json";
            $groups_file        = "$metadata_path/organism_assembly_groups.json";
            if ($raw['org_fingerprints'] === buildPerOrganismFingerprints($organism_data)
                && $raw['config_fingerprint'] === buildConfigFingerprint($taxonomy_tree_file, $groups_file)) {
                return; // nothing changed — no refresh needed
            }
        }
        // A cache with no fingerprints (older format) or unreadable → fall through and refresh.
    }

    file_put_contents($lock_file, '0');
    $shell_cmd = 'echo $$ > ' . escapeshellarg($lock_file) . ' ; '
               . 'php ' . escapeshellarg($script_path)
               . ' > /dev/null 2>&1 ; rm -f ' . escapeshellarg($lock_file);

    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ];
    $proc = @proc_open(['/bin/sh', '-c', $shell_cmd], $descriptors, $pipes);
    if (is_resource($proc)) {
        error_log('MOOP housekeeping: launched organism cache refresh (fingerprints changed)');
    } else {
        @unlink($lock_file);
    }
}

/**
 * Auto-update the local NCBI taxonomy dump once per 30 days.
 *
 * Pure local check — reads a timestamp from .ncbi_taxonomy_meta.json and
 * exits immediately if < 30 days old. No network call happens here.
 * If an update is due, launches sync_ncbi_taxonomy_dump.php as a background
 * process (same pattern as the organism cache refresh) and returns instantly.
 *
 * The background script fetches the 50-byte NCBI MD5, compares it, and only
 * re-downloads the ~60 MB dump when it has actually changed.
 */
function housekeeping_check_ncbi_taxonomy_update() {
    $config        = ConfigManager::getInstance();
    $metadata_path = $config->getPath('metadata_path');
    $lock_file     = "$metadata_path/.ncbi_taxonomy_sync_lock";
    $script_path   = realpath(dirname(__DIR__) . '/scripts/sync_ncbi_taxonomy_dump.php');

    if (!$script_path || !file_exists($script_path)) return;

    // Already running — don't stack jobs
    if (file_exists($lock_file)) {
        $pid = (int)trim(@file_get_contents($lock_file));
        if ($pid > 0 && file_exists("/proc/$pid")) return;
        @unlink($lock_file); // stale lock
    }

    // Check last_checked timestamp — pure local read
    $meta         = function_exists('ncbi_load_local_dump_meta')
                  ? ncbi_load_local_dump_meta($metadata_path)
                  : [];
    $last_checked = $meta['last_checked'] ?? null;
    $stored_gz    = "$metadata_path/ncbi_rankedlineage.dmp.gz";

    // If no local dump at all, skip — user needs to run the first sync manually
    if (!file_exists($stored_gz)) return;

    // 30-day check interval
    $check_interval = 30 * 86400;
    if ($last_checked && (time() - strtotime($last_checked)) < $check_interval) return;

    // Due for a check — launch background sync
    file_put_contents($lock_file, '0');
    $shell_cmd = 'echo $$ > ' . escapeshellarg($lock_file)
               . ' ; php ' . escapeshellarg($script_path)
               . ' > /dev/null 2>&1'
               . ' ; rm -f ' . escapeshellarg($lock_file);

    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ];
    $proc = @proc_open(['/bin/sh', '-c', $shell_cmd], $descriptors, $pipes);
    if (is_resource($proc)) {
        error_log('MOOP housekeeping: launched NCBI taxonomy dump sync (30-day interval)');
    } else {
        @unlink($lock_file);
    }
}
