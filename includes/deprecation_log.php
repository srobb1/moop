<?php
/**
 * DEPRECATION CAPTURE — record what PHP already says is going away.
 *
 * This host's error_reporting excludes E_DEPRECATED (php.ini: E_ALL & ~E_DEPRECATED &
 * ~E_STRICT), so a deprecated call left no trace anywhere: it would surface only as a fatal
 * on the PHP version that removes it. This handler sees deprecations regardless of
 * error_reporting (PHP calls a handler for every level in its mask) and records each
 * DISTINCT one -- message + file + line -- once, as a small file in logs/deprecations/.
 * One file per signature keeps it bounded: a deprecation hit on every request writes once,
 * then only refreshes its "last seen" time at most hourly. The dashboard's PHP
 * Deprecations card lists them.
 *
 * It changes nothing a visitor sees: it handles only E_DEPRECATED / E_USER_DEPRECATED,
 * displays nothing, and lets PHP carry on as before. Self-contained like fatal_log.php --
 * no ConfigManager, no lib -- and every write is best-effort.
 */

if (!function_exists('moop_record_deprecation')) {

    function moop_deprecations_dir(): string
    {
        return dirname(__DIR__) . '/logs/deprecations';
    }

    function moop_record_deprecation(int $errno, string $message, string $file, int $line): bool
    {
        $dir  = moop_deprecations_dir();
        $sig  = md5($message . '|' . $file . '|' . $line);
        $path = "$dir/$sig.json";
        if (is_file($path)) {
            if (time() - (int)@filemtime($path) > 3600) @touch($path);   // "last seen"
            return true;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 02775, true);
            @chmod($dir, 02775);   // umask would leave 0750 when the CLI creates it, and php-fpm could not enter
        }
        $root = dirname(__DIR__) . '/';
        @file_put_contents($path, json_encode([
            'message'    => $message,
            'file'       => strpos($file, $root) === 0 ? substr($file, strlen($root)) : $file,
            'line'       => $line,
            'php'        => PHP_VERSION,
            'first_seen' => date('c'),
            'page'       => $_SERVER['REQUEST_URI'] ?? '(cli)',
        ]));
        @chmod($path, 0664);
        return true;   // handled; PHP's own handler would not have shown it either
    }

    set_error_handler(function (int $errno, string $message, string $file = '', int $line = 0) {
        return moop_record_deprecation($errno, $message, $file, $line);
    }, E_DEPRECATED | E_USER_DEPRECATED);
}
