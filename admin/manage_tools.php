<?php
/**
 * MANAGE TOOLS — Admin Controller
 *
 * Switch tools on and off for THIS site. Saves the 'tools_disabled' list in
 * config/config_editable.json (ConfigManager::saveEditableConfig), never config/tools_config.php:
 * that file is code, and a web-written .php file turns any write bug into code execution.
 *
 * A switched-off tool leaves the toolbox and the navbar's Tools menu, and its own page answers
 * 404 (moop_tool_page_guard()). Built for cuttingclass (notes/CUTTINGCLASS_PLAN.md step 1);
 * with nothing switched off -- the default -- every tool shows exactly as shipped.
 *
 * CSRF is verified in admin_init.php on every POST. POST-redirect-GET.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';   // provides render_display_page()

$config_dir  = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
$config_file = "$config_dir/config_editable.json";
$file_write_error = getFileWriteError($config_file);
$all_tools   = $config->getAllTools(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$file_write_error) {
    // The form posts the tools left ON; everything else in the registry is off. Asking for the
    // ON list means a tool added to tools_config.php later starts on, not silently off.
    //
    // ...which also means a POST with no 'enabled' field reads as "switch EVERYTHING off". On
    // 2026-11-03 a test request that lost its fields did exactly that to the live site. So the
    // form says what it is, and a POST without that marker is refused, never read as "all off".
    if (($_POST['_action'] ?? '') !== 'save_tools') {
        $_SESSION['tools_flash'] = ['type' => 'danger', 'msg' => 'Not saved: the request did not come from this form. Nothing was changed.'];
        header('Location: manage_tools.php');
        exit;
    }
    $on  = array_map('strval', (array) ($_POST['enabled'] ?? []));
    $off = array_values(array_diff(array_keys($all_tools), $on));

    // Menu and toolbox: the rows arrive in the order the admin arranged them; only the ticked
    // ones are kept (dividers in the menu always). Labels are saved only where they differ from
    // the registry's, and a part that matches the defaults is not saved at all -- so a site that
    // changes nothing keeps following config/tools_config.php as it evolves.
    $menu_show = array_map('strval', (array) ($_POST['menu_show'] ?? []));
    $box_show  = array_map('strval', (array) ($_POST['toolbox_show'] ?? []));
    $menu    = array_values(array_filter(array_map('strval', (array) ($_POST['menu_order'] ?? [])),
                                         fn($x) => $x === '|' || in_array($x, $menu_show, true)));
    $toolbox = array_values(array_filter(array_map('strval', (array) ($_POST['toolbox_order'] ?? [])),
                                         fn($x) => in_array($x, $box_show, true)));
    $labels = [];
    foreach ($all_tools as $id => $tool) {
        $defaults = ['menu' => (string) ($tool['menu_label'] ?? $tool['name'] ?? $id), 'toolbox' => (string) ($tool['name'] ?? $id)];
        foreach ($defaults as $where => $default) {
            $label = trim((string) ($_POST['label_' . $where][$id] ?? ''));
            if ($label !== '' && $label !== $default) $labels[$id][$where] = $label;
        }
    }
    $layout = [];
    if ($menu !== $config->defaultToolsMenuOrder()) $layout['menu']    = $menu;
    if ($toolbox !== $config->defaultToolboxOrder()) $layout['toolbox'] = $toolbox;
    if ($labels)                                     $layout['labels']  = $labels;

    $result = $config->saveEditableConfig(['tools_disabled' => $off, 'tools_layout' => $layout], $config_dir);
    $_SESSION['tools_flash'] = !empty($result['success'])
        ? ['type' => 'success', 'msg' => $off
            ? 'Saved. ' . count($off) . ' tool' . (count($off) === 1 ? ' is' : 's are') . ' now switched off.'
            : 'Saved. Every tool is on.']
        : ['type' => 'danger', 'msg' => 'Not saved: ' . htmlspecialchars($result['message'] ?? 'unknown error')];
    header('Location: manage_tools.php');
    exit;
}

$flash = $_SESSION['tools_flash'] ?? null;
unset($_SESSION['tools_flash']);

// Rows for the two ordering lists: what the site shows now, in order, then every other tool
// (unticked) so it can be added. The menu keeps its dividers.
$menu_rows = [];
foreach ($config->toolsMenuOrder() as $id) {
    if ($id === '|' || isset($all_tools[$id])) $menu_rows[] = ['id' => $id, 'show' => true];
}
foreach (array_keys($all_tools) as $id) {
    if (!in_array((string) $id, $config->toolsMenuOrder(), true)) $menu_rows[] = ['id' => (string) $id, 'show' => false];
}
$box_now   = $config->toolboxOrder() ?? $config->defaultToolboxOrder();
$box_rows  = [];
foreach ($box_now as $id) if (isset($all_tools[$id])) $box_rows[] = ['id' => $id, 'show' => true];
foreach (array_keys($all_tools) as $id) {
    if (!in_array((string) $id, $box_now, true)) $box_rows[] = ['id' => (string) $id, 'show' => false];
}

$data = [
    'site'             => $config->getString('site'),
    'menu_rows'        => $menu_rows,
    'box_rows'         => $box_rows,
    'label_of'         => fn(string $id, string $where) => $config->toolLabel($id, $where),
    'default_label_of' => fn(string $id, string $where) => (string) ($where === 'menu'
                              ? ($all_tools[$id]['menu_label'] ?? $all_tools[$id]['name'] ?? $id)
                              : ($all_tools[$id]['name'] ?? $id)),
    'customised'       => (bool) ($config->getArray('tools_layout') ?? []),
    'page_script'      => ['/' . $config->getString('site') . '/js/modules/manage-tools.js'],
    'tools'            => $all_tools,
    'enabled'          => array_map(fn($id) => $config->isToolEnabled((string) $id), array_combine(array_keys($all_tools), array_keys($all_tools))),
    'flash'            => $flash,
    'file_write_error' => $file_write_error,
    'config_file'      => $config_file,
];

echo render_display_page(__DIR__ . '/pages/manage_tools.php', $data, 'Manage Tools');
