<?php
/**
 * Navbar Include - Header image and toolbar for display pages
 * 
 * This file outputs the rotating banner images and toolbar
 * Used by display pages after <body> tag opens
 * 
 * Usage:
 *   <body>
 *     <?php include_once __DIR__ . '/../../includes/navbar.php'; ?>
 *     <!-- Page content -->
 *   </body>
 */
?>

<?php include_once __DIR__ . '/banner.php'; ?>

<?php
include_once __DIR__ . '/toolbar.php';
?>
