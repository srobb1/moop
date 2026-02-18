<?php
/**
 * Local test page - Access via http://127.0.0.1:8888/jbrowse2-test-local.php
 * This version is designed to work with the PHP dev server on port 8888
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBrowse2 Assembly Test (Local)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #333; margin-bottom: 10px; font-size: 32px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 16px; }
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
        .code-block { background: #f4f4f4; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0; overflow-x: auto; font-family: monospace; font-size: 13px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; text-align: center; }
        .stat-label { color: #666; font-size: 13px; text-transform: uppercase; margin-bottom: 5px; }
        .stat-value { color: #333; font-size: 24px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧬 JBrowse2 Assembly Test (Local Dev Server)</h1>
        <p class="subtitle">Testing via PHP dev server on localhost:8888</p>
        
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
                <button onclick="testPublicAccess()">Test Public Access</button>
                <div id="result-public" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 2: Admin Access</h3>
                <button onclick="testAdminAccess()">Test Admin Access</button>
                <div id="result-admin" class="result"></div>
            </div>
            
            <div class="test-group">
                <h3>Test 3: Verify Definition</h3>
                <button onclick="verifyAssemblyDefinition()">Verify Definition</button>
                <div id="result-verify" class="result"></div>
            </div>
        </div>
    </div>

    <script>
        function displayResult(id, msg, type) {
            const el = document.getElementById(id);
            el.className = 'result ' + type;
            el.innerHTML = msg;
        }

        async function testPublicAccess() {
            try {
                const r = await fetch('/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public');
                const data = await r.json();
                if (r.ok) {
                    const tracks = data.tracks ? data.tracks.length : 0;
                    displayResult('result-public', 
                        `✓ Assembly loaded with ${tracks} tracks<br>Name: ${data.assemblies[0].displayName}`,
                        'success'
                    );
                } else {
                    displayResult('result-public', `✗ Error: ${data.error}`, 'error');
                }
            } catch (e) {
                displayResult('result-public', `✗ Error: ${e.message}`, 'error');
            }
        }

        async function testAdminAccess() {
            try {
                const r = await fetch('/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL');
                const data = await r.json();
                if (r.ok) {
                    const tracks = data.tracks ? data.tracks.length : 0;
                    displayResult('result-admin', 
                        `✓ Admin config loaded with ${tracks} tracks`,
                        'success'
                    );
                } else {
                    displayResult('result-admin', `✗ Error: ${data.error}`, 'error');
                }
            } catch (e) {
                displayResult('result-admin', `✗ Error: ${e.message}`, 'error');
            }
        }

        async function verifyAssemblyDefinition() {
            try {
                const r = await fetch('/api/jbrowse2/get-assembly-definition.php?organism=Anoura_caudifer&assembly=GCA_004027475.1');
                const data = await r.json();
                if (r.ok) {
                    displayResult('result-verify', 
                        `✓ Definition file valid<br>Name: ${data.displayName}<br>Aliases: ${data.aliases.join(', ')}`,
                        'success'
                    );
                } else {
                    displayResult('result-verify', `✗ Error: ${data.error}`, 'error');
                }
            } catch (e) {
                displayResult('result-verify', `✗ Error: ${e.message}`, 'error');
            }
        }

        window.addEventListener('load', function() {
            fetch('/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=Public')
                .then(r => r.ok ? document.getElementById('assembly-count').textContent = '1' : document.getElementById('assembly-count').textContent = '0')
                .catch(() => document.getElementById('assembly-count').textContent = '?');
            
            fetch('/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1&access_level=ALL')
                .then(r => r.json())
                .then(d => document.getElementById('track-count').textContent = (d.tracks ? d.tracks.length : 0))
                .catch(() => document.getElementById('track-count').textContent = '?');
            
            fetch('/api/jbrowse2/test-assembly.php?organism=Anoura_caudifer&assembly=GCA_004027475.1')
                .then(r => document.getElementById('api-status').textContent = (r.status < 500 ? '✓ OK' : '✗ Error'))
                .catch(() => document.getElementById('api-status').textContent = '✗ Error');
        });
    </script>
</body>
</html>
