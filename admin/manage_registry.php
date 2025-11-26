<?php
ob_start();
include_once __DIR__ . '/admin_init.php';

// Handle AJAX requests after admin access verification
if (isset($_POST['action'])) {
    ob_end_clean(); // Clear any buffered output
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'fix_file_permissions') {
        echo json_encode(handleFixFilePermissionsAjax());
        exit;
    }
    
    if ($_POST['action'] === 'update_registry') {
        $type = $_POST['type'] ?? 'php';
        $script = $type === 'js' ? 'generate_js_registry.php' : 'generate_registry.php';
        $script_path = __DIR__ . '/../tools/' . $script;
        
        if (!file_exists($script_path)) {
            echo json_encode(['success' => false, 'message' => 'Registry generator script not found']);
            exit;
        }
        
        // Run PHP script via command line
        $cmd = 'php ' . escapeshellarg($script_path) . ' 2>&1';
        exec($cmd, $output, $exitCode);
        $output_text = implode("\n", $output);
        
        // Check if command succeeded
        if ($exitCode === 0) {
            echo json_encode(['success' => true, 'message' => 'Registry updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => trim($output_text)]);
        }
        exit;
    }
}

ob_end_flush(); // Output buffered HTML

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');

// Check file permissions for registry files using ConfigManager paths
$docs_path = $config->getPath('docs_path');
$php_registry_html = $docs_path . '/function_registry.html';
$php_registry_md = $docs_path . '/FUNCTION_REGISTRY.md';
$js_registry_html = $docs_path . '/js_function_registry.html';
$js_registry_md = $docs_path . '/JS_FUNCTION_REGISTRY.md';

// Extract last update time from registry files
function getRegistryLastUpdate($htmlFile, $mdFile) {
    $lastUpdate = 'Never';
    
    // Try to get from HTML file first (has "Generated:" timestamp)
    if (file_exists($htmlFile) && is_readable($htmlFile)) {
        $content = file_get_contents($htmlFile);
        // Look for "Generated: YYYY-MM-DD HH:MM:SS" in the HTML
        if (preg_match('/Generated:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $content, $matches)) {
            $lastUpdate = $matches[1];
            return $lastUpdate;
        }
    }
    
    // Fallback to file modification time
    if (file_exists($htmlFile)) {
        $lastUpdate = date('Y-m-d H:i:s', filemtime($htmlFile));
    } elseif (file_exists($mdFile)) {
        $lastUpdate = date('Y-m-d H:i:s', filemtime($mdFile));
    }
    
    return $lastUpdate;
}

$php_last_update = getRegistryLastUpdate($php_registry_html, $php_registry_md);
$js_last_update = getRegistryLastUpdate($js_registry_html, $js_registry_md);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Function Registry Management</title>
  <?php include_once '../includes/head.php'; ?>
</head>
<body class="bg-light">
  <?php include_once '../includes/navbar.php'; ?>
  
  <div class="container mt-5 mb-5">
    <div class="row mb-4">
      <div class="col-md-12">
        <h1><i class="fa fa-database"></i> Function Registry Management</h1>
        <p class="text-muted">View and update the auto-generated registries of all PHP and JavaScript functions.</p>
      </div>
    </div>

    <!-- Permission Alerts for Registry Files -->
    <?php echo generatePermissionAlert(
        $php_registry_html,
        'PHP Registry HTML File',
        'Web server cannot write to function_registry.html.',
        'file'
    ); ?>
    
    <?php echo generatePermissionAlert(
        $php_registry_md,
        'PHP Registry Markdown File',
        'Web server cannot write to FUNCTION_REGISTRY.md.',
        'file'
    ); ?>
    
    <?php echo generatePermissionAlert(
        $js_registry_html,
        'JavaScript Registry HTML File',
        'Web server cannot write to js_function_registry.html.',
        'file'
    ); ?>
    
    <?php echo generatePermissionAlert(
        $js_registry_md,
        'JavaScript Registry Markdown File',
        'Web server cannot write to JS_FUNCTION_REGISTRY.md.',
        'file'
    ); ?>

    <!-- PHP Registry Card -->
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fa fa-code"></i> PHP Function Registry</h5>
      </div>
      <div class="card-body">
        <p class="mb-3">
          Auto-generated searchable index of all PHP functions with documentation, usage tracking, and duplicate detection.
        </p>
        <div class="mb-3">
          <button class="btn btn-primary btn-sm w-100 mb-2" onclick="location.href='../docs/function_registry.html'" target="_blank">
            <i class="fa fa-external-link"></i> View HTML
          </button>
          <button class="btn btn-info btn-sm w-100 mb-2" onclick="location.href='../docs/FUNCTION_REGISTRY.md'" target="_blank">
            <i class="fa fa-file-text"></i> View Markdown
          </button>
          <button class="btn btn-warning btn-sm w-100" onclick="updateRegistry('php')">
            <i class="fa fa-refresh"></i> Update Now
          </button>
        </div>
        <div class="text-muted small">
          <i class="fa fa-clock-o"></i> Last updated: <strong><?php echo $php_last_update; ?></strong>
        </div>
        <div id="phpResult" class="mt-3 d-none"></div>
      </div>
    </div>

    <!-- JavaScript Registry Card -->
    <div class="card mb-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fa fa-code"></i> JavaScript Function Registry</h5>
      </div>
      <div class="card-body">
        <p class="mb-3">
          Auto-generated searchable index of all JavaScript functions with documentation and usage tracking.
        </p>
        <div class="mb-3">
          <button class="btn btn-info btn-sm w-100 mb-2" onclick="location.href='../docs/js_function_registry.html'" target="_blank">
            <i class="fa fa-external-link"></i> View HTML
          </button>
          <button class="btn btn-info btn-sm w-100 mb-2" onclick="location.href='../docs/JS_FUNCTION_REGISTRY.md'" target="_blank">
            <i class="fa fa-file-text"></i> View Markdown
          </button>
          <button class="btn btn-warning btn-sm w-100" onclick="updateRegistry('js')">
            <i class="fa fa-refresh"></i> Update Now
          </button>
        </div>
        <div class="text-muted small">
          <i class="fa fa-clock-o"></i> Last updated: <strong><?php echo $js_last_update; ?></strong>
        </div>
        <div id="jsResult" class="mt-3 d-none"></div>
      </div>
    </div>

    <!-- Info Section -->
    <div class="alert alert-info">
      <h6 class="alert-heading"><i class="fa fa-lightbulb"></i> How to Use</h6>
      <p class="mb-2">
        The registry generators automatically scan your code files and create searchable indexes with:
      </p>
      <ul class="mb-2">
        <li><strong>PHP Registry:</strong> All functions from <code>lib/</code>, <code>tools/</code>, and <code>admin/</code> directories</li>
        <li><strong>JavaScript Registry:</strong> All functions from <code>js/</code> directory</li>
      </ul>
      <p class="mb-2">
        <strong>Update When:</strong> After adding new functions, modifying existing functions, or moving functions between files.
      </p>
      <p class="mb-0">
        <strong>Permissions:</strong> If you get permission errors when updating, click the <strong>Permissions Help</strong> button for instructions.
      </p>
    </div>
  </div>

  <script src="/moop/js/permission-manager.js"></script>
  <script>
  /**
   * Update registry (PHP or JS)
   */
  function updateRegistry(type) {
    const typeLabel = type === 'php' ? 'PHP' : 'JavaScript';
    const btn = event.target.closest('button');
    const originalText = btn.textContent;
    const resultDiv = document.getElementById(type + 'Result');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Updating...';
    resultDiv.classList.add('d-none');
    
    const data = new FormData();
    data.append('action', 'update_registry');
    data.append('type', type);
    
    fetch(window.location.pathname, {
      method: 'POST',
      body: data
    })
    .then(response => response.json())
    .then(json => {
      if (json.success) {
        // Show success message
        resultDiv.innerHTML = '<div class="alert alert-success">' +
          '<i class="fa fa-check-circle"></i> ' + typeLabel + ' registry updated successfully!' +
          '</div>';
        resultDiv.classList.remove('d-none');
        
        btn.innerHTML = '<i class="fa fa-check"></i> Updated!';
        
        // Update the last updated timestamp
        const now = new Date();
        const timestamp = now.getFullYear() + '-' + 
          String(now.getMonth() + 1).padStart(2, '0') + '-' + 
          String(now.getDate()).padStart(2, '0') + ' ' +
          String(now.getHours()).padStart(2, '0') + ':' +
          String(now.getMinutes()).padStart(2, '0') + ':' +
          String(now.getSeconds()).padStart(2, '0');
        
        const timestampEl = document.querySelector('#' + type + 'Result').previousElementSibling;
        if (timestampEl && timestampEl.textContent.includes('Last updated')) {
          timestampEl.innerHTML = '<i class="fa fa-clock-o"></i> Last updated: <strong>' + timestamp + '</strong>';
        }
        
        setTimeout(() => {
          btn.disabled = false;
          btn.textContent = originalText;
        }, 2000);
      } else {
        resultDiv.innerHTML = '<div class="alert alert-danger">' +
          '<i class="fa fa-exclamation-triangle"></i> Error: ' + (json.message || 'Unknown error') + 
          '<br><small>Check the permission alerts above if this is a permission error.</small>' +
          '</div>';
        resultDiv.classList.remove('d-none');
        
        btn.disabled = false;
        btn.textContent = originalText;
      }
    })
    .catch(error => {
      resultDiv.innerHTML = '<div class="alert alert-danger">' +
        '<i class="fa fa-exclamation-triangle"></i> Error: ' + error.message +
        '</div>';
      resultDiv.classList.remove('d-none');
      
      btn.disabled = false;
      btn.textContent = originalText;
    });
  }
  </script>

</body>
</html>

<?php
include_once '../includes/footer.php';
?>
