<?php
/**
 * Site colors -- one main color repaints the whole site (cuttingclass, 2026-11-05).
 *
 * css/moop.css defines MOOP's palette as variables (--moop-tool-header, --moop-tool-hover,
 * --moop-main-rgb, --moop-main-dark, --moop-main-tint, --moop-main-lightest, --moop-organism-header,
 * --moop-assembly-header, --moop-action, --moop-action-hover), and every main-color use on the site
 * goes through them. A site that sets 'theme_colors' in Site Configuration gets those variables
 * RECOMPUTED from its colors, emitted after the stylesheets (moop_theme_style_tag()). A site that
 * sets nothing gets nothing emitted -- simrbase's teal, byte for byte.
 *
 * Data colors are deliberately NOT themed: the expression scale, gene-type chips and result-count
 * badges mean the same thing on every MOOP site.
 *
 * ONE implementation: the Site Configuration preview asks the server for the palette
 * (admin/api/theme_preview.php) rather than repeating this arithmetic in JavaScript.
 */

/** '#rrggbb' -> [r, g, b] (0-255), or null. */
function moop_hex_rgb(string $hex): ?array {
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) return null;
    return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
}

function moop_rgb_hex(array $rgb): string {
    return sprintf('#%02x%02x%02x', ...array_map(fn($v) => max(0, min(255, (int) round($v))), $rgb));
}

/** [r, g, b] -> [h 0-360, s 0-1, l 0-1]. */
function moop_rgb_hsl(array $rgb): array {
    [$r, $g, $b] = array_map(fn($v) => $v / 255, $rgb);
    $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2;
    if ($max === $min) return [0.0, 0.0, $l];
    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
    $h = $max === $r ? (($g - $b) / $d + ($g < $b ? 6 : 0)) : ($max === $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4);
    return [$h * 60, $s, $l];
}

/** [h, s, l] -> '#rrggbb'. */
function moop_hsl_hex(float $h, float $s, float $l): string {
    $h = fmod(fmod($h, 360) + 360, 360) / 360;
    $s = max(0, min(1, $s)); $l = max(0, min(1, $l));
    if ($s == 0) return moop_rgb_hex([$l * 255, $l * 255, $l * 255]);
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    $f = function ($t) use ($p, $q) {
        if ($t < 0) $t += 1; if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    };
    return moop_rgb_hex([$f($h + 1 / 3) * 255, $f($h) * 255, $f($h - 1 / 3) * 255]);
}

/** WCAG contrast ratio of a color against white (white text on it). */
function moop_contrast_with_white(string $hex): float {
    $rgb = moop_hex_rgb($hex);
    if (!$rgb) return 0.0;
    $lin = array_map(function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; }, $rgb);
    $lum = 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    return round(1.05 / ($lum + 0.05), 2);
}

/** A color one step darker -- the hover / pressed shade. */
function moop_darken(string $hex, float $by): string {
    [$h, $s, $l] = moop_rgb_hsl(moop_hex_rgb($hex));
    return moop_hsl_hex($h, $s, $l * (1 - $by));
}

/**
 * The palette for a site's colors: [css variable => value], or [] when no main color is set
 * (nothing emitted: MOOP's own teal). Pure.
 *
 * From the main color: hover (darker, as #0e7490 is to #0891b2), the text color for washes, two
 * pale tints, the organism header (deeper still), and its r,g,b for rgba() washes. Assembly and
 * action colors are the site's own when given, else suggested: assembly the main hue turned
 * half-way round (a contrasting family), action a third of the way (a distinct but friendly hue),
 * each deep enough to carry white text.
 */
function moop_theme_palette(array $colors): array {
    $main = strtolower(trim((string) ($colors['main'] ?? '')));
    if (!moop_hex_rgb($main)) return [];
    [$h, $s, $l] = moop_rgb_hsl(moop_hex_rgb($main));

    $deep = function (float $hue) use ($s) {
        $sat = max(0.55, min(0.85, $s));
        $hex = moop_hsl_hex($hue, $sat, 0.5);
        for ($ll = 0.5; $ll > 0.2 && moop_contrast_with_white($hex) < 4.5; $ll -= 0.02) $hex = moop_hsl_hex($hue, $sat, $ll);
        return $hex;
    };
    $assembly = moop_hex_rgb((string) ($colors['assembly'] ?? '')) ? strtolower($colors['assembly']) : $deep($h + 180);
    $action   = moop_hex_rgb((string) ($colors['action'] ?? ''))   ? strtolower($colors['action'])   : $deep($h + 120);

    return [
        '--moop-tool-header'     => $main,
        '--moop-tool-hover'      => moop_darken($main, 0.14),
        '--moop-organism-header' => moop_darken($main, 0.30),
        '--moop-main-rgb'        => implode(', ', moop_hex_rgb($main)),
        '--moop-main-dark'       => moop_hsl_hex($h, $s, min($l, 0.25)),
        '--moop-main-tint'       => moop_hsl_hex($h, min($s, 0.5), 0.92),
        '--moop-main-lightest'   => moop_hsl_hex($h, min($s, 0.8), 0.96),
        '--moop-assembly-header' => $assembly,
        '--moop-action'          => $action,
        '--moop-action-hover'    => moop_darken($action, 0.12),
    ];
}

/**
 * Colors that carry white text and fall short of WCAG AA (4.5:1 normal text; 3:1 is the floor for
 * large/bold text like headers): [variable => ratio]. For Site Configuration's warning.
 */
function moop_theme_contrast_warnings(array $palette): array {
    $out = [];
    foreach (['--moop-tool-header', '--moop-organism-header', '--moop-assembly-header', '--moop-action'] as $k) {
        if (isset($palette[$k]) && ($r = moop_contrast_with_white($palette[$k])) < 4.5) $out[$k] = $r;
    }
    return $out;
}

/** The lightest shade of $hex that carries white text at 4.5:1 -- the "use this instead" suggestion. */
function moop_accessible_shade(string $hex): string {
    if (!moop_hex_rgb($hex)) return $hex;
    [$h, $s, $l] = moop_rgb_hsl(moop_hex_rgb($hex));
    $out = $hex;
    for (; $l > 0.05 && moop_contrast_with_white($out) < 4.5; $l -= 0.01) $out = moop_hsl_hex($h, $s, $l);
    return $out;
}

/** MOOP's own palette (css/moop.css), for previewing a site that sets no colors. */
const MOOP_DEFAULT_PALETTE = [
    '--moop-tool-header' => '#0891b2', '--moop-tool-hover' => '#0e7490', '--moop-organism-header' => '#0f766e',
    '--moop-main-rgb' => '8, 145, 178', '--moop-main-dark' => '#155e75', '--moop-main-tint' => '#e0f2f7',
    '--moop-main-lightest' => '#ecfeff', '--moop-assembly-header' => '#e11d48', '--moop-action' => '#6366f1',
    '--moop-action-hover' => '#4f46e5',
];

/** The <style> that applies the site's palette, or '' (simrbase: nothing at all). */
function moop_theme_style_tag(): string {
    $palette = moop_theme_palette((array) ConfigManager::getInstance()->getArray('theme_colors', []));
    if (!$palette) return '';
    $css = '';
    foreach ($palette as $k => $v) {
        // Values come from moop_theme_palette() only -- hex or "r, g, b" -- never raw input.
        if (preg_match('/^(#[0-9a-f]{6}|\d{1,3}, \d{1,3}, \d{1,3})$/', $v)) $css .= "$k:$v;";
    }
    return "<style>:root{" . $css . "}</style>\n";
}
