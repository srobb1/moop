<?php
/**
 * Login Brute-Force Protection
 *
 * Tracks failed login attempts per username and IP address using a simple
 * JSON file in the logs directory. No database or external dependencies.
 *
 * Thresholds (conservative for a research site):
 *   - 5 failures  → 2-second delay on each attempt (slows automated attacks)
 *   - 10 failures → 15-minute lockout, attempt is blocked entirely
 *
 * Counters reset automatically on successful login.
 * Old lockout records (older than LOCKOUT_WINDOW) are pruned on each write.
 *
 * HOW TO USE in login.php:
 *   include_once __DIR__ . '/lib/functions_login_protection.php';
 *   $lockout = check_login_lockout($username, $visitor_ip);
 *   if ($lockout['locked']) { $error = $lockout['message']; }
 *   else {
 *     if (password_verify(...)) { reset_login_failures($username, $visitor_ip); ... }
 *     else { record_login_failure($username, $visitor_ip); $error = ...; }
 *   }
 */

define('LOGIN_ATTEMPTS_FILE_NAME', 'login_attempts.json');
define('LOCKOUT_THRESHOLD',        10);   // failures before lockout
define('DELAY_THRESHOLD',          5);    // failures before adding delay
define('LOCKOUT_WINDOW',           900);  // 15 minutes in seconds
define('LOCKOUT_DELAY_SECONDS',    2);    // sleep() duration after DELAY_THRESHOLD

/**
 * Return path to the login attempts JSON file.
 * Uses the configured error_log directory so it stays outside the web root.
 *
 * @return string Absolute path to attempts file
 */
function get_login_attempts_file() {
    $config   = ConfigManager::getInstance();
    $log_file = $config->getPath('error_log_file');          // e.g. /var/www/html/moop/logs/error.log
    $log_dir  = $log_file ? dirname($log_file) : sys_get_temp_dir();
    return $log_dir . '/' . LOGIN_ATTEMPTS_FILE_NAME;
}

/**
 * Load attempts data from disk.
 *
 * @return array Associative array keyed by identifier (username or IP)
 */
function load_login_attempts() {
    $file = get_login_attempts_file();
    if (!file_exists($file)) {
        return [];
    }
    $data = @json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/**
 * Save attempts data to disk, pruning stale records.
 *
 * @param array $attempts
 */
function save_login_attempts(array $attempts) {
    $now  = time();
    $file = get_login_attempts_file();

    // Remove records that have fully expired (outside lockout window)
    foreach ($attempts as $key => $record) {
        if (($now - ($record['last_attempt'] ?? 0)) > LOCKOUT_WINDOW) {
            unset($attempts[$key]);
        }
    }

    @file_put_contents($file, json_encode($attempts, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Build the lookup keys for a given username + IP combination.
 *
 * We track both independently so that:
 *   - Per-username:  catches password-spraying on a single account from many IPs
 *   - Per-IP:        catches credential-stuffing from a single IP against many accounts
 *
 * @param string $username
 * @param string $ip
 * @return array ['username_key' => string, 'ip_key' => string]
 */
function get_attempt_keys($username, $ip) {
    return [
        'username_key' => 'user:' . strtolower(trim($username)),
        'ip_key'       => 'ip:'   . $ip,
    ];
}

/**
 * Check whether a login attempt should be blocked or delayed.
 *
 * Call this BEFORE checking the password. Returns an array:
 *   ['locked'  => bool,   true if attempt must be blocked]
 *   ['delayed' => bool,   true if sleep() was called]
 *   ['message' => string, human-readable reason if locked]
 *   ['remaining_seconds' => int, seconds until lockout expires (if locked)]
 *
 * @param string $username
 * @param string $ip
 * @return array
 */
function check_login_lockout($username, $ip) {
    $attempts = load_login_attempts();
    $keys     = get_attempt_keys($username, $ip);
    $now      = time();
    $result   = ['locked' => false, 'delayed' => false, 'message' => '', 'remaining_seconds' => 0];

    foreach ([$keys['username_key'], $keys['ip_key']] as $key) {
        $record = $attempts[$key] ?? null;
        if (!$record) {
            continue;
        }

        $count       = (int)($record['count'] ?? 0);
        $last        = (int)($record['last_attempt'] ?? 0);
        $elapsed     = $now - $last;

        // Expired window - treat as clean slate
        if ($elapsed > LOCKOUT_WINDOW) {
            continue;
        }

        if ($count >= LOCKOUT_THRESHOLD) {
            $remaining = LOCKOUT_WINDOW - $elapsed;
            $minutes   = (int)ceil($remaining / 60);
            $result['locked']            = true;
            $result['remaining_seconds'] = $remaining;
            $result['message']           = "Too many failed login attempts. Please try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.';
            return $result;
        }

        if ($count >= DELAY_THRESHOLD) {
            sleep(LOCKOUT_DELAY_SECONDS);
            $result['delayed'] = true;
        }
    }

    return $result;
}

/**
 * Record a failed login attempt for both the username and IP.
 *
 * Call this AFTER a failed password_verify().
 *
 * @param string $username
 * @param string $ip
 */
function record_login_failure($username, $ip) {
    $attempts = load_login_attempts();
    $keys     = get_attempt_keys($username, $ip);
    $now      = time();

    foreach ([$keys['username_key'], $keys['ip_key']] as $key) {
        $record = $attempts[$key] ?? ['count' => 0, 'last_attempt' => $now];

        // Reset count if the window has expired
        if (($now - (int)$record['last_attempt']) > LOCKOUT_WINDOW) {
            $record['count'] = 0;
        }

        $record['count']++;
        $record['last_attempt'] = $now;
        $attempts[$key]         = $record;
    }

    save_login_attempts($attempts);

    // Log repeated failures so admins can see patterns in server logs
    $count = $attempts[$keys['username_key']]['count'] ?? 0;
    if ($count >= DELAY_THRESHOLD) {
        error_log("MOOP login: {$count} failed attempt(s) for username '" . htmlspecialchars($username, ENT_QUOTES) . "' from IP {$ip}");
    }
}

/**
 * Clear failure counters after a successful login.
 *
 * @param string $username
 * @param string $ip
 */
function reset_login_failures($username, $ip) {
    $attempts = load_login_attempts();
    $keys     = get_attempt_keys($username, $ip);

    unset($attempts[$keys['username_key']], $attempts[$keys['ip_key']]);

    save_login_attempts($attempts);
}
