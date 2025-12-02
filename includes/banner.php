<?php
/**
 * Banner Rotator - Shared include for rotating header images
 * 
 * Usage:
 *   <?php include_once __DIR__ . '/../includes/banner.php'; ?>
 * 
 * Displays a rotating banner from /images/banners/ or falls back to config header_img
 * Uses layered approach: blurred background + sharp foreground image
 */

$config = ConfigManager::getInstance();
$images_path = $config->getString('images_path');
$banners_path = $config->getString('banners_path');

$header_images = [];
if (is_dir($banners_path)) {
  $files = scandir($banners_path);
  foreach ($files as $file) {
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
      $header_images[] = $file;
    }
  }
}

if (!empty($header_images)) {
  sort($header_images);
  $selected_image = $header_images[array_rand($header_images)];
  echo "<div class=\"easygdb-top\">";
  echo "  <div class=\"banner-blur\" style=\"background: url(/$images_path/banners/$selected_image) center center no-repeat; background-size:cover;\"></div>";
  echo "  <div class=\"banner-image-wrapper\"><img class=\"banner-image\" src=\"/$images_path/banners/$selected_image\" alt=\"Banner\"></div>";
  echo "</div>";
} elseif (!empty($header_img = $config->getString('header_img'))) {
  echo "<div class=\"easygdb-top\">";
  echo "  <div class=\"banner-blur\" style=\"background: url(/$images_path/$header_img) center center no-repeat; background-size:cover;\"></div>";
  echo "  <div class=\"banner-image-wrapper\"><img class=\"banner-image\" src=\"/$images_path/$header_img\" alt=\"Banner\"></div>";
  echo "</div>";
}
?>
