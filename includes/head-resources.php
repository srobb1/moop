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

    <!-- Bootstrap 5.3.2 CSS — self-hosted -->
    <link href="/<?= $site ?>/css/bootstrap.min.css" rel="stylesheet">

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

    <!-- Admin section cards. Loaded globally rather than via each admin page's page_styles:
         a per-page opt-in is how the card styling drifted into four different idioms in the
         first place, and a new admin page would simply forget it. Class-scoped (.adm-*), so
         it costs public pages nothing but the request. -->
    <link rel="stylesheet" href="/<?= $site ?>/css/admin-cards.css">

    <!-- Optional custom CSS if defined in config -->
    <?php
      $custom_css_path = $config->getPath('custom_css_path', '');
      // Custom CSS is optional; silently skip when the file is absent (a missing
      // optional override must not spam the error log on every page load).
      if (!empty($custom_css_path) && file_exists($custom_css_path)) {
          $custom_css_url = $config->getString('custom_css_url');
          echo '<link rel="stylesheet" href="' . htmlspecialchars($custom_css_url, ENT_QUOTES) . '">';
      }
    ?>

    <!-- DataTables 1.13.4 — self-hosted -->
    <link rel="stylesheet" href="/<?= $site ?>/css/datatables/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/<?= $site ?>/css/datatables/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="/<?= $site ?>/css/datatables/colReorder.dataTables.min.css">

    <!-- Font Awesome 5.7.0 — self-hosted -->
    <link rel="stylesheet" href="/<?= $site ?>/css/fontawesome/all.css">
