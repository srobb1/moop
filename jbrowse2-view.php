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
    <title>JBrowse2 - Genome Browser</title>
    
    <!-- JBrowse2 CSS -->
    <link rel="stylesheet" href="/moop/jbrowse2/css/jbrowse2.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
        
        .jbrowse-toolbar {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .jbrowse-toolbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .jbrowse-toolbar-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .jbrowse-toolbar button {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #dee2e6;
            background-color: white;
            border-radius: 0.25rem;
            cursor: pointer;
        }
        
        .jbrowse-toolbar button:hover {
            background-color: #f0f0f0;
        }
        
        .jbrowse-container {
            height: calc(100% - 45px);
            width: 100%;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            flex-direction: column;
            gap: 1rem;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="jbrowse-toolbar">
        <div class="jbrowse-toolbar-left">
            <span id="assembly-display" style="font-weight: bold;">Loading...</span>
        </div>
        <div class="jbrowse-toolbar-right">
            <span id="user-display" style="font-size: 0.875rem; color: #666;"></span>
            <button onclick="window.opener.focus(); window.close();" title="Close viewer">Close</button>
        </div>
    </div>
    
    <div class="jbrowse-container" id="root">
        <div class="loading">
            <div class="spinner"></div>
            <p>Initializing JBrowse2...</p>
        </div>
    </div>

    <script>
        // Set user info from parent window or current session
        window.moopUserInfo = <?php echo json_encode($user_info); ?>;
        
        // Get assembly name from URL parameter
        const assemblyName = '<?php echo addslashes($assembly_name ?? ''); ?>';
        
        console.log('JBrowse2 Viewer - Assembly:', assemblyName);
        console.log('User Info:', window.moopUserInfo);
    </script>

    <!-- JBrowse2 Application -->
    <script async defer src="/moop/jbrowse2/dist/main.bundle.js"></script>
    
    <!-- Dynamic loader for JBrowse2 -->
    <script src="/moop/js/jbrowse2-view-loader.js"></script>
</body>
</html>
