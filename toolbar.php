<nav class="navbar navbar-expand-md bg-dark navbar-dark sticky-top" style="padding-left:10px">

<?php
    include_once __DIR__ . '/site_config.php';
    include_once __DIR__ . '/access_control.php';
    
    echo "<a class=\"navbar-brand\" href=\"/$site/index.php\" style=\"margin-right:5px\"><img id=\"site_logo\" src=\"$favicon_path\" alt=\"DB_Logo\" style=\"height:25px; vertical-align:text-bottom;\"></a>";
?>

  <!-- Toggler/collapsibe Button -->
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="collapsibleNavbar">
    <ul class="navbar-nav">
      <?php

          echo '<li class="nav-item"><a class="nav-link" href="/about.php">About</a></li>';



echo '<li class="nav-item"><a class="nav-link" href="/Public/help/00_help.php">Help</a></li>';

if ($logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo '<li class="nav-item"><a class="nav-link" href="/' . $site . '/admin/index.php"><i class="fa fa-tools" style="font-size:14px;"></i> Admin Tools</a></li>';
}

?>
      
    </ul>
  
  </div>

  <ul class="navbar-nav ml-auto">
    <?php
    if ($logged_in) {
        echo'<li class="nav-item"><a id="logout_link" class="nav-link" style="color:white;" href="/' . $site . '/logout.php">Log Out <i class="fa fa-sign-out-alt" style="font-size:16px;color:white"></i></a></li>';
    } else {
        echo'<li class="nav-item"><a id="login_link" class="nav-link" style="color:white;" href="/' . $site . '/login.php">Log In <i class="fa fa-sign-in-alt" style="font-size:16px;color:white"></i></a></li>';
    }
    ?>
  </ul>

</nav>

<style>
   @media (max-width: 575px) {
     #search_box {
       width: 193px;
       margin-right: .5rem!important;
     }
   }
   
   #tbp_link {
     display:none;
     color:#d44;
   }
  
   #tbp_link:hover {
     color:#f44;
   }
  
</style>
