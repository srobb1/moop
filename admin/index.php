<?php
session_start();
$access_group = 'Admin';
include_once 'admin_header.php';
include_once '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Tools</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <h2><i class="fa fa-tools"></i> Admin Tools</h2>
  
  <div class="row mt-4">
    <div class="col-md-6 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="fa fa-user-plus"></i> Create User</h5>
          <p class="card-text">Add new users to the system and manage user accounts.</p>
          <a href="createUser.php" class="btn btn-primary">Go to Create User</a>
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
          <h5 class="card-title"><i class="fa fa-file-alt"></i> Manage Group Descriptions</h5>
          <p class="card-text">Edit and manage descriptions for organism assembly groups.</p>
          <a href="manage_group_descriptions.php" class="btn btn-primary">Go to Group Descriptions</a>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>

<?php
include_once '../footer.php';
?>
