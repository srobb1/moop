/**
 * JBrowse2 View Loader
 * Intercepts JBrowse2 config loading and provides dynamic assembly configuration
 * based on user authentication and permissions
 */

console.log('JBrowse2 View Loader: Initializing');
console.log('Assembly:', window.moopAssemblyName);
console.log('User:', window.moopUserInfo?.username);

// Fetch and inject the config before JBrowse2 loads
(async function initializeJBrowse2() {
    try {
        const userInfo = window.moopUserInfo;
        const assemblyName = window.moopAssemblyName;
        const site = window.moopSite;

        if (!userInfo || !assemblyName) {
            console.error('Missing required configuration: userInfo or assemblyName');
            return;
        }

        console.log('Fetching JBrowse2 config for assembly:', assemblyName);

        // Get the API base URL from the site
        const apiUrl = `/${site}/api/jbrowse2/get-config.php?assembly=${encodeURIComponent(assemblyName)}`;
        
        const response = await fetch(apiUrl, {
            credentials: 'include', // Include cookies for authentication
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch config: ${response.status} ${response.statusText}`);
        }

        const configData = await response.json();
        console.log('Config loaded:', configData);

        // Make the config globally available to JBrowse2
        window.jbrowseConfig = configData;

        // Override the fetch for config.json to return our dynamic config
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

        console.log('JBrowse2 View Loader: Ready');

    } catch (error) {
        console.error('JBrowse2 View Loader Error:', error);
        alert('Failed to initialize JBrowse2: ' + error.message);
    }
})();
