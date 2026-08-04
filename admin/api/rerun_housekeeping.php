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

// Optional targeted re-run: `task=permission_check` runs THAT task alone.
//
// Why targeted matters: the dashboard's permission card is a cached snapshot, so right
// after fixing something in the Permission Manager it keeps reporting the problem you
// just fixed. The full sweep answers that, but it also re-walks the organism tree, hits
// the annotation caches and can kick off an organism-cache rebuild — a lot of work to
// refresh one card. Running just the one task is seconds instead, which is what makes it
// reasonable to fire automatically after a fix.
//
// Validated against the registry (not passed through to call_user_func), so this cannot
// become a way to invoke an arbitrary function name.
$only = [];
$requested = isset($_POST['task']) ? trim((string) $_POST['task']) : '';
if ($requested !== '') {
    $known = array_column(housekeeping_task_registry(), 'name');
    if (!in_array($requested, $known, true)) {
        echo json_encode([
            'success' => false,
            'reason'  => 'no_such_task',
            'message' => 'Unknown housekeeping task: ' . $requested,
        ]);
        exit;
    }
    $only = [$requested];
}

// housekeeping_run_tasks() does the work inline and ignores the interval — the throttle
// is for the automatic path, and the admin has explicitly asked. It still honours the
// lock, so this cannot stampede a background run already in flight.
$started = microtime(true);
$result  = housekeeping_run_tasks($only);
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
$response = [
    'success'     => true,
    'elapsed_ms'  => $elapsed,
    'task_count'  => count($result['tasks']),
    'failed'      => count($failed),
    'tasks'       => $result['tasks'],
    'partial'     => (bool) $only,
    'message'     => $failed
        ? count($failed) . ' of ' . count($result['tasks']) . ' tasks failed — see the log'
        : ($only
            ? 'Re-checked: ' . implode(', ', $only)
            : 'All ' . count($result['tasks']) . ' tasks completed'),
];

// Hand back the freshly written summary when the permission scan was one of the tasks, so
// the caller can say what the answer IS ("no issues") instead of only that it re-ran. The
// task has already written this to $_SESSION and logs/.housekeeping_status.json.
if (in_array('permission_check', array_column($result['tasks'], 'name'), true)) {
    $ps = $_SESSION['perm_summary'] ?? null;
    $response['perm_summary'] = [
        'finding_count' => $ps['finding_count'] ?? 0,
        'high'          => $ps['high'] ?? 0,
        'medium'        => $ps['medium'] ?? 0,
        'low'           => $ps['low'] ?? 0,
        'worst'         => $ps['worst'] ?? null,
        'checked_at'    => $ps['checked_at'] ?? null,
    ];
}

echo json_encode($response);
