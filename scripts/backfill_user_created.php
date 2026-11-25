<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
/**
 * Give accounts made before 2026-11-25 a "Created" date (lib/user_activity.php): the date of the first
 * site-data backup snapshot (a git commit of users.json in site_data_path) that has the account.
 * That is when the backup first SAW it -- the account may be a little older (the hourly snapshot,
 * and a commit is made by hand), so Manage Users marks these dates "≈". An account already in the
 * very first snapshot was made on or before that date.
 *
 * Never replaces a date Manage Users recorded. Accounts no longer in users.json are skipped.
 *
 *   sudo -u apache php scripts/backfill_user_created.php            # show what it would set
 *   sudo -u apache php scripts/backfill_user_created.php --write    # save it
 *
 * Runs as apache because users.json and user_activity.json are readable by the web server only.
 */
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/user_activity.php';
require_once __DIR__ . '/../lib/functions_system.php';

$write  = in_array('--write', $argv, true);
$config = ConfigManager::getInstance();
$users_raw = @file_get_contents((string) $config->getPath('users_file'));
$users = $users_raw === false ? null : json_decode($users_raw, true);
if (!is_array($users)) { fwrite(STDERR, "Cannot read users.json -- run as the web user: sudo -u apache php " . $argv[0] . "\n"); exit(1); }

$repo = rtrim((string) $config->getPath('site_data_path'), '/');
$git  = moop_find_executable('git') ?: 'git';
if ($repo === '' || !is_dir("$repo/.git")) { fwrite(STDERR, "No site-data git repo at '$repo'.\n"); exit(1); }
// The repo belongs to another user; say it is trusted for this one command only.
$g = fn(string $args) => $git . ' -c safe.directory=' . escapeshellarg($repo) . ' -C ' . escapeshellarg($repo) . ' ' . $args;

exec($g('log --reverse --format=%H\ %ct -- users.json'), $commits, $rc);
if ($rc !== 0 || !$commits) { fwrite(STDERR, "git log found no snapshots of users.json in $repo.\n"); exit(1); }

// The first snapshot each username appears in.
$first = [];
$oldest = (int) explode(' ', $commits[0])[1];
foreach ($commits as $line) {
    [$hash, $ts] = explode(' ', $line);
    $out = []; exec($g('show ' . escapeshellarg("$hash:users.json")), $out, $rc);
    $snap = $rc === 0 ? json_decode(implode("\n", $out), true) : null;
    if (!is_array($snap)) continue;
    foreach (array_keys($snap) as $u) $first[$u] = $first[$u] ?? (int) $ts;
}

$act = moop_user_activity_all() ?? [];
$n = 0;
foreach (array_keys($users) as $u) {
    $have = $act[$u] ?? [];
    if (!empty($have['created']) && ($have['created_how'] ?? '') === 'manage_users') { printf("  %-24s kept   %s (recorded by Manage Users)\n", $u, date('Y-m-d', $have['created'])); continue; }
    if (!isset($first[$u])) { printf("  %-24s none   not in any snapshot (made after the last backup commit?)\n", $u); continue; }
    printf("  %-24s %s %s%s\n", $u, $write ? 'set ' : 'would', date('Y-m-d', $first[$u]),
           $first[$u] === $oldest ? '  (in the first snapshot: made on or before this date)' : '');
    if ($write && !moop_user_activity_created($u, $first[$u], 'backup_history')) { fwrite(STDERR, "WRITE FAILED for $u\n"); exit(1); }
    $n++;
}
echo $write ? "Saved $n dates.\n" : "$n dates found. Nothing saved -- add --write to save them.\n";
