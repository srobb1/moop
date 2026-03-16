<?php
/**
 * HEAD RESOURCES - CSS and Meta Tags for <head>
 *
 * Contains only content that goes INSIDE the <head> tag:
 * - Meta tags (charset, viewport, favicon, CSRF token)
 * - CSS links (Bootstrap, DataTables, Font Awesome, MOOP custom styles)
 *
 * IMPORTANT: All JavaScript is loaded at end of <body> in layout.php.
 * Do NOT add <script> tags here — double-loading Bootstrap JS breaks
 * collapsible sections, accordions, modals, and other Bootstrap components.
 *
 * INCLUDED BY: layout.php (render_display_page) — the only consumer
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

    <!-- Font Awesome 5.7.0 - REQUIRED for icons (navigation, buttons, status indicators) -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">
