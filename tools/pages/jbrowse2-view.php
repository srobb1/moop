<?php
// JBrowse2 View Page with Custom Loader
$assembly = htmlspecialchars($_GET['assembly'] ?? '', ENT_QUOTES);
$user_info = array(
    'logged_in' => isset($_SESSION['username']),
    'username' => $_SESSION['username'] ?? 'anonymous',
    'access_level' => $_SESSION['access_level'] ?? 'Public',
    'is_admin' => isset($_SESSION['is_admin']) && $_SESSION['is_admin']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBrowse2 Viewer - Assembly: <?php echo $assembly; ?></title>
    <base href="/moop/jbrowse2/">
    <link rel="stylesheet" href="/moop/jbrowse2/css/jbrowse2.css">
</head>
<body>
    <div id="jbrowse2"></div>
    
    <script>
        window.moopUserInfo = <?php echo json_encode($user_info); ?>;
        window.moopAssembly = <?php echo json_encode($assembly); ?>;
        window.moopSite = 'moop';
    </script>
    
    <script src="/moop/js/jbrowse2-view-loader.js"></script>
</body>
</html>
