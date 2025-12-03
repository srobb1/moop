<?php
/**
 * HEAD RESOURCES - Stylesheet and Meta Tags for <head>
 * 
 * Contains only content that goes INSIDE the <head> tag:
 * - Meta tags (charset, viewport, favicon)
 * - CSS links (Bootstrap, MOOP custom styles)
 * - JavaScript that needs early loading (if any)
 * 
 * This file does NOT:
 * - Output <!DOCTYPE>, <html>, or <head> tags (page provides these)
 * - Include page-setup.php (that's for full page structure)
 * 
 * INCLUDED BY: page-setup.php (which sets up full page)
 * PAIRED WITH: footer.php (via page-setup.php)
 * 
 * USAGE:
 *   <head>
 *     <title>Your Title</title>
 *     <?php include_once __DIR__ . '/head-resources.php'; ?>
 *   </head>
 * 
 * Note: All CSS and JS loads happen here centrally
 */

// Ensure config is loaded
if (!class_exists('ConfigManager')) {
    include_once __DIR__ . '/config_init.php';
}
$config = ConfigManager::getInstance();
$images_path = $config->getString('images_path');
$site = $config->getString('site');
?>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo "/$images_path/favicon.ico"; ?>">

    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- MOOP Base Styles (global styles + loader animation) -->
    <link rel="stylesheet" href="/<?= $site ?>/css/moop.css">
    
    <!-- Display Styles (feature colors, badges, etc.) -->
    <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
    
    <!-- Search Controls Styles (search and filter buttons) -->
    <link rel="stylesheet" href="/<?= $site ?>/css/search-controls.css">
    
    <!-- Optional custom CSS if defined in config -->
    <?php
      $custom_css_path = $config->getPath('custom_css_path', '');
      if (!empty($custom_css_path)) {
          if (file_exists($custom_css_path)) {
              echo "<link rel=\"stylesheet\" href=\"$custom_css_path\">";
          } else {
              // Log warning if custom CSS path is configured but file doesn't exist
              error_log("Warning: custom_css_path configured in site_config.php but file not found: $custom_css_path");
          }
      }
    ?>

    <!-- DataTables 1.13.4 core and Bootstrap 5 theme -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- DataTables Buttons 2.3.6 with Bootstrap 5 theme -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
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
