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
 *
 * INCLUDED BY: layout.php (render_display_page)
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
    <?php if (function_exists('generate_csrf_token')): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES) ?>">
    <?php endif; ?>

    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- MOOP Base Styles (global styles + loader animation) -->
    <link rel="stylesheet" href="/<?= $site ?>/css/moop.css">
    
    <!-- Display Styles (feature colors, badges, etc.) -->
    <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
    
    <!-- Breadcrumb Trail Styles -->
    <link rel="stylesheet" href="/<?= $site ?>/css/breadcrumbs.css">
    
    <!-- Search Controls Styles (search and filter buttons) -->
    <link rel="stylesheet" href="/<?= $site ?>/css/search-controls.css">
    
    <!-- Loading Indicator Styles (for database scanning operations) -->
    <link rel="stylesheet" href="/<?= $site ?>/css/loading-indicator.css">
    
    <!-- Optional custom CSS if defined in config -->
    <?php
      $custom_css_path = $config->getPath('custom_css_path', '');
      if (!empty($custom_css_path)) {
          if (file_exists($custom_css_path)) {
              $custom_css_url = $config->getString('custom_css_url');
              echo '<link rel="stylesheet" href="' . htmlspecialchars($custom_css_url, ENT_QUOTES) . '">';
          } else {
              // Log warning if custom CSS path is configured but file doesn't exist
              error_log("Warning: custom_css_path configured in site_config.php but file not found: $custom_css_path");
          }
      }
    ?>

    <!-- DataTables 1.13.4 core and Bootstrap 5 theme -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" integrity="sha384-ISVEfRng8Op3e05C6sGn0g+2Dx1ksAPwbTbkf3mNMmYLxY883tj0WZV+vPNjwvt6" crossorigin="anonymous">
    <!-- DataTables Buttons 2.3.6 with Bootstrap 5 theme -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" integrity="sha384-760jVcHKEQ7zpIFhZXFECFibxtsaQSxVvecxbyuYKJI9zvZCZdEVfpjHmL/pNq9K" crossorigin="anonymous">
    <!-- Column reordering functionality -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/colreorder/1.5.5/css/colReorder.dataTables.min.css" integrity="sha384-LFPyhBHWePyFBkS6Kg3KZZX/XMsZG/c63KSiqh6vhDJTCMchcsjt/0edYvoF349b" crossorigin="anonymous">

    <!-- jQuery library - required for DataTables plugin -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK" crossorigin="anonymous"></script>

    <!-- Bootstrap 5.3.2 Bundle (includes Popper for dropdowns/tooltips) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <!-- DataTables 1.13.4 CORE library (MUST load before theme and extensions) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js" integrity="sha384-edQnMujp90eoACbp4sS9zj/0dMW+mjTJFxCNeW0hM7rVy4OutMVBq6ec4axiLP9U" crossorigin="anonymous"></script>
    <!-- DataTables 1.13.4 Bootstrap 5 theme -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js" integrity="sha384-ON66nBewQ67SNHiJWBO8f7ldsYeQ6wShDTaaikVGjNyNxC7P2rTge/Gf77mL/Ijt" crossorigin="anonymous"></script>
    <!-- DataTables Buttons 2.3.6 CORE (modern functionality) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js" integrity="sha384-POmQLDS+gc6Qd3ZalIVuutsaZQkTDU8foo3J6egQy4YmrNhxcGuIlKXsas5XaZNG" crossorigin="anonymous"></script>
    <!-- DataTables Buttons 2.3.6 Bootstrap 5 theme -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js" integrity="sha384-R/1aieuV27Trs+cczprs/2SFjCwdqKQo/4qYM0SBHv/wla+LSTHWds8FCw6s51Bb" crossorigin="anonymous"></script>
    <!-- DataTables Buttons 2.3.6 extensions (CSV, Excel, Print, ColVis) -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js" integrity="sha384-HTrhO6m5QbXzKQm/28HT6tYIdf/GeCK8xucgDpUbyrlqDwVh5AN5il4gcRxyIF51" crossorigin="anonymous"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js" integrity="sha384-m1bkDRTx51kIl1OC2S24Tnj0vEwit7u2Be60VaV392LbUhm9qrm5GBlraC3S2U/l" crossorigin="anonymous"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js" integrity="sha384-h/SRPFzc2+BE+XfOqlAqiHb43fnY8jzXhQ0fI1JBfgrjbxUokMr9To2eLbSWEt1g" crossorigin="anonymous"></script>

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" type="text/javascript" integrity="sha384-+mbV2IY1Zk/X1p/nWllGySJSUN8uMs+gUAN10Or95UBH0fpj6GfKgPmgC5EXieXG" crossorigin="anonymous"></script>

    <!-- Font Awesome 5.7.0 - REQUIRED for download button icons (copy, CSV, Excel, print) -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">
