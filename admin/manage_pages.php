<?php
/**
 * MANAGE PAGES — Admin Controller
 *
 * Content pages written in Markdown (About, Protocols, FAQ, ...), English and Spanish, stored
 * as site data under metadata/pages/ (lib/content_pages.php) and published at page.php?p=<slug>.
 * Cuttingclass step 2; opt-in -- a site with no pages has nothing new. A page shows in a menu
 * only when the menu links it (step 3).
 *
 *   manage_pages.php             the list
 *   manage_pages.php?new=1       a new page
 *   manage_pages.php?edit=<slug> edit one
 *
 * CSRF is verified in admin_init.php on every POST. POST-redirect-GET.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/content_pages.php';

$flash = $_SESSION['pages_flash'] ?? null;
unset($_SESSION['pages_flash']);
$go = function (array $flash, string $to) {
    $_SESSION['pages_flash'] = $flash;
    header('Location: ' . $to);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['_action'] ?? '');
    if ($action === 'save') {
        $slug     = trim((string) ($_POST['slug'] ?? ''));
        $original = (string) ($_POST['original_slug'] ?? '');
        $page = ['slug' => $slug, 'title' => [], 'body' => []];
        foreach (MOOP_PAGE_LANGS as $lang) {
            $page['title'][$lang] = trim((string) ($_POST["title_$lang"] ?? ''));
            $page['body'][$lang]  = str_replace("\r\n", "\n", (string) ($_POST["body_$lang"] ?? ''));
        }
        // A new page must not overwrite one that already has this address. An existing page
        // keeps its address: links to it would break if it changed.
        if ($original === '' && moop_page_get($slug) !== null) {
            $_SESSION['pages_draft'] = $page;
            $go(['type' => 'danger', 'msg' => 'There is already a page at the address "' . htmlspecialchars($slug) . '". Choose another.'], 'manage_pages.php?new=1');
        }
        if ($original !== '' && $original !== $slug) {
            $go(['type' => 'danger', 'msg' => 'A page keeps its address once created.'], 'manage_pages.php?edit=' . rawurlencode($original));
        }
        $r = moop_page_save($page);
        if (!$r['ok']) {
            $_SESSION['pages_draft'] = $page;
            $go(['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars((string) $r['error'])],
                $original === '' ? 'manage_pages.php?new=1' : 'manage_pages.php?edit=' . rawurlencode($original));
        }
        $go(['type' => 'success', 'msg' => 'Saved "' . htmlspecialchars($page['title']['en']) . '".'], 'manage_pages.php?edit=' . rawurlencode($slug));
    }
    if ($action === 'delete') {
        $slug = (string) ($_POST['slug'] ?? '');
        $r = moop_page_delete($slug);
        $go($r['ok'] ? ['type' => 'success', 'msg' => 'Deleted the page "' . htmlspecialchars($slug) . '".']
                     : ['type' => 'danger', 'msg' => htmlspecialchars((string) $r['error'])], 'manage_pages.php');
    }
    $go(['type' => 'danger', 'msg' => 'Nothing was changed.'], 'manage_pages.php');
}

$mode = isset($_GET['edit']) ? 'edit' : (isset($_GET['new']) ? 'new' : 'list');
$page = null;
if ($mode === 'edit') {
    $page = moop_page_get((string) $_GET['edit']);
    if ($page === null) $go(['type' => 'danger', 'msg' => 'No such page.'], 'manage_pages.php');
}
if ($mode === 'new') {
    $page = moop_page_normalise($_SESSION['pages_draft'] ?? []);
}
if ($mode === 'edit' && isset($_SESSION['pages_draft'])) {
    $page = moop_page_normalise($_SESSION['pages_draft']) ;   // a failed save keeps what was typed
}
unset($_SESSION['pages_draft']);

$data = [
    'site'        => $config->getString('site'),
    'mode'        => $mode,
    'page'        => $page,
    'pages'       => $mode === 'list' ? moop_page_list() : [],
    'pages_dir'   => moop_pages_dir(),
    'flash'       => $flash,
    'page_script' => ['/' . $config->getString('site') . '/js/modules/manage-pages.js'],
];
echo render_display_page(__DIR__ . '/pages/manage_pages.php', $data, 'Manage Pages');
