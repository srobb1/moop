<?php
/**
 * MESSAGES FROM VISITORS -- the inbox for tools/feedback.php (2026-11-23). Storage: lib/feedback.php.
 *
 * Every action is a POST (admin_init.php checks the CSRF token). Replying is the admin's own email
 * program: the site cannot send mail yet (notes/EMAIL_ALERTS_PLAN.md).
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/feedback.php';

$siteTitle = $config->getString('siteTitle');
$notice = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id = (string) ($_POST['id'] ?? '');
    $to = (string) ($_POST['status'] ?? '');
    if (preg_match('/^[0-9a-f]{12}$/', $id)) {
        $notice = moop_feedback_set_status($id, $to)
            ? ['success', $to === 'delete' ? 'Message deleted.' : 'Marked ' . $to . '.']
            : ['danger', 'Could not save the change — the messages file may not be writable (see Filesystem Permissions).'];
    }
}
$show = in_array($_GET['show'] ?? '', ['open', 'all'], true) ? $_GET['show'] : 'open';
$all = moop_feedback_all();
$messages = $all === null ? [] : ($show === 'all' ? $all : array_values(array_filter($all, fn($m) => ($m['status'] ?? 'new') !== 'done')));

echo render_display_page(__DIR__ . '/pages/manage_feedback.php', [
    'notice'    => $notice,
    'messages'  => $messages,
    'unreadable'=> $all === null,
    'counts'    => array_count_values(array_map(fn($m) => $m['status'] ?? 'new', $all ?? [])),
    'total'     => count($all ?? []),
    'show'      => $show,
    'file'      => moop_feedback_file(),
    'siteTitle' => $siteTitle,
], 'Messages from Visitors');
