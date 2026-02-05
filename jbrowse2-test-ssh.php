<?php
/**
 * Test page for SSH tunnel access
 * Access via: http://localhost:8000/moop/jbrowse2-test-ssh.php
 * 
 * This version automatically detects the PHP dev server on port 8888
 * and creates URLs that tunnel through to it
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBrowse2 Assembly Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #333; margin-bottom: 10px; font-size: 32px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .info-box { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .section { margin-bottom: 40px; }
        .section h2 { color: #333; font-size: 20px; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .test-group { background: #f9f9f9; border-left: 4px solid #007bff; padding: 20px; margin-bottom: 15px; border-radius: 4px; }
        .test-group h3 { color: #007bff; margin-bottom: 10px; font-size: 16px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; margin-right: 10px; margin-bottom: 10px; transition: background 0.3s; }
        button:hover { background: #0056b3; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .result { margin-top: 15px; padding: 15px; border-radius: 4px; display: none; }
        .result.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; display: block; }
        .result.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; display: block; }
        .result.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; display: block; }
        .code-block { background: #f4f4f4; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0; overflow-x: auto; font-family: monospace; font-size: 12px; word-break: break-all; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; text-align: center; }
        .stat-label { color: #666; font-size: 13px; text-transform: uppercase; margin-bottom: 5px; }
        .stat-value { color: #333; font-size: 24px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧬 JBrowse2 Assembly Test</h1>
        <p class="subtitle">Testing JBrowse2 API through SSH tunnel</p>
        
        <div class="info-box">
            <strong>ℹ️ SSH Tunnel Configuration:</strong><br>
            Local Port: 8000 → Server Port: 80<br>
            <span id="api-url-display">API calls will be routed to: http://localhost:8000</span>
        </div>
        
        <div class="section">
            <h2>📊 System Status</h2>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-label">Assemblies</div>
                    <div class="stat-value" id="assembly-count">-</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tracks</div>
                    <div class="stat-value" id="track-count">-</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">API</div>
                    <div class="stat-value" id="api-status">-</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>🧪 API Tests</h2>
            
            <div class="test-group">
                <h3>Test 1: Public Access</h3>
                <p>Load assembly config with public track access</p>
                <button onclick="testPublicAccess()">Test Public Access</button>
                <div id="result-public" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 2: Admin Access</h3>
                <p>Load assembly config with all tracks (admin access)</p>
                <button onclick="testAdminAccess()">Test Admin Access</button>
                <div id="result-admin" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 3: Verify Definition</h3>
                <p>Check assembly definition file</p>
                <button onclick="verifyAssemblyDefinition()">Verify Definition</button>
                <div id="result-verify" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 4: View JBrowse2</h3>
                <p>Open the JBrowse2 genome browser</p>
                <button onclick="window.open('/jbrowse2/', '_blank')">Open JBrowse2 →</button>
            </div>
        </div>
        
        <div class="section">
            <h2>🔧 Troubleshooting</h2>
            <p>If tests fail with 404 errors:</p>
            <ol style="margin-left: 20px; line-height: 1.8;">
                <li>Verify SSH tunnel is active: <code>ssh -L 8000:localhost:80 ubuntu@ec2-3-92-1-223.compute-1.amazonaws.com</code></li>
                <li>Verify PHP dev server is running: <code>ps aux | grep "php -S"</code></li>
                <li>Check server logs for errors</li>
            </ol>
        </div>
    </div>

    <script>
        // Get the API base URL - works through SSH tunnel on port 8000
        function getApiUrl(endpoint) {
            // Apache serves MOOP at /moop/, so we need to use /moop/api/...
            if (!endpoint.startsWith('/moop')) {
                endpoint = '/moop' + endpoint;
            }
            const currentUrl = new URL(window.location.href);
            return currentUrl.origin + endpoint;
        }

        function displayResult(id, msg, type) {
            const el = document.getElementById(id);
            el.className = 'result ' + type;
            el.innerHTML = msg;
        }

        async function testPublicAccess() {
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Testing...';
            
            try {
                const url = getApiUrl('/moop/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public');
                const r = await fetch(url);
                const data = await r.json();
                
                if (r.ok) {
                    const tracks = data.tracks ? data.tracks.length : 0;
                    const name = data.assemblies?.[0]?.displayName || 'Unknown';
                    displayResult('result-public', 
                        `✓ Assembly loaded successfully<br><strong>${name}</strong><br>Tracks: ${tracks}`,
                        'success'
                    );
                } else {
                    displayResult('result-public', `✗ Error: ${data.error}`, 'error');
                }
            } catch (e) {
                displayResult('result-public', `✗ Network error: ${e.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Test Public Access';
            }
        }

        async function testAdminAccess() {
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Testing...';
            
            try {
                const url = getApiUrl('/moop/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL');
                const r = await fetch(url);
                const data = await r.json();
                
                if (r.ok) {
                    const tracks = data.tracks ? data.tracks.length : 0;
                    displayResult('result-admin', 
                        `✓ Admin config loaded<br>Total tracks: ${tracks} (includes admin-only)`,
                        'success'
                    );
                } else {
                    displayResult('result-admin', `✗ Error: ${data.error}`, 'error');
                }
            } catch (e) {
                displayResult('result-admin', `✗ Network error: ${e.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Test Admin Access';
            }
        }

        async function verifyAssemblyDefinition() {
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Checking...';
            
            try {
                const url = getApiUrl('/moop/api/jbrowse2/get-assembly-definition.php?organism=Anoura_caudifer&assembly=GCA_004027475.1');
                const r = await fetch(url);
                const data = await r.json();
                
                if (r.ok) {
                    displayResult('result-verify', 
                        `✓ Definition valid<br><strong>${data.displayName}</strong><br>Aliases: ${data.aliases.join(', ')}`,
                        'success'
                    );
                } else {
                    displayResult('result-verify', `✗ Error: ${data.error}`, 'error');
                }
            } catch (e) {
                displayResult('result-verify', `✗ Network error: ${e.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Verify Definition';
            }
        }

        // Load page status
        window.addEventListener('load', function() {
            const baseUrl = getApiUrl('');
            document.getElementById('api-url-display').textContent = 'API calls routed to: ' + baseUrl;
            
            fetch(getApiUrl('/moop/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public'))
                .then(r => {
                    document.getElementById('assembly-count').textContent = r.ok ? '1' : '0';
                    document.getElementById('api-status').textContent = r.ok ? '✓ OK' : '✗ Error';
                })
                .catch(e => {
                    document.getElementById('assembly-count').textContent = '?';
                    document.getElementById('api-status').textContent = '✗ Error';
                });
            
            fetch(getApiUrl('/moop/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL'))
                .then(r => r.json())
                .then(d => document.getElementById('track-count').textContent = (d.tracks ? d.tracks.length : 0))
                .catch(() => document.getElementById('track-count').textContent = '?');
        });
    </script>
</body>
</html>
