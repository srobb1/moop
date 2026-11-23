<?php
/**
 * Messages from visitors -- questions, data requests, problems (2026-11-23).
 *
 *   tools/feedback.php        the form (footer link "Send us a message"; ?from=<page> fills in where they were)
 *   admin/manage_feedback.php the inbox: read, mark answered / done, reply by email
 *
 * WHERE: {dirname(users_file)}/feedback.json -- the site's PRIVATE folder, next to users.json, outside
 * the document root. Messages may carry an email address, so they are treated like credentials:
 * 0640, never world-readable, a 'sensitive' permission rule (lib/permission_check.php), and copied to
 * the private site-data backup by housekeeping_snapshot_site_data(). The folder is already labelled
 * httpd_sys_rw_content_t (scripts/fix_moop_selinux.sh, PRIVATE).
 *
 * One small JSON file, rewritten whole under an flock: a site gets a handful a week, and the inbox
 * needs to change a message's status. A write writes a temp file and renames it, so a failed write
 * never leaves half a file -- and moop_feedback_add() REPORTS failure; the form never says "sent"
 * for a message that was not saved (CLAUDE.md §11: silent write failures).
 *
 * Abuse: the form is behind the human check like every page; plus a hidden honeypot field, a
 * per-session limit (5 an hour) and a site-wide cap (MOOP_FEEDBACK_DAY_CAP a day).
 */

const MOOP_FEEDBACK_KINDS   = ['question', 'data', 'problem', 'other'];
const MOOP_FEEDBACK_MAX_LEN = 5000;
const MOOP_FEEDBACK_DAY_CAP = 100;
const MOOP_FEEDBACK_STATUSES = ['new', 'answered', 'done'];

/** The file, or '' when the site has no private folder configured. $file overrides (tests). */
function moop_feedback_file(?string $file = null): string {
    if ($file !== null) return $file;
    $users = (string) ConfigManager::getInstance()->getPath('users_file');
    return $users === '' ? '' : dirname($users) . '/feedback.json';
}

/** All messages, newest first. A missing file is an empty inbox; an unreadable one is an error (null). */
function moop_feedback_all(?string $file = null): ?array {
    $f = moop_feedback_file($file);
    if ($f === '' || !file_exists($f)) return [];
    $raw = @file_get_contents($f);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    usort($d, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
    return $d;
}

/** Count of messages still marked new (the dashboard banner). */
function moop_feedback_new_count(?string $file = null): int {
    return count(array_filter(moop_feedback_all($file) ?? [], fn($m) => ($m['status'] ?? 'new') === 'new'));
}

/**
 * Check what a visitor sent. Returns [clean message array, error list]. Never trusts the client:
 * lengths cut, control characters removed, the page reduced to a path on this site.
 */
function moop_feedback_validate(array $in): array {
    $clean = fn($s, $n) => mb_substr(trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]+/u', '', (string) $s)), 0, $n);
    $kind = in_array($in['kind'] ?? '', MOOP_FEEDBACK_KINDS, true) ? $in['kind'] : 'other';
    $text = $clean($in['message'] ?? '', MOOP_FEEDBACK_MAX_LEN);
    $name = $clean(str_replace(["\r", "\n"], ' ', (string) ($in['name'] ?? '')), 120);
    $email = $clean($in['email'] ?? '', 200);
    $page = (string) ($in['from'] ?? '');
    // Only a path on this site: no scheme, no host, no "//".
    $page = (preg_match('#^/(?!/)[^\s<>"\'\\\\]{0,300}$#', $page)) ? $page : '';
    $errors = [];
    if (mb_strlen($text) < 3) $errors[] = 'message';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
    return [['kind' => $kind, 'message' => $text, 'name' => $name, 'email' => $email, 'page' => $page], $errors];
}

/**
 * Save one message. $who = ['user' => username or '', 'level' => access level].
 * Returns 'ok', 'cap' (site-wide daily cap reached) or 'error' (could not write).
 */
function moop_feedback_add(array $msg, array $who, ?string $file = null, ?int $now = null): string {
    $f = moop_feedback_file($file);
    if ($f === '' || !is_dir(dirname($f))) return 'error';
    $now = $now ?? time();
    $lock = @fopen($f . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) return 'error';
    try {
        $all = moop_feedback_all($file);
        if ($all === null) return 'error';                 // never overwrite a file we could not read
        $today = date('Y-m-d', $now);
        if (count(array_filter($all, fn($m) => date('Y-m-d', $m['ts'] ?? 0) === $today)) >= MOOP_FEEDBACK_DAY_CAP) return 'cap';
        $all[] = $msg + [
            'id' => bin2hex(random_bytes(6)), 'ts' => $now, 'status' => 'new',
            'user' => (string) ($who['user'] ?? ''), 'level' => (string) ($who['level'] ?? 'PUBLIC'),
        ];
        return moop_feedback_write($f, $all) ? 'ok' : 'error';
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Change one message's status (or delete it: $status === 'delete'). Returns true when saved. */
function moop_feedback_set_status(string $id, string $status, ?string $file = null): bool {
    if ($status !== 'delete' && !in_array($status, MOOP_FEEDBACK_STATUSES, true)) return false;
    $f = moop_feedback_file($file);
    $lock = @fopen($f . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) return false;
    try {
        $all = moop_feedback_all($file);
        if ($all === null) return false;
        $found = false;
        foreach ($all as $i => $m) {
            if (($m['id'] ?? '') !== $id) continue;
            $found = true;
            if ($status === 'delete') unset($all[$i]);
            else { $all[$i]['status'] = $status; $all[$i]['status_ts'] = time(); }
        }
        return $found && moop_feedback_write($f, array_values($all));
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Temp file + rename, 0640. False on any failure. */
function moop_feedback_write(string $f, array $all): bool {
    $tmp = $f . '.tmp' . getmypid();
    $json = json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || @file_put_contents($tmp, $json . "\n") === false) { @unlink($tmp); return false; }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
    return true;
}
