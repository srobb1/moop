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

  <?php include_once __DIR__ . '/banner.php'; ?>


<?php
include_once __DIR__ . '/toolbar.php';
?>

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

</style>
