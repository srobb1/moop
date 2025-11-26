<?php
include_once __DIR__ . '/admin_init.php';

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');

// Handle AJAX fix permissions request (unified system)
if (isset($_POST['action']) && $_POST['action'] === 'fix_file_permissions') {
    header('Content-Type: application/json');
    echo json_encode(handleFixFilePermissionsAjax());
    exit;
}

// Handle AJAX update registry requests
if (isset($_POST['action']) && $_POST['action'] === 'update_registry') {
    header('Content-Type: application/json');
    
    $type = $_POST['type'] ?? 'php';
    $script = $type === 'js' ? 'generate_js_registry.php' : 'generate_registry.php';
    $script_path = __DIR__ . '/../tools/' . $script;
    
    if (!file_exists($script_path)) {
        echo json_encode(['success' => false, 'message' => 'Registry generator script not found']);
        exit;
    }
    
    ob_start();
    include $script_path;
    $output = ob_get_clean();
    
    echo json_encode(['success' => true, 'message' => 'Registry updated successfully', 'output' => $output]);
    exit;
}

// Check file permissions for registry files
$php_registry = __DIR__ . '/../lib/function_registry.php';
$html_registry = __DIR__ . '/../docs/function_registry.html';
$md_registry = __DIR__ . '/../docs/FUNCTION_REGISTRY.md';
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
      <div class="col-md-8">
        <h1><i class="fa fa-database"></i> Function Registry Management</h1>
        <p class="text-muted">View and update the auto-generated registries of all PHP and JavaScript functions.</p>
      </div>
      <div class="col-md-4 text-end">
        <button class="btn btn-primary" onclick="location.href='../docs/function_registry.html'" target="_blank">
          <i class="fa fa-book"></i> View PHP Registry
        </button>
        <button class="btn btn-info" onclick="location.href='../docs/js_function_registry.html'" target="_blank">
          <i class="fa fa-book"></i> View JS Registry
        </button>
      </div>
    </div>

    <!-- Permission Alerts -->
    <?php echo generatePermissionAlert(
        $php_registry,
        'PHP Registry File Permission Issue',
        'Cannot update the PHP function registry.',
        'file'
    ); ?>
    
    <?php echo generatePermissionAlert(
        $html_registry,
        'HTML Documentation Permission Issue',
        'Cannot update the HTML documentation.',
        'file'
    ); ?>

    <!-- PHP Registry Section -->
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fa fa-code"></i> PHP Function Registry</h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-8">
            <p class="mb-2"><strong>Auto-generated registry of all PHP functions</strong></p>
            <p class="text-muted small mb-0">
              Scans <code>lib/</code>, <code>tools/</code>, and <code>admin/</code> directories for all PHP functions.
              Creates searchable function index with documentation, usage tracking, and duplicate detection.
            </p>
          </div>
          <div class="col-md-4 text-end">
            <button class="btn btn-warning btn-sm" onclick="updateRegistry('php')">
              <i class="fa fa-refresh"></i> Update Registry
            </button>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <div class="card border-light">
              <div class="card-body text-center">
                <h6 class="text-muted">Total Functions</h6>
                <h3 class="text-primary" id="phpFunctionCount">-</h3>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-light">
              <div class="card-body text-center">
                <h6 class="text-muted">Files Scanned</h6>
                <h3 class="text-success" id="phpFileCount">-</h3>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-light">
              <div class="card-body text-center">
                <h6 class="text-muted">Last Updated</h6>
                <p class="small mb-0" id="phpLastUpdate">Loading...</p>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <div id="phpResult" class="d-none"></div>
        </div>

        <hr>

        <div class="row text-center text-muted small">
          <div class="col-md-4">
            <i class="fa fa-file"></i> <code>lib/function_registry.php</code>
          </div>
          <div class="col-md-4">
            <i class="fa fa-html5"></i> <code>docs/function_registry.html</code>
          </div>
          <div class="col-md-4">
            <i class="fa fa-markdown"></i> <code>docs/FUNCTION_REGISTRY.md</code>
          </div>
        </div>
      </div>
    </div>

    <!-- JavaScript Registry Section -->
    <div class="card mb-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fa fa-code"></i> JavaScript Function Registry</h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-8">
            <p class="mb-2"><strong>Auto-generated registry of all JavaScript functions</strong></p>
            <p class="text-muted small mb-0">
              Scans <code>js/</code> directory for all JavaScript functions.
              Creates searchable function index with documentation and usage tracking.
            </p>
          </div>
          <div class="col-md-4 text-end">
            <button class="btn btn-warning btn-sm" onclick="updateRegistry('js')">
              <i class="fa fa-refresh"></i> Update Registry
            </button>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <div class="card border-light">
              <div class="card-body text-center">
                <h6 class="text-muted">Total Functions</h6>
                <h3 class="text-info" id="jsFunctionCount">-</h3>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-light">
              <div class="card-body text-center">
                <h6 class="text-muted">Files Scanned</h6>
                <h3 class="text-success" id="jsFileCount">-</h3>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-light">
              <div class="card-body text-center">
                <h6 class="text-muted">Last Updated</h6>
                <p class="small mb-0" id="jsLastUpdate">Loading...</p>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <div id="jsResult" class="d-none"></div>
        </div>

        <hr>

        <div class="row text-center text-muted small">
          <div class="col-md-6">
            <i class="fa fa-html5"></i> <code>docs/js_function_registry.html</code>
          </div>
          <div class="col-md-6">
            <i class="fa fa-markdown"></i> <code>docs/JS_FUNCTION_REGISTRY.md</code>
          </div>
        </div>
      </div>
    </div>

    <!-- Info Section -->
    <div class="alert alert-info">
      <h6 class="alert-heading"><i class="fa fa-lightbulb"></i> How to Use</h6>
      <p class="mb-2">
        The registry generators automatically scan your code files and create:
      </p>
      <ul class="mb-2">
        <li><strong>PHP Registry:</strong> Indexed list of all PHP functions with documentation, usage tracking, and duplicate detection</li>
        <li><strong>JavaScript Registry:</strong> Indexed list of all JavaScript functions with documentation and usage info</li>
      </ul>
      <p class="mb-2">
        <strong>When to update:</strong> After adding new functions, modifying existing functions, or moving functions between files.
      </p>
      <p class="mb-0">
        <strong>Outputs:</strong> PHP and HTML files for searchability, plus Markdown for documentation.
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
        
        // Parse output to extract stats if present
        if (json.output && json.output.includes('✅')) {
          const output = json.output;
          
          // Extract function count
          const funcMatch = output.match(/Functions found: (\d+)/);
          if (funcMatch) {
            document.getElementById(type + 'FunctionCount').textContent = funcMatch[1];
          }
          
          // Extract file count
          const fileMatch = output.match(/Files scanned: (\d+)/);
          if (fileMatch) {
            document.getElementById(type + 'FileCount').textContent = fileMatch[1];
          }
        }
        
        // Update timestamp
        document.getElementById(type + 'LastUpdate').textContent = new Date().toLocaleString();
        
        setTimeout(() => {
          btn.disabled = false;
          btn.textContent = originalText;
        }, 2000);
      } else {
        resultDiv.innerHTML = '<div class="alert alert-danger">' +
          '<i class="fa fa-exclamation-triangle"></i> Error: ' + (json.message || 'Unknown error') +
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

  /**
   * Load registry stats on page load
   */
  document.addEventListener('DOMContentLoaded', function() {
    // Try to get file modification times
    const now = new Date();
    
    // Set last update times to now (in production, read from file modification time)
    document.getElementById('phpLastUpdate').textContent = now.toLocaleString();
    document.getElementById('jsLastUpdate').textContent = now.toLocaleString();
  });
  </script>

</body>
</html>

<?php
include_once '../includes/footer.php';
?>
