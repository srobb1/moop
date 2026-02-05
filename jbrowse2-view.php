<?php
/**
 * JBrowse2 Viewer - Fullscreen Genome Browser
 * 
 * Displays JBrowse2 in fullscreen mode for maximum viewing area
 * Serves the JBrowse2 application with injected user authentication
 */

include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/lib/moop_functions.php';
include_once __DIR__ . '/includes/config_init.php';

// Get configuration
$config = ConfigManager::getInstance();
$site = $config->getString('site');
$jbrowse_config = $config->getArray('jbrowse2');

// Get assembly parameter
$assembly_name = $_GET['assembly'] ?? null;

// User authentication info for JavaScript
$user_info = [
    'logged_in' => is_logged_in(),
    'username' => get_username(),
    'access_level' => get_access_level(),
    'is_admin' => ($_SESSION['is_admin'] ?? false),
];

// Read the JBrowse2 index.html
$jbrowse_index = file_get_contents(__DIR__ . '/jbrowse2/index.html');

// Create proper base href from site configuration
$base_href = "/<{$site}>/jbrowse2/";

// Inject configuration, user info and assembly name into the HTML before </body>
$user_info_script = '<script>
    window.moopUserInfo = ' . json_encode($user_info) . ';
    window.moopAssemblyName = ' . json_encode($assembly_name ?? '') . ';
    window.moopSite = ' . json_encode($site) . ';
    window.moopConfig = ' . json_encode($jbrowse_config) . ';
    console.log("JBrowse2 Viewer - Assembly:", window.moopAssemblyName);
    console.log("User Info:", window.moopUserInfo);
    console.log("Site:", window.moopSite);
</script>';

// Replace base href in HTML to use site from configuration
$jbrowse_index = preg_replace(
    '/<base href="[^"]*"/',
    '<base href="' . htmlspecialchars($base_href) . '"',
    $jbrowse_index
);

$jbrowse_index = str_replace('</body>', $user_info_script . "\n</body>", $jbrowse_index);

// Output the modified HTML with proper base path context
echo $jbrowse_index;
?>
