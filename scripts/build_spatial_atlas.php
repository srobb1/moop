<?php
/**
 * Build spatial atlas files (lib/spatial_atlas.php; plan notes/SPATIAL_ATLAS_PLAN.md).
 *
 *   php scripts/build_spatial_atlas.php [--encoding=uint8|uint16|float32] [bundle dir ...]
 *
 * Default: every bundle at organisms/<organism>/<assembly>/<gene set>/spatial_atlas/source/<name>/atlas.json.
 * The output goes to <gene set>/spatial_atlas/<name>.sqlite (named by atlas.json, not the folder).
 * Run as the files' owner. Prints the counts and the review list (what could not be used).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/spatial_atlas.php';

$encoding = 'uint8';
$bundles  = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--encoding=(.+)$/', $a, $m)) $encoding = $m[1];
    elseif ($a === '-h' || $a === '--help') { echo "usage: php {$argv[0]} [--encoding=uint8|uint16|float32] [bundle dir ...]\n"; exit(0); }
    else $bundles[] = $a;
}
if (!$bundles) {
    $root = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/');
    foreach (glob("$root/*/*/*/spatial_atlas/source/*/atlas.json") ?: [] as $f) $bundles[] = dirname($f);
}
if (!$bundles) { echo "No atlas bundle found (organisms/*/*/*/spatial_atlas/source/<name>/atlas.json)\n"; exit(0); }

$failed = 0;
foreach ($bundles as $b) {
    $t0 = microtime(true);
    echo "Building from $b ($encoding) ...\n";
    $r = moop_atlas_build($b, $encoding);
    if (!$r['ok']) { $failed++; printf("  FAILED: %s -- the previous file, if any, is unchanged\n", $r['error']); }
    else {
        $c = $r['counts'];
        printf("  %s\n  mesh %d vertices / %d triangles, overlay %d points; %d genes (%d with values, %d without); "
             . "values %.3g..%.3g; %d correlated pairs; %d vertices labelled  [%.0fs, %.0f MB]\n",
               $r['file'], $c['vertex_count'], $c['face_count'], $c['overlay_count'], $c['gene_count'], $c['genes_with_values'],
               $c['genes_without_values'], $c['value_min'], $c['value_max'], $c['correlated_pairs'], $c['regions_labelled'],
               microtime(true) - $t0, filesize($r['file']) / 1048576);
    }
    foreach ($r['review'] as $kind => $details) {
        printf("  review: %-55s %6d, e.g. %s\n", $kind, array_sum($details), implode(' | ', array_slice(array_keys($details), 0, 3)));
    }
}
exit($failed ? 1 : 0);
