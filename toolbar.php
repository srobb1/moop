<?php
// if ($tb_login) {
//   include_once 'login_modal.php';
// }
?>

<nav class="navbar navbar-expand-md bg-dark navbar-dark sticky-top" style="padding-left:10px">
 
<?php
    echo "<a class=\"navbar-brand\" href=\"/$site/index.php\" style=\"margin-right:5px\"><img id=\"site_logo\" src=\"$favicon_path\" alt=\"DB_Logo\" style=\"height:25px; vertical-align:text-bottom;\"></a>";
?>

<?php
  if (!$tb_rm_home) {
      echo "<a class=\"navbar-brand\" href=\"/$site/index.php\"> $siteTitle</a>";
  }
?>

  <!-- Toggler/collapsibe Button -->
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="collapsibleNavbar">
    <ul class="navbar-nav">
      <?php

          echo '<li class="nav-item"><a class="nav-link" href="/about.php">About</a></li>';

include_once realpath("$easy_gdb_path/more.php");

echo '<li class="nav-item"><a class="nav-link" href="/Public/help/00_help.php">Help</a></li>
';

include_once realpath("$custom_text_path/custom_toolbar.php");

if ($tb_private) {
    echo '<li class="nav-item"><a id="tbp_link" class="nav-link" href="#"><b>Private links</b></a></li>';
}
?>
      
    </ul>
  
  <?php
    if ($tb_search_box) {
        echo '<form class="ml-auto form-inline" id="egdb_search_form" action="/easy_gdb/tools/search/search_output.php" method="get">
';
        echo '<input type="search_box" class="form-control mr-sm-2" id="search_box" name="search_keywords" placeholder="Search">
';
        echo '<button type="submit" class="btn btn-info"><i class="fa fa-search" style="font-size:16px;color:white"></i></button>';
        echo '</form>';
    }

if ($logged_in) {
    if (isset($users[$username]) && isset($users[$username]['role']) && $users[$username]['role'] === 'admin') {
        echo '<a id="admin_link" class="ml-auto" style="color:white; cursor:pointer; margin-right: 10px;" href="/' . $site . '/admin/index.php">Admin Tools</a>';
    }
    echo'<a id="logout_link" class="ml-auto" style="color:white; cursor:pointer;" href="logout.php">Log Out <i class="fa fa-sign-out-alt" style="font-size:16px;color:white"></i></a>';
} else {
    echo'<a id="login_link" class="ml-auto" style="color:white; cursor:pointer" href="login.php">Log In <i class="fa fa-sign-in-alt" style="font-size:16px;color:white"></i></a>';
}
?>
  
  </div>

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
