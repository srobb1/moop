<?php
include_once __DIR__ . '/admin_init.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Admin Tools</title>
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2><i class="fa fa-tools"></i> Admin Tools</h2>
  
  <div class="row mt-4">
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-users"></i> Manage Users</h5>
          <p class="card-text">Add new users to the system and manage user accounts.</p>
          <a href="createUser.php" class="btn btn-primary">Go to Manage Users</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-dna"></i> Manage Organisms</h5>
          <p class="card-text">View current organisms, assemblies, and learn how to add new data.</p>
          <a href="manage_organisms.php" class="btn btn-primary">Go to Manage Organisms</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-layer-group"></i> Manage Groups</h5>
          <p class="card-text">Configure organism assembly groups and group descriptions.</p>
          <a href="manage_groups.php" class="btn btn-primary">Go to Manage Groups</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-layer-group"></i> Manage Groups & Descriptions</h5>
          <p class="card-text">Manage organism assembly groups and their descriptions.</p>
          <a href="manage_groups.php" class="btn btn-primary">Go to Groups</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-tags"></i> Manage Annotation Sections</h5>
          <p class="card-text">Configure annotation section types and descriptions.</p>
          <a href="manage_annotations.php" class="btn btn-primary">Go to Annotation Sections</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-project-diagram"></i> Manage Phylogenetic Tree</h5>
          <p class="card-text">Generate and customize the phylogenetic tree from organism taxonomy data.</p>
          <a href="manage_phylo_tree.php" class="btn btn-primary">Go to Phylo Tree</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-exclamation-triangle"></i> Error Logs</h5>
          <p class="card-text">View and manage application error logs for debugging and monitoring.</p>
          <a href="error_log.php" class="btn btn-primary">View Error Logs</a>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-file-code"></i> PHP Function Registry</h5>
          <p class="card-text">View all PHP functions, their usage, and identify unused functions.</p>
          <a href="../docs/function_registry.html" class="btn btn-primary" target="_blank">View Registry</a>
          <button id="updatePhpRegistry" class="btn btn-warning" style="display:none; margin-left: 5px;" onclick="updateRegistry('php')">Update Registry</button>
        </div>
      </div>
    </div>
    
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-file-code"></i> JavaScript Function Registry</h5>
          <p class="card-text">View all JavaScript functions, their usage, and identify unused functions.</p>
          <a href="../docs/js_function_registry.html" class="btn btn-primary" target="_blank">View Registry</a>
          <button id="updateJsRegistry" class="btn btn-warning" style="display:none; margin-left: 5px;" onclick="updateRegistry('js')">Update Registry</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Check if user is admin and show update buttons
function checkAdminStatus() {
  // If this page is loaded, user is already authenticated as admin
  // Show update buttons
  document.getElementById('updatePhpRegistry').style.display = 'inline-block';
  document.getElementById('updateJsRegistry').style.display = 'inline-block';
}

function updateRegistry(type) {
  const btn = document.getElementById('update' + (type === 'php' ? 'Php' : 'Js') + 'Registry');
  const originalText = btn.textContent;
  btn.textContent = 'Updating...';
  btn.disabled = true;
  
  const script = type === 'php' ? 'generate_registry.php' : 'generate_js_registry.php';
  
  fetch('../tools/' + script)
    .then(response => {
      if (response.ok) {
        btn.textContent = 'Updated! Reloading...';
        setTimeout(() => {
          location.reload();
        }, 1000);
      } else {
        btn.textContent = 'Error updating';
        setTimeout(() => {
          btn.textContent = originalText;
          btn.disabled = false;
        }, 2000);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      btn.textContent = 'Error updating';
      setTimeout(() => {
        btn.textContent = originalText;
        btn.disabled = false;
      }, 2000);
    });
}

// Show admin buttons on page load
window.addEventListener('DOMContentLoaded', checkAdminStatus);
</script>

</body>
</html>

<?php
include_once '../includes/footer.php';
?>
