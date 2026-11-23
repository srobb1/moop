<?php
/**
 * NEWS -- the "What's new" box on the home page and tools/news.php (2026-11-23). Storage and rules:
 * lib/home_panel.php. Shown only on a site whose home is set to "with Getting started and What's new"
 * (Manage Pages -> Home page). Every change is a POST (admin_init.php checks the CSRF token).
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/home_panel.php';

$groups = [];
foreach (getGroupData() as $e) foreach ((array) ($e['groups'] ?? []) as $g) $groups[(string) $g] = true;
$groups = array_keys($groups);
sort($groups, SORT_NATURAL | SORT_FLAG_CASE);

$notice = null;
$form = ['id' => '', 'date' => date('Y-m-d'), 'text' => '', 'groups' => []];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id = (string) ($_POST['id'] ?? '');
    $id = preg_match('/^[0-9a-f]{10}$/', $id) ? $id : null;
    if (($_POST['_action'] ?? '') === 'delete' && $id !== null) {
        $notice = moop_news_put($id, null) ? ['success', 'Deleted.'] : ['danger', 'Could not save — the news file may not be writable (see Filesystem Permissions).'];
    } elseif (($_POST['_action'] ?? '') === 'save') {
        [$item, $errors] = moop_news_validate($_POST, $groups);
        if ($errors) {
            $notice = ['danger', implode(' ', $errors)];
            $form = $item + ['id' => $id ?? ''];
        } else {
            $notice = moop_news_put($id, $item) ? ['success', $id ? 'Saved.' : ($config->homeSetting()['mode'] === 'panel' ? 'Added. It shows on the home page now.' : 'Added.')]
                                                : ['danger', 'Could not save — the news file may not be writable (see Filesystem Permissions).'];
        }
    }
} elseif (isset($_GET['edit'])) {
    foreach (moop_news_all() ?? [] as $n) if ($n['id'] === $_GET['edit']) $form = $n + ['groups' => []];
}

$all = moop_news_all();
$home = $config->homeSetting();
echo render_display_page(__DIR__ . '/pages/manage_news.php', [
    'notice'     => $notice,
    'news'       => $all ?? [],
    'unreadable' => $all === null,
    'form'       => $form,
    'groups'     => $groups,
    'panel_on'   => $home['mode'] === 'panel',
    'file'       => moop_news_file(),
], 'News');
