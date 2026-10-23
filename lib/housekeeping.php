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

// Re-run at most once an hour. This was 4h while the tasks ran INLINE in the admin
// request — a long interval was the only thing limiting how often someone ate the ~4.5s
// cost. Now that the run is spawned into the background (run_housekeeping()), the cost is
// invisible, so the interval can be tightened purely on freshness. The expensive task is
// ~4.4s once an hour: about 105 seconds of CPU a day, and every other task is
// fingerprint-gated and no-ops when nothing changed.
define('HOUSEKEEPING_MIN_INTERVAL', 3600);

// environment_check and permission_check call getWebServerUser(). That function lives in
// functions_system.php, which nothing here loaded — it happened to be in scope only
// because admin_init.php includes moop_functions.php (which requires it) a few lines
// earlier. Depend on it explicitly: if that ordering ever changed, both tasks would throw,
// the catch below would swallow it into the log, and the dashboard cards would simply
// VANISH — identical to "everything is clean". That is the silent-failure shape this file
// is supposed to detect, not exhibit.
require_once __DIR__ . '/functions_system.php';
// refresh_organism_cache calls moop_organism_cache_staleness() -- the same explicit rule. The
// copy it replaced guarded itself with function_exists(), which in the background runner
// (scripts/run_housekeeping.php loads only config_init + this file) was always false, so it
// never once judged the cache fresh: the rebuild script was started every run and its own
// copy of the check said "already up to date".
require_once __DIR__ . '/functions_data.php';
require_once __DIR__ . '/library_audit.php';

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
    // EVERY persisted key, under its own name. This used to be a hand-kept list of four keys,
    // and jbrowse_index_summary was never added to it -- so the gene-index count was written
    // every hour and reached no page. housekeeping_persist_status() and the session use the
    // same key by convention (each task sets both), so there is nothing to map.
    foreach (loadJsonFile($file, []) as $key => $value) {
        $_SESSION[$key] = $value;
    }
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
            'desc'  => 'Copies changed site data (config_editable.json, secrets.php, metadata incl. glossary.json, organism.json files, users.json) to the backup directory. It does NOT commit: if that directory is a git repo, MOOP only reads its state for the dashboard badge — committing and pushing stay manual, by design.',
        ],
        [
            'name'  => 'environment_check',
            'fn'    => 'housekeeping_environment_check',
            'label' => 'Environment check',
            'desc'  => 'Detects degraded requirements that cause silent failures: missing PHP extensions, missing JWT keys, unwritable directories, absent CLI tools (blastn, samtools, makeblastdb), and a primer3 installed without its thermodynamic parameters — which runs but fails every query.',
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
            'desc'  => "Rebuilds each organism's annotation_sources_cache.json when it is missing or older than that organism's SQLite database. In the background run it also rebuilds the per-gene-set counts the gene set page shows (seconds per database, so a few minutes per run; the rest on the next).",
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
        [
            'name'  => 'jbrowse_indexes',
            'fn'    => 'housekeeping_check_jbrowse_indexes',
            'label' => 'JBrowse track indexes',
            'desc'  => 'Rebuilds and counts gene tracks whose bgzip/tabix index is older than its source GFF, or is empty, truncated or missing. annotations.gff3 is a symlink into organisms/ so it follows every gene-set reload, while the .gz JBrowse actually reads is a snapshot that only changes on an explicit re-prep — so a reload stales the browser track silently. Rebuilds stale ones first — only in the background run, smallest first, about five minutes per run, the rest on the next; the Run housekeeping now button counts, then starts that background rebuild when there is work.',
        ],
        [
            'name'  => 'security_updates',
            'fn'    => 'housekeeping_check_security_updates',
            'label' => 'OS security updates',
            'desc'  => "Lists pending operating-system security updates, worst first, and flags the packages the site runs on — PHP, the web server, OpenSSL, SQLite, the kernel. The web server may not ask the package manager itself, so this only READS a report that root writes: once a day (the moop-security-report timer) and after every dnf/apt install or update. Running housekeeping re-reads that report; it does not re-check the system. Report-only: installing updates needs root.",
        ],
        [
            'name'  => 'library_advisories',
            'fn'    => 'housekeeping_check_library_advisories',
            'label' => 'Third-party library vulnerabilities',
            'desc'  => 'Lists the third-party libraries the site ships (the front-end files in scripts/vendor_assets.txt and the Composer packages), verifies each file against its pinned checksum, and asks the OSV vulnerability database (osv.dev) about each exact version — at most once a week, or straight away when a version changes. Only package names and versions are sent.',
        ],
        [
            'name'  => 'jbrowse_version',
            'fn'    => 'housekeeping_check_jbrowse_version',
            'label' => 'JBrowse2 version',
            'desc'  => 'Compares the bundled JBrowse2 against the latest upstream release. Reads the local version every run, but contacts GitHub at most once a week. Report-only — upgrading is a CLI job.',
        ],
    ];
}

/** Absolute path to the marker file whose mtime records the last run. */
function housekeeping_marker_file(): string {
    return ConfigManager::getInstance()->getPath('site_path') . '/logs/.housekeeping_last_run';
}

/** Absolute path to the PID lock held for the duration of a run. */
function housekeeping_lock_file(): string {
    return ConfigManager::getInstance()->getPath('site_path') . '/logs/.housekeeping_lock';
}

/**
 * Is a run in flight right now? Reads the PID lock and checks the process is alive, so a
 * lock left behind by a crashed request does not wedge housekeeping forever.
 *
 * The dashboard uses this to tell the admin "running in the background — your numbers
 * will be current next visit", which is the honest way to present the async trade.
 */
function housekeeping_is_running(): bool {
    $lock = housekeeping_lock_file();
    if (!file_exists($lock)) return false;
    $pid = (int) trim((string) @file_get_contents($lock));
    return $pid > 0 && file_exists("/proc/$pid");
}

/** Has HOUSEKEEPING_MIN_INTERVAL elapsed since the last run? */
function housekeeping_is_due(): bool {
    $last = @filemtime(housekeeping_marker_file()) ?: 0;
    return (time() - $last) >= HOUSEKEEPING_MIN_INTERVAL;
}

/**
 * Seconds since the last run started, or null if it has never run.
 */
function housekeeping_age_seconds(): ?int {
    $last = @filemtime(housekeeping_marker_file()) ?: 0;
    return $last ? max(0, time() - $last) : null;
}

/**
 * AUTO PATH — called from admin_init.php on every admin page load.
 *
 * Hydrates the session cheaply, then, if due, launches the work in a DETACHED BACKGROUND
 * PROCESS and returns immediately. It must never block: this sits in the request path of
 * every admin page, and the work is ~4.5s (permission_check alone is ~4.4s of it). Before
 * 2026-07-16 this ran the tasks INLINE, so every ~4h one unlucky admin paid 4.5 seconds
 * for a page they just wanted to glance at — and admin_init.php's comment claimed it was
 * "non-blocking", which is presumably why nobody noticed.
 *
 * Verified from a real web request: proc_open is available under php-fpm, the parent
 * returns in ~1ms, the child SURVIVES the request ending, runs as the web user, and
 * inherits php-fpm's mount namespace — that last point matters, because clean_temp_files
 * targets sys_get_temp_dir() and php-fpm has PrivateTmp=yes. A cron job would see a
 * DIFFERENT /tmp and silently clean nothing; a spawned child sees the right one.
 *
 * Do NOT call proc_close() on the handle: it WAITS for the child to exit, which would
 * reintroduce exactly the block this removes (measured: 1ms without, 2020ms with).
 */
function run_housekeeping(): void {
    // Cheap, unthrottled: every request's session gets the latest known status,
    // regardless of whether a run is launched on this tick.
    housekeeping_hydrate_session_from_status();

    if (!housekeeping_is_due())   return;
    if (housekeeping_is_running()) return;

    // Touch the marker BEFORE spawning so concurrent admin requests do not each launch a
    // child. The child also claims the lock, which closes the remaining race.
    $logs_dir = ConfigManager::getInstance()->getPath('site_path') . '/logs';
    if (!is_dir($logs_dir)) @mkdir($logs_dir, 0755, true);
    @touch(housekeeping_marker_file());

    housekeeping_spawn_background();
}

/**
 * Launch scripts/run_housekeeping.php detached. Returns true if the spawn was accepted.
 *
 * Invokes a real script file rather than an inline `php -r` string — nested quoting there
 * is fragile enough that it silently produced a child which never ran during testing.
 */
function housekeeping_spawn_background(?string $task = null): bool {
    $script = ConfigManager::getInstance()->getPath('site_path') . '/scripts/run_housekeeping.php';
    if (!file_exists($script)) {
        error_log('MOOP housekeeping: cannot spawn — missing ' . $script);
        return false;
    }

    $php = PHP_BINARY ?: 'php';
    // PHP_BINARY under php-fpm is the fpm binary, which cannot run scripts. Fall back to
    // a plain CLI php on PATH.
    if (str_contains($php, 'php-fpm') || !is_executable($php)) {
        $php = moop_find_executable('php') ?: 'php';
    }

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --spawned'
         . ($task !== null ? ' ' . escapeshellarg('--task=' . $task) : '');
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ];
    $proc = @proc_open(['/bin/sh', '-c', $cmd], $descriptors, $pipes);
    if (!is_resource($proc)) {
        error_log('MOOP housekeeping: proc_open failed — falling back to inline run');
        housekeeping_run_tasks($task !== null ? [$task] : []);   // better slow than never
        return false;
    }
    // Deliberately NO proc_close(): it blocks until the child exits.
    return true;
}

/**
 * DOES THE WORK, inline and synchronously. Honours the lock (never stampede a run already
 * in flight) but does NOT check the interval — callers decide that.
 *
 * Called by: scripts/run_housekeeping.php (the background child) and
 * admin/api/rerun_housekeeping.php (the dashboard's "Run now" button, where blocking is
 * correct — the admin asked and is waiting for the answer).
 *
 * @param list<string> $only Task names to run. Empty = the full sweep. Unknown names are
 *                           ignored; if nothing survives filtering the run is refused with
 *                           reason 'no_such_task' rather than silently doing nothing.
 * @return array{ran:bool, reason:?string, tasks:list<array{name:string,ok:bool,ms:int,error:?string}>}
 */
function housekeeping_run_tasks(array $only = []): array {
    $result = ['ran' => false, 'reason' => null, 'tasks' => []];

    $logs_dir  = ConfigManager::getInstance()->getPath('site_path') . '/logs';
    $lock_file = housekeeping_lock_file();

    $tasks = housekeeping_task_registry();
    if ($only) {
        $tasks = array_values(array_filter($tasks, fn($t) => in_array($t['name'], $only, true)));
        if (!$tasks) {
            $result['reason'] = 'no_such_task';
            return $result;
        }
    }

    if (housekeeping_is_running()) {
        $result['reason'] = 'already_running';
        return $result;
    }

    if (!is_dir($logs_dir)) @mkdir($logs_dir, 0755, true);
    @file_put_contents($lock_file, (string) getmypid());
    // Touch the marker before running: a slow task must not let other requests pile up.
    //
    // ONLY for a full sweep. A targeted re-run (the permission card's "Re-check now")
    // must not satisfy the interval for the other nine tasks — touching the marker there
    // would mean re-checking one card silently postpones every other check by a full
    // interval, so the more an admin used the button the staler everything else got.
    if (!$only) {
        @touch(housekeeping_marker_file());
    }

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
        $site_path . '/metadata/glossary.json'                  => 'metadata/glossary.json',
        $site_path . '/metadata/group_taxon_exceptions.json'    => 'metadata/group_taxon_exceptions.json',
        $site_path . '/metadata/annotation_scores.json'         => 'metadata/annotation_scores.json',
        // Web-side overrides of expression bundles (access level etc.). The bundles themselves are
        // copied in from the compute box; THIS file is the only web-side state, so it must be backed up.
        $site_path . '/metadata/expression_overrides.json'      => 'metadata/expression_overrides.json',
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
| `metadata/glossary.json` | Admin-editable glossary term definitions |
| `metadata/group_taxon_exceptions.json` | Group/taxonomy differences marked deliberate |
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
    $failed = [];          // destinations the web server could not write
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
            // CHECK THE RETURN. This used to be a bare `@file_put_contents(...)` followed
            // unconditionally by $copied_count++, so a write that failed was counted as a
            // backup: the dashboard reported "backed up N files", the task showed a tick,
            // and the file had not moved.
            //
            // It was not hypothetical. metadata/glossary.json in the backup was mode 640
            // owned by smr while every sibling was 664 apache — housekeeping runs as
            // apache, so it had read but not write. Every run from 2026-07-23 to
            // 2026-08-04 "succeeded" while the file sat unchanged, which means a dozen
            // days of Manage Glossary edits were never backed up by the thing whose only
            // job is to back them up. A backup that reports success without writing is
            // worse than no backup, because nobody goes looking.
            if (@file_put_contents($dest, $source_content) === false) {
                $failed[] = $dest_relative;
                error_log("MOOP housekeeping: FAILED to back up $dest_relative to "
                        . "$site_data_path — check ownership and mode on the destination "
                        . "(it must be writable by the web server user)");
                continue;
            }
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
        'status' => $failed ? 'error' : 'ok',
        'is_git' => $is_git,
        'git' => $is_git ? housekeeping_git_status($site_data_path) : null,
        'last_run' => date('Y-m-d H:i:s'),
        'files_copied' => $copied_count,
        // Surfaced so a write the web server cannot perform is visible on the dashboard
        // instead of hiding behind a success count. Empty is the normal case.
        'files_failed' => $failed,
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

    // 2b. Git permission hooks armed
    //
    // Any checkout, merge, rebase, cherry-pick or reset that writes a served .php leaves it
    // mode 640, which php-fpm cannot read -> EVERY page 500s, thrown before MOOP's error
    // handler is installed, so logs/error.log stays empty and the real error is in root-only
    // /var/log/php-fpm/www-error.log. It took the site down twice on 2026-09-16.
    //
    // .githooks/ repairs it automatically, but git will not run a tracked hook until the
    // clone opts in -- cloning a repo must never execute its code -- so a fresh clone is
    // silently unprotected. setup-check.php checks this too, but it is a CLI tool that 403s
    // over HTTP and nothing links to it; this is the copy an admin actually sees.
    //
    // 'danger', not 'warning', even though nothing is broken yet: the blast radius is the
    // whole site rather than one feature, and the fix is a single command.
    //
    // .git/config is read directly rather than shelling out to `git config`, because this
    // runs as the web user and git refuses a repo owned by someone else ("dubious
    // ownership") unless safe.directory is set.
    if (is_dir($site_path . '/.git')) {
        $hook_script = $site_path . '/.githooks/moop-fix-web-perms';
        $hooks_path  = null;
        $git_config  = $site_path . '/.git/config';
        if (is_readable($git_config)) {
            if (preg_match('/^\s*hooksPath\s*=\s*(\S+)/mi', (string) @file_get_contents($git_config), $m)) {
                $hooks_path = rtrim(trim($m[1]), '/');
            }
        }
        // core.hooksPath FIRST: .git/config is world-readable, so this arm is reliable from
        // the web user. The script check below is not — .githooks/ created under a 027 umask
        // is 750, and then file_exists() is false for apache whether the script is missing or
        // merely unreadable. Reporting "missing" there sent the admin after the wrong fix, so
        // that arm now names both causes and only runs once the config arm is satisfied.
        if ($hooks_path !== '.githooks') {
            $warnings[] = [
                'level' => 'danger',
                'message' => 'Git is not running MOOP\'s permission hooks — this clone is unprotected. Any checkout, merge or rebase that writes a served <code>.php</code> leaves it mode 640 and 500s the whole site. Fix with: <code>git config core.hooksPath .githooks</code>',
            ];
        } elseif (!file_exists($hook_script)) {
            $warnings[] = [
                'level' => 'danger',
                'message' => 'Git is set to use <code>.githooks/</code> but the web server cannot see <code>moop-fix-web-perms</code> there — it is missing, or the directory is not traversable by the web user. Until it runs, a checkout that writes a served <code>.php</code> leaves it mode 640 and 500s the whole site. Fix with: <code>chmod 755 .githooks .githooks/*</code> (or restore the file from the repo).',
            ];
        }
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

    // 2770 for a directory that holds credentials, 2775 for the rest — resolved from the
    // permission rules' 'sensitive' flags, never hardcoded here. This used to print 2775
    // for every directory including the site-data backup, so following the dashboard's own
    // advice made users.json (bcrypt hashes) and secrets.php readable by every local user.
    // permission_check.php is otherwise pulled in later (the permission_check task); this is
    // require_once, so loading it here costs nothing and keeps the guard below from silently
    // falling through to the default.
    require_once $site_path . '/lib/permission_check.php';

    $sensitive_paths = [];
    if (function_exists('moop_permission_sensitive_paths')) {
        $sensitive_paths = moop_permission_sensitive_paths($config, [
            'web_group'            => $web_info['group'],
            'moop_owner'           => function_exists('getMoopOwner') ? getMoopOwner() : '',
            'metadata_path'        => $config->getPath('metadata_path'),
            'organism_data'        => $config->getPath('organism_data'),
            'cache_path'           => $config->getPath('cache_path'),
            'site_path'            => $site_path,
            'absolute_images_path' => $config->getPath('absolute_images_path'),
            'docs_path'            => $config->getPath('docs_path'),
            'site_data_path'       => $site_data_path,
        ]);
    }

    foreach ($writable_dirs as $label => $dir) {
        if (is_dir($dir) && !is_writable($dir)) {
            $dir_mode = function_exists('moop_permission_dir_mode')
                ? moop_permission_dir_mode($dir, $sensitive_paths)
                : '2770';   // fail CLOSED: never advise widening when we cannot tell
            $fix_cmd = "sudo chgrp " . $web_info['group'] . " " . htmlspecialchars($dir) . " && sudo chmod " . $dir_mode . " " . htmlspecialchars($dir);
            $warnings[] = [
                'level' => 'warning',
                'message' => "Directory <code>$label/</code> is not writable — admin changes may not save. Fix with: <code>$fix_cmd</code>",
            ];
        }
    }

    // 4. CLI tools available -- resolved the same way the code that runs them does
    // (moop_find_executable: PATH, then /usr/local/bin and /usr/bin).
    $cli_tools = [
        'blastn'          => 'BLAST searches will not work',
        'blast_formatter' => 'BLAST result display and downloads will not work',
        'blastdbcmd'      => 'BLAST sequence retrieval will not work',
        'samtools'        => 'BAM/CRAM tracks and FAI indexing will not work',
        'makeblastdb'     => 'BLAST index building will not work',
        'bgzip'           => 'JBrowse gene tracks cannot be built or rebuilt',
        'tabix'           => 'JBrowse gene tracks cannot be built or rebuilt',
    ];
    foreach ($cli_tools as $tool => $impact) {
        if (moop_find_executable($tool) === '') {
            $warnings[] = [
                'level' => 'warning',
                'message' => "CLI tool <code>$tool</code> not found in PATH — $impact.",
            ];
        }
    }

    // 4b. primer3, which needs TWO things present, not one.
    //
    // Checked apart from the list above because "the binary exists" is not the
    // same as "primer3 works": its own `make install` installs the executables
    // and NOT the thermodynamic parameter tables, so a perfectly ordinary
    // install leaves primer3_core on the PATH failing every query. Reporting it
    // as present on the strength of the binary alone would be exactly the
    // silent-success shape this codebase keeps hitting.
    //
    // Absent entirely is INFO, not a warning: primer design is an optional
    // feature and most deployments will not have installed it. Half-installed
    // is a warning, because that one looks fine from the outside.
    //
    // The level is used directly as a Bootstrap class — admin/pages/admin.php
    // renders alert-{level} — so it must be one Bootstrap defines. 'notice' is
    // not, and would have produced an unstyled div that still showed the text:
    // wrong enough to look broken, right enough not to be noticed.
    require_once __DIR__ . '/primer/Primer3.php';
    $p3 = Primer3::status();

    if ($p3['problem'] === 'missing') {
        $warnings[] = [
            'level'   => 'info',
            'message' => 'Primer design tool <code>primer3_core</code> is not installed — the '
                       . 'primer designer will be unavailable. Install it with '
                       . '<code>sudo bash scripts/install_primer3.sh</code>, which uses your '
                       . 'distribution\'s package where there is one (Debian and Ubuntu have it) '
                       . 'and builds from source otherwise.',
        ];
    } elseif ($p3['problem'] === 'no_parameters') {
        $warnings[] = [
            'level'   => 'warning',
            'message' => '<code>primer3_core</code> is installed at <code>'
                       . htmlspecialchars($p3['binary']) . '</code> but its thermodynamic '
                       . 'parameters were not found in any known location. primer3 will fail on '
                       . 'every query with <code>PRIMER_ERROR=Unable to open file …</code>. Its '
                       . 'own <code>make install</code> does not copy that directory — re-run '
                       . '<code>scripts/install_primer3.sh</code>, or set '
                       . '<code>primer3_config_path</code> if it is somewhere unusual.',
        ];
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
 * Count JBrowse gene sets whose registration is out of step with their genes.gff
 * (gene_set_jbrowse_problems() -- the one rule): the gene track's bgzip/tabix index, the two
 * coordinate files, and the search index.
 *
 * WHY THIS EXISTS. data/genomes/{org}/{asm}/{gs}/annotations.gff3 is a SYMLINK into
 * organisms/, so it silently follows every gene-set reload -- while annotations.gff3.gz,
 * which is what JBrowse actually reads, is a snapshot that only changes when someone
 * re-preps it by hand. Nothing tied the two together, so a reload staled the browser
 * track and said nothing. On 2026-07-29 that was 41 of 69 tracks, some two months
 * behind; the two Bradypodion gene sets were serving feature IDs that no longer existed
 * in the database at all, so every gene-page link into JBrowse missed.
 *
 * Until 2026-10-21 only the track index was checked: the search index was never counted
 * (none existed, and nothing said so), and coordinate files that failed to rebuild while the
 * track was fine were invisible.
 *
 * Counting is stat calls only. REBUILDING (since 2026-10-13, user's decision) happens first,
 * and ONLY in the background run (PHP_SAPI 'cli', scripts/run_housekeeping.php): it
 * re-compresses GFFs that can exceed 100 MB, which must never happen inside a page request
 * -- and housekeeping still runs inline from the admin "re-run" button and when a spawn
 * fails. It used to report only, on the grounds that rebuilding "belongs behind a button";
 * the button existed, and 42 of 68 tracks were stale anyway.
 *
 * Summary: 'need' = gene sets with any problem. 'stale'/'missing'/'broken' count TRACK index
 * problems only (a broken track shows no genes at all); 'coords' and 'search' count gene sets
 * with that part out of date.
 */
function housekeeping_check_jbrowse_indexes() {
    $config    = ConfigManager::getInstance();
    $site_path = $config->getPath('site_path');
    require_once $site_path . '/lib/jbrowse/gene_set_functions.php';
    $base      = "$site_path/data/genomes";
    $rebuilt   = PHP_SAPI === 'cli' ? housekeeping_rebuild_stale_gene_indexes($base) : null;

    // 'cli': whether search indexes CAN be built here -- without it every one would be listed
    // as a failure each hour, when the fix is a single install.
    $summary = ['total' => 0, 'need' => 0, 'stale' => 0, 'missing' => 0, 'broken' => 0, 'coords' => 0, 'search' => 0,
                'cli' => moop_find_jbrowse_cli() !== null,
                'examples' => [], 'checked_at' => time(), 'last_rebuild' => $rebuilt];
    // A count-only run (the Re-run button, php-fpm) must not wipe the record of the last
    // real rebuild -- it is what tells the admin whether the background run is working.
    if ($rebuilt === null) {
        $prev = json_decode((string)@file_get_contents(housekeeping_status_file()), true);
        $summary['last_rebuild'] = $prev['jbrowse_index_summary']['last_rebuild'] ?? null;
    }

    foreach (housekeeping_gene_set_jbrowse_problems($base) as $key => $problems) {
        $summary['total']++;
        if (!$problems) continue;
        $summary['need']++;
        foreach ($problems as $pr) {
            if ($pr['part'] === 'track') $summary[$pr['kind']]++;
        }
        $parts = array_unique(array_column($problems, 'part'));
        foreach (['coords', 'search'] as $part) {
            if (in_array($part, $parts, true)) $summary[$part]++;
        }
        // Enough to identify the gene set on the dashboard; the manager page re-checks live.
        if (count($summary['examples']) < 60) {
            [$org, $asm, $gs] = explode('/', $key);
            $summary['examples'][] = ['organism' => $org, 'assembly' => $asm, 'gene_set' => $gs,
                                      'why' => implode('; ', array_column($problems, 'why'))];
        }
    }

    $_SESSION['jbrowse_index_summary'] = $summary;
    housekeeping_persist_status('jbrowse_index_summary', $summary);
}

/**
 * gene_set_jbrowse_problems() for every registered gene set, keyed "org/asm/gs".
 * A dangling annotations.gff3 symlink means the gene set is gone, not stale: skipped.
 *
 * @return array<string, list<array>>
 */
function housekeeping_gene_set_jbrowse_problems(string $base): array {
    $out = [];
    foreach (is_dir($base) ? (glob("$base/*/*/*/annotations.gff3") ?: []) : [] as $gff) {
        if (!file_exists($gff)) continue;   // glob() does not follow the link; file_exists() does
        [$org, $asm, $gs] = array_slice(explode('/', substr($gff, strlen($base) + 1)), 0, 3);
        $out["$org/$asm/$gs"] = gene_set_jbrowse_problems($gff, moop_gene_track_json_path($org, $asm, $gs));
    }
    return $out;
}

/**
 * Bring gene sets back in step with their genes.gff -- broken tracks first, then smallest
 * GFF first -- until $budget_seconds is spent; the rest wait for the next run (hourly), so a
 * large backlog is worked through without any one run holding the housekeeping lock for long.
 * A broken track shows no genes at all; a stale one shows old ones.
 *
 * "Missing" includes a gene set with no .gz at all. That used to be skipped as "never
 * registered -- an admin decision", but annotations.gff3 is only ever created by
 * prepareGeneSetForJBrowse() itself, so the symlink existing means registration was
 * attempted and the compression step is what failed.
 *
 * Each gene set gets the manager's own Re-prep: prepareGeneSetForJBrowse() (not forced, so it
 * rebuilds only the track index and coordinate files that are out of date and leaves the track
 * config alone), then the search index when that part is still out of date. Success means
 * gene_set_jbrowse_problems() finds nothing afterwards -- not that each step returned true.
 *
 * @return array{done: list<string>, failed: list<string>, left: int, seconds: int}
 *         failed entries are "org/asm/gs — reason".
 */
function housekeeping_rebuild_stale_gene_indexes(string $base, int $budget_seconds = 300): array {
    $t0 = time();
    $out = ['done' => [], 'failed' => [], 'left' => 0, 'seconds' => 0];
    if (!is_dir($base)) return $out;

    $config = ConfigManager::getInstance();
    require_once $config->getPath('site_path') . '/lib/jbrowse/gene_set_functions.php';

    $todo = [];
    foreach (housekeeping_gene_set_jbrowse_problems($base) as $key => $problems) {
        if (!$problems) continue;
        $broken = in_array('broken', array_column($problems, 'kind'), true);
        $todo[$key] = [$broken ? 0 : 1, (int)@filesize("$base/$key/annotations.gff3")];
    }
    uasort($todo, fn($a, $b) => $a <=> $b);

    foreach (array_keys($todo) as $i => $key) {
        if (time() - $t0 >= $budget_seconds) { $out['left'] = count($todo) - $i; break; }
        [$org, $asm, $gs] = explode('/', $key);
        $log = [];
        prepareGeneSetForJBrowse($org, $asm, $gs, $config, $log, false);
        $left = moop_gene_set_jbrowse_problems($org, $asm, $gs);
        if (in_array('search', array_column($left, 'part'), true)
            && !in_array('track', array_column($left, 'part'), true) && moop_find_jbrowse_cli() !== null) {
            $ix = buildGeneSetTextIndex($org, $asm, $gs, MOOP_JBROWSE_TEXT_INDEX_ATTRIBUTES, $config);
            if (!$ix['success']) $log[] = 'ERROR — search index: ' . ($ix['error'] ?? 'unknown error');
            $left = moop_gene_set_jbrowse_problems($org, $asm, $gs);
        }
        // Without the CLI a search index cannot be built; the card says so once, rather than
        // this listing every gene set as failed.
        if (moop_find_jbrowse_cli() === null) $left = array_values(array_filter($left, fn($p) => $p['part'] !== 'search'));
        if (!$left) {
            $out['done'][] = $key;
            continue;
        }
        // The reason travels WITH the result, into the status file the dashboard reads. The
        // background child's error_log() goes to its stderr, which is /dev/null -- so a
        // reason that only went to the log was a reason nobody would ever see.
        $errors = array_values(array_filter($log, fn($l) => strpos($l, 'ERROR') !== false || strpos($l, 'WARNING') !== false));
        $why    = implode(' | ', $errors ?: array_column($left, 'why'));
        $out['failed'][] = "$key — $why";
        error_log("MOOP housekeeping: re-prep failed for $key: $why");
    }
    $out['seconds'] = time() - $t0;
    return $out;
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

    // database_queries.php is not loaded by admin_init — include lazily. functions_database.php
    // (fetchData) IS loaded by admin_init, but not in the background CLI run, where every
    // rebuild here died with "Call to undefined function fetchData()" -- invisible, because in
    // steady state nothing is stale and the loop below never reaches a query.
    require_once dirname(__DIR__) . '/lib/functions_database.php';
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

    // Per-GENE-SET counts, for the gene set page's Annotation Summary and its search filter.
    // Seconds per database (a walk of feature_annotation), so ONLY in the background CLI run,
    // like the gene-track index rebuild -- never inside the Re-run button's request -- and
    // within a time budget: after a full reload all 85 are stale at once (~3 min warm, more
    // cold), and the rest simply wait for the next run. Until a gene set's counts exist, its
    // page shows no Annotation Summary rather than the organism's figures under its name.
    if (PHP_SAPI === 'cli') {
        housekeeping_refresh_gene_set_annotation_counts($organism_data, $entries);
        housekeeping_refresh_gene_set_summaries($organism_data, $entries);
    }
}

/**
 * Build gene_set_summary.json (the organism page's gene set comparison: assembly N50 / size /
 * sequence count from the .fai, gene + transcript + annotated counts from the database) for each
 * organism whose database or any genome .fai is newer than it. Background run only, smallest
 * first, within a budget -- see lib/gene_set_summary.php.
 */
function housekeeping_refresh_gene_set_summaries(string $organism_data, array $entries, int $budget_seconds = 120): void {
    require_once dirname(__DIR__) . '/lib/gene_set_summary.php';
    $t0   = time();
    $todo = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || !is_dir("$organism_data/$entry")) continue;
        if (!is_file("$organism_data/$entry/organism.sqlite")) continue;
        $file = moop_gene_set_summary_file($entry);
        if ($file === '' || !moop_gene_set_summary_is_stale("$organism_data/$entry", $file)) continue;
        $todo[$entry] = filesize("$organism_data/$entry/organism.sqlite");
    }
    asort($todo);

    $done = 0;
    foreach (array_keys($todo) as $organism) {
        if (time() - $t0 >= $budget_seconds) break;
        $file = moop_gene_set_summary_file($organism);
        $tmp  = "$file.tmp-" . getmypid();
        if (file_put_contents($tmp, json_encode(moop_build_gene_set_summary("$organism_data/$organism"))) !== false
            && rename($tmp, $file)) {
            $done++;
        } else {
            @unlink($tmp);
            error_log("MOOP housekeeping: could not write $file");
        }
    }
    $left = count($todo) - $done;
    if ($done > 0 || $left > 0) {
        error_log("MOOP housekeeping: gene set summaries built for $done organism(s), $left left for the next run");
    }
}

/**
 * Build gene_set_annotation_counts.json for every organism whose database is newer than it,
 * smallest database first, until $budget_seconds is spent.
 *
 * @param list<string> $entries  scandir() of organism_data
 */
function housekeeping_refresh_gene_set_annotation_counts(string $organism_data, array $entries, int $budget_seconds = 240): void {
    $t0   = time();
    $todo = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $db   = "$organism_data/$entry/organism.sqlite";
        $file = moop_gene_set_annotation_counts_file($entry);
        if (!is_file($db) || $file === '') continue;
        if (is_file($file) && filemtime($file) >= filemtime($db)) continue;
        $todo[$entry] = filesize($db);
    }
    asort($todo);

    $done = 0;
    foreach (array_keys($todo) as $organism) {
        if (time() - $t0 >= $budget_seconds) break;
        $db     = "$organism_data/$organism/organism.sqlite";
        $counts = getAnnotationSourcesByGeneSet($db);
        // Every gene set gets an entry, an empty one included, so "this gene set has no
        // annotations" is recorded rather than looking like "not built yet".
        $all = "SELECT g.genome_accession || '/' || gs.gene_set_name AS k FROM gene_set gs JOIN genome g ON g.genome_id = gs.genome_id";
        foreach (fetchData($all, $db, []) as $row) {
            $counts['sources'][$row['k']]    = $counts['sources'][$row['k']] ?? [];
            $counts['type_genes'][$row['k']] = $counts['type_genes'][$row['k']] ?? [];
        }
        $counts['count'] = 'genes';   // what 'count' means -- readers refuse a file without it
        $file = moop_gene_set_annotation_counts_file($organism);
        // Temp + rename: a reader never sees a half-written file.
        $tmp  = "$file.tmp-" . getmypid();
        if (file_put_contents($tmp, json_encode($counts)) !== false && rename($tmp, $file)) {
            // The term-count file this replaced (pre-2026-10-22 name); nothing reads it now.
            @unlink(dirname($file) . '/gene_set_annotation_counts.json');
            $done++;
        } else {
            @unlink($tmp);
            error_log("MOOP housekeeping: could not write $file");
        }
    }
    $left = count($todo) - $done;
    if ($done > 0 || $left > 0) {
        error_log("MOOP housekeeping: per-gene-set annotation counts built for $done organism(s), $left left for the next run");
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

    // Already running? One shared definition — moop_organism_cache_refresh_active() also
    // drops the lock when its process is gone, so a crashed worker cannot wedge refreshes.
    if (moop_organism_cache_refresh_active()) return;

    // Only refresh when the underlying data actually changed — a DB rebuilt/copied over,
    // groups or taxonomy edited, or a cache of an older format — by the one staleness test
    // every admin page uses (moop_organism_cache_staleness()), rather than on a fixed timer.
    // Keeps refreshes out of the admin's way: the only slow case is a big rebuild, and it
    // runs in the background below. No cache, or an unreadable one: refresh.
    $raw = file_exists($cache_file) ? loadJsonFile($cache_file, []) : [];
    if ($raw) {
        $metadata_path = $config->getPath('metadata_path');
        $staleness = moop_organism_cache_staleness($raw, $organism_data,
            "$metadata_path/taxonomy_tree_config.json", "$metadata_path/organism_assembly_groups.json");
        if (!$staleness['stale']) return;   // nothing changed — no refresh needed
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

/**
 * Known vulnerabilities in the third-party libraries MOOP ships (lib/library_audit.php).
 *
 * The local half -- which libraries, which versions, do the files still match their pinned
 * checksums -- is cheap and runs every pass. The OSV lookup is a network call, so at most
 * weekly, like jbrowse_version; sooner when the inventory changed (a library was upgraded,
 * so last week's answer is about a version no longer shipped) or when started on its own
 * (--task=library_advisories). Only package names and versions leave the server.
 * If OSV cannot be reached the last answer is kept and marked, never replaced by "none found".
 */
function housekeeping_check_library_advisories(): void {
    $root = ConfigManager::getInstance()->getPath('site_path');
    $pkgs = moop_library_inventory($root);
    $prev = $_SESSION['library_advisories'] ?? [];
    $sig  = md5(json_encode(array_map(fn($p) => "{$p['ecosystem']}:{$p['name']}@{$p['version']}", $pkgs)));
    $forced = PHP_SAPI === 'cli' && in_array('--task=library_advisories', $GLOBALS['argv'] ?? [], true);
    $due = $forced || empty($prev['checked_at']) || ($prev['signature'] ?? '') !== $sig
        || (time() - (int)strtotime($prev['checked_at'])) > 7 * 86400;

    $vulns = null;
    if ($due) $vulns = moop_osv_check($pkgs);
    $reached = $vulns !== null;
    if (!$reached) {
        // Not due, or OSV unreachable: carry last time's findings over by package@version.
        $old = [];
        foreach ($prev['packages'] ?? [] as $p) $old["{$p['ecosystem']}:{$p['name']}@{$p['version']}"] = $p['vulns'] ?? [];
        $vulns = [];
        foreach ($pkgs as $i => $p) $vulns[$i] = $old["{$p['ecosystem']}:{$p['name']}@{$p['version']}"] ?? [];
    }
    foreach ($pkgs as $i => &$p) { $p['vulns'] = $vulns[$i] ?? []; }
    unset($p);

    $result = [
        'packages'    => $pkgs,
        'checked_at'  => $reached ? date('c') : ($prev['checked_at'] ?? null),
        'signature'   => $reached ? $sig : ($prev['signature'] ?? null),
        'osv_failed'  => $due && !$reached,
        // Third-party files the list above does not cover, so OSV never hears of them.
        'unlisted'    => moop_unlisted_vendor_files($root),
    ];
    $_SESSION['library_advisories'] = $result;
    housekeeping_persist_status('library_advisories', $result);
}

/**
 * Pending OS security updates -- the check that would have caught PHP2Shell.
 *
 * PHP2Shell (2026-10-21) was patched only because someone read an article. This host runs
 * RHEL's PHP 8.0, which upstream no longer supports: fixes arrive only as Red Hat backports,
 * so the version number never looks new and nothing on the site watched the errata. When
 * this was written the host had 121 security advisories pending, nginx and OpenSSL among them.
 *
 * The web server usually may NOT ask the package manager: under SELinux httpd_t cannot open
 * the RPM database ("rpmdb open failed", tested 2026-10-22). So the source is a report root
 * writes daily into the cache directory (scripts/install_security_report.sh -- a systemd
 * timer), and this task only READS it: cheap, so it runs every housekeeping pass.
 * Without a report it tries the package manager directly (fine on a host without that
 * policy), only in the background CLI run, at most daily; if that fails too, the result
 * says how to set the report up rather than claiming "no updates".
 *
 * Report-only, never installs: that needs root, and updating PHP restarts php-fpm.
 */
function housekeeping_check_security_updates(): void {
    $report = moop_cache_root() . '/security_updates.txt';
    if (is_readable($report)) {
        $result = moop_parse_security_report((string)file_get_contents($report));
        $result['source'] = 'report';
    } else {
        $prev   = $_SESSION['security_updates'] ?? null;
        $forced = PHP_SAPI === 'cli' && in_array('--task=security_updates', $GLOBALS['argv'] ?? [], true);
        $due    = $forced || !$prev || empty($prev['checked_at'])
               || (time() - (int)strtotime($prev['checked_at'])) > 86400;
        if (PHP_SAPI !== 'cli' || !$due) {
            return;   // ~2 s of package manager: never in a page request, at most daily
        }
        $text = "# generated: " . date('c') . "\n# running_kernel: " . php_uname('r') . "\n";
        if (($dnf = moop_find_executable('dnf')) !== '') {
            $out = []; $rc = 0;
            exec(escapeshellarg($dnf) . ' -q -C updateinfo list --security 2>&1', $out, $rc);
            $text .= "# manager: dnf\n" . implode("\n", $out) . "\n# exit: $rc\n";
        } elseif (($apt = moop_find_executable('apt-get')) !== '') {
            $out = []; $rc = 0;
            exec(escapeshellarg($apt) . ' -s -o Debug::NoLocking=1 upgrade 2>&1', $out, $rc);
            $text .= "# manager: apt\n" . implode("\n", $out) . "\n# exit: $rc\n";
        } else {
            $text .= "# manager: none\n";
        }
        $result = moop_parse_security_report($text);
        $result['source'] = 'direct';
    }
    $_SESSION['security_updates'] = $result;
    housekeeping_persist_status('security_updates', $result);
}

/**
 * Parse a security report: "# key: value" header lines, then the package manager's own
 * output. Pure (no I/O) so it is testable; shared by the root-written report and the direct run.
 *
 * @return array{checked_at:?string, manager:?string, error:?string, stale:bool, reboot:?bool,
 *               advisories:array, by_severity:array, watched:array, packages:int, command:?string}
 */
function moop_parse_security_report(string $text): array {
    $head = [];
    $body = [];
    foreach (preg_split('/\R/', $text) as $line) {
        if (preg_match('/^# (\w+): ?(.*)$/', $line, $m)) $head[$m[1]] = trim($m[2]);
        else $body[] = $line;
    }
    $body = implode("\n", $body);
    $r = ['checked_at' => $head['generated'] ?? null, 'manager' => $head['manager'] ?? null, 'error' => null,
          'stale' => false, 'reboot' => null, 'advisories' => [], 'by_severity' => [], 'watched' => [],
          'packages' => 0, 'command' => null];

    // A report root stopped writing is worse than none: it shows old good news. 3 days = the
    // daily timer has missed at least two runs.
    if ($r['checked_at'] && time() - (int)strtotime($r['checked_at']) > 3 * 86400) $r['stale'] = true;

    if (isset($head['reboot_required'])) {
        $r['reboot'] = true;
    } elseif (!empty($head['newest_kernel']) && !empty($head['running_kernel'])) {
        $r['reboot'] = $head['newest_kernel'] !== $head['running_kernel'];
    }

    $exit = isset($head['exit']) ? (int)$head['exit'] : 0;
    if ($r['manager'] === 'none' || $r['manager'] === null) {
        $r['error'] = 'No supported package manager (dnf or apt-get) found.';
    } elseif ($exit !== 0) {
        $r['error'] = $r['manager'] . ' exited ' . $exit . ': ' . substr(preg_replace('/\s+/', ' ', $body), 0, 300);
    } elseif ($r['manager'] === 'dnf') {
        $r = array_merge($r, moop_parse_dnf_security($body));
        $r['command'] = 'sudo dnf update --security';
    } else {
        $r = array_merge($r, moop_parse_apt_security($body));
        $r['command'] = 'sudo apt-get update && sudo apt-get upgrade';
    }
    return $r;
}

/**
 * The packages the site itself runs on, by product. Matched by name prefix ("php" covers
 * php-fpm, php-cli...). Short on purpose: everything else is still counted, just not called out.
 */
function moop_security_watch_list(): array {
    // package-name prefix => the product it belongs to (dnf and apt name the same thing differently)
    return ['php' => 'PHP', 'nginx' => 'nginx', 'httpd' => 'Apache httpd', 'apache2' => 'Apache httpd',
            'openssl' => 'OpenSSL', 'libssl' => 'OpenSSL', 'sqlite' => 'SQLite', 'libsqlite3' => 'SQLite',
            'kernel' => 'Linux kernel', 'linux-image' => 'Linux kernel', 'glibc' => 'glibc', 'libc6' => 'glibc',
            'curl' => 'curl', 'libcurl' => 'curl'];
}

/**
 * Parse `dnf updateinfo list --security` ("RHSA-2026:59362 Important/Sec. nginx-2:1.20.1-28.el9_8.5.x86_64").
 * One advisory can list many packages and one package many advisories: counts are of
 * ADVISORIES, and a watched PRODUCT (kernel-core, kernel-tools... = Linux kernel) is reported
 * once, at its worst severity.
 *
 * @return array{advisories: array<string,string>, by_severity: array<string,int>, watched: array<string,array{severity:string, advisories:list<string>}>, packages: int}
 */
function moop_parse_dnf_security(string $text): array {
    $rank = ['Critical' => 4, 'Important' => 3, 'Moderate' => 2, 'Low' => 1];
    $advisories = []; $packages = []; $watched = [];
    foreach (preg_split('/\R/', $text) as $line) {
        if (!preg_match('/^(\S+)\s+(\w+)\/Sec\.\s+(\S+)$/', trim($line), $m)) continue;
        [, $id, $sev, $nevra] = $m;
        // name-[epoch:]version-release.arch -> name
        $name = preg_replace('/-(\d+:)?[^-]+-[^-]+$/', '', $nevra);
        $advisories[$id] = $sev;
        $packages[$name] = true;
        foreach (moop_security_watch_list() as $prefix => $product) {
            if (strpos($name, $prefix) !== 0) continue;
            $w_old = $watched[$product]['severity'] ?? null;
            if ($w_old === null || ($rank[$sev] ?? 0) > ($rank[$w_old] ?? 0)) $watched[$product]['severity'] = $sev;
            $watched[$product]['advisories'][$id] = true;
            break;
        }
    }
    foreach ($watched as &$w) { $w['advisories'] = array_keys($w['advisories']); }
    unset($w);
    uasort($watched, fn($a, $b) => ($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0));
    $by = array_count_values($advisories);
    uksort($by, fn($a, $b) => ($rank[$b] ?? 0) <=> ($rank[$a] ?? 0));
    return ['advisories' => $advisories, 'by_severity' => $by, 'watched' => $watched, 'packages' => count($packages)];
}

/**
 * Parse `apt-get -s upgrade`: "Inst nginx [1.22.1-9] (1.22.1-9+deb12u1 Debian-Security:12/stable-security [amd64])".
 * apt has no severities, so every security update counts as one "Security" entry.
 * ⚠️ Written for Debian/Ubuntu and NOT yet run on one -- verify on the first such host.
 */
function moop_parse_apt_security(string $text): array {
    $packages = []; $watched = [];
    foreach (preg_split('/\R/', $text) as $line) {
        if (!preg_match('/^Inst (\S+) .*\(\S+ ([^)]*)\)/', $line, $m)) continue;
        if (stripos($m[2], 'security') === false) continue;
        $packages[$m[1]] = true;
        foreach (moop_security_watch_list() as $prefix => $product) {
            if (strpos($m[1], $prefix) === 0) { $watched[$product] = ['severity' => 'Security', 'advisories' => []]; break; }
        }
    }
    return ['advisories' => [], 'by_severity' => $packages ? ['Security' => count($packages)] : [],
            'watched' => $watched, 'packages' => count($packages)];
}

/**
 * Is the bundled JBrowse2 behind the latest upstream release?
 *
 * NETWORK-GATED, following housekeeping_check_ncbi_taxonomy_update(): the local version is
 * read on every run (a 6-byte file), but GitHub is contacted at most once every 7 days.
 * Housekeeping runs hourly, so an ungated check would be ~168 pointless API calls a week
 * against a project that releases every few weeks.
 *
 * Report-only by design. It never upgrades anything: upgrading has to happen from a shell
 * (jbrowse2/ is the one tree where read-only-to-the-web-server is the real defence — see
 * docs/JBrowse2/UPGRADING.md), and it needs a human to verify tracks still render afterwards.
 *
 * Failure is silent and non-sticky: a host with no outbound network, or GitHub being down,
 * leaves the previously known result in place rather than raising an alarm the admin cannot
 * act on. `latest` is simply absent until a check succeeds.
 */
function housekeeping_check_jbrowse_version() {
    $config    = ConfigManager::getInstance();
    $site_path = $config->getPath('site_path');
    $version_file = $site_path . '/jbrowse2/version.txt';

    // No JBrowse installed → nothing to report. Not an error; JBrowse is optional.
    if (!file_exists($version_file)) {
        $_SESSION['jbrowse_version'] = null;
        housekeeping_persist_status('jbrowse_version', null);
        return;
    }

    $current = trim((string)@file_get_contents($version_file));
    if ($current === '') return;

    // Reuse whatever the last successful check found; only the local version is refreshed.
    $prev   = $_SESSION['jbrowse_version'] ?? [];
    $latest = $prev['latest']     ?? null;
    $when   = $prev['checked_at'] ?? null;

    $due = !$when || (time() - (int)strtotime($when)) > (7 * 86400);

    if ($due && function_exists('curl_init')) {
        $ch = curl_init('https://api.github.com/repos/GMOD/jbrowse-components/releases/latest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'MOOP-version-check',
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $body) {
            $data = json_decode($body, true);
            $tag  = is_array($data) ? ($data['tag_name'] ?? '') : '';
            // Releases are tagged "v4.3.0"; version.txt holds "4.3.0".
            if (preg_match('/^v?(\d+\.\d+\.\d+)$/', trim($tag), $m)) {
                $latest = $m[1];
                $when   = date('c');
            }
        }
        // Any other outcome: keep the previous $latest/$when. Silence is deliberate.
    }

    $result = [
        'current'    => $current,
        'latest'     => $latest,
        'checked_at' => $when,
        // version_compare handles 4.1.15 > 4.1.3 correctly; a string compare does not.
        'outdated'   => ($latest !== null) && version_compare($current, $latest, '<'),
    ];

    $_SESSION['jbrowse_version'] = $result;
    housekeeping_persist_status('jbrowse_version', $result);
}
