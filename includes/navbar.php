<?php
/**
 * Navbar Include - Header image and toolbar for display pages
 * 
 * This file outputs the header image (if configured) and toolbar
 * Used by display pages after <body> tag opens
 * 
 * Usage:
 *   <body>
 *     <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>
 *     <!-- Page content -->
 *   </body>
 * 
 * Note: Requires $header_img and $images_path to be defined in site_config.php
 */
?>

<?php
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
