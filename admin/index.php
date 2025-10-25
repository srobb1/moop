<?php
session_start();
$access_group = 'Admin';
include_once 'admin_header.php';
include_once '../header.php';
?>

<div class="container">
  <h2>Admin Tools</h2>
  <ul>
    <li><a href="createUser.php">Create User</a></li>
    <li><a href="manage_groups.php">Manage Groups</a></li>
  </ul>
</div>

<?php
include_once '../footer.php';
?>
