/**
 * JBrowse2 View Loader
 * 
 * Initializes JBrowse2 fullscreen viewer with specific assembly
 * Called from jbrowse2-view.php
 */

(function() {
    'use strict';

    /**
     * Fetch assembly configuration from API
     */
    async function fetchAssemblyConfig(assemblyName) {
        try {
            const site = window.moopSite || 'moop';
            const userInfo = window.moopUserInfo || { logged_in: false };
            
            const accessLevel = userInfo.access_level || 'Public';
            const url = `/${site}/api/jbrowse2/get-config.php?assembly=${encodeURIComponent(assemblyName)}&access_level=${encodeURIComponent(accessLevel)}`;
            
            console.log('Fetching from:', url);
            const response = await fetch(url);
            
            if (!response.ok) {
                console.error('API response:', response.status, response.statusText);
                return null;
            }
            
            const data = await response.json();
            if (data.success) {
                return data.config;
            }
            return null;
        } catch (error) {
            console.error('Error fetching assembly config:', error);
            return null;
        }
    }

    /**
     * Wait for JBrowse2 to be ready
     */
    function waitForJBrowse2Ready(callback, maxAttempts = 30) {
        let attempts = 0;
        
        function check() {
            attempts++;
            
            // Check if JBrowse2 API is available
            if (window.JBrowse && window.JBrowse2) {
                console.log('JBrowse2 is ready');
                callback();
                return;
            }
            
            if (attempts < maxAttempts) {
                setTimeout(check, 100);
            } else {
                console.warn('JBrowse2 did not load in time');
            }
        }
        
        check();
    }

    /**
     * Configure JBrowse2 with the assembly
     */
    async function configureJBrowse2(config, assemblyName) {
        try {
            console.log('Configuring JBrowse2 with assembly config:', config);
            
            // JBrowse2 should pick up the config from sessionStorage or configURL
            // We'll store it for the app to find
            sessionStorage.setItem('moopAssemblyConfig', JSON.stringify(config));
            
        } catch (error) {
            console.error('Error configuring JBrowse2:', error);
        }
    }

    /**
     * Initialize when DOM is ready
     */
    async function init() {
        try {
            const assemblyName = window.moopAssemblyName;
            const userInfo = window.moopUserInfo || { logged_in: false };
            
            console.log('JBrowse2 View Loader: Initializing');
            console.log('Assembly:', assemblyName);
            console.log('User:', userInfo.username || 'Guest');

            if (!assemblyName) {
                console.error('No assembly specified');
                return;
            }

            // Fetch assembly configuration from API
            console.log('Fetching JBrowse2 config for assembly:', assemblyName);
            const config = await fetchAssemblyConfig(assemblyName);
            
            if (!config) {
                console.error('Assembly not found or access denied');
                return;
            }

            console.log('Config loaded:', config);

            // Store config for JBrowse2 to use
            await configureJBrowse2(config, assemblyName);
            
            // Wait for JBrowse2 to initialize, then inject the assembly
            waitForJBrowse2Ready(async () => {
                console.log('JBrowse2 ready, injecting assembly config');
                
                // Use JBrowse2 API to add assembly if available
                if (window.JBrowse2 && window.JBrowse2.app) {
                    try {
                        // Try to open the assembly
                        window.JBrowse2.app.showModal(config.displayName || assemblyName);
                    } catch (e) {
                        console.warn('Could not use JBrowse2 API:', e);
                    }
                }
            });

        } catch (error) {
            console.error('Error initializing JBrowse2 View:', error);
        }
    }

    // Initialize when document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
