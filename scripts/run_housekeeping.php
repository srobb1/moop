<?php
/**
 * Housekeeping runner — the background child.
 *
 * Normally you never run this by hand. lib/housekeeping.php spawns it detached from an
 * admin page load (housekeeping_spawn_background()), so the ~4.5s of work happens out of
 * the request path and the admin's page returns immediately.
 *
 * ── Do NOT put this in cron ──────────────────────────────────────────────────────
 * It would appear to work and quietly do the wrong thing. php-fpm runs with
 * PrivateTmp=yes, so it has its OWN /tmp. The clean_temp_files task targets
 * sys_get_temp_dir(); spawned from php-fpm it inherits that namespace and cleans the
 * right directory, but from cron it would see the real /tmp — which never contains the
 * web-created BLAST scratch files — clean nothing, and report success.
 *
 * File ownership is the second trap: run as a non-web user, this writes
 * logs/.housekeeping_status.json as that user, and php-fpm can then no longer update it.
 * The guard below refuses rather than let that happen silently.
 *
 * Housekeeping is deliberately cron-free: MOOP is meant to be set up by biologists, not
 * sysadmins, and "no cron to configure" is a feature.
 *
 * Usage (both do the same thing; --spawned only affects logging):
 *     php scripts/run_housekeeping.php
 *     sudo -u apache php scripts/run_housekeeping.php     # if running it by hand
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = dirname(__DIR__);
require_once $base . '/includes/config_init.php';
require_once $base . '/lib/housekeeping.php';

$spawned = in_array('--spawned', $argv ?? [], true);

// ── Guard: must run as the web server user ──────────────────────────────────────
// Anything this writes must stay writable by php-fpm. Getting this wrong does not fail
// loudly — it leaves a status file php-fpm cannot update, and the dashboard silently
// freezes on whatever numbers were last written.
$expected = 'apache';
if (function_exists('getWebServerUser')) {
    $w = getWebServerUser();
    // Under CLI this reports the CURRENT user, so it is only a hint — not a source of
    // truth. Fall back to the conventional web user.
    $expected = ($w['user'] ?? '') !== '' && PHP_SAPI !== 'cli' ? $w['user'] : 'apache';
}

$me = trim((string) @shell_exec('id -un 2>/dev/null'));
if ($me !== '' && $me !== $expected && !$spawned) {
    fwrite(STDERR, "\nRefusing to run as '$me' — housekeeping must run as the web server user ('$expected').\n\n");
    fwrite(STDERR, "  Files written now would be owned by '$me', and php-fpm could no longer\n");
    fwrite(STDERR, "  update them. The dashboard would then freeze on stale numbers, silently.\n\n");
    fwrite(STDERR, "  Run it as the web user instead:\n");
    fwrite(STDERR, "      sudo -u $expected php " . __FILE__ . "\n\n");
    fwrite(STDERR, "  Or just let it happen on its own: it runs automatically in the background\n");
    fwrite(STDERR, "  when an admin loads an admin page, and the Admin Dashboard has a\n");
    fwrite(STDERR, "  \"Run housekeeping now\" button.\n\n");
    exit(1);
}

$started = microtime(true);
$result  = housekeeping_run_tasks();
$elapsed = round(microtime(true) - $started, 1);

if (!$result['ran']) {
    $msg = 'MOOP housekeeping: skipped (' . ($result['reason'] ?? 'unknown') . ')';
    error_log($msg);
    if (!$spawned) fwrite(STDOUT, $msg . "\n");
    exit(0);
}

$failed = array_values(array_filter($result['tasks'], fn($t) => !$t['ok']));
$summary = 'MOOP housekeeping: ' . count($result['tasks']) . ' tasks in ' . $elapsed . 's'
         . ($failed ? ', ' . count($failed) . ' FAILED' : '');
error_log($summary);

// Interactive run: show per-task detail. The spawned child logs the summary only —
// nobody is reading its stdout, and it goes to /dev/null anyway.
if (!$spawned) {
    fwrite(STDOUT, $summary . "\n");
    foreach ($result['tasks'] as $t) {
        fwrite(STDOUT, sprintf("  %s %-28s %5d ms%s\n",
            $t['ok'] ? 'ok ' : 'ERR', $t['name'], $t['ms'],
            $t['error'] ? ' — ' . $t['error'] : ''));
    }
}

exit($failed ? 1 : 0);
