<?php
/**
 * The palette Site Configuration's Colors card previews: POST main / assembly / action (each
 * '' or #rrggbb) -> JSON { palette, warnings, suggestion }. lib/theme_colors.php is the only place
 * the palette is worked out; the page does not repeat that arithmetic in JavaScript.
 * Admin only, CSRF-checked (admin_init.php). Saves nothing.
 */
include_once __DIR__ . '/../admin_init.php';
require_once __DIR__ . '/../../lib/theme_colors.php';
header('Content-Type: application/json');
$in = [];
foreach (['main', 'assembly', 'action'] as $k) {
    $v = strtolower(trim((string) ($_POST[$k] ?? '')));
    $in[$k] = preg_match('/^#[0-9a-f]{6}$/', $v) ? $v : '';
}
$palette = moop_theme_palette($in) ?: MOOP_DEFAULT_PALETTE;
echo json_encode([
    'palette'    => $palette,
    'warnings'   => moop_theme_contrast_warnings($palette),
    'suggestion' => $in['main'] !== '' && moop_contrast_with_white($in['main']) < 4.5 ? moop_accessible_shade($in['main']) : '',
]);
