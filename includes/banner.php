<?php
/**
 * Banner Rotator - Shared include for rotating header images
 *
 * Usage:
 *   <?php include_once __DIR__ . '/../includes/banner.php'; ?>
 *
 * Every image in images/banners/ is part of the rotation. The home page always shows the
 * one named by the `header_img` setting (Manage Site Configuration → Header Banner Images),
 * so the site has a consistent front door; every other page picks at random, so moving
 * around the site shows the collection off.
 *
 * Previously `header_img` was a fallback used only when images/banners/ was empty, and it
 * was resolved against images/ rather than images/banners/. Since the uploader writes to
 * images/banners/, the configured file was never at the path the fallback looked in — so
 * the fallback emitted a 404 in the one situation it existed to cover. Resolving it inside
 * banners/ (with the old location still honoured for pre-existing configs) both fixes that
 * and gives the setting a job that is visible rather than theoretical.
 *
 * Uses layered approach: blurred background + sharp foreground image
 */

$config       = ConfigManager::getInstance();
$images_path  = $config->getString('images_path');
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
sort($header_images);

/**
 * The home page is the site's front door and should look the same every visit, so it uses
 * the configured image rather than the rotation. Compare the resolved script path so that
 * /moop/ and /moop/index.php are both recognised, and a page that merely *links* home is not.
 */
$_script   = basename($_SERVER['SCRIPT_NAME'] ?? '');
$_is_home  = ($_script === '' || $_script === 'index.php');

$selected_image = null;
$header_img     = $config->getString('header_img');

if ($_is_home && $header_img !== '' && in_array($header_img, $header_images, true)) {
    $selected_image = $header_img;          // fixed banner for the front door
} elseif (!empty($header_images)) {
    $selected_image = $header_images[array_rand($header_images)];
}

if ($selected_image !== null) {
    $url = "/$images_path/banners/" . rawurlencode($selected_image);
    echo "<div class=\"moop-top\">";
    echo "  <div class=\"banner-blur\" style=\"background: url($url) center center no-repeat; background-size:cover;\"></div>";
    echo "  <div class=\"banner-image-wrapper\"><img class=\"banner-image\" src=\"$url\" alt=\"Banner\"></div>";
    echo "</div>";
} elseif ($header_img !== '' && file_exists(dirname($banners_path) . '/' . $header_img)) {
    // Legacy location: a header_img sitting directly in images/ from before banners/ existed.
    $url = "/$images_path/" . rawurlencode($header_img);
    echo "<div class=\"moop-top\">";
    echo "  <div class=\"banner-blur\" style=\"background: url($url) center center no-repeat; background-size:cover;\"></div>";
    echo "  <div class=\"banner-image-wrapper\"><img class=\"banner-image\" src=\"$url\" alt=\"Banner\"></div>";
    echo "</div>";
}
?>
