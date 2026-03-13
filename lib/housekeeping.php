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
        ['name' => 'clean_temp_files',  'fn' => 'housekeeping_clean_temp_files'],
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
