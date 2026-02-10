<nav class="navbar navbar-expand-md bg-dark navbar-dark sticky-top">

<?php
    if (!class_exists('ConfigManager')) {
        include_once __DIR__ . '/config_init.php';
    }
    $config = ConfigManager::getInstance();
    $site = $config->getString('site');
    $title = $config->getString('siteTitle');
    $favicon_path = $config->getUrl('favicon_path');
    
    echo "<a class=\"navbar-brand\" href=\"/$site/index.php\"><img id=\"site_logo\" src=\"$favicon_path\" alt=\"DB_Logo\"></a>";
?>

  <!-- Toggler/collapsibe Button -->
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="collapsibleNavbar">
    <ul class="navbar-nav">
      <?php
      echo '<li class="nav-item"><a class="nav-link" href="/' . $site . '/index.php"><i class="fa fa-home"></i>'. $title.'</a></li>';
      echo '<li class="nav-item"><a class="nav-link" href="/' . $site . '/jbrowse2.php"><i class="fa fa-dna"></i> Genome Browser</a></li>';

          echo '<li class="nav-item"><a class="nav-link" href="/' . $site . '/about.php">About</a></li>';



echo '<li class="nav-item"><a class="nav-link" href="/' . $site . '/help.php">Help</a></li>';

if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo '<li class="nav-item"><a class="nav-link" href="/' . $site . '/admin/admin.php"><i class="fa fa-tools"></i> Admin Tools</a></li>';
}

?>
      
    </ul>
  
  </div>

  <ul class="navbar-nav ml-auto">
    <?php
    // Show username and access level badges for all users
    $access_level = get_access_level();
    
    // Map access levels to display text
    $access_display = [
        'PUBLIC' => 'Public',
        'COLLABORATOR' => 'Collaborator',
        'IP_IN_RANGE' => 'Trusted Network',
        'ADMIN' => 'Administrator'
    ];
    $access_text = $access_display[$access_level] ?? ucfirst(strtolower($access_level));
    
    // Map access levels to badge classes
    $access_class = [
        'PUBLIC' => 'badge-secondary',
        'COLLABORATOR' => 'badge-info',
        'IP_IN_RANGE' => 'badge-warning',
        'ADMIN' => 'badge-danger'
    ];
    $badge_class = $access_class[$access_level] ?? 'badge-secondary';
    
    echo '<li class="nav-item d-flex align-items-center mr-3">';
    
    // Show username badge only for logged-in users
    if (is_logged_in()) {
        $username = htmlspecialchars(get_username());
        echo '<span class="badge badge-light mr-2"><i class="fa fa-user"></i> ' . $username . '</span>';
    }
    
    // Always show access level badge
    echo '<span class="badge ' . $badge_class . '">' . $access_text . '</span>';
    echo '</li>';
    
    // IP_IN_RANGE users should see "Login" to allow admin login over IP auth
    if (get_access_level() === 'IP_IN_RANGE') {
        echo'<li class="nav-item"><a id="login_link" class="nav-link" href="/' . $site . '/login.php">Log In <i class="fa fa-sign-in-alt"></i></a></li>';
    } elseif (is_logged_in()) {
        echo'<li class="nav-item"><a id="logout_link" class="nav-link" href="/' . $site . '/logout.php">Log Out <i class="fa fa-sign-out-alt"></i></a></li>';
    } else {
        echo'<li class="nav-item"><a id="login_link" class="nav-link" href="/' . $site . '/login.php">Log In <i class="fa fa-sign-in-alt"></i></a></li>';
    }
    ?>
  </ul>

</nav>
