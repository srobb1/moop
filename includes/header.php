<!doctype html>
<html lang="en">
<?php
session_start();
include_once __DIR__ . '/config_init.php';
include_once __DIR__ . '/access_control.php';

?>

  <head>
    <title><?php $config = ConfigManager::getInstance(); echo $config->getString('siteTitle'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href=<?php echo "/" . $config->getString('images_path') . "/favicon.ico";?>

    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- MOOP Base Styles (consolidated layout styles) -->
    <link rel="stylesheet" href="/<?= $config->getString('site') ?>/css/moop.css">
    <!-- <link rel="stylesheet" href="/<?= $config->getString('site') ?>/css/tree.css"> DISABLED: Conflicts with new bash-style tree -->


    <?php
      $custom_css_path = $config->getPath('custom_css_path');
      if ($custom_css_path && file_exists($custom_css_path)) {
        echo "<link rel=\"stylesheet\" href=\"$custom_css_path\">";
      }
    ?>

    <!-- DataTables 1.13.4 core and Bootstrap 5 theme -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Buttons 2.3.6 with Bootstrap 5 theme -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <!-- DataTables Buttons 1.6.4 CSS for button styling -->
    <!-- DataTables Buttons 1.6.4 CSS - NOT NECESSARY (v2.3.6 provides all needed styling) -->
    <!-- <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.dataTables.min.css"> -->
    <!-- Column reordering functionality -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/colreorder/1.5.5/css/colReorder.dataTables.min.css">


    <!-- jQuery library - required for DataTables plugin -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <!-- Bootstrap 5.3.2 Bundle (includes Popper for dropdowns/tooltips) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables 1.13.4 CORE library (MUST load before theme and extensions) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <!-- DataTables 1.13.4 Bootstrap 5 theme -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Buttons 2.3.6 CORE (modern functionality) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <!-- DataTables Buttons 2.3.6 Bootstrap 5 theme -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <!-- DataTables Buttons 2.3.6 extensions (CSV, Excel, Print, ColVis) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>

    <!-- DataTables 1.10.24 (LEGACY: Required for button compatibility in hybrid approach) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <!-- DataTables Buttons 1.6.4 (LEGACY: Required for core button functionality in hybrid approach) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.6.4/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.bootstrap4.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.colVis.min.js"></script>

    <!-- jszip for Excel export functionality -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" type="text/javascript"></script>

    <!-- Font Awesome 5.7.0 - REQUIRED for download button icons (copy, CSV, Excel, print) -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">
  </head>

  <body>

  <?php
  $header_img = $config->getString('header_img');
  $images_path = $config->getString('images_path');
  
  if (!empty($header_img)) {
    echo "<div class=\"container-fluid easygdb-top\">";
      echo "<div style=\"background: url(/$images_path/$header_img) center center no-repeat; background-size:cover;\">";
      echo "<img class=\"cover-img\" src=/$images_path/$header_img style=\"visibility: hidden;\"/>";
      echo "</div>";
    echo "</div>";
  }
  ?>


<?php
include_once __DIR__ . '/toolbar.php';
?>

<div class="page_container">

<div id="jb_cookies_Modal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Cookies policy</h4>
      </div>
      <div class="modal-body">
        <p>
          Jbrowse uses cookies to remember your configuration in the genome browser, such as track load and position.
          When using Jbrowse in this site you accept the use of these cookies.
        </p>
      </div>
      <div class="modal-footer">
        <a id="jb_ok_cookies" href="/jbrowse/" target="_blank" type="button" class="btn btn-default">OK</a>
      </div>
    </div>

  </div>
</div>

<script>
  jQuery(document).ready(function() {

    var jb_link = "/jbrowse/";

    $(".jbrowse_link").click(function(event){
      event.preventDefault();
      jb_link = $(this).attr('href');
      $("#jb_ok_cookies").attr('href', jb_link);
      $("#jb_cookies_Modal").modal();
    });

    $("#jb_ok_cookies").click(function(event){
      $("#jb_cookies_Modal").modal("hide");
    });

  });
</script>

<style>

  .cover-img {
    height: 277px;
    width:20px;
    overflow: hidden;
  }
/*
  .cover-img {
    width: 100%;
    height: auto;
    visibility: hidden;
  }
*/

  .cover-title {
    position: absolute;
    padding:10px;
    margin-top:200px;
    font-size: 24px;
    color:#fff;
    width: 50%;
    background: black; /* For browsers that do not support gradients */
    background-color: rgba(0, 0, 0, 0.5);
    background: -webkit-linear-gradient(left, rgba(0, 0, 0, 0.8) , rgba(0, 0, 0, 0)); /* For Safari 5.1 to 6.0 */
    background: -o-linear-gradient(right, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0)); /* For Opera 11.1 to 12.0 */
    background: -moz-linear-gradient(right, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0)); /* For Firefox 3.6 to 15 */
    background: linear-gradient(to right, rgba(0, 0, 0, 0.8) , rgba(0, 0, 0, 0)); /* Standard syntax */
  }

  .easygdb-top {
    background-color: #a7d0e5;
    width: 100%;
    height: 277px;
    padding:0px
  }

  .institution_logo3 {
    right:0px;
    position:absolute;
    top:75px;
  }

  .img-rounded-5 {
    border-radius: 5px;
    margin:10px;
  }

</style>
