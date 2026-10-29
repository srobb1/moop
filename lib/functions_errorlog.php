<?php
/**
 * Error Logging Functions
 * Centralized error logging and retrieval for admin viewing
 */

/**
 * Log an error to the error log file
 * 
 * @param string $error_message The error message to log
 * @param string $context Optional context (e.g., organism name, page name)
 * @param array $additional_info Additional details to log
 * @return void
 */
function logError($error_message, $context = '', $additional_info = []) {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath('error_log_file');
    
    $error_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $error_message,
        'context' => $context,
        'user' => $_SESSION['username'] ?? 'anonymous',
        'page' => $_SERVER['REQUEST_URI'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'details' => $additional_info
    ];
    
    $log_line = json_encode($error_entry) . "\n";
    
    if ($log_file) {
        error_log($log_line, 3, $log_file);
    }
}

/**
 * Get error log entries
 * 
 * @param int $limit Maximum number of entries to retrieve (0 = all)
 * @return array Array of error entries
 */
function getErrorLog($limit = 0) {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath('error_log_file');
    
    if (!$log_file || !file_exists($log_file)) {
        return [];
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }
    
    $errors = [];
    foreach (array_reverse($lines) as $line) {
        $error = json_decode($line, true);
        if ($error) {
            $errors[] = $error;
        }
    }
    
    if ($limit > 0) {
        $errors = array_slice($errors, 0, $limit);
    }
    
    return $errors;
}

/**
 * Clear the error log file
 * 
 * @return bool True if successful
 */
function clearErrorLog() {
    $config = ConfigManager::getInstance();
    $log_file = $config->getPath('error_log_file');
    
    if (!$log_file) {
        return false;
    }
    
    return file_put_contents($log_file, '') !== false;
}

/**
 * How long an entry is kept. Entries name the user and the address, so they are personal
 * data like the System Monitor's request log (also 30 days); housekeeping trims them.
 */
const MOOP_ERROR_LOG_RETENTION_DAYS = 30;

/**
 * Where an entry came from, for the page: a PHP crash (includes/fatal_log.php) or a problem
 * MOOP noticed and reported itself (logError()).
 */
function moop_error_log_kind(array $entry): string {
    return in_array($entry['context'] ?? '', ['php_fatal', 'php_uncaught_exception'], true) ? 'crash' : 'reported';
}

/** "file:line" for a crash, relative to the site; '' when the entry has no location. */
function moop_error_log_where(array $entry): string {
    $d = is_array($entry['details'] ?? null) ? $entry['details'] : [];
    if (empty($d['file'])) return '';
    $file = (string)$d['file'];
    $root = dirname(__DIR__) . '/';
    if (strpos($file, $root) === 0) $file = substr($file, strlen($root));
    return $file . (isset($d['line']) ? ':' . (int)$d['line'] : '');
}

/**
 * The identity of an error: same message from the same FILE = the same problem, however often
 * it happened. Used to group the page and to remove one problem once it is fixed.
 *
 * Numbers are ignored, and so is the line: "Allowed memory size of 134217728 bytes exhausted
 * (tried to allocate 19017728 bytes)" is the same problem at every size and on every line of
 * the template that happened to be running — it showed as four rows before (2026-10-29).
 */
function moop_error_log_key(array $entry): string {
    $where = preg_replace('/:\d+$/', '', moop_error_log_where($entry));
    $msg   = preg_replace('/\d+/', 'N', (string)($entry['error'] ?? ''));
    return substr(sha1($msg . '|' . $where . '|' . moop_error_log_kind($entry)), 0, 16);
}

/**
 * Group entries (newest first, as getErrorLog() returns them) by moop_error_log_key().
 *
 * @return list<array{key: string, error: string, kind: string, where: string, count: int,
 *                    first: string, last: string, pages: int, latest: array}> newest problem first
 */
function moop_error_log_groups(array $entries): array {
    $groups = [];
    foreach ($entries as $e) {
        $k = moop_error_log_key($e);
        if (!isset($groups[$k])) {
            $groups[$k] = ['key' => $k, 'error' => (string)($e['error'] ?? ''), 'kind' => moop_error_log_kind($e),
                           'where' => moop_error_log_where($e), 'count' => 0, 'first' => '', 'last' => (string)($e['timestamp'] ?? ''),
                           'pages' => [], 'latest' => $e];
        }
        $g = &$groups[$k];
        $g['count']++;
        $g['first'] = (string)($e['timestamp'] ?? $g['first']);   // entries arrive newest first
        if (!empty($e['page'])) $g['pages'][strtok((string)$e['page'], '?')] = true;
        unset($g);
    }
    foreach ($groups as &$g) $g['pages'] = count($g['pages']);
    unset($g);
    return array_values($groups);
}

/**
 * Rewrite the log keeping only the entries $keep accepts. Returns how many were removed, or
 * null if the file could not be rewritten.
 *
 * IN PLACE, under an exclusive lock — never a temp file renamed over it. A rename gives the log
 * the new file's owner and SELinux label; run once as root (a manual housekeeping run) it
 * would leave a log the web server can no longer append to, and every later error would be
 * lost silently. An entry appended by another request mid-rewrite can still be lost (appends
 * do not take the lock); this runs on an admin's click or once an hour, so that window is tiny.
 */
function moop_error_log_rewrite(callable $keep, ?string $log_file = null): ?int {
    $log_file = $log_file ?? ConfigManager::getInstance()->getPath('error_log_file');
    if (!$log_file || !is_file($log_file)) return 0;
    $fh = @fopen($log_file, 'c+');
    if ($fh === false) return null;
    try {
        if (!flock($fh, LOCK_EX)) return null;
        $kept = [];
        $total = 0;
        while (($line = fgets($fh)) !== false) {
            if (trim($line) === '') continue;
            $total++;
            $e = json_decode($line, true);
            if (!is_array($e) || $keep($e)) $kept[] = rtrim($line, "\n");   // unreadable lines are kept, never dropped
        }
        $removed = $total - count($kept);
        if ($removed === 0) return 0;
        $body = $kept ? implode("\n", $kept) . "\n" : '';
        if (!rewind($fh) || !ftruncate($fh, 0) || fwrite($fh, $body) !== strlen($body)) return null;
        fflush($fh);
        return $removed;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/** Remove every entry of one problem (moop_error_log_key()) — the "Fixed — remove" button. */
function moop_error_log_remove_group(string $key, ?string $log_file = null): ?int {
    return moop_error_log_rewrite(fn(array $e) => moop_error_log_key($e) !== $key, $log_file);
}

/** Drop entries older than MOOP_ERROR_LOG_RETENTION_DAYS (housekeeping). */
function moop_error_log_prune(?int $now = null, ?string $log_file = null): ?int {
    $cutoff = date('Y-m-d H:i:s', ($now ?? time()) - MOOP_ERROR_LOG_RETENTION_DAYS * 86400);
    return moop_error_log_rewrite(fn(array $e) => (string)($e['timestamp'] ?? '') >= $cutoff, $log_file);
}
