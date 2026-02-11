/**
 * JBrowse2 Dynamic Loader
 * 
 * Fetches and displays assemblies based on user authentication
 * Integrates with MOOP authentication system
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        apiUrl: '/moop/api/jbrowse2/get-config.php',
        containerSelector: '#assembly-list-container',
        countSelector: '#assembly-count',
        userStatusSelector: '#user-status'
    };

    /**
     * Load assemblies from API based on user authentication
     */
    async function loadAssemblies() {
        try {
            console.log('Loading assemblies from:', CONFIG.apiUrl);
            console.log('User info:', window.moopUserInfo);

            const response = await fetch(CONFIG.apiUrl);
            if (!response.ok) {
                throw new Error(`API error: ${response.status}`);
            }

            const config = await response.json();

            // Update user status
            updateUserStatus(config.userAccessLevel);

            // Display assemblies
            if (!config.assemblies || config.assemblies.length === 0) {
                displayNoAssemblies();
                return;
            }

            // Display assembly list
            displayAssemblies(config.assemblies);

            // Update count
            document.querySelector(CONFIG.countSelector).textContent = 
                `${config.assemblies.length} Available`;

        } catch (error) {
            console.error('Error loading assemblies:', error);
            displayError(error.message);
        }
    }

    /**
     * Update user status display
     */
    function updateUserStatus(accessLevel) {
        const statusEl = document.querySelector(CONFIG.userStatusSelector);
        if (!statusEl) return;

        const userInfo = window.moopUserInfo;
        let statusText = '';

        if (userInfo.logged_in) {
            statusText = `Logged in as <strong>${userInfo.username}</strong> (${accessLevel})`;
        } else {
            statusText = `Guest user viewing <strong>${accessLevel}</strong> content`;
        }

        statusEl.innerHTML = statusText;
    }

    /**
     * Display list of assemblies
     */
    function displayAssemblies(assemblies) {
        const container = document.querySelector(CONFIG.containerSelector);
        
        let html = '<div class="list-group">';

        assemblies.forEach(assembly => {
            html += createAssemblyCard(assembly);
        });

        html += '</div>';
        container.innerHTML = html;

        // Attach click handlers
        assemblies.forEach(assembly => {
            const btn = container.querySelector(`[data-assembly-id="${assembly.name}"]`);
            if (btn) {
                btn.addEventListener('click', () => openAssembly(assembly));
            }
        });
    }

    /**
     * Create HTML card for an assembly
     */
    function createAssemblyCard(assembly) {
        const accessLevelClass = {
            'Public': 'badge-light border-success',
            'Collaborator': 'badge-warning',
            'ALL': 'badge-danger'
        }[assembly.accessLevel] || 'badge-secondary';

        const accessLevelText = {
            'Public': 'Public',
            'Collaborator': 'Collaborator',
            'ALL': 'Admin'
        }[assembly.accessLevel] || assembly.accessLevel;

        return `
            <div class="list-group-item assembly-card">
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">${escapeHtml(assembly.displayName)}</h5>
                        <p class="mb-2 text-muted small">
                            <strong>Aliases:</strong> ${escapeHtml(assembly.aliases.join(', '))}
                        </p>
                        <span class="badge ${accessLevelClass}">${accessLevelText}</span>
                    </div>
                    <button class="btn btn-sm btn-primary" 
                            data-assembly-id="${escapeHtml(assembly.name)}"
                            data-assembly-name="${escapeHtml(assembly.displayName)}">
                        View Genome →
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Display when no assemblies are available
     */
    function displayNoAssemblies() {
        const container = document.querySelector(CONFIG.containerSelector);
        
        const userInfo = window.moopUserInfo;
        let message = '';

        if (userInfo.logged_in) {
            message = `
                <div class="access-denied">
                    <h4>No Assemblies Available</h4>
                    <p>Your account doesn't have access to any assemblies yet.</p>
                    <p class="text-muted">Contact an administrator for access.</p>
                </div>
            `;
        } else {
            message = `
                <div class="access-denied">
                    <h4>Public Assemblies Not Available</h4>
                    <p>There are currently no public assemblies available for browsing.</p>
                    <p class="text-muted">
                        <a href="/moop/login.php">Log in</a> to access additional data.
                    </p>
                </div>
            `;
        }

        container.innerHTML = message;
        document.querySelector(CONFIG.countSelector).textContent = '0 Available';
    }

    /**
     * Display error message
     */
    function displayError(message) {
        const container = document.querySelector(CONFIG.containerSelector);
        
        const html = `
            <div class="alert alert-danger">
                <h4>Error Loading Assemblies</h4>
                <p>${escapeHtml(message)}</p>
                <p class="text-muted small mb-0">
                    Check the browser console (F12) for more details.
                </p>
            </div>
        `;

        container.innerHTML = html;
    }

    /**
     * Open assembly in JBrowse2
     */
    function openAssembly(assembly) {
        console.log('Opening assembly in iframe:', assembly);
        
        // Hide assembly list and show viewer
        document.getElementById('assembly-list-container').style.display = 'none';
        document.getElementById('assembly-viewer-container').style.display = 'block';
        
        // Extract organism and assembly ID from assembly.name (format: Organism_Assembly)
        const nameParts = assembly.name.split('_');
        let organism, assemblyId;
        
        if (nameParts.length >= 2) {
            // Find the assembly ID part (starts with GCA or GCF)
            const gcaIndex = nameParts.findIndex(part => part.startsWith('GCA') || part.startsWith('GCF'));
            if (gcaIndex !== -1) {
                organism = nameParts.slice(0, gcaIndex).join('_');
                assemblyId = nameParts.slice(gcaIndex).join('_');
            } else {
                // Fallback: last part is assembly ID
                organism = nameParts.slice(0, -1).join('_');
                assemblyId = nameParts[nameParts.length - 1];
            }
        } else {
            organism = assembly.name;
            assemblyId = 'default';
        }
        
        // Use cached config endpoint for better performance
        // This caches configs per access level and validates permissions
        const iframe = document.getElementById('jbrowse2-iframe');
        const configUrl = `/moop/api/jbrowse2/assembly-cached.php?organism=${encodeURIComponent(organism)}&assembly=${encodeURIComponent(assemblyId)}`;
        iframe.src = `/moop/jbrowse2/index.html?config=${encodeURIComponent(configUrl)}`;
        iframe.title = `JBrowse2 Viewer for ${assembly.displayName}`;
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Load a specific assembly and display JBrowse2 viewer
     */
    async function loadSpecificAssembly(assemblyName) {
        try {
            updateUserStatus();
            
            const container = document.getElementById('assembly-list-container');
            if (!container) {
                console.error('Container not found');
                return;
            }
            
            // Show loading state
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading JBrowse2 viewer for ${escapeHtml(assemblyName)}...</p>
                </div>
            `;
            
            // Load configuration from API
            const userInfo = window.moopUserInfo;
            const response = await fetch('/moop/api/jbrowse2/get-config.php');
            
            if (!response.ok) {
                throw new Error(`Failed to load config: ${response.status}`);
            }
            
            const config = await response.json();
            
            // Find the requested assembly
            const assembly = (config.assemblies || []).find(a => a.name === assemblyName);
            
            if (!assembly) {
                container.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <strong>Assembly not found:</strong> ${escapeHtml(assemblyName)}
                    </div>
                `;
                return;
            }
            
            // Check access level
            if (assembly.accessLevel === 'Admin' && userInfo.access_level !== 'Admin') {
                container.innerHTML = `
                    <div class="alert alert-warning" role="alert">
                        <strong>Access Denied:</strong> This assembly requires admin access.
                    </div>
                `;
                return;
            }
            
            // Display JBrowse2 viewer in an iframe
            const iframeUrl = `/moop/jbrowse2-view.php?assembly=${encodeURIComponent(assembly.name)}`;
            
            container.innerHTML = `
                <div style="height: 800px; width: 100%; border: 1px solid #ddd;">
                    <iframe 
                        src="${escapeHtml(iframeUrl)}" 
                        style="width: 100%; height: 100%; border: none;"
                        title="JBrowse2 Viewer for ${escapeHtml(assembly.displayName)}"
                    ></iframe>
                </div>
                <div style="margin-top: 1rem;">
                    <a href="/moop/jbrowse2.php" class="btn btn-sm btn-secondary">
                        ← Back to Assembly List
                    </a>
                </div>
            `;
        } catch (error) {
            console.error('Error loading specific assembly:', error);
            const container = document.getElementById('assembly-list-container');
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <strong>Error loading assembly:</strong> ${escapeHtml(error.message)}
                    </div>
                `;
            }
        }
    }

    /**
     * Initialize on page load
     */
    function init() {
        console.log('Initializing JBrowse2 loader');
        
        // Check if an assembly is specified in the URL
        const params = new URLSearchParams(window.location.search);
        const assemblyName = params.get('assembly');
        
        if (assemblyName) {
            console.log('Loading specific assembly:', assemblyName);
            loadSpecificAssembly(assemblyName);
        } else {
            console.log('Loading assembly list');
            loadAssemblies();
        }
    }

    // Load when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
