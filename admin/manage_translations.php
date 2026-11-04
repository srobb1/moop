<?php
/**
 * MANAGE TRANSLATIONS — Admin Controller (cuttingclass step 5b, notes/CUTTINGCLASS_PLAN.md)
 *
 * The site's interface text in its other languages (t(), includes/config_init.php; the catalog,
 * lib/i18n.php). The work happens in a SPREADSHEET, not on this page:
 *   1. Download  -- one row per string this site shows, the translation pre-filled where it exists.
 *   2. Translate -- an agent drafts ("machine draft"), a person checks ("reviewed").
 *   3. Upload    -- checked BEFORE anything is saved: codes like %d must match, a blank cell never
 *                   erases a translation, English that was edited by mistake is reported. The
 *                   admin sees what would change and saves it, or discards it.
 * Plus "Show gaps": brackets untranslated text ⟦like this⟧ on the real pages, for this admin only.
 *
 * Opt-in: on a one-language site this page only points at Manage Languages.
 * CSRF is verified in admin_init.php. POST-redirect-GET.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/i18n.php';

$others = array_values(array_filter($config->languages(), fn($l) => $l['code'] !== 'en'));
$codes  = array_column($others, 'code');
$lang   = (string) ($_REQUEST['lang'] ?? ($codes[0] ?? ''));
if (!in_array($lang, $codes, true)) $lang = $codes[0] ?? '';
$lang_name = '';
foreach ($others as $l) if ($l['code'] === $lang) $lang_name = $l['name'];

// 1. Download the spreadsheet.
if ($lang !== '' && isset($_GET['export'])) {
    $scope = ($_GET['scope'] ?? '') === 'all' ? 'all' : 'shown';
    $bytes = moop_i18n_xlsx(moop_i18n_export_rows($lang, $scope), $lang_name);
    $name  = $config->getString('site') . "-translations-$lang-" . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

$flash = $_SESSION['i18n_flash'] ?? null;
unset($_SESSION['i18n_flash']);
$back = 'manage_translations.php?lang=' . urlencode($lang);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['_action'] ?? '');
    $msg = ['type' => 'danger', 'msg' => 'Nothing was changed.'];

    if ($action === 'gaps') {
        $_SESSION['i18n_show_gaps'] = !empty($_POST['on']);
        $msg = ['type' => 'success', 'msg' => $_SESSION['i18n_show_gaps']
            ? 'Show gaps is on: untranslated text is marked ⟦like this⟧ on the site\'s pages, for you only. Switch the site to ' . htmlspecialchars($lang_name) . ' to see it.'
            : 'Show gaps is off.'];
    } elseif ($action === 'upload' && $lang !== '') {
        // 3a. Check an upload. Nothing is saved yet: the result waits in the session.
        $f = $_FILES['sheet'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $msg = ['type' => 'warning', 'msg' => 'Choose the spreadsheet to upload first.'];
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $msg = ['type' => 'danger', 'msg' => 'That file is over 5 MB -- it is not one this page exported.'];
        } else {
            $rows = moop_i18n_read_upload($f['tmp_name'], (string) $f['name']);
            $res  = is_array($rows) ? moop_i18n_check_upload($lang, $rows, moop_i18n_rows($lang)) : ['error' => $rows];
            if (isset($res['error'])) {
                $msg = ['type' => 'danger', 'msg' => htmlspecialchars($res['error'])];
            } else {
                $_SESSION['i18n_pending'] = ['lang' => $lang, 'file' => (string) $f['name'], 'res' => $res];
                $msg = null;
            }
        }
    } elseif ($action === 'apply' && !empty($_SESSION['i18n_pending']) && $_SESSION['i18n_pending']['lang'] === $lang) {
        // 3b. Save what was checked.
        $r = moop_i18n_save($lang, $_SESSION['i18n_pending']['res']['changes'], (string) ($_SESSION['username'] ?? ''));
        unset($_SESSION['i18n_pending']);
        $msg = ['type' => $r['success'] ? 'success' : 'danger', 'msg' => htmlspecialchars($r['message'])];
    } elseif ($action === 'discard') {
        unset($_SESSION['i18n_pending']);
        $msg = ['type' => 'secondary', 'msg' => 'The upload was discarded. Nothing was saved.'];
    }
    if ($msg !== null) $_SESSION['i18n_flash'] = $msg;
    header('Location: ' . $back);
    exit;
}

$rows    = $lang !== '' ? moop_i18n_rows($lang) : [];
$pending = $_SESSION['i18n_pending'] ?? null;
if ($pending && $pending['lang'] !== $lang) $pending = null;

$data = [
    'site'      => $config->getString('site'),
    'flash'     => $flash,
    'others'    => $others,
    'lang'      => $lang,
    'lang_name' => $lang_name,
    'counts'    => moop_i18n_counts($rows),
    'catalog'   => $lang !== '' ? moop_i18n_catalog($lang) : [],
    'pending'   => $pending,
    'gaps_on'   => !empty($_SESSION['i18n_show_gaps']),
];
echo render_display_page(__DIR__ . '/pages/manage_translations.php', $data, 'Manage Translations');
