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
    
    $plugins = json_decode(file_get_contents($pluginFile), true);
    
    if (!is_array($plugins)) {
        error_log("Invalid JBrowse2 plugin config format");
        return [];
    }
    
    // Filter to only enabled plugins and return in JBrowse2 format
    $enabledPlugins = [];
    foreach ($plugins as $plugin) {
        if (isset($plugin['enabled']) && $plugin['enabled'] === true) {
            $enabledPlugins[] = [
                'name' => $plugin['name'],
                'url' => $plugin['url']
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
