<?php
/**
 * MANAGE MENU — Admin Controller
 *
 * The main navigation bar: links (to a page, About, Help, Home or an address), drop-downs of
 * links, and where the Tools drop-down sits. Saved as 'main_menu' in config_editable.json via
 * lib/main_menu.php -- the same functions a page's "Show in menu" uses (Manage Pages), so the two
 * cannot disagree. A menu identical to the default is stored as empty, so the site keeps
 * following the default (Tools, About, Help). Cuttingclass step 3; opt-in.
 *
 * The editor is drawn by js/modules/manage-menu.js from the data below and posts the whole menu
 * back as JSON; ConfigManager::mainMenuProblem() checks it before anything is saved.
 * CSRF is verified in admin_init.php on every POST. POST-redirect-GET.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/content_pages.php';
require_once __DIR__ . '/../lib/main_menu.php';

$flash = $_SESSION['menu_flash'] ?? null;
unset($_SESSION['menu_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['_action'] ?? '');
    if ($action === 'reset') {
        $r = moop_menu_save(ConfigManager::defaultMainMenu());
        $msg = !empty($r['success']) ? ['type' => 'success', 'msg' => 'The menu is back to Tools, About, Help.']
                                     : ['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars((string) ($r['message'] ?? ''))];
    } elseif ($action === 'save_menu') {
        $menu = json_decode((string) ($_POST['menu_json'] ?? ''), true);
        $problem = ConfigManager::mainMenuProblem($menu);
        if ($problem !== null) {
            $msg = ['type' => 'danger', 'msg' => 'Not saved: the menu is not valid (' . htmlspecialchars($problem) . '). Nothing was changed.'];
        } else {
            $r = moop_menu_save($menu);
            $msg = !empty($r['success']) ? ['type' => 'success', 'msg' => 'Saved the menu.']
                                         : ['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars((string) ($r['message'] ?? ''))];
        }
    } else {
        $msg = ['type' => 'danger', 'msg' => 'Not saved: the request did not come from this form. Nothing was changed.'];
    }
    $_SESSION['menu_flash'] = $msg;
    header('Location: manage_menu.php');
    exit;
}

$pages = array_map(fn($p) => ['slug' => $p['slug'], 'title' => $p['title']['en']], array_values(moop_page_list()));
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$data = [
    'site'           => $config->getString('site'),
    'flash'          => $flash,
    'customised'     => (bool) $config->getArray('main_menu'),
    'page_script'    => ['/' . $config->getString('site') . '/js/modules/manage-menu.js'],
    'inline_scripts' => [
        'window.mmData = ' . json_encode(['menu' => $config->mainMenu(), 'pages' => $pages], $flags) . ';',
    ],
];
echo render_display_page(__DIR__ . '/pages/manage_menu.php', $data, 'Manage Menu');
