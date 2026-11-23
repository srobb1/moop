<?php
/**
 * SEND US A MESSAGE -- questions, data requests, problems (2026-11-23). Storage and rules: lib/feedback.php.
 *
 * Linked from the footer on every page as ?from=<that page>, so the message says where the visitor was.
 * A form, not a mailto: link -- people answer a box with a Send button far more readily than they open
 * their mail program, and a mailto: link does nothing at all on a machine with no mail program set up.
 *
 * A stale token (the visitor took longer than the session to write) shows the form again WITH their
 * text and asks them to press Send again -- never the bare "Security Error" page, which loses it.
 */
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/feedback.php';

$siteTitle = $config->getString('siteTitle');
$state = 'form';            // form | sent | error | cap | limit
$errors = [];
$values = ['kind' => in_array($_GET['kind'] ?? '', MOOP_FEEDBACK_KINDS, true) ? $_GET['kind'] : 'question',
           'message' => '', 'name' => '', 'email' => '', 'from' => (string) ($_GET['from'] ?? '')];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    [$msg, $errors] = moop_feedback_validate($_POST);
    $values = ['kind' => $msg['kind'], 'message' => $msg['message'], 'name' => $msg['name'],
               'email' => $msg['email'], 'from' => $msg['page']];
    $hour = (int) floor(time() / 3600);
    $_SESSION['feedback_sent'] = ($_SESSION['feedback_sent']['h'] ?? 0) === $hour ? $_SESSION['feedback_sent'] : ['h' => $hour, 'n' => 0];
    if (!verify_csrf_token(get_csrf_token_from_request())) {
        $errors = ['token'];
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        $state = 'sent';    // the honeypot: a bot filled the hidden field. Say "sent", save nothing.
    } elseif (!$errors && $_SESSION['feedback_sent']['n'] >= 5) {
        $state = 'limit';
    } elseif (!$errors) {
        $r = moop_feedback_add($msg, ['user' => get_username(), 'level' => get_access_level()]);
        if ($r === 'ok') { $state = 'sent'; $_SESSION['feedback_sent']['n']++; }
        else {
            $state = $r;    // 'cap' or 'error' -- the form stays filled in
            if ($r === 'error') error_log('MOOP feedback: could not save a message to ' . moop_feedback_file());
        }
    }
}

$admin_email = $config->getString('admin_email');
echo render_display_page(__DIR__ . '/pages/feedback.php', [
    'state'       => $state,
    'errors'      => $errors,
    'values'      => $values,
    'admin_email' => $admin_email,
    'siteTitle'   => $siteTitle,
], t_plain('Send us a message') . ' - ' . $siteTitle);
