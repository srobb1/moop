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
        console.log('Opening assembly:', assembly);

        // Create a basic genome view session
        const session = {
            name: `${assembly.displayName} - View`,
            view: {
                type: 'LinearGenomeView',
                assemblies: [assembly.name],
                tracks: []
            }
        };

        // For now, show the assembly details
        // In a full implementation, this would:
        // 1. Initialize JBrowse2 LinearGenomeView
        // 2. Pass the assembly configuration
        // 3. Load available tracks for the user
        
        alert(`Opening ${assembly.displayName}\n\nFull JBrowse2 view coming soon.\n\nAssembly: ${assembly.name}\nAliases: ${assembly.aliases.join(', ')}`);
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
     * Initialize on page load
     */
    function init() {
        console.log('Initializing JBrowse2 loader');
        loadAssemblies();
    }

    // Load when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
