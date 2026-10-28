<?php
/**
 * JBrowse2 Plugin Helper Functions
 * 
 * Provides functions to load and manage JBrowse2 plugins
 */

/**
 * Load enabled JBrowse2 plugins from config file
 * 
 * @return array Array of plugin configurations for JBrowse2
 */
function loadJBrowse2Plugins() {
    $pluginFile = __DIR__ . '/../../config/jbrowse2_plugins.json';
    
    if (!file_exists($pluginFile)) {
        error_log("JBrowse2 plugin config not found: $pluginFile");
        return [];
    }
    
    $plugins = loadJsonFile($pluginFile, []);
    
    if (!is_array($plugins)) {
        error_log("Invalid JBrowse2 plugin config format");
        return [];
    }
    
    // Filter to only enabled plugins and return in JBrowse2 format.
    // A url without a scheme or leading slash is a file this site serves itself
    // (js/vendor/, pinned in scripts/vendor_assets.txt) and resolves under the site prefix.
    // Self-hosted on purpose: the Content-Security-Policy allows scripts from 'self' only,
    // and an unversioned third-party URL changes under us without notice.
    $site = ConfigManager::getInstance()->getString('site', 'moop');
    $enabledPlugins = [];
    foreach ($plugins as $plugin) {
        if (isset($plugin['enabled']) && $plugin['enabled'] === true) {
            $url = (string) $plugin['url'];
            if (!preg_match('#^(https?:)?//|^/#', $url)) {
                $url = '/' . trim($site, '/') . '/' . $url;
            }
            $enabledPlugins[] = [
                'name' => $plugin['name'],
                'url' => $url
            ];
        }
    }
    
    return $enabledPlugins;
}

/**
 * Get plugin configuration for JBrowse2 config structure
 * 
 * @return array Plugin array (NOT nested in configuration)
 */
function getJBrowse2PluginConfiguration() {
    return loadJBrowse2Plugins();
}
