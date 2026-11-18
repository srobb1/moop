<?php
/**
 * Spatial atlas tool page -- one atlas, one gene at a time. Recreates the EndoAtlas authors' Dash app:
 * title + project description (their figure and abstract) on the left; gene picker, 3-D prediction,
 * in situ picture and similar genes on the right (js/modules/spatial-atlas.js).
 *
 *   tools/spatial_atlas.php?atlas=endoatlas[&gene=NV2g017667000.1]
 *   tools/spatial_atlas.php?organism=O&assembly=A&gene_set=G&atlas=N[&gene=ID]   (when names repeat)
 *
 * gene may be any ID of the gene the crosswalk knows (a RefSeq gene finds its NV2 gene). Access:
 * moop_atlas_visible() (gene set visible AND the atlas rule). Plan: notes/SPATIAL_ATLAS_PLAN.md.
 */
include_once __DIR__ . '/tool_init.php';
include_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../lib/spatial_atlas.php';

$name = (string) ($_GET['atlas'] ?? '');
$org  = (string) ($_GET['organism'] ?? '');
$at   = $org !== ''
    ? ['organism' => $org, 'assembly' => (string) ($_GET['assembly'] ?? ''), 'gene_set' => (string) ($_GET['gene_set'] ?? ''), 'name' => $name]
    : moop_atlas_by_name($name);
$db = null;
if ($at && moop_atlas_visible($at['organism'], $at['assembly'], $at['gene_set'], $at['name'])) {
    $db = moop_atlas_open(moop_atlas_file($at['organism'], $at['assembly'], $at['gene_set'], $at['name']));
}
if (!$db) {
    http_response_code(404);
    echo render_display_page(__DIR__ . '/pages/spatial_atlas.php', ['missing' => true], 'Atlas not found');
    exit;
}
$meta = moop_atlas_meta($db);

// The gene to open: asked for (found under any of its IDs), else the atlas's own default.
$asked = trim((string) ($_GET['gene'] ?? ''));
$gene  = '';
if ($asked !== '' && strlen($asked) <= 200) {
    $gene = moop_atlas_resolve($db, moop_atlas_candidate_ids($at['organism'], $asked,
                                    array_slice(moop_atlas_lookup_ids($at['organism'], $at['assembly'], $at['gene_set'], $asked), 1))) ?? '';
}
$not_found = $asked !== '' && $gene === '';
if ($gene === '') $gene = (string) ($meta['default_gene'] ?? '');
$assets = $db->query('SELECT name FROM asset')->fetchAll(PDO::FETCH_COLUMN);

$api = moop_url('api/spatial_atlas.php');
$q   = ['organism' => $at['organism'], 'assembly' => $at['assembly'], 'gene_set' => $at['gene_set'], 'atlas' => $at['name']];
$opts = $q + [
    'api'       => $api,
    'plotlyUrl' => moop_url('js/vendor/plotly-gl3d.min.js'),
    'geneUrl'   => moop_url('tools/parent.php') . '?' . http_build_query(['organism' => $at['organism']]) . '&uniquename=',
    'toolUrl'   => moop_url('tools/spatial_atlas.php') . '?' . http_build_query($q) . '&gene=',
    'gene'      => $gene,
    'mode'      => 'tool',
];

echo render_display_page(__DIR__ . '/pages/spatial_atlas.php', [
    'missing'   => false,
    'meta'      => $meta,
    'asset_url' => fn(string $a) => in_array($a, $assets, true) ? $api . '?' . http_build_query($q + ['what' => 'asset', 'name' => $a]) : '',
    'asked'     => $asked,
    'not_found' => $not_found,
    'page_styles' => [moop_url_base() . '/css/spatial-atlas.css'],
    'page_script' => [moop_url_base() . '/js/modules/spatial-atlas.js', moop_url_base() . '/js/spatial-atlas-page.js'],
    'inline_scripts' => ['const moopAtlas = ' . json_encode($opts, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . ';'],
], (string) ($meta['title'] ?? 'Spatial atlas'));
