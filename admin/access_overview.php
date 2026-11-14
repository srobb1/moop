<?php
/**
 * ACCESS OVERVIEW — Admin Controller. "Who can see what", every item on one page.
 * Plan: notes/PERMISSIONS_PLAN.md §4c (step 5). Rows: lib/access_overview.php (read-only; it asks the
 * same functions the site uses). Content: admin/pages/access_overview.php.
 *
 * Edits only what lives in access_rules.json -- pages and analyses -- through the ONE shared dialog
 * (admin/pages/_access_rule_dialog.php). Data sets link to Manage Groups / Manage Users and JBrowse
 * tracks to their track sheet, so each setting still has exactly one home.
 *
 * CSRF is verified in admin_init.php on every POST. POST-redirect-GET.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/access_overview.php';
require_once __DIR__ . '/../lib/content_pages.php';

$flash = $_SESSION['access_flash'] ?? null;
unset($_SESSION['access_flash']);
$go = function (array $flash) {
    $_SESSION['access_flash'] = $flash;
    header('Location: access_overview.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_access') {
    $kind = (string) ($_POST['kind'] ?? '');
    $key  = (string) ($_POST['key'] ?? '');
    if ($kind === 'page') {
        if (moop_page_get($key) === null) $go(['type' => 'danger', 'msg' => 'No such page.']);
        $r = moop_page_rule_set($key, moop_access_rule_from_post($_POST));
        $go($r['ok'] ? ['type' => 'success', 'msg' => 'Page "' . htmlspecialchars($key) . '" is now visible to: ' . htmlspecialchars(moop_access_rule_label(moop_page_rule($key))) . '.']
                     : ['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars((string) $r['error'])]);
    }
    if ($kind === 'analysis') {
        // Only an analysis that exists on this site -- never a rule for a key someone typed -- and
        // with ITS default, so choosing the default clears the stored rule (moop_analysis_rule_set()).
        $edit = null;
        foreach (moop_access_overview()['rows'] as $row) {
            if (($row['edit']['kind'] ?? '') === 'analysis' && $row['edit']['key'] === $key) { $edit = $row['edit']; break; }
        }
        if ($edit === null) $go(['type' => 'danger', 'msg' => 'No such analysis.']);
        $r = moop_analysis_rule_set($key, ['visible_to' => (string) ($_POST['visible_to'] ?? '')], $edit['default']);
        $go($r['ok'] ? ['type' => 'success', 'msg' => '"' . htmlspecialchars($edit['title']) . '" is now visible to: '
                                                   . htmlspecialchars(moop_access_rule_label(moop_analysis_rule($key, $edit['default']))) . '.']
                     : ['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars((string) $r['error'])]);
    }
    $go(['type' => 'danger', 'msg' => 'Nothing to save.']);
}

$overview = moop_access_overview();
$organisms = array_values(array_unique(array_filter(array_column($overview['rows'], 'organism'), 'strlen')));
sort($organisms, SORT_NATURAL | SORT_FLAG_CASE);

$data = [
    'flash'           => $flash,
    'rows'            => $overview['rows'],
    'rules_ok'        => $overview['rules_ok'],
    'users_ok'        => $overview['users_ok'],
    'errors'          => $overview['errors'],
    'organisms'       => $organisms,
    'kinds'           => MOOP_ACCESS_OVERVIEW_KINDS,
    'access_datasets' => moop_access_dataset_choices(),   // the shared dialog's data-set list (pages)
    'page_script'     => [moop_url_base() . '/js/modules/access-overview.js', moop_url_base() . '/js/modules/access-rule-dialog.js'],
];
echo render_display_page(__DIR__ . '/pages/access_overview.php', $data, 'Access Overview');
