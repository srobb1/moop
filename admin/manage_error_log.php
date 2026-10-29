<?php
/**
 * ERROR LOG — controller (content: admin/pages/error_log.php).
 *
 * Shows logs/error.log grouped by PROBLEM, not entry: the same message from the same place is
 * one row with a count, first and last seen. One card per entry (up to 500) buried a crash that
 * happened 29 times under 29 identical cards. Entries older than MOOP_ERROR_LOG_RETENTION_DAYS
 * are trimmed by housekeeping (error_log_retention).
 */

include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';

$siteTitle = $config->getString('siteTitle');
$site      = $config->getString('site');

// Both actions rewrite the log, so both are POST: admin_init.php runs csrf_protect() on every
// POST, and only on POST. Clearing used to be a GET link — any page that could make an admin's
// browser request it wiped the log with no token check.
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clear') {
        $notice = clearErrorLog() ? ['success', 'The error log has been cleared.'] : ['danger', 'The error log could not be cleared.'];
    } elseif ($action === 'remove' && preg_match('/^[0-9a-f]{16}$/', $_POST['key'] ?? '')) {
        $n = moop_error_log_remove_group($_POST['key']);
        $notice = $n === null
            ? ['danger', 'The error log could not be rewritten — nothing was removed.']
            : ['success', 'Removed ' . $n . ' entr' . ($n === 1 ? 'y' : 'ies') . ' of that problem. If it happens again, it will come back.'];
    }
}

$entries = getErrorLog();
$groups  = moop_error_log_groups($entries);

$filter_kind   = in_array($_GET['kind'] ?? '', ['crash', 'reported'], true) ? $_GET['kind'] : '';
$filter_search = trim((string)($_GET['q'] ?? ''));
$shown = array_values(array_filter($groups, function (array $g) use ($filter_kind, $filter_search) {
    if ($filter_kind !== '' && $g['kind'] !== $filter_kind) return false;
    if ($filter_search === '') return true;
    return stripos($g['error'] . ' ' . $g['where'] . ' ' . json_encode($g['latest']), $filter_search) !== false;
}));

$log_file = $config->getPath('error_log_file');

echo render_display_page(__DIR__ . '/pages/error_log.php', [
    'notice'        => $notice,
    'groups'        => $groups,
    'shown'         => $shown,
    'entry_count'   => count($entries),
    'log_bytes'     => ($log_file && is_file($log_file)) ? filesize($log_file) : 0,
    'filter_kind'   => $filter_kind,
    'filter_search' => $filter_search,
    'site'          => $site,
    'siteTitle'     => $siteTitle,
    'page_script'   => '/' . $site . '/js/error-log.js',
], 'Error Log');
