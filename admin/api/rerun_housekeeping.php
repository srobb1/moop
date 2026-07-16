<?php
/**
 * API endpoint: force a housekeeping run now, bypassing the 4h interval throttle.
 *
 * Why this exists: housekeeping results are precomputed and cached
 * (HOUSEKEEPING_MIN_INTERVAL, see lib/housekeeping.php), so a dashboard health card can
 * keep reporting something you have already fixed for up to 4 hours. That is correct for
 * the default path — the permission sweep and organism-tree walk are far too expensive to
 * run on every dashboard load — but it is maddening right after a fix. This gives the
 * admin a way to say "recheck now" without waiting or deleting files by hand.
 *
 * Auth + CSRF are handled by admin_init.php (verified on every POST).
 */

include_once __DIR__ . '/../admin_init.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

header('Content-Type: application/json');

// This is deliberately synchronous. The full sweep is a few seconds (the permission scan
// dominates), and the admin explicitly asked for it, so blocking until there is a real
// answer beats returning immediately with nothing to show.
@set_time_limit(120);

$started = microtime(true);
$result  = run_housekeeping(true);   // force: skip the throttle, still honour the lock
$elapsed = (int) round((microtime(true) - $started) * 1000);

if (!$result['ran']) {
    // The only reason a FORCED run does not run is the lock — another admin request is
    // already mid-sweep. Not an error; tell the truth and let them retry.
    echo json_encode([
        'success' => false,
        'reason'  => $result['reason'],
        'message' => $result['reason'] === 'already_running'
            ? 'Housekeeping is already running in another request — try again in a moment.'
            : 'Housekeeping did not run (' . ($result['reason'] ?? 'unknown') . ').',
    ]);
    exit;
}

$failed = array_values(array_filter($result['tasks'], fn($t) => !$t['ok']));

// Report per-task outcomes, not just "done" — the caller renders them as a list so the
// admin can see what actually ran and what each one cost.
echo json_encode([
    'success'     => true,
    'elapsed_ms'  => $elapsed,
    'task_count'  => count($result['tasks']),
    'failed'      => count($failed),
    'tasks'       => $result['tasks'],
    'message'     => $failed
        ? count($failed) . ' of ' . count($result['tasks']) . ' tasks failed — see the log'
        : 'All ' . count($result['tasks']) . ' tasks completed',
]);
