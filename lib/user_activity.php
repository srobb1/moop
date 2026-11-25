<?php
/**
 * Account activity: when each user account was created, when it last logged in, and how often (2026-11-25).
 *
 *   login.php                 moop_user_activity_login() after a successful password check
 *   admin/manage_users.php    moop_user_activity_created() on create, moop_user_activity_forget() on delete;
 *                             the Users table shows Created / Last login / Logins
 *   scripts/backfill_user_created.php  dates for accounts made before this existed, from the site-data
 *                             backup's git history (the first snapshot that has the account)
 *
 * WHERE: {dirname(users_file)}/user_activity.json -- the private folder, next to users.json, NOT inside it.
 * users.json is rewritten whole by Manage Users with no lock; a login writing there too could race an
 * admin edit and lose one of the two changes in the file that holds the password hashes. A separate
 * file, rewritten under an flock (temp file + rename, as lib/feedback.php), keeps the credentials file
 * admin-written only. It names users and their login times, so it is treated like credentials: 0640,
 * a 'sensitive' permission rule, and copied to the private site-data backup.
 *
 * Shape: { "username": { "created": ts|null, "created_how": "manage_users"|"backup_history",
 *          "last_login": ts, "login_count": n, "logins": [ts, ...] (the most recent MOOP_USER_ACTIVITY_KEEP) } }
 * Counts LOGINS (a password typed on the login page), not visits: a session can last for days, and
 * address-range (IP_IN_RANGE) visitors have no account and never log in.
 *
 * A failed write never blocks a login -- it is logged (error_log) and the login goes ahead.
 */
const MOOP_USER_ACTIVITY_KEEP = 200;   // timestamps kept per user, enough for "logins in the last 90 days"

/** The file, or '' when the site has no private folder configured. $file overrides (tests). */
function moop_user_activity_file(?string $file = null): string {
    if ($file !== null) return $file;
    $users = (string) ConfigManager::getInstance()->getPath('users_file');
    return $users === '' ? '' : dirname($users) . '/user_activity.json';
}

/** Everything, [username => record]. A missing file is empty; an unreadable / broken one is null. */
function moop_user_activity_all(?string $file = null): ?array {
    $f = moop_user_activity_file($file);
    if ($f === '' || !file_exists($f)) return [];
    $raw = @file_get_contents($f);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

/** Change the file under its lock: $change(array &$all) edits it in place. False on any failure. */
function moop_user_activity_update(callable $change, ?string $file = null): bool {
    $f = moop_user_activity_file($file);
    if ($f === '') return false;
    $lock = @fopen($f . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) return false;
    try {
        $all = moop_user_activity_all($file);
        if ($all === null) return false;                 // never overwrite a file we could not read
        $change($all);
        $tmp  = $f . '.tmp' . getmypid();
        $json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents($tmp, $json . "\n") === false) { @unlink($tmp); return false; }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
        return true;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** A successful login. Logged, never fatal, on failure. */
function moop_user_activity_login(string $username, ?string $file = null, ?int $now = null): bool {
    $now = $now ?? time();
    $ok = moop_user_activity_update(function (array &$all) use ($username, $now) {
        $r = $all[$username] ?? [];
        $r['last_login']  = $now;
        $r['login_count'] = (int) ($r['login_count'] ?? 0) + 1;
        $r['logins']      = array_slice(array_merge($r['logins'] ?? [], [$now]), -MOOP_USER_ACTIVITY_KEEP);
        $all[$username]   = $r + ['created' => null];
    }, $file);
    if (!$ok) error_log("MOOP user activity: could not record a login for '$username' in " . moop_user_activity_file($file));
    return $ok;
}

/** An account made on Manage Users. $how: 'manage_users' (now) or 'backup_history' (backfill, keeps an earlier date). */
function moop_user_activity_created(string $username, ?int $when = null, string $how = 'manage_users', ?string $file = null): bool {
    $when = $when ?? time();
    $ok = moop_user_activity_update(function (array &$all) use ($username, $when, $how) {
        $r = $all[$username] ?? [];
        // A backfill never replaces a date Manage Users recorded; a new account (same name re-made) does.
        if ($how === 'backup_history' && !empty($r['created']) && ($r['created_how'] ?? '') === 'manage_users') return;
        $r['created'] = $when; $r['created_how'] = $how;
        $all[$username] = $r;
    }, $file);
    if (!$ok) error_log("MOOP user activity: could not record the creation of '$username'");
    return $ok;
}

/** A deleted account: its record goes too (a new account with the same name starts fresh). */
function moop_user_activity_forget(string $username, ?string $file = null): bool {
    return moop_user_activity_update(function (array &$all) use ($username) { unset($all[$username]); }, $file);
}

/** Logins in the last $days days, from the kept timestamps. */
function moop_user_activity_recent(array $record, int $days, ?int $now = null): int {
    $since = ($now ?? time()) - $days * 86400;
    return count(array_filter($record['logins'] ?? [], fn($t) => $t >= $since));
}
