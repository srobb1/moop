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

// Check file permissions for tools directory where registries are generated
$tools_dir = __DIR__ . '/../tools';
$docs_dir = __DIR__ . '/../docs';
$lib_dir = __DIR__ . '/../lib';

// Check if www-data can write to necessary directories
$tools_writable = is_writable($tools_dir);
$docs_writable = is_writable($docs_dir);
$lib_writable = is_writable($lib_dir);

// Get web server user info
$web_user = get_current_user() ?: 'www-data';
$web_group_info = @posix_getgrgid(@posix_getegid());
$web_group = $web_group_info !== false ? $web_group_info['name'] : 'www-data';
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

    <!-- Permission Alerts for Registry Directories -->
    <?php echo generatePermissionAlert(
        $lib_dir,
        'Library Directory Permission Issue',
        'Web server cannot write to the lib/ directory where function_registry.php is generated.',
        'directory'
    ); ?>
    
    <?php echo generatePermissionAlert(
        $docs_dir,
        'Documentation Directory Permission Issue',
        'Web server cannot write to the docs/ directory where registry documentation is generated.',
        'directory'
    ); ?>
    
    <?php echo generatePermissionAlert(
        $tools_dir,
        'Tools Directory Permission Issue',
        'Web server cannot write to the tools/ directory where registry generators run.',
        'directory'
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
        <div class="row mb-3">
          <div class="col-md-6">
            <button class="btn btn-primary btn-sm" onclick="location.href='../docs/function_registry.html'" target="_blank">
              <i class="fa fa-external-link"></i> View HTML Registry
            </button>
          </div>
          <div class="col-md-6 text-end">
            <button class="btn btn-info btn-sm" onclick="location.href='../docs/FUNCTION_REGISTRY.md'" target="_blank">
              <i class="fa fa-file-text"></i> View Markdown
            </button>
          </div>
        </div>
        <button class="btn btn-warning btn-sm" onclick="updateRegistry('php')">
          <i class="fa fa-refresh"></i> Update Registry Now
        </button>
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
        <div class="row mb-3">
          <div class="col-md-6">
            <button class="btn btn-info btn-sm" onclick="location.href='../docs/js_function_registry.html'" target="_blank">
              <i class="fa fa-external-link"></i> View HTML Registry
            </button>
          </div>
          <div class="col-md-6 text-end">
            <button class="btn btn-info btn-sm" onclick="location.href='../docs/JS_FUNCTION_REGISTRY.md'" target="_blank">
              <i class="fa fa-file-text"></i> View Markdown
            </button>
          </div>
        </div>
        <button class="btn btn-warning btn-sm" onclick="updateRegistry('js')">
          <i class="fa fa-refresh"></i> Update Registry Now
        </button>
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
