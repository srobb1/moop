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
            const url = `/${site}/api/jbrowse2/get-config.php?assembly=${encodeURIComponent(assemblyName)}`;
            
            console.log('Fetching from:', url);
            const response = await fetch(url, {
                credentials: 'include'
            });
            
            if (!response.ok) {
                console.error('API response:', response.status, response.statusText);
                return null;
            }
            
            const data = await response.json();
            console.log('Config response:', data);
            return data;
        } catch (error) {
            console.error('Error fetching assembly config:', error);
            return null;
        }
    }

    /**
     * Intercept fetch for config.json to return our dynamic config
     */
    function interceptConfigFetch(configData) {
        const originalFetch = window.fetch;
        window.fetch = function(url, options) {
            // If fetching config.json, return our dynamic config
            if (typeof url === 'string' && url.includes('config.json')) {
                console.log('Intercepting config.json request, returning dynamic config');
                return Promise.resolve(
                    new Response(JSON.stringify(configData), {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' }
                    })
                );
            }
            // For all other requests, use original fetch
            return originalFetch.apply(this, arguments);
        };
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

            // Build the full JBrowse2 config from assembly definition
            const jbrowse2Config = {
                assemblies: [config],
                disableSessionStorage: true,
                defaultSession: {
                    name: 'Default',
                    view: {
                        id: 'linear-genome-view',
                        type: 'LinearGenomeView',
                        offsetPx: 0,
                        bpPerPx: 1,
                        minimumBlockWidth: 1,
                        tracks: []
                    }
                }
            };

            // Intercept JBrowse2's config.json fetch to return our dynamic config
            interceptConfigFetch(jbrowse2Config);
            
            console.log('JBrowse2 View Loader: Ready');

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
