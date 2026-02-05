/**
 * JBrowse2 View Loader
 * 
 * Initializes JBrowse2 fullscreen viewer with specific assembly
 * Called from jbrowse2-view.php
 */

(function() {
    'use strict';

    /**
     * Wait for JBrowse2 to be loaded and ready
     */
    async function initializeJBrowse2() {
        try {
            const assemblyName = getAssemblyNameFromUrl();
            console.log('Initializing JBrowse2 for assembly:', assemblyName);

            if (!assemblyName) {
                showError('No assembly specified. Please use the Genome Browser to select an assembly.');
                return;
            }

            // Fetch assembly configuration
            const config = await fetchAssemblyConfig(assemblyName);
            
            if (!config) {
                showError('Assembly not found or access denied.');
                return;
            }

            // Update toolbar display
            document.getElementById('assembly-display').textContent = 
                config.displayName || assemblyName;
            
            updateUserDisplay();

            // Wait for JBrowse2 application to be ready
            waitForJBrowse2(async () => {
                await loadAssemblyInJBrowse2(config, assemblyName);
            });

        } catch (error) {
            console.error('Error initializing JBrowse2:', error);
            showError(`Failed to initialize: ${error.message}`);
        }
    }

    /**
     * Get assembly name from URL parameter
     */
    function getAssemblyNameFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.get('assembly');
    }

    /**
     * Fetch assembly configuration from API
     */
    async function fetchAssemblyConfig(assemblyName) {
        try {
            const site = window.moopSite || 'moop';
            const apiUrl = `/${site}/api/jbrowse2/get-config.php`;
            const response = await fetch(apiUrl);
            if (!response.ok) {
                throw new Error(`API error: ${response.status}`);
            }

            const data = await response.json();
            const assembly = (data.assemblies || []).find(a => a.name === assemblyName);
            
            return assembly || null;
        } catch (error) {
            console.error('Error fetching config:', error);
            return null;
        }
    }

    /**
     * Update user display in toolbar
     */
    function updateUserDisplay() {
        const userInfo = window.moopUserInfo;
        const userDisplay = document.getElementById('user-display');
        
        if (userInfo.logged_in) {
            userDisplay.textContent = `${userInfo.username} (${userInfo.access_level})`;
        } else {
            userDisplay.textContent = 'Guest (Public)';
        }
    }

    /**
     * Wait for JBrowse2 to load before initializing
     */
    function waitForJBrowse2(callback, maxAttempts = 100, delayMs = 100) {
        let attempts = 0;

        function check() {
            // Check if JBrowse2 session and root element exist
            if (window.JBrowse && window.JBrowse.SESSION && document.getElementById('root').children.length > 1) {
                console.log('JBrowse2 ready');
                callback();
            } else if (attempts < maxAttempts) {
                attempts++;
                setTimeout(check, delayMs);
            } else {
                showError('JBrowse2 failed to initialize. Check browser console for details.');
            }
        }

        check();
    }

    /**
     * Load assembly into JBrowse2 session
     */
    async function loadAssemblyInJBrowse2(config, assemblyName) {
        try {
            console.log('Loading assembly config into JBrowse2:', config);

            // Add assembly to JBrowse2 session if not already present
            const session = window.JBrowse?.SESSION;
            if (!session) {
                throw new Error('JBrowse2 session not found');
            }

            // Add the assembly configuration
            if (!session.assemblyManager.getAssembly(config.name)) {
                session.addAssemblyConfiguration({
                    name: config.name,
                    displayName: config.displayName,
                    aliases: config.aliases,
                    sequence: config.sequence
                });
            }

            // Create a linear genome view for this assembly
            createLinearView(session, config);

        } catch (error) {
            console.error('Error loading assembly:', error);
            showError(`Failed to load assembly: ${error.message}`);
        }
    }

    /**
     * Create a linear genome view
     */
    async function createLinearView(session, config) {
        try {
            // Create or update the view configuration
            const viewConfig = {
                type: 'LinearGenomeView',
                assembly: config.name,
                tracks: config.tracks || [],
                hideHeader: false,
                hideHeaderOverlay: false
            };

            // Check if view already exists, otherwise create new one
            if (!session.views || session.views.length === 0) {
                session.addView(viewConfig);
            } else {
                // Update existing view
                const view = session.views[0];
                if (view.assemblyNames && !view.assemblyNames.includes(config.name)) {
                    view.assemblyNames.push(config.name);
                }
            }

            // Update loading message
            document.getElementById('root').innerHTML = ''; // Clear loading message

            console.log('Assembly loaded successfully');

        } catch (error) {
            console.error('Error creating linear view:', error);
            showError(`Failed to create view: ${error.message}`);
        }
    }

    /**
     * Show error message to user
     */
    function showError(message) {
        const root = document.getElementById('root');
        root.innerHTML = `
            <div style="
                padding: 2rem;
                text-align: center;
                color: #721c24;
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 0.25rem;
                margin: 2rem;
            ">
                <h4>Error</h4>
                <p>${escapeHtml(message)}</p>
                <p style="font-size: 0.875rem; margin-top: 1rem;">
                    <a href="javascript:window.close()">Close this window</a>
                </p>
            </div>
        `;
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize when DOM is ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeJBrowse2);
    } else {
        initializeJBrowse2();
    }
})();
