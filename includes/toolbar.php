<nav class="navbar navbar-expand-md bg-dark navbar-dark sticky-top">

<?php
    include_once __DIR__ . '/../site_config.php';
    
    echo "<a class=\"navbar-brand\" href=\"/$site/index.php\"><img id=\"site_logo\" src=\"$favicon_path\" alt=\"DB_Logo\"></a>";
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

if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo '<li class="nav-item"><a class="nav-link" href="/' . $site . '/admin/index.php"><i class="fa fa-tools"></i> Admin Tools</a></li>';
}

?>
      
    </ul>
  
  </div>

  <ul class="navbar-nav ml-auto">
    <?php
    if (is_logged_in()) {
        echo'<li class="nav-item"><a id="logout_link" class="nav-link" href="/' . $site . '/logout.php">Log Out <i class="fa fa-sign-out-alt"></i></a></li>';
    } else {
        echo'<li class="nav-item"><a id="login_link" class="nav-link" href="/' . $site . '/login.php">Log In <i class="fa fa-sign-in-alt"></i></a></li>';
    }
    ?>
  </ul>

</nav>
