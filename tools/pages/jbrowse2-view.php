<?php
// JBrowse2 View Page with Custom Loader
//
// ⚠️ ORPHAN: nothing renders this file — there is no tools/jbrowse2-view.php controller and
// no reference to it anywhere in the tree — but unlike the other orphaned page files it is
// directly reachable and returns 200, so a stumbled-upon URL renders a half-working viewer.
// It is a deletion candidate alongside tools/pages/registry.php and js_registry.php
// (notes/ADMIN_UI_FOLLOWUPS.md §5); left in place here only because that is a separate call.
//
// It is standalone (its own <head>), so it gets none of layout.php's globals — hence the
// bootstrap below rather than relying on window.sitePath.
include_once __DIR__ . '/../tool_init.php';

$config = ConfigManager::getInstance();
$site   = $config->getString('site');

$assembly = htmlspecialchars($_GET['assembly'] ?? '', ENT_QUOTES);

// Read identity through the accessors, not raw $_SESSION: a raw read reports the real
// account while an admin is using "View as PUBLIC", so the viewer would claim privileges
// the request will not actually be granted.
$user_info = array(
    'logged_in'    => is_logged_in(),
    'username'     => get_username() !== '' ? get_username() : 'anonymous',
    'access_level' => get_access_level(),
    'is_admin'     => function_exists('moop_session_is_admin') ? moop_session_is_admin() : false
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBrowse2 Viewer - Assembly: <?php echo $assembly; ?></title>
    <base href="/<?= htmlspecialchars($site) ?>/jbrowse2/">
    <link rel="stylesheet" href="/<?= htmlspecialchars($site) ?>/jbrowse2/css/jbrowse2.css">
</head>
<body>
    <div id="jbrowse2"></div>
    
    <script>
        window.moopUserInfo = <?php echo json_encode($user_info); ?>;
        window.moopAssembly = <?php echo json_encode($assembly); ?>;
        window.moopSite = <?php echo json_encode($site); ?>;
        window.sitePath = <?php echo json_encode('/' . $site); ?>;
    </script>

    <script src="/<?= htmlspecialchars($site) ?>/js/jbrowse2-view-loader.js"></script>
</body>
</html>
