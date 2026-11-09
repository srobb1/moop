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
require_once __DIR__ . '/../lib/main_menu.php';

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
        $page = ['slug' => $slug, 'format' => ($_POST['format'] ?? '') === 'html' ? 'html' : 'markdown', 'title' => [], 'body' => []];
        foreach (moop_page_langs() as $lang) {   // active languages only; retired text is kept by moop_page_save()
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
        // "Show in menu": the menu itself is changed (lib/main_menu.php) -- the page stores no copy
        // of where it sits, so this and Manage Menu cannot disagree.
        $place = (string) ($_POST['menu_place'] ?? 'none');
        $menu  = $config->mainMenu();
        // Titles from the page AS STORED, not as posted: the editor posts only the active languages,
        // and a label built from those dropped a retired language's menu label (found 2026-11-03 --
        // the page kept its Spanish, its menu entry lost it).
        $stored = moop_page_get($slug);
        $new   = moop_menu_set_page($menu, $slug, $place, $stored['title'] ?? $page['title']);
        if ($new != $menu) {
            $m = moop_menu_save($new);
            if (empty($m['success'])) {
                $go(['type' => 'warning', 'msg' => 'Saved the page, but not its place in the menu: ' . htmlspecialchars((string) ($m['message'] ?? ''))],
                    'manage_pages.php?edit=' . rawurlencode($slug));
            }
        }
        $go(['type' => 'success', 'msg' => 'Saved "' . htmlspecialchars($page['title']['en']) . '".'], 'manage_pages.php?edit=' . rawurlencode($slug));
    }
    if ($action === 'save_home') {
        $mode = (string) ($_POST['home_mode'] ?? 'builtin');
        $slug = (string) ($_POST['home_page'] ?? '');
        if ($mode !== 'builtin' && moop_page_get($slug) === null) {
            $go(['type' => 'danger', 'msg' => 'Choose which page to use for the home page.'], 'manage_pages.php');
        }
        $value = $mode === 'builtin' ? [] : ['mode' => $mode, 'page' => $slug];
        $dir = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
        $r = $config->saveEditableConfig(['home' => $value], $dir);
        $go(!empty($r['success']) ? ['type' => 'success', 'msg' => 'Saved the home page.']
                                  : ['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars((string) ($r['message'] ?? ''))], 'manage_pages.php');
    }
    if ($action === 'delete') {
        $slug = (string) ($_POST['slug'] ?? '');
        $r = moop_page_delete($slug);
        if ($r['ok']) {
            // A deleted page leaves the menu too (the menu would skip it anyway; this keeps it tidy).
            $menu = $config->mainMenu();
            $new  = moop_menu_without_page($menu, $slug);
            if ($new != $menu) moop_menu_save($new);
            // ...and if it was the home page (or the home's intro), the home goes back to MOOP's own.
            if ($config->homeSetting()['page'] === $slug) {
                $config->saveEditableConfig(['home' => []], $config->getPath('root_path') . '/' . $config->getString('site') . '/config');
            }
        }
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

$menu_now = $config->mainMenu();
$data = [
    'site'        => $config->getString('site'),
    'menu_place'  => $page !== null ? moop_menu_page_place($menu_now, $page['slug']) : 'none',
    'menu_dropdowns' => moop_menu_dropdowns($menu_now),
    'home'        => $config->homeSetting(),
    'mode'        => $mode,
    'page'        => $page,
    'pages'       => moop_page_list(),   // the list, and the editor's "Links you can use"
    'tool_links'  => $config->getToolsMenu(),
    'pages_dir'   => moop_pages_dir(),
    'flash'       => $flash,
    'page_script' => [moop_url_base() . '/js/modules/manage-pages.js'],
];
echo render_display_page(__DIR__ . '/pages/manage_pages.php', $data, 'Manage Pages');
