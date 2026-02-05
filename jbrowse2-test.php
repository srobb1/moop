<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBrowse2 Assembly Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .test-group {
            background: #f9f9f9;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .test-group h3 {
            color: #007bff;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
            margin-bottom: 10px;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #0056b3;
        }
        
        button.secondary {
            background: #6c757d;
        }
        
        button.secondary:hover {
            background: #545b62;
        }
        
        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
            display: none;
        }
        
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        
        .result.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            display: block;
        }
        
        .code-block {
            background: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .loading {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .stat-value {
            color: #333;
            font-size: 24px;
            font-weight: bold;
        }
        
        .endpoint-list {
            list-style: none;
        }
        
        .endpoint-list li {
            padding: 10px;
            margin: 5px 0;
            background: #f4f4f4;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        
        .endpoint-list code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .success-checkmark {
            color: #28a745;
            font-weight: bold;
        }
        
        .error-cross {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧬 JBrowse2 Assembly Test</h1>
        <p class="subtitle">Test the JBrowse2 assembly API and view genome data</p>
        
        <div class="section">
            <h2>📊 System Status</h2>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-label">Assemblies Configured</div>
                    <div class="stat-value" id="assembly-count">-</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tracks Available</div>
                    <div class="stat-value" id="track-count">-</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">API Status</div>
                    <div class="stat-value" id="api-status">-</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>🧪 API Tests</h2>
            
            <div class="test-group">
                <h3>Test 1: Fetch Assembly Configuration (Public Access)</h3>
                <p>Load assembly config for <strong>Anoura_caudifer (GCA_004027475.1)</strong> with public access</p>
                <button onclick="testPublicAccess()">Test Public Access</button>
                <div id="result-public" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 2: Admin Access</h3>
                <p>Load assembly config with <strong>admin (ALL) access level</strong></p>
                <button onclick="testAdminAccess()">Test Admin Access</button>
                <div id="result-admin" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 3: Verify Assembly Definition</h3>
                <p>Check if assembly definition file exists and is valid</p>
                <button onclick="verifyAssemblyDefinition()">Verify Definition</button>
                <div id="result-verify" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 4: Track Loading</h3>
                <p>Verify tracks are being filtered and loaded correctly</p>
                <button onclick="testTrackLoading()">Test Tracks</button>
                <div id="result-tracks" class="result"></div>
            </div>
        </div>
        
        <div class="section">
            <h2>🌐 View in JBrowse2</h2>
            <p>Once tests pass, you can view the genome in JBrowse2:</p>
            <div style="margin-top: 15px;">
                <button class="secondary" onclick="window.open('/jbrowse2/', '_blank')">
                    Open JBrowse2 in New Tab →
                </button>
            </div>
        </div>
        
        <div class="section">
            <h2>📡 API Endpoints</h2>
            <ul class="endpoint-list">
                <li>
                    <strong>Test API (No Session):</strong><br>
                    <code>/api/jbrowse2/test-assembly.php?organism=ORGANISM&assembly=ASSEMBLY&access_level=PUBLIC</code>
                </li>
                <li>
                    <strong>Production API (With Session):</strong><br>
                    <code>/api/jbrowse2/assembly.php?organism=ORGANISM&assembly=ASSEMBLY</code>
                </li>
                <li>
                    <strong>Assembly Definition Files:</strong><br>
                    <code>/metadata/jbrowse2-configs/assemblies/{ORGANISM}_{ASSEMBLY}.json</code>
                </li>
            </ul>
        </div>
        
        <div class="section">
            <h2>📝 Raw API Response</h2>
            <button onclick="showRawResponse()">Show Raw JSON Response</button>
            <div id="raw-response" style="margin-top: 15px; display: none;">
                <div class="code-block" id="raw-response-content"></div>
            </div>
        </div>
    </div>

    <script>
        // Utility function to get the correct API base URL
        function getApiBaseUrl() {
            // If we're accessing through localhost:8000 tunnel, use that same base
            const currentUrl = new URL(window.location.href);
            return currentUrl.origin; // This will be http://localhost:8000
        }

        // Utility function to display results
        function displayResult(elementId, message, type = 'info', details = null) {
            const element = document.getElementById(elementId);
            element.className = `result ${type}`;
            element.innerHTML = `<strong>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</strong> ${message}`;
            
            if (details) {
                element.innerHTML += `<div class="code-block" style="margin-top: 10px; color: inherit;">${details}</div>`;
            }
        }

        // Test 1: Public Access
        async function testPublicAccess() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = 'Testing<span class="loading"></span>';
            
            try {
                const baseUrl = getApiBaseUrl();
                const response = await fetch(`${baseUrl}/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public`);
                const data = await response.json();
                
                if (response.ok) {
                    const trackCount = data.tracks ? data.tracks.length : 0;
                    const assemblyName = data.assemblies?.[0]?.displayName || 'Unknown';
                    
                    displayResult('result-public', 
                        `✓ Successfully loaded assembly config with ${trackCount} track(s)`,
                        'success',
                        `Assembly: <strong>${assemblyName}</strong><br>
                         Display Name: <strong>${data.assemblies?.[0]?.displayName}</strong><br>
                         Aliases: <strong>${data.assemblies?.[0]?.aliases?.join(', ')}</strong><br>
                         Tracks: ${trackCount}`
                    );
                } else {
                    displayResult('result-public', `Error: ${data.error}`, 'error');
                }
            } catch (error) {
                displayResult('result-public', `Network error: ${error.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Test Public Access';
            }
        }

        // Test 2: Admin Access
        async function testAdminAccess() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = 'Testing<span class="loading"></span>';
            
            try {
                const baseUrl = getApiBaseUrl();
                const response = await fetch(`${baseUrl}/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL`);
                const data = await response.json();
                
                if (response.ok) {
                    const trackCount = data.tracks ? data.tracks.length : 0;
                    
                    displayResult('result-admin', 
                        `✓ Successfully loaded admin config with ${trackCount} track(s)`,
                        'success',
                        `Access Level: <strong>Admin (ALL)</strong><br>
                         Visible Tracks: ${trackCount}<br>
                         Includes admin-only tracks`
                    );
                } else {
                    displayResult('result-admin', `Error: ${data.error}`, 'error');
                }
            } catch (error) {
                displayResult('result-admin', `Network error: ${error.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Test Admin Access';
            }
        }

        // Test 3: Verify Definition
        async function verifyAssemblyDefinition() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = 'Checking<span class="loading"></span>';
            
            try {
                const baseUrl = getApiBaseUrl();
                const response = await fetch(`${baseUrl}/api/jbrowse2/get-assembly-definition.php?organism=Anoura_caudifer&assembly=GCA_004027475.1`);
                const data = await response.json();
                
                if (response.ok) {
                    displayResult('result-verify',
                        `✓ Assembly definition file is valid JSON`,
                        'success',
                        `File: <strong>Anoura_caudifer_GCA_004027475.1.json</strong><br>
                         Name: <strong>${data.name}</strong><br>
                         Display: <strong>${data.displayName}</strong><br>
                         Aliases: <strong>${data.aliases?.join(', ')}</strong><br>
                         Access Level: <strong>${data.defaultAccessLevel}</strong>`
                    );
                } else {
                    displayResult('result-verify', `Error: ${data.error}`, 'error');
                }
            } catch (error) {
                displayResult('result-verify', `Error: ${error.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Verify Definition';
            }
        }

        // Test 4: Track Loading
        async function testTrackLoading() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = 'Testing<span class="loading"></span>';
            
            try {
                const baseUrl = getApiBaseUrl();
                const response = await fetch(`${baseUrl}/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL`);
                const data = await response.json();
                
                if (response.ok && data.tracks) {
                    const tracks = data.tracks.map(t => `<li>${t.name} (${t.type})</li>`).join('');
                    
                    displayResult('result-tracks',
                        `✓ Successfully loaded ${data.tracks.length} track(s)`,
                        'success',
                        `<strong>Loaded Tracks:</strong><ul style="margin-left: 20px; margin-top: 10px;">${tracks}</ul>`
                    );
                } else {
                    displayResult('result-tracks', `No tracks found`, 'error');
                }
            } catch (error) {
                displayResult('result-tracks', `Error: ${error.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Test Tracks';
            }
        }

        // Show Raw Response
        async function showRawResponse() {
            const element = document.getElementById('raw-response');
            if (element.style.display === 'block') {
                element.style.display = 'none';
                return;
            }
            
            try {
                const baseUrl = getApiBaseUrl();
                const response = await fetch(`${baseUrl}/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public`);
                const data = await response.json();
                
                document.getElementById('raw-response-content').textContent = JSON.stringify(data, null, 2);
                element.style.display = 'block';
            } catch (error) {
                document.getElementById('raw-response-content').textContent = `Error: ${error.message}`;
                element.style.display = 'block';
            }
        }

        // Initialize on page load
        window.addEventListener('load', function() {
            const baseUrl = getApiBaseUrl();
            
            // Count assemblies by trying to fetch known assembly and checking if it works
            fetch(`${baseUrl}/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public`)
                .then(r => {
                    if (r.ok) {
                        document.getElementById('assembly-count').textContent = '1';
                    } else {
                        document.getElementById('assembly-count').textContent = '0';
                    }
                })
                .catch(() => document.getElementById('assembly-count').textContent = '?');
            
            // Count tracks from the API response
            fetch(`${baseUrl}/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL`)
                .then(r => r.json())
                .then(data => {
                    const count = data.tracks ? data.tracks.length : 0;
                    document.getElementById('track-count').textContent = count || '0';
                })
                .catch(() => document.getElementById('track-count').textContent = '?');
            
            // Check API status
            fetch(`${baseUrl}/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public`)
                .then(r => r.status < 500 ? '✓ OK' : '✗ Error')
                .then(status => document.getElementById('api-status').textContent = status)
                .catch(() => document.getElementById('api-status').textContent = '✗ Error');
        });
    </script>
</body>
</html>
