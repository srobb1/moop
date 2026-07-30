<?php
/**
 * FATAL ERROR CAPTURE — make PHP fatals visible without root.
 *
 * Until this existed, a PHP fatal on this deployment left NO trace anyone could read.
 * nginx maps 500/502/503/504 to one page ("The page you are looking for is temporarily
 * unavailable"), so the browser cannot tell a timeout from a crash; the real message goes
 * to /var/log/php-fpm/www-error.log, which is root-only; and MOOP's own logError() is
 * never reached, because a fatal skips the rest of the request.
 *
 * That combination cost a whole debugging session on 2026-07-30: an intermittent 500 on a
 * gene page, with the page loading fine every time it was retried, and no way to tell
 * whether it had been slow (504 / max_execution_time) or had crashed (500). Every
 * hypothesis had to be tested by re-measuring instead of read off a log line.
 *
 * WHAT IT RECORDS, and why each field earns its place:
 *   elapsed_s     php-fpm here runs max_execution_time = 30 (the CLI reports 0, so this
 *                 is easy to get wrong). A fatal at ~30 s with 'Maximum execution time'
 *                 is a timeout; the same message at 0.4 s is a real bug. Nothing else
 *                 distinguishes them after the fact.
 *   peak_memory   memory_limit is 128M. An allocation fatal names the limit, but the peak
 *                 tells you how close ordinary requests run to it.
 *   type          E_ERROR vs E_PARSE vs E_COMPILE_ERROR. A COMPILE_ERROR is usually an
 *                 unreadable include -- e.g. a file left at mode 640, which is a
 *                 documented trap in this repo (CLAUDE.md section 11).
 *
 * DELIBERATELY SELF-CONTAINED. It does not call logError(), ConfigManager, or any lib:
 * the most valuable fatal to capture is one thrown while those are still loading, and a
 * handler that depends on the thing that broke records nothing. The log path is resolved
 * from __DIR__, and every write is best-effort -- a handler must never itself fatal.
 *
 * Output is one JSON object per line, matching logError()'s shape, so the admin error-log
 * viewer renders these alongside application errors with no changes.
 */

if (!function_exists('moop_log_fatal')) {

    /**
     * Append one entry to logs/error.log. Best-effort: silent on any failure.
     */
    function moop_log_fatal(array $entry): void
    {
        $log_file = dirname(__DIR__) . '/logs/error.log';
        $line = @json_encode($entry);
        if ($line === false) return;
        @error_log($line . "\n", 3, $log_file);
    }

    /**
     * Shared field set for both handlers.
     */
    function moop_fatal_context(): array
    {
        $started = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        return [
            'timestamp'   => date('Y-m-d H:i:s'),
            'user'        => $_SESSION['username'] ?? 'anonymous',
            'page'        => $_SERVER['REQUEST_URI'] ?? '(cli)',
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
            'elapsed_s'   => $started ? round(microtime(true) - $started, 2) : null,
            'peak_memory' => round(memory_get_peak_usage(true) / 1048576, 1) . ' MB',
            'limit'       => ini_get('memory_limit'),
            'max_exec'    => ini_get('max_execution_time'),
        ];
    }

    // Uncaught exceptions: these are fatal too, and carry a stack trace worth keeping.
    //
    // The 500 MUST be set by hand here. Installing an exception handler makes PHP consider
    // the exception handled, so it stops setting the status itself and the response goes
    // out as 200 -- verified: before this line, a page that threw returned HTTP 200 with a
    // truncated body. That is strictly worse than the crash it was meant to record,
    // because monitoring, curl and the browser would all call it a success.
    set_exception_handler(function ($e) {
        moop_log_fatal(array_merge(moop_fatal_context(), [
            'error'   => 'Uncaught ' . get_class($e) . ': ' . $e->getMessage(),
            'context' => 'php_uncaught_exception',
            'details' => [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
            ],
        ]));
        if (!headers_sent()) {
            http_response_code(500);
        }
    });

    // Fatals proper. Only the error types that actually END the request -- warnings and
    // notices are left alone, so this never becomes a firehose that hides the real thing.
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e === null) return;
        $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING
               | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR;
        if (!($e['type'] & $fatal)) return;

        $names = [
            E_ERROR           => 'E_ERROR',
            E_PARSE           => 'E_PARSE',
            E_CORE_ERROR      => 'E_CORE_ERROR',
            E_CORE_WARNING    => 'E_CORE_WARNING',
            E_COMPILE_ERROR   => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR      => 'E_USER_ERROR',
        ];

        moop_log_fatal(array_merge(moop_fatal_context(), [
            'error'   => $e['message'],
            'context' => 'php_fatal',
            'details' => [
                'type' => $names[$e['type']] ?? $e['type'],
                'file' => $e['file'],
                'line' => $e['line'],
            ],
        ]));
    });
}
