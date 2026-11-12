<?php
/**
 * Emergency account recovery from a shell — for when you cannot log in on the web.
 *
 * Uses this site's own config to find its users file (the `users_file` setting) and its
 * login-lockout record, so the same script works on every MOOP site: run the copy that
 * lives in that site's folder.
 *
 *   sudo -u apache php scripts/reset_password.php --list
 *   sudo -u apache php scripts/reset_password.php <username>     # new password, asked twice
 *   sudo -u apache php scripts/reset_password.php --unlock       # clear all login lockouts
 *   sudo -u apache php scripts/reset_password.php <username> --unlock
 *
 * Why `sudo -u apache`: the users file is readable only by the web server's user (mode 600),
 * and running as that user keeps the file's owner unchanged. Root also works (it writes the
 * file in place, so the owner and SELinux label stay as they are).
 *
 * Security: this grants nothing new. Whoever can run it can already read and write the
 * users file directly. It refuses to run from the web (see the guard below), and the
 * password is read from the terminal with echo off — never from the command line, where
 * it would land in shell history and in `ps`.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = dirname(__DIR__);
require_once $base . '/includes/config_init.php';
require_once $base . '/lib/functions_json.php';
require_once $base . '/lib/functions_login_protection.php';

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, "reset_password: $msg\n");
    exit($code);
}

function read_hidden(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    // Checked once, before anything is read: asking again after a piped read warns that
    // buffered input is lost. stream_isatty is core PHP (posix is not installed everywhere).
    static $tty = null;
    $tty ??= stream_isatty(STDIN);
    if ($tty) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($tty) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }
    if ($line === false) {
        fail('no password entered');
    }
    return rtrim($line, "\r\n");
}

$args     = array_slice($argv, 1);
$list     = in_array('--list', $args, true);
$unlock   = in_array('--unlock', $args, true);
$names    = array_values(array_filter($args, fn($a) => $a !== '' && $a[0] !== '-'));
$unknown  = array_diff(array_filter($args, fn($a) => $a !== '' && $a[0] === '-'), ['--list', '--unlock']);

if ($unknown || count($names) > 1 || (!$list && !$unlock && !$names)) {
    fwrite(STDERR, "Usage: sudo -u apache php scripts/reset_password.php [--list] [--unlock] [<username>]\n");
    exit(2);
}

$config    = ConfigManager::getInstance();
$usersFile = $config->getPath('users_file');
echo "Site folder: $base\nUsers file:  $usersFile\n";

if (!is_file($usersFile)) {
    fail("users file not found: $usersFile");
}
if (!is_readable($usersFile)) {
    // Name the file's owner without the posix extension (absent on some hosts).
    $owner = trim((string) shell_exec('id -nu ' . escapeshellarg((string) fileowner($usersFile)) . ' 2>/dev/null')) ?: 'apache';
    fail("cannot read the users file — run as $owner:  sudo -u $owner php " . implode(' ', $argv));
}

$raw   = file_get_contents($usersFile);
$users = json_decode($raw, true);
if (!is_array($users)) {
    fail('the users file is not valid JSON — not touching it. Restore it from the site-data backup.');
}

if ($list) {
    foreach ($users as $name => $u) {
        $role = $u['role'] ?? '';
        printf("  %-24s %s\n", $name, $role === 'admin' ? 'admin' : 'collaborator');
    }
}

if ($names) {
    $username = $names[0];
    if (!isset($users[$username])) {
        fail("no user named '$username' (use --list to see them)");
    }
    if (!is_writable($usersFile)) {
        fail('the users file is not writable by this user — run with sudo -u apache');
    }

    $pw = read_hidden("New password for $username: ");
    if (strlen($pw) < 8) {
        fail('password must be at least 8 characters — nothing changed');
    }
    if (read_hidden('Again: ') !== $pw) {
        fail('the two passwords differ — nothing changed');
    }

    $users[$username]['password'] = password_hash($pw, PASSWORD_DEFAULT);
    $json = json_encode($users, JSON_PRETTY_PRINT);
    if ($json === false) {
        fail('could not encode the users file — nothing changed');
    }
    // In place (not temp + rename) so the owner, mode and SELinux label stay exactly as they are.
    if (file_put_contents($usersFile, $json, LOCK_EX) === false) {
        fail('WRITE FAILED — the password was NOT changed');
    }
    // Read it back: a write that "succeeded" but did not land is the failure this project keeps meeting.
    $check = json_decode((string) file_get_contents($usersFile), true);
    if (!is_array($check) || !password_verify($pw, $check[$username]['password'] ?? '')) {
        fail('the file did not read back correctly — check it before relying on the new password');
    }
    echo "Password changed for $username.\n";
}

if ($unlock) {
    $attemptsFile = get_login_attempts_file();
    if (!is_file($attemptsFile)) {
        echo "No lockout record ($attemptsFile) — nobody is locked out.\n";
    } elseif (file_put_contents($attemptsFile, '{}', LOCK_EX) === false) {
        fail("could not clear $attemptsFile");
    } else {
        echo "Login lockouts cleared ($attemptsFile).\n";
    }
}
