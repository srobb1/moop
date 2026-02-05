<?php
/**
 * JBrowse2 Viewer - Fullscreen Genome Browser
 * 
 * Displays JBrowse2 in fullscreen mode for maximum viewing area
 * Uses parent window's authentication (passed via window.opener)
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/lib/moop_functions.php';

// Get assembly parameter
$assembly_name = $_GET['assembly'] ?? null;

// User authentication info for JavaScript
$user_info = [
    'logged_in' => is_logged_in(),
    'username' => get_username(),
    'access_level' => get_access_level(),
    'is_admin' => ($_SESSION['is_admin'] ?? false),
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#000000"/>
    <meta name="description" content="A fast and flexible genome browser"/>
    <title>JBrowse2 - Genome Browser</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            overscroll-behavior-x: none;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif;
            background-color: #fff;
            height: 100vh;
            overflow: hidden;
        }
        
        #root {
            height: 100%;
            width: 100%;
        }
    </style>
</head>
<body>
    <noscript>You need to enable JavaScript to run this app.</noscript>
    <div id="root"></div>

    <script>
        // Set user info for JBrowse2 and custom loaders
        window.moopUserInfo = <?php echo json_encode($user_info); ?>;
        window.moopAssemblyName = '<?php echo addslashes($assembly_name ?? ''); ?>';
        
        console.log('JBrowse2 Viewer - Assembly:', window.moopAssemblyName);
        console.log('User Info:', window.moopUserInfo);
    </script>

    <!-- JBrowse2 Application - Load from static/js -->
    <script defer src="/moop/jbrowse2/static/js/main.9f05d716.js"></script>
</body>
</html>
