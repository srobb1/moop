<?php
/**
 * MANAGE LANGUAGES — Admin Controller
 *
 * The languages this site writes its pages and menu labels in ('languages' in
 * config_editable.json; ConfigManager::languages()). English is always first and always there:
 * untranslated text falls back to it.
 *
 * A language is never deleted -- it is RETIRED, the way an organism is (lib/retire.php): it moves
 * to 'languages_retired' with when and by whom, leaves the page editors, the menu editor and the
 * site, and everything written in it stays stored (lib/content_pages.php keeps every language a
 * page has; menu labels keep theirs). Restore puts it back with all of that text.
 *
 * Opt-in: the default is English only. CSRF is verified in admin_init.php. POST-redirect-GET.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/content_pages.php';

$config_dir = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
$flash = $_SESSION['lang_flash'] ?? null;
unset($_SESSION['lang_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active  = $config->languages();
    $retired = $config->retiredLanguages();
    $action  = (string) ($_POST['_action'] ?? '');
    $code    = strtolower(trim((string) ($_POST['code'] ?? '')));
    $msg     = null;

    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if (in_array($code, array_column($retired, 'code'), true)) {
            $msg = ['type' => 'warning', 'msg' => '"' . htmlspecialchars($code) . '" is retired, with its text kept. Restore it below rather than adding it again.'];
        } else {
            $active[] = ['code' => $code, 'name' => $name];
        }
    } elseif ($action === 'retire' && $code !== 'en') {
        foreach ($active as $i => $l) {
            if ($l['code'] === $code) {
                unset($active[$i]);
                $retired[] = ['code' => $code, 'name' => $l['name'], 'retired_at' => date('c'), 'retired_by' => (string) ($_SESSION['username'] ?? '')];
            }
        }
    } elseif ($action === 'restore') {
        foreach ($retired as $i => $l) {
            if ($l['code'] === $code) {
                unset($retired[$i]);
                $active[] = ['code' => $code, 'name' => $l['name']];
            }
        }
    } else {
        $msg = ['type' => 'danger', 'msg' => 'Nothing was changed.'];
    }

    if ($msg === null) {
        $r = $config->saveEditableConfig(['languages' => array_values($active), 'languages_retired' => array_values($retired)], $config_dir);
        $msg = !empty($r['success'])
            ? ['type' => 'success', 'msg' => ['add' => 'Added', 'retire' => 'Retired', 'restore' => 'Restored'][$action] . ' "' . htmlspecialchars($code) . '".'
                                             . ($action === 'retire' ? ' Everything written in it is kept; Restore brings it back.' : '')]
            : ['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars((string) ($r['message'] ?? ''))];
    }
    $_SESSION['lang_flash'] = $msg;
    header('Location: manage_languages.php');
    exit;
}

// How much is written in each language, so retiring one shows what it holds.
$written = [];
foreach (moop_page_list() as $p) {
    foreach ($p['body'] as $lang => $text) {
        if (trim($text) !== '') $written[$lang] = ($written[$lang] ?? 0) + 1;
    }
}

$data = [
    'site'      => $config->getString('site'),
    'flash'     => $flash,
    'languages' => $config->languages(),
    'retired'   => $config->retiredLanguages(),
    'written'   => $written,
];
echo render_display_page(__DIR__ . '/pages/manage_languages.php', $data, 'Manage Languages');
